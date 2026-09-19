"""
Admin UI to customize each main-menu button's label text, color (Bot API 9.4
`style`: primary=blue / success=green / danger=red / default=gray) and an
optional Telegram Premium custom-emoji icon - captured by having the admin
just SEND the emoji itself (no id typing needed).
"""
from aiogram import F, Router
from aiogram.fsm.context import FSMContext
from aiogram.types import CallbackQuery, InlineKeyboardButton, Message

from bot.handlers.common import get_settings
from bot.keyboards import BUTTON_KEYS, STYLE_CHOICES, admin_main_menu, cancel_kb, with_back
from bot.states import AdminButtons
from database.db import get_session
from utils.auth import is_admin
from utils.tg import extract_custom_emoji_id

router = Router(name="admin_buttons")


@router.callback_query(F.data == "adm_buttons")
async def cb_buttons_home(call: CallbackQuery):
    if not is_admin(call.from_user.id):
        return
    async with get_session() as session:
        settings = await get_settings(session)
        rows = [[InlineKeyboardButton(text=label, callback_data=f"admbtn_{key}")] for key, label in BUTTON_KEYS.items()]
        await call.message.edit_text(
            "کدام دکمه را می‌خواهید شخصی‌سازی کنید؟\n(«بازگشت» یک تنظیم مشترک برای همه دکمه‌های بازگشت است)",
            reply_markup=with_back(rows, "adm_home", settings),
        )


@router.callback_query(F.data.in_([f"admbtn_{k}" for k in BUTTON_KEYS]))
async def cb_button_detail(call: CallbackQuery):
    if not is_admin(call.from_user.id):
        return
    key = call.data.split("_", 1)[1]
    async with get_session() as session:
        settings = await get_settings(session)
        cfg = (settings.button_colors or {}).get(key, {})
        text = (
            f"دکمه: {BUTTON_KEYS.get(key, key)}\n"
            f"متن فعلی: {cfg.get('label') or '(پیش‌فرض)'}\n"
            f"رنگ فعلی: {cfg.get('style') or 'پیش‌فرض (خاکستری)'}\n"
            f"ایموجی پرمیوم: {'تنظیم شده ✅' if cfg.get('emoji_id') else 'تنظیم نشده'}"
        )
        rows = [
            [InlineKeyboardButton(text="✏️ تغییر متن دکمه", callback_data=f"admbtn_label_{key}")],
            [InlineKeyboardButton(text="🔵 آبی", callback_data=f"admbtn_style_{key}_primary"),
             InlineKeyboardButton(text="🟢 سبز", callback_data=f"admbtn_style_{key}_success")],
            [InlineKeyboardButton(text="🔴 قرمز", callback_data=f"admbtn_style_{key}_danger"),
             InlineKeyboardButton(text="⚪ پیش‌فرض", callback_data=f"admbtn_style_{key}_default")],
            [InlineKeyboardButton(text="😊 تنظیم ایموجی پرمیوم", callback_data=f"admbtn_emoji_{key}")],
            [InlineKeyboardButton(text="🗑 حذف ایموجی", callback_data=f"admbtn_emojidel_{key}")],
        ]
        await call.message.edit_text(text, reply_markup=with_back(rows, "adm_buttons", settings))


async def _get_cfg(session, key: str) -> dict:
    settings = await get_settings(session)
    return dict((settings.button_colors or {}).get(key, {}))


async def _save_cfg(session, key: str, cfg: dict):
    settings = await get_settings(session)
    colors = dict(settings.button_colors or {})
    colors[key] = cfg
    settings.button_colors = colors
    await session.commit()


@router.callback_query(F.data.startswith("admbtn_style_"))
async def cb_button_style_set(call: CallbackQuery):
    if not is_admin(call.from_user.id):
        return
    rest = call.data[len("admbtn_style_"):]  # e.g. "buy_panel_primary" - key itself may contain "_"
    key, style_choice = rest.rsplit("_", 1)
    async with get_session() as session:
        cfg = await _get_cfg(session, key)
        cfg["style"] = STYLE_CHOICES.get(style_choice)
        await _save_cfg(session, key, cfg)
    await call.answer("✅ رنگ بروزرسانی شد")
    call.data = f"admbtn_{key}"
    await cb_button_detail(call)


