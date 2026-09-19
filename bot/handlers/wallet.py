from aiogram import F, Router
from aiogram.fsm.context import FSMContext
from aiogram.types import CallbackQuery, InlineKeyboardButton, InlineKeyboardMarkup, Message

from bot.handlers.common import get_or_create_user, get_settings, notify_admins
from bot.keyboards import cancel_kb, with_back
from bot.states import Wallet
from database.db import get_session
from database.models import Transaction, TransactionStatus, TransactionType, User
from utils.price import toman_to_ton

router = Router(name="wallet")


@router.callback_query(F.data == "wallet")
async def cb_wallet(call: CallbackQuery):
    async with get_session() as session:
        user = await get_or_create_user(session, call.from_user)
        settings = await get_settings(session)
        text = f"💰 موجودی کیف پول شما: {user.balance:,.0f} تومان"
        kb = InlineKeyboardMarkup(inline_keyboard=[
            [InlineKeyboardButton(text="💳 شارژ با کارت به کارت", callback_data="charge_card")],
            [InlineKeyboardButton(text="💎 شارژ با تون کوین (TON)", callback_data="charge_ton")],
            [InlineKeyboardButton(text="بازگشت", callback_data="main_menu")],
        ])
        await call.message.edit_text(text, reply_markup=kb)


@router.callback_query(F.data == "charge_card")
async def cb_charge_card(call: CallbackQuery, state: FSMContext):
    async with get_session() as session:
        settings = await get_settings(session)
        await call.message.edit_text(
            f"مبلغ مورد نظر برای شارژ را به تومان وارد کنید.\n"
            f"حداقل: {settings.min_charge:,.0f} | حداکثر: {settings.max_charge:,.0f}",
            reply_markup=cancel_kb(settings, "wallet"),
        )
    await state.set_state(Wallet.waiting_card_amount)


@router.message(Wallet.waiting_card_amount)
async def card_amount_received(message: Message, state: FSMContext):
    if not message.text or not message.text.isdigit():
        await message.answer("لطفا فقط عدد وارد کنید.")
        return
    amount = float(message.text)
    async with get_session() as session:
        settings = await get_settings(session)
        if not (settings.min_charge <= amount <= settings.max_charge):
            await message.answer(
                f"مبلغ باید بین {settings.min_charge:,.0f} و {settings.max_charge:,.0f} تومان باشد."
            )
            return

        user = await get_or_create_user(session, message.from_user)
        tx = Transaction(user_id=user.id, amount=amount, type=TransactionType.CHARGE_CARD,
                          status=TransactionStatus.PENDING)
        session.add(tx)
        await session.commit()
        tx_id = tx.id

        card = settings.card_number or "تنظیم نشده - با پشتیبانی تماس بگیرید"
        await message.answer(
            f"مبلغ {amount:,.0f} تومان را به شماره کارت زیر واریز کنید و سپس عکس رسید را ارسال نمایید:\n\n"
            f"💳 {card}\n\n"
            f"کد پیگیری تراکنش شما: #{tx_id}",
            reply_markup=cancel_kb(settings, "wallet"),
        )
    await state.set_state(Wallet.waiting_receipt)
    await state.update_data(tx_id=tx_id)


@router.message(Wallet.waiting_receipt, F.photo)
async def receipt_received(message: Message, state: FSMContext):
    data = await state.get_data()
    tx_id = data.get("tx_id")
    async with get_session() as session:
        user = await get_or_create_user(session, message.from_user)

        caption = (
            f"🧾 رسید واریز جدید\n"
            f"کاربر: {user.telegram_id} (@{user.username})\n"
            f"تراکنش: #{tx_id}"
        )
        kb = InlineKeyboardMarkup(inline_keyboard=[[
            InlineKeyboardButton(text="✅ تایید", callback_data=f"tx_approve_{tx_id}"),
            InlineKeyboardButton(text="❌ رد", callback_data=f"tx_reject_{tx_id}"),
        ]])

        settings = await get_settings(session)
        sent_ok = False
        # try the report channel first (keeps the approve/reject buttons there)
        if settings.report_channel:
            try:
                await message.bot.send_photo(settings.report_channel, message.photo[-1].file_id,
                                              caption=caption, parse_mode=None, reply_markup=kb)
                sent_ok = True
            except Exception:
                pass
        # always ALSO try the owner directly, so a misconfigured/absent
        # report channel never means the receipt just disappears
        from config import OWNER_ID
        try:
            await message.bot.send_photo(OWNER_ID, message.photo[-1].file_id, caption=caption,
                                          parse_mode=None, reply_markup=kb)
            sent_ok = True
        except Exception:
            pass

        if sent_ok:
            await message.answer("رسید شما دریافت شد و پس از تایید ادمین موجودی شما شارژ خواهد شد ✅")
        else:
            await message.answer("رسید دریافت شد اما ارسال آن به مدیریت با خطا مواجه شد؛ لطفا با پشتیبانی تماس بگیرید.")
    await state.clear()


