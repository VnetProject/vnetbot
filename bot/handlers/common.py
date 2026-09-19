import logging

from aiogram import F, Router
from aiogram.filters import CommandStart
from aiogram.fsm.context import FSMContext
from aiogram.types import CallbackQuery, InlineKeyboardButton, InlineKeyboardMarkup, Message
from sqlalchemy import select

from bot.keyboards import admin_main_menu, main_menu, with_back
from bot.states import Support
from config import OWNER_ID
from database.db import get_session
from database.models import MandatoryChannel, Settings, SupportMessage, User
from utils.auth import is_admin

router = Router(name="common")
log = logging.getLogger("common")


async def get_or_create_user(session, tg_user) -> User:
    result = await session.execute(select(User).where(User.telegram_id == tg_user.id))
    user = result.scalar_one_or_none()
    if user is None:
        user = User(telegram_id=tg_user.id, username=tg_user.username)
        session.add(user)
        await session.commit()
        await session.refresh(user)
    return user


async def get_settings(session) -> Settings:
    return await session.get(Settings, 1)


async def notify_admins(bot, session, text: str, parse_mode: str | None = None):
    """Send a notification to the configured report channel AND always,
    as a safety net, directly to OWNER_ID (a channel that's misconfigured,
    or where the bot isn't admin, should never silently swallow messages)."""
    settings = await get_settings(session)
    sent = False
    if settings.report_channel:
        try:
            await bot.send_message(settings.report_channel, text, parse_mode=parse_mode)
            sent = True
        except Exception as e:
            log.warning("failed to send to report_channel %s: %s", settings.report_channel, e)
    try:
        await bot.send_message(OWNER_ID, text, parse_mode=parse_mode)
        sent = True
    except Exception as e:
        log.warning("failed to DM OWNER_ID: %s", e)
    return sent


async def check_mandatory_join(bot, session, telegram_id: int) -> list[MandatoryChannel]:
    result = await session.execute(select(MandatoryChannel))
    channels = result.scalars().all()
    not_joined = []
    for ch in channels:
        try:
            member = await bot.get_chat_member(ch.chat_id, telegram_id)
            if member.status in ("left", "kicked"):
                not_joined.append(ch)
        except Exception:
            continue
    return not_joined


@router.message(CommandStart())
async def cmd_start(message: Message, state: FSMContext):
    await state.clear()
    async with get_session() as session:
        user = await get_or_create_user(session, message.from_user)
        if user.is_banned:
            await message.answer("⛔️ شما توسط مدیریت مسدود شده‌اید.")
            return

        not_joined = await check_mandatory_join(message.bot, session, message.from_user.id)
        if not_joined:
            kb_rows = [[InlineKeyboardButton(text=f"عضویت در {c.title or c.chat_id}",
                                              url=f"https://t.me/{str(c.chat_id).lstrip('@')}")] for c in not_joined]
            kb_rows.append([InlineKeyboardButton(text="✅ عضو شدم", callback_data="check_join")])
            await message.answer("برای استفاده از ربات ابتدا در کانال(های) زیر عضو شوید:",
                                  reply_markup=InlineKeyboardMarkup(inline_keyboard=kb_rows))
            return

        settings = await get_settings(session)
        await message.answer(settings.texts.get("welcome", "خوش آمدید"), reply_markup=main_menu(settings),
                              parse_mode="HTML")


@router.callback_query(F.data == "check_join")
async def cb_check_join(call: CallbackQuery):
    async with get_session() as session:
        not_joined = await check_mandatory_join(call.bot, session, call.from_user.id)
        if not_joined:
            await call.answer("هنوز در همه کانال‌ها عضو نشده‌اید ❌", show_alert=True)
            return
        settings = await get_settings(session)
        await call.message.edit_text(settings.texts.get("welcome", "خوش آمدید"), reply_markup=main_menu(settings),
                                      parse_mode="HTML")


@router.callback_query(F.data == "main_menu")
async def cb_main_menu(call: CallbackQuery, state: FSMContext):
    await state.clear()
    async with get_session() as session:
        settings = await get_settings(session)
        await call.message.edit_text(settings.texts.get("welcome", "خوش آمدید"), reply_markup=main_menu(settings),
                                      parse_mode="HTML")


@router.callback_query(F.data == "cancel_fsm")
async def cb_cancel_fsm(call: CallbackQuery, state: FSMContext):
    """Generic 'back/cancel' target attached to every FSM text-prompt -
    clears whatever step the user/admin was on and returns them to the
    right home screen."""
    await state.clear()
    async with get_session() as session:
        settings = await get_settings(session)
        if is_admin(call.from_user.id):
            await call.message.edit_text("🛠 پنل مدیریت", reply_markup=admin_main_menu(settings))
        else:
            await call.message.edit_text(settings.texts.get("welcome", "خوش آمدید"), reply_markup=main_menu(settings),
                                          parse_mode="HTML")


@router.callback_query(F.data == "faq")
async def cb_faq(call: CallbackQuery):
    async with get_session() as session:
        settings = await get_settings(session)
        await call.message.edit_text(
            settings.texts.get("faq", "-"),
            reply_markup=with_back([], "main_menu", settings),
            parse_mode="HTML",
        )


@router.callback_query(F.data == "rules")
async def cb_rules(call: CallbackQuery):
    async with get_session() as session:
        settings = await get_settings(session)
        await call.message.edit_text(
            settings.texts.get("rules", "-"),
            reply_markup=with_back([], "main_menu", settings),
            parse_mode="HTML",
        )


@router.callback_query(F.data == "support")
async def cb_support(call: CallbackQuery, state: FSMContext):
    async with get_session() as session:
        settings = await get_settings(session)
        await call.message.edit_text(
            settings.texts.get("support_prompt", "پیام خود را بنویسید:"),
            reply_markup=with_back([], "main_menu", settings),
            parse_mode="HTML",
        )
    await state.set_state(Support.waiting_message)


@router.message(Support.waiting_message)
async def support_message_received(message: Message, state: FSMContext):
    async with get_session() as session:
        user = await get_or_create_user(session, message.from_user)
        session.add(SupportMessage(user_id=user.id, message=message.text or ""))
        await session.commit()
        settings = await get_settings(session)

        # parse_mode=None on purpose: this text is raw user input and must
        # never be interpreted as HTML/Markdown (a stray '<' or '&' would
        # otherwise make Telegram reject the whole message and the report
        # would silently never arrive).
        notify_text = (
            f"📩 پیام پشتیبانی جدید\n"
            f"کاربر: {user.telegram_id} (@{user.username})\n\n"
            f"{message.text}"
        )
        await notify_admins(message.bot, session, notify_text, parse_mode=None)

        await message.answer(settings.texts.get("support_received", "ارسال شد ✅"), reply_markup=main_menu(settings),
                              parse_mode="HTML")
    await state.clear()
