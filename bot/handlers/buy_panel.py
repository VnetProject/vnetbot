from aiogram import F, Router
from aiogram.fsm.context import FSMContext
from aiogram.types import CallbackQuery, InlineKeyboardButton, InlineKeyboardMarkup, Message
from sqlalchemy import select

from bot.handlers.common import get_or_create_user, get_settings
from bot.keyboards import cancel_kb, with_back
from bot.states import BuyPanel
from database.db import get_session
from database.models import Panel, Plan, Reseller, ResellerStatus
from utils.panel_client import create_reseller_admin
from utils.validators import (
    generate_valid_password,
    is_valid_username,
    password_errors,
)

router = Router(name="buy_panel")


@router.callback_query(F.data == "buy_panel")
async def cb_buy_panel(call: CallbackQuery, state: FSMContext):
    await state.clear()
    async with get_session() as session:
        settings = await get_settings(session)
        result = await session.execute(select(Plan).where(Plan.is_active == True))  # noqa: E712
        plans = result.scalars().all()
        if not plans:
            await call.message.edit_text("در حال حاضر پلنی برای فروش موجود نیست.",
                                          reply_markup=with_back([], "main_menu", settings))
            return

        rows = [
            [InlineKeyboardButton(
                text=f"{p.name} | {p.price_per_gb:,.0f}ت/GB | حداقل موجودی {p.min_balance:,.0f}ت",
                callback_data=f"plan_{p.id}",
            )]
            for p in plans
        ]
        await call.message.edit_text("یک پلن را انتخاب کنید:", reply_markup=with_back(rows, "main_menu", settings))
    await state.set_state(BuyPanel.choosing_plan)


@router.callback_query(BuyPanel.choosing_plan, F.data.startswith("plan_"))
async def plan_chosen(call: CallbackQuery, state: FSMContext):
    plan_id = int(call.data.split("_")[1])
    async with get_session() as session:
        plan = await session.get(Plan, plan_id)
        settings = await get_settings(session)
        if plan is None or not plan.is_active:
            await call.answer("این پلن دیگر موجود نیست.", show_alert=True)
            return

        user = await get_or_create_user(session, call.from_user)
        if user.balance < plan.min_balance:
            text = settings.texts.get("insufficient_balance", "موجودی کافی نیست").format(
                min_balance=f"{plan.min_balance:,.0f}"
            )
            await call.message.edit_text(text, reply_markup=with_back([], "buy_panel", settings), parse_mode="HTML")
            return

        await state.update_data(plan_id=plan.id)
        await call.message.edit_text(
            "نام کاربری دلخواه برای پنل خود را وارد کنید (حداقل ۶ کاراکتر، فقط حروف انگلیسی/عدد/آندرلاین):",
            reply_markup=cancel_kb(settings, "buy_panel"),
        )
    await state.set_state(BuyPanel.waiting_username)


@router.message(BuyPanel.waiting_username)
async def username_received(message: Message, state: FSMContext):
    username = (message.text or "").strip()
    if not is_valid_username(username):
        await message.answer("نام کاربری نامعتبر است. باید حداقل ۶ کاراکتر و فقط شامل حروف/عدد/آندرلاین باشد.")
        return
    await state.update_data(username=username)

    async with get_session() as session:
        settings = await get_settings(session)
        suggested = generate_valid_password()
        await state.update_data(suggested_password=suggested)
        kb = InlineKeyboardMarkup(inline_keyboard=[
            [InlineKeyboardButton(text="✅ استفاده از رمز پیشنهادی", callback_data="use_suggested_pwd")],
            [InlineKeyboardButton(text="بازگشت", callback_data="cancel_fsm")],
        ])
        await message.answer(
            "رمز عبور دلخواه را وارد کنید. رمز باید شامل موارد زیر باشد:\n"
            "• حداقل ۲ حرف بزرگ انگلیسی\n"
            "• حداقل ۲ حرف کوچک انگلیسی\n"
            "• حداقل ۴ عدد\n"
            "• حداقل یک کاراکتر ویژه (@ & % $ ! #)\n"
            "• حداقل ۸ کاراکتر\n\n"
            f"یا از رمز پیشنهادی زیر استفاده کنید:\n{suggested}",
            reply_markup=kb,
        )
    await state.set_state(BuyPanel.waiting_password)


async def _finalize_purchase(answer_target, from_user, state: FSMContext, password: str):
    """answer_target must expose .answer(text) - works for both a Message and
    the fake-call message proxy used by slash commands."""
    data = await state.get_data()
    plan_id = data["plan_id"]
    username = data["username"]

    async with get_session() as session:
        plan = await session.get(Plan, plan_id)
        if plan is None:
            await answer_target.answer("این پلن دیگر موجود نیست.")
            await state.clear()
            return
        # NOTE: fetch the Panel explicitly by id instead of `plan.panel` -
        # SQLAlchemy's async ORM does not support implicit lazy-loading of
        # relationships outside of a sync greenlet, and `plan.panel` would
        # crash with "MissingGreenlet" here (this was the bug that made
        # panel purchases silently fail after entering the password).
        panel = await session.get(Panel, plan.panel_id)

        user = await get_or_create_user(session, from_user)

        if user.balance < plan.min_balance:
            await answer_target.answer("موجودی شما دیگر کافی نیست.")
            await state.clear()
            return

        try:
            await create_reseller_admin(panel, username, password, plan.admin_role)
        except Exception as e:
            await answer_target.answer(f"خطا در ایجاد نمایندگی روی پنل: {e}\nبا پشتیبانی تماس بگیرید.")
            await state.clear()
            return

        reseller = Reseller(
            user_id=user.id,
            plan_id=plan.id,
            panel_id=panel.id,
            panel_username=username,
            panel_password=password,
            admin_role=plan.admin_role,
            status=ResellerStatus.ACTIVE,
        )
        session.add(reseller)
        await session.commit()

        login_address = panel.resell_address or panel.address
        await answer_target.answer(
            "✅ نمایندگی شما با موفقیت ساخته شد!\n\n"
            f"🌐 آدرس ورود: {login_address}\n"
            f"👤 نام کاربری: {username}\n"
            f"🔑 رمز عبور: {password}\n\n"
            "می‌توانید از بخش «لیست نمایندگی هام» وضعیت آن را مدیریت کنید."
        )
    await state.clear()


@router.callback_query(BuyPanel.waiting_password, F.data == "use_suggested_pwd")
async def use_suggested_password(call: CallbackQuery, state: FSMContext):
    data = await state.get_data()
    await _finalize_purchase(call.message, call.from_user, state, data["suggested_password"])


@router.message(BuyPanel.waiting_password)
async def password_received(message: Message, state: FSMContext):
    password = (message.text or "").strip()
    errors = password_errors(password)
    if errors:
        await message.answer("رمز عبور نامعتبر است، موارد زیر رعایت نشده:\n- " + "\n- ".join(errors))
        return
    await _finalize_purchase(message, message.from_user, state, password)
