from aiogram import F, Router
from aiogram.fsm.context import FSMContext
from aiogram.types import CallbackQuery, InlineKeyboardButton, InlineKeyboardMarkup, Message
from sqlalchemy import select

from bot.handlers.common import get_settings
from bot.keyboards import admin_main_menu, cancel_kb, with_back
from bot.states import AdminPlan
from database.db import get_session
from database.models import Panel, Plan
from utils.auth import is_admin
from utils.panel_client import get_admin_roles

router = Router(name="admin_plan_admin")


@router.callback_query(F.data == "adm_plans")
async def cb_plans_list(call: CallbackQuery):
    if not is_admin(call.from_user.id):
        return
    async with get_session() as session:
        settings = await get_settings(session)
        result = await session.execute(select(Plan))
        plans = result.scalars().all()
        rows = [[InlineKeyboardButton(text=f"{'🟢' if p.is_active else '🔴'} {p.name}", callback_data=f"adm_plan_{p.id}")]
                for p in plans]
        rows.append([InlineKeyboardButton(text="➕ افزودن پلن", callback_data="adm_plan_add")])
        await call.message.edit_text("لیست پلن‌ها:", reply_markup=with_back(rows, "adm_home", settings))


@router.callback_query(F.data == "adm_plan_add")
async def cb_plan_add_start(call: CallbackQuery, state: FSMContext):
    if not is_admin(call.from_user.id):
        return
    async with get_session() as session:
        settings = await get_settings(session)
        result = await session.execute(select(Panel).where(Panel.is_active == True))  # noqa: E712
        panels = result.scalars().all()
        if not panels:
            await call.answer("ابتدا یک پنل اضافه کنید.", show_alert=True)
            return
        await call.message.edit_text("نام پلن را وارد کنید:", reply_markup=cancel_kb(settings, "adm_plans"))
    await state.set_state(AdminPlan.name)


@router.message(AdminPlan.name)
async def plan_name_received(message: Message, state: FSMContext):
    await state.update_data(name=message.text.strip())
    async with get_session() as session:
        settings = await get_settings(session)
        await message.answer("قیمت هر گیگابایت مصرفی را به تومان وارد کنید:",
                              reply_markup=cancel_kb(settings, "adm_plans"))
    await state.set_state(AdminPlan.price_per_gb)


@router.message(AdminPlan.price_per_gb)
async def plan_price_gb_received(message: Message, state: FSMContext):
    if not message.text.replace(".", "", 1).isdigit():
        await message.answer("لطفا فقط عدد وارد کنید.")
        return
    await state.update_data(price_per_gb=float(message.text))
    async with get_session() as session:
        settings = await get_settings(session)
        await message.answer("قیمت هر ساعت روشن بودن نمایندگی را به تومان وارد کنید:",
                              reply_markup=cancel_kb(settings, "adm_plans"))
    await state.set_state(AdminPlan.price_per_hour)


@router.message(AdminPlan.price_per_hour)
async def plan_price_hour_received(message: Message, state: FSMContext):
    if not message.text.replace(".", "", 1).isdigit():
        await message.answer("لطفا فقط عدد وارد کنید.")
        return
    await state.update_data(price_per_hour=float(message.text))
    async with get_session() as session:
        settings = await get_settings(session)
        await message.answer("حداکثر تعداد کاربری که این نمایندگی می‌تواند بسازد را وارد کنید:",
                              reply_markup=cancel_kb(settings, "adm_plans"))
    await state.set_state(AdminPlan.user_limit)


@router.message(AdminPlan.user_limit)
async def plan_user_limit_received(message: Message, state: FSMContext):
    if not message.text.isdigit():
        await message.answer("لطفا فقط عدد وارد کنید.")
        return
    await state.update_data(user_limit=int(message.text))
    async with get_session() as session:
        settings = await get_settings(session)
        result = await session.execute(select(Panel).where(Panel.is_active == True))  # noqa: E712
        panels = result.scalars().all()
        rows = [[InlineKeyboardButton(text=p.name, callback_data=f"planpanel_{p.id}")] for p in panels]
        await message.answer("این پلن برای کدام پنل باشد؟",
                              reply_markup=with_back(rows, "adm_plans", settings))
    await state.set_state(AdminPlan.choosing_panel)


@router.callback_query(AdminPlan.choosing_panel, F.data.startswith("planpanel_"))
async def plan_panel_chosen(call: CallbackQuery, state: FSMContext):
    panel_id = int(call.data.split("_")[1])
    await state.update_data(panel_id=panel_id)
    async with get_session() as session:
        panel = await session.get(Panel, panel_id)
        settings = await get_settings(session)

        roles = await get_admin_roles(panel)
        if roles:
            await state.update_data(available_roles=dict(roles))
            rows = [[InlineKeyboardButton(text=name, callback_data=f"planrole_{rid}")] for rid, name in roles]
            await call.message.edit_text(
                "نقش (رول) ادمین را از لیست زیر که مستقیماً از پنل خوانده شده انتخاب کنید:",
                reply_markup=with_back(rows, "adm_plans", settings),
            )
        else:
            await call.message.edit_text(
                "نتوانستم لیست رول‌ها را مستقیماً از پنل بخوانم (نسخه SDK را بررسی کنید).\n"
                "نام رول را دستی وارد کنید (مثلاً admin یا operator):",
                reply_markup=cancel_kb(settings, "adm_plans"),
            )
    await state.set_state(AdminPlan.choosing_role)


