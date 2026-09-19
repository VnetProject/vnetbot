from aiogram import F, Router
from aiogram.types import CallbackQuery

from bot.handlers.common import get_or_create_user, get_settings
from bot.keyboards import with_back
from database.db import get_session
from database.models import Panel
from utils.panel_client import create_test_user

router = Router(name="test_request")


@router.callback_query(F.data == "get_test")
async def cb_get_test(call: CallbackQuery):
    async with get_session() as session:
        settings = await get_settings(session)
        await get_or_create_user(session, call.from_user)

        if not settings.test_enabled:
            await call.answer("دریافت تست غیرفعال است.", show_alert=True)
            return
        if not settings.test_panel_id:
            await call.answer("پنل تست تنظیم نشده است.", show_alert=True)
            return

        panel = await session.get(Panel, settings.test_panel_id)
        if panel is None:
            await call.answer("پنل تست یافت نشد.", show_alert=True)
            return

        # NOTE: test_data_limit_gb column name kept as-is to avoid a DB
        # migration, but its value is now interpreted in MEGABYTES.
        try:
            test_user = await create_test_user(panel, settings.test_data_limit_gb, settings.test_days)
        except Exception as e:
            await call.message.edit_text(f"خطا در ساخت کانفیگ تست: {e}",
                                          reply_markup=with_back([], "main_menu", settings))
            return

        sub_url = getattr(test_user, "subscription_url", "-")
        await call.message.edit_text(
            "✅ کانفیگ تست شما ساخته شد:\n\n"
            f"👤 نام کاربری: {test_user.username}\n"
            f"📦 حجم: {settings.test_data_limit_gb:g} مگابایت\n"
            f"⏰ مدت: {settings.test_days} روز\n"
            f"🔗 لینک اشتراک:\n{sub_url}",
            reply_markup=with_back([], "main_menu", settings),
        )
