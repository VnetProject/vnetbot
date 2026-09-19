import asyncio

from aiogram import F, Router
from aiogram.fsm.context import FSMContext
from aiogram.types import CallbackQuery, InlineKeyboardButton, InlineKeyboardMarkup, Message
from sqlalchemy import select

from bot.keyboards import admin_main_menu, cancel_kb
from utils.auth import is_admin
from bot.handlers.common import get_settings
from bot.keyboards import with_back
from bot.states import AdminBroadcast, AdminChannel
from database.db import get_session
from database.models import MandatoryChannel, User

router = Router(name="admin_broadcast_channels")


# ---------------- Broadcast ----------------

@router.callback_query(F.data == "adm_broadcast")
async def cb_broadcast_start(call: CallbackQuery, state: FSMContext):
    if not is_admin(call.from_user.id):
        return
    async with get_session() as session:
        settings = await get_settings(session)
        await call.message.edit_text(
            "پیام همگانی (متن، عکس، ویدیو و ...) را ارسال کنید تا برای همه کاربران فوروارد شود:",
            reply_markup=with_back([], "adm_home", settings),
        )
    await state.set_state(AdminBroadcast.waiting_content)


@router.message(AdminBroadcast.waiting_content)
async def broadcast_content_received(message: Message, state: FSMContext):
    async with get_session() as session:
        result = await session.execute(select(User.telegram_id).where(User.is_banned == False))  # noqa: E712
        ids = [row[0] for row in result.all()]
        settings = await get_settings(session)

    sent, failed = 0, 0
    status_msg = await message.answer(f"در حال ارسال به {len(ids)} کاربر...")
    for tg_id in ids:
        try:
            await message.copy_to(tg_id)
            sent += 1
        except Exception:
            failed += 1
        await asyncio.sleep(0.05)  # basic flood-control pacing

    await status_msg.edit_text(f"✅ ارسال شد: {sent} | ❌ ناموفق: {failed}")
    await message.answer("بازگشت به پنل مدیریت:", reply_markup=admin_main_menu(settings))
    await state.clear()


# ---------------- Channels ----------------

@router.callback_query(F.data == "adm_channels")
async def cb_channels_home(call: CallbackQuery):
    if not is_admin(call.from_user.id):
        return
    async with get_session() as session:
        settings = await get_settings(session)
        result = await session.execute(select(MandatoryChannel))
        channels = result.scalars().all()
        text = "📣 کانال‌های جوین اجباری:\n" + ("\n".join(f"- {c.chat_id}" for c in channels) or "(هیچکدام)")
        text += f"\n\n📢 کانال گزارشات: {settings.report_channel or '-'}"
        rows = [
            [InlineKeyboardButton(text="➕ افزودن کانال اجباری", callback_data="admch_add_mandatory")],
            [InlineKeyboardButton(text="✏️ تنظیم کانال گزارشات", callback_data="admch_set_report")],
        ]
        if channels:
            rows += [[InlineKeyboardButton(text=f"🗑 حذف {c.chat_id}", callback_data=f"admch_del_{c.id}")] for c in channels]
        await call.message.edit_text(text, reply_markup=with_back(rows, "adm_home", settings))


@router.callback_query(F.data == "admch_add_mandatory")
async def cb_add_mandatory_start(call: CallbackQuery, state: FSMContext):
    if not is_admin(call.from_user.id):
        return
    await state.set_state(AdminChannel.waiting_mandatory)
    async with get_session() as session:
        settings = await get_settings(session)
        await call.message.edit_text(
            "آیدی عددی یا یوزرنیم کانال را وارد کنید (مثال: @mychannel یا -1001234567890).\n"
            "توجه: ربات باید ادمین آن کانال باشد.",
            reply_markup=cancel_kb(settings, "adm_channels"),
        )


@router.message(AdminChannel.waiting_mandatory)
async def mandatory_channel_received(message: Message, state: FSMContext):
    chat_id = message.text.strip()
    async with get_session() as session:
        session.add(MandatoryChannel(chat_id=chat_id, title=chat_id))
        await session.commit()
        settings = await get_settings(session)
    await message.answer("✅ کانال اضافه شد.", reply_markup=admin_main_menu(settings))
    await state.clear()


@router.callback_query(F.data.startswith("admch_del_"))
async def cb_channel_delete(call: CallbackQuery):
    if not is_admin(call.from_user.id):
        return
    cid = int(call.data.split("_")[-1])
    async with get_session() as session:
        ch = await session.get(MandatoryChannel, cid)
        await session.delete(ch)
        await session.commit()
    await cb_channels_home(call)


@router.callback_query(F.data == "admch_set_report")
async def cb_set_report_start(call: CallbackQuery, state: FSMContext):
    if not is_admin(call.from_user.id):
        return
    await state.set_state(AdminChannel.waiting_report)
    async with get_session() as session:
        settings = await get_settings(session)
        await call.message.edit_text(
            "آیدی عددی یا یوزرنیم کانال گزارشات را وارد کنید (ربات باید در آن ادمین باشد):",
            reply_markup=cancel_kb(settings, "adm_channels"),
        )


@router.message(AdminChannel.waiting_report)
async def report_channel_received(message: Message, state: FSMContext):
    async with get_session() as session:
        settings = await get_settings(session)
        settings.report_channel = message.text.strip()
        await session.commit()
    await message.answer("✅ کانال گزارشات تنظیم شد.", reply_markup=admin_main_menu(settings))
    await state.clear()