@router.callback_query(AdminPlan.choosing_role, F.data.startswith("planrole_"))
async def plan_role_button_chosen(call: CallbackQuery, state: FSMContext):
    role_id = call.data.split("_", 1)[1]
    await state.update_data(admin_role=role_id)
    async with get_session() as session:
        settings = await get_settings(session)
        await call.message.edit_text("حداقل موجودی کیف پول لازم برای خرید این پلن را به تومان وارد کنید:",
                                      reply_markup=cancel_kb(settings, "adm_plans"))
    await state.set_state(AdminPlan.min_balance)


@router.message(AdminPlan.choosing_role)
async def plan_role_text_received(message: Message, state: FSMContext):
    await state.update_data(admin_role=message.text.strip())
    async with get_session() as session:
        settings = await get_settings(session)
        await message.answer("حداقل موجودی کیف پول لازم برای خرید این پلن را به تومان وارد کنید:",
                              reply_markup=cancel_kb(settings, "adm_plans"))
    await state.set_state(AdminPlan.min_balance)


@router.message(AdminPlan.min_balance)
async def plan_min_balance_received(message: Message, state: FSMContext):
    if not message.text.replace(".", "", 1).isdigit():
        await message.answer("لطفا فقط عدد وارد کنید.")
        return
    data = await state.get_data()
    async with get_session() as session:
        plan = Plan(
            name=data["name"],
            price_per_gb=data["price_per_gb"],
            price_per_hour=data["price_per_hour"],
            user_limit=data["user_limit"],
            panel_id=data["panel_id"],
            admin_role=data["admin_role"],
            min_balance=float(message.text),
        )
        session.add(plan)
        await session.commit()
        settings = await get_settings(session)
    await message.answer(f"✅ پلن «{plan.name}» ساخته شد.", reply_markup=admin_main_menu(settings))
    await state.clear()


@router.callback_query(F.data.regexp(r"^adm_plan_\d+$"))
async def cb_plan_detail(call: CallbackQuery):
    if not is_admin(call.from_user.id):
        return
    pid = int(call.data.split("_")[-1])
    async with get_session() as session:
        plan = await session.get(Plan, pid)
        settings = await get_settings(session)
        if plan is None:
            await call.answer("یافت نشد", show_alert=True)
            return
        panel = await session.get(Panel, plan.panel_id)  # explicit fetch, see buy_panel.py note
        text = (
            f"📦 {plan.name}\n"
            f"قیمت هر گیگ: {plan.price_per_gb:,.0f} تومان\n"
            f"قیمت هر ساعت: {plan.price_per_hour:,.0f} تومان\n"
            f"محدودیت یوزر: {plan.user_limit}\n"
            f"پنل: {panel.name}\n"
            f"رول ادمین: {plan.admin_role}\n"
            f"حداقل موجودی: {plan.min_balance:,.0f} تومان\n"
            f"وضعیت: {'فعال' if plan.is_active else 'غیرفعال'}"
        )
        rows = [
            [InlineKeyboardButton(
                text="⛔️ غیرفعال کردن" if plan.is_active else "▶️ فعال کردن",
                callback_data=f"adm_plan_toggle_{pid}",
            )],
            [InlineKeyboardButton(text="🗑 حذف", callback_data=f"adm_plan_del_{pid}")],
        ]
        await call.message.edit_text(text, reply_markup=with_back(rows, "adm_plans", settings))


@router.callback_query(F.data.startswith("adm_plan_toggle_"))
async def cb_plan_toggle(call: CallbackQuery):
    if not is_admin(call.from_user.id):
        return
    pid = int(call.data.split("_")[-1])
    async with get_session() as session:
        plan = await session.get(Plan, pid)
        plan.is_active = not plan.is_active
        await session.commit()
    call.data = f"adm_plan_{pid}"
    await cb_plan_detail(call)


@router.callback_query(F.data.startswith("adm_plan_del_"))
async def cb_plan_delete(call: CallbackQuery):
    if not is_admin(call.from_user.id):
        return
    pid = int(call.data.split("_")[-1])
    async with get_session() as session:
        plan = await session.get(Plan, pid)
        await session.delete(plan)
        await session.commit()
        settings = await get_settings(session)
    await call.message.edit_text("پلن حذف شد.", reply_markup=with_back([], "adm_plans", settings))
