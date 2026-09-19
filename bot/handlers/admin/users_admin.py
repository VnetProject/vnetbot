from aiogram import F, Router
from aiogram.fsm.context import FSMContext
from aiogram.types import CallbackQuery, InlineKeyboardButton, InlineKeyboardMarkup, Message
from sqlalchemy import select

from bot.keyboards import admin_main_menu, cancel_kb
from utils.auth import is_admin
from bot.handlers.common import get_settings
from bot.keyboards import with_back
from bot.states import AdminUserSearch
from database.db import get_session
from database.models import Transaction, TransactionStatus, TransactionType, User

router = Router(name="admin_users")


@router.callback_query(F.data == "adm_users")
async def cb_users_start(call: CallbackQuery, state: FSMContext):
    if not is_admin(call.from_user.id):
        return
    async with get_session() as session:
        settings = await get_settings(session)
        await call.message.edit_text("آیدی عددی کاربر را ارسال کنید:", reply_markup=with_back([], "adm_home", settings))
    await state.set_state(AdminUserSearch.waiting_id)


@router.message(AdminUserSearch.waiting_id)
async def user_id_received(message: Message, state: FSMContext):
    if not message.text or not message.text.isdigit():
        await message.answer("لطفا فقط آیدی عددی ارسال کنید.")
        return
    tg_id = int(message.text)
    async with get_session() as session:
        result = await session.execute(select(User).where(User.telegram_id == tg_id))
        user = result.scalar_one_or_none()
        if user is None:
            await message.answer("کاربری با این آیدی یافت نشد.")
            await state.clear()
            return

        await state.update_data(user_id=user.id)
        text = (
            f"👤 کاربر: {user.telegram_id} (@{user.username})\n"
            f"💰 موجودی: {user.balance:,.0f} تومان\n"
            f"وضعیت: {'مسدود ⛔️' if user.is_banned else 'عادی ✅'}"
        )
        kb = InlineKeyboardMarkup(inline_keyboard=[
            [InlineKeyboardButton(text="➕ افزایش موجودی", callback_data="admu_add_balance"),
             InlineKeyboardButton(text="➖ کاهش موجودی", callback_data="admu_sub_balance")],
            [InlineKeyboardButton(text="⛔️ مسدود کردن" if not user.is_banned else "✅ رفع مسدودیت",
                                   callback_data="admu_toggle_ban")],
            [InlineKeyboardButton(text="✉️ ارسال پیام به کاربر", callback_data="admu_message")],
        ])
        await message.answer(text, reply_markup=kb)
    await state.clear()
    await state.update_data(user_id=user.id)


@router.callback_query(F.data == "admu_toggle_ban")
async def cb_toggle_ban(call: CallbackQuery, state: FSMContext):
    if not is_admin(call.from_user.id):
        return
    data = await state.get_data()
    async with get_session() as session:
        user = await session.get(User, data["user_id"])
        user.is_banned = not user.is_banned
        await session.commit()
        settings = await get_settings(session)
    await call.message.edit_text("✅ وضعیت بروزرسانی شد.", reply_markup=admin_main_menu(settings))


@router.callback_query(F.data.in_(["admu_add_balance", "admu_sub_balance"]))
async def cb_balance_start(call: CallbackQuery, state: FSMContext):
    if not is_admin(call.from_user.id):
        return
    await state.update_data(direction=("add" if call.data == "admu_add_balance" else "sub"))
    await state.set_state(AdminUserSearch.waiting_balance_amount)
    async with get_session() as session:
        settings = await get_settings(session)
        await call.message.edit_text("مبلغ را به تومان وارد کنید:", reply_markup=cancel_kb(settings, "adm_users"))


@router.message(AdminUserSearch.waiting_balance_amount)
async def balance_amount_received(message: Message, state: FSMContext):
    if not message.text.isdigit():
        await message.answer("لطفا فقط عدد وارد کنید.")
        return
    amount = float(message.text)
    data = await state.get_data()
    async with get_session() as session:
        user = await session.get(User, data["user_id"])
        signed = amount if data["direction"] == "add" else -amount
        user.balance += signed
        session.add(Transaction(user_id=user.id, amount=signed, type=TransactionType.ADMIN_ADJUST,
                                 status=TransactionStatus.APPROVED, note="تنظیم دستی توسط مدیریت"))
        await session.commit()
        settings = await get_settings(session)
        try:
            await message.bot.send_message(
                user.telegram_id,
                f"💰 موجودی کیف پول شما توسط مدیریت {'افزایش' if signed > 0 else 'کاهش'} یافت "
                f"({abs(signed):,.0f} تومان).\nموجودی جدید: {user.balance:,.0f} تومان",
            )
        except Exception:
            pass
    await message.answer(f"✅ موجودی جدید کاربر: {user.balance:,.0f} تومان", reply_markup=admin_main_menu(settings))
    await state.clear()


@router.callback_query(F.data == "admu_message")
async def cb_message_start(call: CallbackQuery, state: FSMContext):
    if not is_admin(call.from_user.id):
        return
    await state.set_state(AdminUserSearch.waiting_message)
    async with get_session() as session:
        settings = await get_settings(session)
        await call.message.edit_text("متن پیام برای ارسال به کاربر را بنویسید:",
                                      reply_markup=cancel_kb(settings, "adm_users"))


@router.message(AdminUserSearch.waiting_message)
async def message_to_user_received(message: Message, state: FSMContext):
    data = await state.get_data()
    async with get_session() as session:
        user = await session.get(User, data["user_id"])
        settings = await get_settings(session)
        try:
            await message.bot.send_message(user.telegram_id, f"📩 پیام از مدیریت:\n\n{message.text}")
            await message.answer("✅ پیام ارسال شد.", reply_markup=admin_main_menu(settings))
        except Exception as e:
            await message.answer(f"خطا در ارسال پیام: {e}")
    await state.clear()
