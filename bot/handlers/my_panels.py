import datetime

from aiogram import F, Router
from aiogram.fsm.context import FSMContext
from aiogram.types import CallbackQuery, InlineKeyboardButton, InlineKeyboardMarkup, Message
from sqlalchemy import select

from bot.handlers.common import get_or_create_user, get_settings
from bot.keyboards import cancel_kb, with_back
from bot.states import ManageReseller
from database.db import get_session
from database.models import Panel, Reseller, ResellerStatus
from utils.panel_client import (
    change_reseller_password,
    delete_reseller_admin,
    disable_reseller_admin,
    enable_reseller_admin,
)
from utils.validators import generate_valid_password, password_errors

router = Router(name="my_panels")


@router.callback_query(F.data == "my_panels")
async def cb_my_panels(call: CallbackQuery):
    async with get_session() as session:
        user = await get_or_create_user(session, call.from_user)
        settings = await get_settings(session)
        result = await session.execute(
            select(Reseller).where(Reseller.user_id == user.id, Reseller.status != ResellerStatus.DELETED)
        )
        resellers = result.scalars().all()
        if not resellers:
            await call.message.edit_text("شما هنوز نمایندگی‌ای خریداری نکرده‌اید.",
                                          reply_markup=with_back([], "main_menu", settings))
            return

        rows = [
            [InlineKeyboardButton(
                text=f"{'🟢' if r.status == ResellerStatus.ACTIVE else '🔴'} {r.panel_username}",
                callback_data=f"reseller_{r.id}",
            )]
            for r in resellers
        ]
        await call.message.edit_text("نمایندگی‌های شما:", reply_markup=with_back(rows, "main_menu", settings))


async def _render_reseller_detail(session, reseller_id: int):
    reseller = await session.get(Reseller, reseller_id)
    if reseller is None:
        return None, None
    panel = await session.get(Panel, reseller.panel_id)  # explicit fetch - see note in buy_panel.py

    status_txt = "فعال 🟢" if reseller.status == ResellerStatus.ACTIVE else "غیرفعال 🔴"
    expire_txt = reseller.expire_at.strftime("%Y-%m-%d %H:%M") if reseller.expire_at else "بدون محدودیت"
    limit_txt = f"{reseller.usage_limit_toman:,.0f} تومان" if reseller.usage_limit_toman else "بدون محدودیت"

    text = (
        f"👤 نام کاربری: {reseller.panel_username}\n"
        f"📶 وضعیت: {status_txt}\n"
        f"💸 مصرف شده: {reseller.spent_toman:,.0f} تومان\n"
        f"🧮 محدودیت مصرف: {limit_txt}\n"
        f"⏰ تاریخ انقضا: {expire_txt}\n"
    )

    login_address = panel.resell_address or panel.address
    rid = reseller.id
    kb_rows = [
        [InlineKeyboardButton(text="🌐 ورود به پنل", url=login_address)],
        [InlineKeyboardButton(text="🔄 بروزرسانی", callback_data=f"reseller_{rid}")],
        [InlineKeyboardButton(text="🔑 تغییر رمز", callback_data=f"rsact_pwd_{rid}")],
        [
            InlineKeyboardButton(
                text="⛔️ غیرفعال کردن" if reseller.status == ResellerStatus.ACTIVE else "▶️ فعال کردن",
                callback_data=f"rsact_toggle_{rid}",
            ),
            InlineKeyboardButton(text="🗑 حذف", callback_data=f"rsact_delete_{rid}"),
        ],
        [InlineKeyboardButton(text="🧮 تنظیم محدودیت مصرف", callback_data=f"rsact_limit_{rid}")],
        [InlineKeyboardButton(text="⏰ تنظیم تاریخ انقضا", callback_data=f"rsact_expire_{rid}")],
    ]
    return text, kb_rows


@router.callback_query(F.data.regexp(r"^reseller_\d+$"))
async def cb_reseller_detail(call: CallbackQuery):
    reseller_id = int(call.data.split("_")[1])
    async with get_session() as session:
        text, kb_rows = await _render_reseller_detail(session, reseller_id)
        settings = await get_settings(session)
        if text is None:
            await call.answer("یافت نشد.", show_alert=True)
            return
        await call.message.edit_text(text, reply_markup=with_back(kb_rows, "my_panels", settings))


@router.callback_query(F.data.startswith("rsact_toggle_"))
async def cb_toggle(call: CallbackQuery):
    rid = int(call.data.split("_")[-1])
    async with get_session() as session:
        reseller = await session.get(Reseller, rid)
        panel = await session.get(Panel, reseller.panel_id)
        try:
            if reseller.status == ResellerStatus.ACTIVE:
                await disable_reseller_admin(panel, reseller.panel_username)
                reseller.status = ResellerStatus.DISABLED
            else:
                await enable_reseller_admin(panel, reseller.panel_username)
                reseller.status = ResellerStatus.ACTIVE
            await session.commit()
        except Exception as e:
            await call.answer(f"خطا: {e}", show_alert=True)
            return
        text, kb_rows = await _render_reseller_detail(session, rid)
        settings = await get_settings(session)
    await call.answer("انجام شد ✅")
    await call.message.edit_text(text, reply_markup=with_back(kb_rows, "my_panels", settings))