@router.callback_query(F.data.startswith("admbtn_label_"))
async def cb_button_label_start(call: CallbackQuery, state: FSMContext):
    if not is_admin(call.from_user.id):
        return
    key = call.data.split("_", 2)[2]
    await state.update_data(button_key=key)
    await state.set_state(AdminButtons.waiting_label)
    async with get_session() as session:
        settings = await get_settings(session)
        await call.message.edit_text(
            "متن جدید دکمه را ارسال کنید (می‌توانید ایموجی معمولی هم داخلش بگذارید):",
            reply_markup=cancel_kb(settings, f"admbtn_{key}"),
        )


@router.message(AdminButtons.waiting_label)
async def button_label_received(message: Message, state: FSMContext):
    data = await state.get_data()
    key = data["button_key"]
    async with get_session() as session:
        cfg = await _get_cfg(session, key)
        cfg["label"] = message.text.strip()
        await _save_cfg(session, key, cfg)
        settings = await get_settings(session)
    await message.answer("✅ متن دکمه بروزرسانی شد.", reply_markup=admin_main_menu(settings))
    await state.clear()


@router.callback_query(F.data.startswith("admbtn_emoji_"))
async def cb_button_emoji_start(call: CallbackQuery, state: FSMContext):
    if not is_admin(call.from_user.id):
        return
    key = call.data.split("_", 2)[2]
    await state.update_data(button_key=key)
    await state.set_state(AdminButtons.waiting_emoji)
    async with get_session() as session:
        settings = await get_settings(session)
        await call.message.edit_text(
            "همین‌جا ایموجی پرمیوم مورد نظر را (خودِ ایموجی، نه کد آن) ارسال کنید.\n"
            "توجه: چون شما این ایموجی را ارسال می‌کنید، خودتان باید Telegram Premium داشته باشید "
            "تا ایموجی به‌صورت پرمیوم شناسایی شود؛ در غیر این صورت به‌صورت ایموجی معمولی ذخیره می‌شود.",
            reply_markup=cancel_kb(settings, f"admbtn_{key}"),
        )


@router.message(AdminButtons.waiting_emoji)
async def button_emoji_received(message: Message, state: FSMContext):
    data = await state.get_data()
    key = data["button_key"]
    emoji_id = extract_custom_emoji_id(message)
    async with get_session() as session:
        settings = await get_settings(session)
        if not emoji_id:
            await message.answer(
                "این پیام شامل ایموجی پرمیوم (custom emoji) نبود - یک ایموجی معمولی بود یا متن.\n"
                "یک ایموجی پرمیوم واقعی (از پنل ایموجی تلگرام، بخش پرمیوم) ارسال کنید یا برای انصراف روی دکمه زیر بزنید.",
                reply_markup=cancel_kb(settings, f"admbtn_{key}"),
            )
            return
        cfg = await _get_cfg(session, key)
        cfg["emoji_id"] = emoji_id
        await _save_cfg(session, key, cfg)
    await message.answer("✅ ایموجی دکمه تنظیم شد.", reply_markup=admin_main_menu(settings))
    await state.clear()


@router.callback_query(F.data.startswith("admbtn_emojidel_"))
async def cb_button_emoji_delete(call: CallbackQuery):
    if not is_admin(call.from_user.id):
        return
    key = call.data.split("_", 2)[2]
    async with get_session() as session:
        cfg = await _get_cfg(session, key)
        cfg.pop("emoji_id", None)
        await _save_cfg(session, key, cfg)
    await call.answer("✅ حذف شد")
    call.data = f"admbtn_{key}"
    await cb_button_detail(call)
