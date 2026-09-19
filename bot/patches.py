"""
Global aiogram patches applied once at startup (imported first in main.py).

1) Telegram raises "message is not modified" whenever we edit a message with
   the exact same text+keyboard it already has (e.g. tapping "بروزرسانی" or
   re-opening the same detail screen). That's not a real error - we just
   swallow it here instead of forcing every single handler to try/except it.
"""
from aiogram.exceptions import TelegramBadRequest
from aiogram.types import Message

_original_edit_text = Message.edit_text
_original_edit_caption = Message.edit_caption
_original_edit_reply_markup = Message.edit_reply_markup


async def _safe_edit_text(self, *args, **kwargs):
    try:
        return await _original_edit_text(self, *args, **kwargs)
    except TelegramBadRequest as e:
        if "message is not modified" in str(e):
            return self
        raise


async def _safe_edit_caption(self, *args, **kwargs):
    try:
        return await _original_edit_caption(self, *args, **kwargs)
    except TelegramBadRequest as e:
        if "message is not modified" in str(e):
            return self
        raise


async def _safe_edit_reply_markup(self, *args, **kwargs):
    try:
        return await _original_edit_reply_markup(self, *args, **kwargs)
    except TelegramBadRequest as e:
        if "message is not modified" in str(e):
            return self
        raise


Message.edit_text = _safe_edit_text
Message.edit_caption = _safe_edit_caption
Message.edit_reply_markup = _safe_edit_reply_markup
