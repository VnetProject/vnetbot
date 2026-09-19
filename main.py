import asyncio
import logging

# Must be imported before any handler modules touch aiogram's Message class.
import bot.patches  # noqa: F401

from aiogram import Bot, Dispatcher
from aiogram.client.default import DefaultBotProperties
from aiogram.types import BotCommand
from apscheduler.schedulers.asyncio import AsyncIOScheduler

from config import BOT_TOKEN
from database.db import init_db
from utils.billing import run_billing_cycle

from bot.handlers import commands, common, wallet, buy_panel, my_panels, test_request
from bot.handlers.admin import (
    broadcast_channels,
    buttons_admin,
    panel_admin,
    plan_admin,
    settings_admin,
    texts_admin,
    users_admin,
)

logging.basicConfig(level=logging.INFO, format="%(asctime)s | %(levelname)s | %(name)s | %(message)s")

BOT_COMMANDS = [
    BotCommand(command="start", description="شروع / منوی اصلی"),
    BotCommand(command="new", description="خرید نمایندگی"),
    BotCommand(command="wallet", description="کیف پول"),
    BotCommand(command="myplans", description="لیست نمایندگی هام"),
    BotCommand(command="test", description="دریافت تست"),
    BotCommand(command="faq", description="سوالات متداول"),
    BotCommand(command="rules", description="قوانین"),
    BotCommand(command="support", description="پشتیبانی"),
]


async def main():
    await init_db()

    # IMPORTANT: default parse_mode is intentionally None (not HTML/Markdown).
    # Most messages interpolate raw user-provided text (support messages,
    # usernames, custom passwords, admin free-text) - if that default were
    # HTML/Markdown, a stray '<', '&' or '_' in ANY of those would make
    # Telegram reject the whole message with a "can't parse entities" error,
    # which is exactly why support/receipt notifications were silently not
    # arriving before. Only the admin-authored `settings.texts` (welcome,
    # rules, faq, ...) are sent with an explicit parse_mode="HTML" per-call,
    # since only those are trusted/intended to contain formatting or Premium
    # custom-emoji tags.
    bot = Bot(token=BOT_TOKEN, default=DefaultBotProperties(parse_mode=None))
    dp = Dispatcher()

    dp.include_router(commands.router)
    dp.include_router(common.router)
    dp.include_router(wallet.router)
    dp.include_router(buy_panel.router)
    dp.include_router(my_panels.router)
    dp.include_router(test_request.router)

    dp.include_router(panel_admin.router)
    dp.include_router(plan_admin.router)
    dp.include_router(settings_admin.router)
    dp.include_router(texts_admin.router)
    dp.include_router(users_admin.router)
    dp.include_router(broadcast_channels.router)
    dp.include_router(buttons_admin.router)

    scheduler = AsyncIOScheduler()
    scheduler.add_job(run_billing_cycle, "interval", hours=1, args=[bot], id="billing_cycle")
    scheduler.start()

    await bot.set_my_commands(BOT_COMMANDS)
    await bot.delete_webhook(drop_pending_updates=True)
    await dp.start_polling(bot)


if __name__ == "__main__":
    asyncio.run(main())