@router.callback_query(F.data.startswith("rsact_delete_"))
async def cb_delete(call: CallbackQuery):
    rid = int(call.data.split("_")[-1])
    async with get_session() as session:
        reseller = await session.get(Reseller, rid)
        panel = await session.get(Panel, reseller.panel_id)
        try:
            await delete_reseller_admin(panel, reseller.panel_username)
        except Exception:
            pass
        reseller.status = ResellerStatus.DELETED
        await session.commit()
        settings = await get_settings(session)
    await call.message.edit_text("نمایندگی حذف شد.", reply_markup=with_back([], "my_panels", settings))


@router.callback_query(F.data.startswith("rsact_pwd_"))
async def cb_change_pwd_start(call: CallbackQuery, state: FSMContext):
    rid = int(call.data.split("_")[-1])
    await state.update_data(reseller_id=rid)
    async with get_session() as session:
        settings = await get_settings(session)
        suggested = generate_valid_password()
        await state.update_data(suggested_password=suggested)
        kb = InlineKeyboardMarkup(inline_keyboard=[
            [InlineKeyboardButton(text="✅ استفاده از رمز پیشنهادی", callback_data="use_suggested_new_pwd")],
            [InlineKeyboardButton(text="بازگشت", callback_data=f"reseller_{rid}")],
        ])
        await call.message.edit_text(
            "رمز جدید را وارد کنید (۲ حرف بزرگ، ۲ حرف کوچک، ۴ عدد، ۱ کاراکتر ویژه، حداقل ۸ کاراکتر) "
            "یا از رمز پیشنهادی استفاده کنید:\n"
            f"{suggested}",
            reply_markup=kb,
        )
    await state.set_state(ManageReseller.waiting_new_password)


async def _apply_new_password(answer_target, state: FSMContext, password: str):
    data = await state.get_data()
    rid = data["reseller_id"]
    async with get_session() as session:
        reseller = await session.get(Reseller, rid)
        panel = await session.get(Panel, reseller.panel_id)
        try:
            await change_reseller_password(panel, reseller.panel_username, password)
        except Exception as e:
            await answer_target.answer(f"خطا: {e}")
            await state.clear()
            return
        reseller.panel_password = password
        await session.commit()
    await answer_target.answer(f"رمز با موفقیت تغییر کرد ✅\nرمز جدید: {password}")
    await state.clear()


@router.callback_query(ManageReseller.waiting_new_password, F.data == "use_suggested_new_pwd")
async def use_suggested_new_pwd(call: CallbackQuery, state: FSMContext):
    data = await state.get_data()
    await _apply_new_password(call.message, state, data["suggested_password"])


@router.message(ManageReseller.waiting_new_password)
async def new_password_received(message: Message, state: FSMContext):
    password = (message.text or "").strip()
    errors = password_errors(password)
    if errors:
        await message.answer("رمز نامعتبر است:\n- " + "\n- ".join(errors))
        return
    await _apply_new_password(message, state, password)


@router.callback_query(F.data.startswith("rsact_limit_"))
async def cb_limit_start(call: CallbackQuery, state: FSMContext):
    rid = int(call.data.split("_")[-1])
    await state.update_data(reseller_id=rid)
    async with get_session() as session:
        settings = await get_settings(session)
        await call.message.edit_text(
            "حداکثر مبلغی که این نمایندگی مجاز است مصرف کند را به تومان وارد کنید (برای حذف محدودیت 0 بفرستید):",
            reply_markup=cancel_kb(settings, f"reseller_{rid}"),
        )
    await state.set_state(ManageReseller.waiting_usage_limit)


@router.message(ManageReseller.waiting_usage_limit)
async def limit_received(message: Message, state: FSMContext):
    if not message.text or not message.text.isdigit():
        await message.answer("لطفا فقط عدد وارد کنید.")
        return
    amount = float(message.text)
    data = await state.get_data()
    async with get_session() as session:
        reseller = await session.get(Reseller, data["reseller_id"])
        reseller.usage_limit_toman = amount if amount > 0 else None
        reseller.spent_toman = 0
        await session.commit()
    await message.answer("محدودیت مصرف بروزرسانی شد ✅")
    await state.clear()


@router.callback_query(F.data.startswith("rsact_expire_"))
async def cb_expire_start(call: CallbackQuery, state: FSMContext):
    rid = int(call.data.split("_")[-1])
    await state.update_data(reseller_id=rid)
    async with get_session() as session:
        settings = await get_settings(session)
        await call.message.edit_text(
            "تاریخ انقضا را به فرمت YYYY-MM-DD وارد کنید (برای حذف تاریخ انقضا عدد 0 بفرستید):",
            reply_markup=cancel_kb(settings, f"reseller_{rid}"),
        )
    await state.set_state(ManageReseller.waiting_expire_date)


@router.message(ManageReseller.waiting_expire_date)
async def expire_received(message: Message, state: FSMContext):
    text = (message.text or "").strip()
    data = await state.get_data()
    async with get_session() as session:
        reseller = await session.get(Reseller, data["reseller_id"])
        if text == "0":
            reseller.expire_at = None
        else:
            try:
                reseller.expire_at = datetime.datetime.strptime(text, "%Y-%m-%d")
            except ValueError:
                await message.answer("فرمت تاریخ نامعتبر است. مثال: 2026-12-31")
                return
        await session.commit()
    await message.answer("تاریخ انقضا بروزرسانی شد ✅")
    await state.clear()
