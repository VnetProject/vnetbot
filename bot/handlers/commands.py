"""
Slash-command shortcuts to the same sections as the inline buttons, so users
can type e.g. /new instead of tapping through the menu (as requested: هر
دکمه یک کد داشته باشد مثل /new یا /support).

Each command reuses the EXACT same handler function as the matching button
via `fake_call_from_message`, so there is only one implementation to
maintain and the two can never drift apart.
"""
from aiogram import Router
from aiogram.filters import Command
from aiogram.fsm.context import FSMContext
from aiogram.types import Message

from bot.handlers import buy_panel, common, my_panels, test_request, wallet
from utils.tg import fake_call_from_message

router = Router(name="commands")

# key: (command names, human description shown in Telegram's command menu)
COMMAND_INFO = {
    "new": "خرید نمایندگی",
    "wallet": "کیف پول",
    "myplans": "لیست نمایندگی هام",
    "test": "دریافت تست",
    "faq": "سوالات متداول",
    "rules": "قوانین",
    "support": "پشتیبانی",
}


@router.message(Command("new"))
async def cmd_new(message: Message, state: FSMContext):
    await buy_panel.cb_buy_panel(fake_call_from_message(message), state)


@router.message(Command("wallet"))
async def cmd_wallet(message: Message):
    await wallet.cb_wallet(fake_call_from_message(message))


@router.message(Command("myplans"))
async def cmd_my_panels(message: Message):
    await my_panels.cb_my_panels(fake_call_from_message(message))


@router.message(Command("test"))
async def cmd_test(message: Message):
    await test_request.cb_get_test(fake_call_from_message(message))


@router.message(Command("faq"))
async def cmd_faq(message: Message):
    await common.cb_faq(fake_call_from_message(message))


@router.message(Command("rules"))
async def cmd_rules(message: Message):
    await common.cb_rules(fake_call_from_message(message))


@router.message(Command("support"))
async def cmd_support(message: Message, state: FSMContext):
    await common.cb_support(fake_call_from_message(message), state)