@router.callback_query(F.data.startswith("tx_approve_") | F.data.startswith("tx_reject_"))
async def cb_tx_review(call: CallbackQuery):
    """Owner/report-channel admins tap approve/reject on a submitted receipt."""
    approve = call.data.startswith("tx_approve_")
    tx_id = int(call.data.split("_")[-1])
    async with get_session() as session:
        tx = await session.get(Transaction, tx_id)
        if tx is None or tx.status != TransactionStatus.PENDING:
            await call.answer("این تراکنش قبلا بررسی شده است.", show_alert=True)
            return
        user = await session.get(User, tx.user_id)
        if approve:
            user.balance += tx.amount
            tx.status = TransactionStatus.APPROVED
            await session.commit()
            new_caption = (call.message.caption or "") + "\n\n✅ تایید شد"
            try:
                await call.message.edit_caption(caption=new_caption)
            except Exception:
                pass
            try:
                await call.bot.send_message(user.telegram_id, f"✅ کیف پول شما به مبلغ {tx.amount:,.0f} تومان شارژ شد.")
            except Exception:
                pass
        else:
            tx.status = TransactionStatus.REJECTED
            await session.commit()
            new_caption = (call.message.caption or "") + "\n\n❌ رد شد"
            try:
                await call.message.edit_caption(caption=new_caption)
            except Exception:
                pass
            try:
                await call.bot.send_message(user.telegram_id, "❌ رسید واریزی شما تایید نشد. با پشتیبانی تماس بگیرید.")
            except Exception:
                pass
    await call.answer()


@router.callback_query(F.data == "charge_ton")
async def cb_charge_ton(call: CallbackQuery, state: FSMContext):
    async with get_session() as session:
        settings = await get_settings(session)
        await call.message.edit_text(
            "مبلغ مورد نظر برای شارژ را به تومان وارد کنید تا معادل آن به TON محاسبه شود.",
            reply_markup=cancel_kb(settings, "wallet"),
        )
    await state.set_state(Wallet.waiting_ton_amount)


@router.message(Wallet.waiting_ton_amount)
async def ton_amount_received(message: Message, state: FSMContext):
    if not message.text or not message.text.isdigit():
        await message.answer("لطفا فقط عدد وارد کنید.")
        return
    amount = float(message.text)
    async with get_session() as session:
        settings = await get_settings(session)
        if not (settings.min_charge <= amount <= settings.max_charge):
            await message.answer(
                f"مبلغ باید بین {settings.min_charge:,.0f} و {settings.max_charge:,.0f} تومان باشد."
            )
            return
        try:
            ton_amount = await toman_to_ton(amount, settings.usd_to_toman)
        except Exception:
            await message.answer("خطا در دریافت قیمت لحظه‌ای TON. لطفا بعدا تلاش کنید.")
            await state.clear()
            return

        user = await get_or_create_user(session, message.from_user)
        tx = Transaction(user_id=user.id, amount=amount, type=TransactionType.CHARGE_TON,
                          status=TransactionStatus.PENDING)
        session.add(tx)
        await session.commit()
        tx_id = tx.id

        wallet_addr = settings.ton_wallet or "تنظیم نشده - با پشتیبانی تماس بگیرید"
        await message.answer(
            f"معادل {amount:,.0f} تومان: {ton_amount} TON\n\n"
            f"مقدار بالا را به کیف پول زیر واریز کرده و سپس هش تراکنش (TX Hash) را به همین چت ارسال کنید:\n"
            f"💎 {wallet_addr}\n\nکد پیگیری: #{tx_id}",
            reply_markup=cancel_kb(settings, "wallet"),
        )
    await state.update_data(tx_id=tx_id)
    await state.set_state(Wallet.waiting_receipt)


@router.message(Wallet.waiting_receipt, F.text)
async def ton_hash_received(message: Message, state: FSMContext):
    """TX hash sent as plain text (TON charges don't have a photo receipt)."""
    data = await state.get_data()
    tx_id = data.get("tx_id")
    async with get_session() as session:
        user = await get_or_create_user(session, message.from_user)
        notify_text = (
            f"💎 هش تراکنش TON جدید\n"
            f"کاربر: {user.telegram_id} (@{user.username})\n"
            f"تراکنش: #{tx_id}\n"
            f"هش: {message.text}"
        )
        from aiogram.types import InlineKeyboardButton as Btn
        from aiogram.types import InlineKeyboardMarkup as Markup
        kb = Markup(inline_keyboard=[[
            Btn(text="✅ تایید", callback_data=f"tx_approve_{tx_id}"),
            Btn(text="❌ رد", callback_data=f"tx_reject_{tx_id}"),
        ]])
        settings = await get_settings(session)
        sent_ok = False
        if settings.report_channel:
            try:
                await message.bot.send_message(settings.report_channel, notify_text, parse_mode=None, reply_markup=kb)
                sent_ok = True
            except Exception:
                pass
        from config import OWNER_ID
        try:
            await message.bot.send_message(OWNER_ID, notify_text, parse_mode=None, reply_markup=kb)
            sent_ok = True
        except Exception:
            pass

        if sent_ok:
            await message.answer("هش تراکنش دریافت شد و پس از تایید ادمین موجودی شما شارژ خواهد شد ✅")
        else:
            await message.answer("دریافت شد اما ارسال آن به مدیریت با خطا مواجه شد؛ لطفا با پشتیبانی تماس بگیرید.")
    await state.clear()
