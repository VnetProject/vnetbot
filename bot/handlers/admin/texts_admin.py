from aiogram import F, Router
from aiogram.fsm.context import FSMContext
from aiogram.types import CallbackQuery, InlineKeyboardButton, Message

from bot.handlers.common import get_settings
from bot.keyboards import admin_main_menu, cancel_kb, with_back
from bot.states import AdminSettings
from database.db import get_session
from utils.auth import is_admin

router = Router(name="admin_texts")

TEXT_LABELS = {
    "welcome": "متن خوش‌آمدگویی",
    "rules": "متن قوانین",
    "faq": "متن سوالات متداول",
    "insufficient_balance": "متن موجودی ناکافی (از {min_balance} استفاده کنید)",
    "support_prompt": "متن درخواست پیام پشتیبانی",
    "support_received": "متن تایید دریافت پیام پشتیبانی",
}


@router.callback_query(F.data == "adm_texts")
async def cb_texts_home(call: CallbackQuery):
    if not is_admin(call.from_user.id):
        return
    async with get_session() as session:
        settings = await get_settings(session)
        rows = [[InlineKeyboardButton(text=label, callback_data=f"admtxt_{key}")] for key, label in TEXT_LABELS.items()]
        await call.message.edit_text("کدام متن را می‌خواهید ویرایش کنید؟", reply_markup=with_back(rows, "adm_home", settings))


@router.callback_query(F.data.startswith("admtxt_"))
async def cb_text_edit_start(call: CallbackQuery, state: FSMContext):
    if not is_admin(call.from_user.id):
        return
    key = call.data.split("_", 1)[1]
    await state.update_data(text_key=key)
    async with get_session() as session:
        settings = await get_settings(session)
        current = settings.texts.get(key, "-")
        await call.message.edit_text(
            f"متن فعلی:\n\n{current}\n\n"
            "متن جدید را ارسال کنید. اگر می‌خواهید از ایموجی پرمیوم استفاده کنید، خود ایموجی را همراه "
            "متن، درست همان‌جایی که باید نمایش داده شود، بفرستید - هر تعداد که بخواهید:",
            reply_markup=cancel_kb(settings, "adm_texts"),
        )
    await state.set_state(AdminSettings.editing_text_value)


@router.message(AdminSettings.editing_text_value)
async def text_value_received(message: Message, state: FSMContext):
    data = await state.get_data()
    # html_text preserves any Premium custom-emoji the admin typed/pasted as
    # <tg-emoji emoji-id="...">...</tg-emoji> tags, so they render for end
    # users too (sent with parse_mode="HTML" wherever settings.texts is used).
    new_value = message.html_text
    async with get_session() as session:
        settings = await get_settings(session)
        texts = dict(settings.texts)
        texts[data["text_key"]] = new_value
        settings.texts = texts
        await session.commit()
    await message.answer("✅ متن بروزرسانی شد.", reply_markup=admin_main_menu(settings))
    await state.clear()
