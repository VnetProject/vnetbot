from aiogram import F, Router
from aiogram.fsm.context import FSMContext
from aiogram.types import CallbackQuery, InlineKeyboardButton, Message
from sqlalchemy import select

from bot.handlers.common import get_settings
from bot.keyboards import admin_main_menu, cancel_kb, with_back
from bot.states import AdminSettings
from database.db import get_session
from database.models import Panel
from utils.auth import is_admin

router = Router(name="admin_settings")


@router.callback_query(F.data == "adm_settings")
async def cb_settings_home(call: CallbackQuery):
    if not is_admin(call.from_user.id):
        return
    async with get_session() as session:
        settings = await get_settings(session)
        text = (
            f"💳 شماره کارت: {settings.card_number or '-'}\n"
            f"💎 ولت TON: {settings.ton_wallet or '-'}\n"
            f"↔️ حداقل/حداکثر شارژ: {settings.min_charge:,.0f} / {settings.max_charge:,.0f}\n"
            f"💵 نرخ دلار به تومان: {settings.usd_to_toman:,.0f}\n"
            f"🎁 تست: {settings.test_data_limit_gb:g} مگابایت / {settings.test_days} روز / "
            f"{'فعال' if settings.test_enabled else 'غیرفعال'}\n"
        )
        rows = [
            [InlineKeyboardButton(text="✏️ شماره کارت", callback_data="adms_card_number")],
            [InlineKeyboardButton(text="✏️ ولت TON", callback_data="adms_ton_wallet")],
            [InlineKeyboardButton(text="✏️ حداقل شارژ", callback_data="adms_min_charge")],
            [InlineKeyboardButton(text="✏️ حداکثر شارژ", callback_data="adms_max_charge")],
            [InlineKeyboardButton(text="✏️ نرخ دلار به تومان", callback_data="adms_usd_rate")],
            [InlineKeyboardButton(text="✏️ حجم تست (مگابایت)", callback_data="adms_test_gb")],
            [InlineKeyboardButton(text="✏️ مدت تست (روز)", callback_data="adms_test_days")],
            [InlineKeyboardButton(text="🖥 انتخاب پنل تست", callback_data="adms_test_panel")],
            [InlineKeyboardButton(
                text="⛔️ غیرفعال کردن تست" if settings.test_enabled else "▶️ فعال کردن تست",
                callback_data="adms_toggle_test",
            )],
        ]
        await call.message.edit_text(text, reply_markup=with_back(rows, "adm_home", settings))


_FIELD_MAP = {
    "adms_card_number": ("card_number", AdminSettings.card_number, "شماره کارت جدید را وارد کنید:", "str"),
    "adms_ton_wallet": ("ton_wallet", AdminSettings.ton_wallet, "آدرس ولت TON جدید را وارد کنید:", "str"),
    "adms_min_charge": ("min_charge", AdminSettings.min_charge, "حداقل مبلغ شارژ (تومان) را وارد کنید:", "float"),
    "adms_max_charge": ("max_charge", AdminSettings.max_charge, "حداکثر مبلغ شارژ (تومان) را وارد کنید:", "float"),
    "adms_usd_rate": ("usd_to_toman", AdminSettings.usd_rate, "نرخ هر دلار به تومان را وارد کنید:", "float"),
    "adms_test_gb": ("test_data_limit_gb", AdminSettings.test_gb, "حجم تست به مگابایت را وارد کنید:", "float"),
    "adms_test_days": ("test_days", AdminSettings.test_days, "مدت تست به روز را وارد کنید:", "int"),
}


@router.callback_query(F.data.in_(_FIELD_MAP.keys()))
async def cb_settings_field_start(call: CallbackQuery, state: FSMContext):
    if not is_admin(call.from_user.id):
        return
    field, fsm_state, prompt, cast = _FIELD_MAP[call.data]
    await state.update_data(field=field, cast=cast)
    await state.set_state(fsm_state)
    async with get_session() as session:
        settings = await get_settings(session)
        await call.message.edit_text(prompt, reply_markup=cancel_kb(settings, "adm_settings"))


@router.message(AdminSettings.card_number)
@router.message(AdminSettings.ton_wallet)
@router.message(AdminSettings.min_charge)
@router.message(AdminSettings.max_charge)
@router.message(AdminSettings.usd_rate)
@router.message(AdminSettings.test_gb)
@router.message(AdminSettings.test_days)
async def settings_field_received(message: Message, state: FSMContext):
    data = await state.get_data()
    value = message.text.strip()
    cast = data["cast"]
    if cast == "float":
        if not value.replace(".", "", 1).isdigit():
            await message.answer("لطفا فقط عدد وارد کنید.")
            return
        value = float(value)
    elif cast == "int":
        if not value.isdigit():
            await message.answer("لطفا فقط عدد وارد کنید.")
            return
        value = int(value)

    async with get_session() as session:
        settings = await get_settings(session)
        setattr(settings, data["field"], value)
        await session.commit()
    await message.answer("✅ بروزرسانی شد.", reply_markup=admin_main_menu(settings))
    await state.clear()


@router.callback_query(F.data == "adms_toggle_test")
async def cb_toggle_test(call: CallbackQuery):
    if not is_admin(call.from_user.id):
        return
    async with get_session() as session:
        settings = await get_settings(session)
        settings.test_enabled = not settings.test_enabled
        await session.commit()
    await cb_settings_home(call)


@router.callback_query(F.data == "adms_test_panel")
async def cb_test_panel_choose(call: CallbackQuery):
    if not is_admin(call.from_user.id):
        return
    async with get_session() as session:
        settings = await get_settings(session)
        result = await session.execute(select(Panel).where(Panel.is_active == True))  # noqa: E712
        panels = result.scalars().all()
        rows = [[InlineKeyboardButton(text=p.name, callback_data=f"adms_settestpanel_{p.id}")] for p in panels]
        await call.message.edit_text("پنل تست را انتخاب کنید:", reply_markup=with_back(rows, "adm_settings", settings))


@router.callback_query(F.data.startswith("adms_settestpanel_"))
async def cb_test_panel_set(call: CallbackQuery):
    if not is_admin(call.from_user.id):
        return
    pid = int(call.data.split("_")[-1])
    async with get_session() as session:
        settings = await get_settings(session)
        settings.test_panel_id = pid
        await session.commit()
    await call.answer("✅ تنظیم شد")
    await cb_settings_home(call)
