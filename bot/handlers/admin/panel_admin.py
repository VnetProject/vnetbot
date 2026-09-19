from aiogram import F, Router
from aiogram.filters import Command
from aiogram.fsm.context import FSMContext
from aiogram.types import CallbackQuery, InlineKeyboardButton, InlineKeyboardMarkup, Message
from sqlalchemy import select

from bot.handlers.common import get_settings
from bot.keyboards import admin_main_menu, cancel_kb, with_back
from bot.states import AdminPanelForm
from database.db import get_session
from database.models import Panel
from utils.auth import is_admin
from utils.panel_client import test_panel_connection

router = Router(name="admin_panel_admin")


@router.message(Command("admin"))
async def cmd_admin(message: Message):
    if not is_admin(message.from_user.id):
        return
    async with get_session() as session:
        settings = await get_settings(session)
        await message.answer("🛠 پنل مدیریت", reply_markup=admin_main_menu(settings))


@router.callback_query(F.data == "adm_home")
async def cb_admin_home(call: CallbackQuery):
    if not is_admin(call.from_user.id):
        return
    async with get_session() as session:
        settings = await get_settings(session)
        await call.message.edit_text("🛠 پنل مدیریت", reply_markup=admin_main_menu(settings))


# ---------------- Panels CRUD ----------------

@router.callback_query(F.data == "adm_panels")
async def cb_panels_list(call: CallbackQuery):
    if not is_admin(call.from_user.id):
        return
    async with get_session() as session:
        settings = await get_settings(session)
        result = await session.execute(select(Panel))
        panels = result.scalars().all()
        rows = [[InlineKeyboardButton(text=f"{'🟢' if p.is_active else '🔴'} {p.name}", callback_data=f"adm_panel_{p.id}")]
                for p in panels]
        rows.append([InlineKeyboardButton(text="➕ افزودن پنل", callback_data="adm_panel_add")])
        await call.message.edit_text("لیست پنل‌ها:", reply_markup=with_back(rows, "adm_home", settings))


@router.callback_query(F.data == "adm_panel_add")
async def cb_panel_add_start(call: CallbackQuery, state: FSMContext):
    if not is_admin(call.from_user.id):
        return
    async with get_session() as session:
        settings = await get_settings(session)
        await call.message.edit_text("نام پنل را وارد کنید:", reply_markup=cancel_kb(settings, "adm_panels"))
    await state.set_state(AdminPanelForm.name)


@router.message(AdminPanelForm.name)
async def panel_name_received(message: Message, state: FSMContext):
    await state.update_data(name=message.text.strip())
    async with get_session() as session:
        settings = await get_settings(session)
        await message.answer("آدرس API پنل را وارد کنید (مثال: https://panel.example.com:443):",
                              reply_markup=cancel_kb(settings, "adm_panels"))
    await state.set_state(AdminPanelForm.address)


@router.message(AdminPanelForm.address)
async def panel_address_received(message: Message, state: FSMContext):
    await state.update_data(address=message.text.strip())
    async with get_session() as session:
        settings = await get_settings(session)
        await message.answer("نام کاربری ادمین sudo پنل را وارد کنید:", reply_markup=cancel_kb(settings, "adm_panels"))
    await state.set_state(AdminPanelForm.username)


@router.message(AdminPanelForm.username)
async def panel_username_received(message: Message, state: FSMContext):
    await state.update_data(username=message.text.strip())
    async with get_session() as session:
        settings = await get_settings(session)
        await message.answer("پسورد ادمین sudo پنل را وارد کنید:", reply_markup=cancel_kb(settings, "adm_panels"))
    await state.set_state(AdminPanelForm.password)


@router.message(AdminPanelForm.password)
async def panel_password_received(message: Message, state: FSMContext):
    data = await state.get_data()
    password = message.text.strip()

    checking_msg = await message.answer("⏳ در حال بررسی اتصال به پنل...")

    # Build a throwaway Panel-like object just to test the connection before
    # saving anything to the DB.
    class _TmpPanel:
        pass

    tmp = _TmpPanel()
    tmp.address = data["address"]
    tmp.username = data["username"]
    tmp.password = password
    tmp.name = data["name"]

    ok, error = await test_panel_connection(tmp)

    async with get_session() as session:
        panel = Panel(
            name=data["name"], address=data["address"],
            username=data["username"], password=password,
            is_active=True,
        )
        session.add(panel)
        await session.commit()
        settings = await get_settings(session)

    if ok:
        await checking_msg.edit_text(f"✅ اتصال به پنل «{panel.name}» موفق بود.")
    else:
        await checking_msg.edit_text(
            f"⚠️ پنل «{panel.name}» ذخیره شد اما اتصال ناموفق بود:\n{error}\n\n"
            "آدرس/یوزرنیم/پسورد را از بخش «✏️ تغییر یوزر/پسورد» بررسی و اصلاح کنید."
        )
    await message.answer("بازگشت به پنل مدیریت:", reply_markup=admin_main_menu(settings))
    await state.clear()


@router.callback_query(F.data.regexp(r"^adm_panel_\d+$"))
async def cb_panel_detail(call: CallbackQuery):
    if not is_admin(call.from_user.id):
        return
    pid = int(call.data.split("_")[-1])
    async with get_session() as session:
        panel = await session.get(Panel, pid)
        settings = await get_settings(session)
        if panel is None:
            await call.answer("یافت نشد", show_alert=True)
            return
        text = (
            f"🖥 {panel.name}\n"
            f"آدرس: {panel.address}\n"
            f"آدرس نمایندگی: {panel.resell_address or '(از آدرس اصلی استفاده می‌شود)'}\n"
            f"یوزرنیم: {panel.username}\n"
            f"وضعیت: {'فعال' if panel.is_active else 'غیرفعال'}"
        )
        rows = [
            [InlineKeyboardButton(text="🔌 تست اتصال", callback_data=f"adm_panel_test_{pid}")],
            [InlineKeyboardButton(text="✏️ تغییر آدرس نمایندگی", callback_data=f"adm_panel_editresell_{pid}")],
            [InlineKeyboardButton(text="✏️ تغییر یوزر/پسورد", callback_data=f"adm_panel_editcred_{pid}")],
            [InlineKeyboardButton(
                text="⛔️ غیرفعال کردن" if panel.is_active else "▶️ فعال کردن",
                callback_data=f"adm_panel_toggle_{pid}",
            )],
            [InlineKeyboardButton(text="🗑 حذف", callback_data=f"adm_panel_del_{pid}")],
        ]
        await call.message.edit_text(text, reply_markup=with_back(rows, "adm_panels", settings))


@router.callback_query(F.data.startswith("adm_panel_test_"))
async def cb_panel_test(call: CallbackQuery):
    if not is_admin(call.from_user.id):
        return
    pid = int(call.data.split("_")[-1])
    await call.answer("در حال بررسی...")
    async with get_session() as session:
        panel = await session.get(Panel, pid)
        ok, error = await test_panel_connection(panel)
    if ok:
        await call.answer("✅ اتصال موفق بود", show_alert=True)
    else:
        await call.answer(f"❌ اتصال ناموفق: {error}"[:200], show_alert=True)


@router.callback_query(F.data.startswith("adm_panel_toggle_"))
async def cb_panel_toggle(call: CallbackQuery):
    if not is_admin(call.from_user.id):
        return
    pid = int(call.data.split("_")[-1])
    async with get_session() as session:
        panel = await session.get(Panel, pid)
        panel.is_active = not panel.is_active
        await session.commit()
    call.data = f"adm_panel_{pid}"
    await cb_panel_detail(call)


@router.callback_query(F.data.startswith("adm_panel_del_"))
async def cb_panel_delete(call: CallbackQuery):
    if not is_admin(call.from_user.id):
        return
    pid = int(call.data.split("_")[-1])
    async with get_session() as session:
        panel = await session.get(Panel, pid)
        await session.delete(panel)
        await session.commit()
        settings = await get_settings(session)
    await call.message.edit_text("پنل حذف شد.", reply_markup=with_back([], "adm_panels", settings))


@router.callback_query(F.data.startswith("adm_panel_editresell_"))
async def cb_panel_editresell_start(call: CallbackQuery, state: FSMContext):
    if not is_admin(call.from_user.id):
        return
    pid = int(call.data.split("_")[-1])
    await state.update_data(panel_id=pid, field="resell_address")
    await state.set_state(AdminPanelForm.editing_field)
    async with get_session() as session:
        settings = await get_settings(session)
        await call.message.edit_text("آدرس جدیدی که کاربران برای ورود به نمایندگی استفاده کنند را وارد کنید:",
                                      reply_markup=cancel_kb(settings, f"adm_panel_{pid}"))


@router.callback_query(F.data.startswith("adm_panel_editcred_"))
async def cb_panel_editcred_start(call: CallbackQuery, state: FSMContext):
    if not is_admin(call.from_user.id):
        return
    pid = int(call.data.split("_")[-1])
    await state.update_data(panel_id=pid, field="password")
    await state.set_state(AdminPanelForm.editing_field)
    async with get_session() as session:
        settings = await get_settings(session)
        await call.message.edit_text(
            "توجه: در پاسارگارد نام کاربری ادمین sudo قابل تغییر نیست، فقط پسورد.\n"
            "پسورد جدید را وارد کنید:",
            reply_markup=cancel_kb(settings, f"adm_panel_{pid}"),
        )


@router.message(AdminPanelForm.editing_field)
async def panel_editing_field_received(message: Message, state: FSMContext):
    data = await state.get_data()
    async with get_session() as session:
        panel = await session.get(Panel, data["panel_id"])
        setattr(panel, data["field"], message.text.strip())
        await session.commit()
        settings = await get_settings(session)
    await message.answer("✅ بروزرسانی شد.", reply_markup=admin_main_menu(settings))
    await state.clear()
