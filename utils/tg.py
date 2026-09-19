"""
Small Telegram-related helpers shared across handlers.
"""
from types import SimpleNamespace


def fake_call_from_message(message):
    """Build a minimal stand-in for a CallbackQuery from a plain Message, so
    the exact same handler function can serve both an inline-button tap and
    a typed slash command (e.g. /new triggers the same code as the "خرید
    نمایندگی" button, /support the same as the "پشتیبانی" button, etc).

    The wrapped `.message.edit_text(...)` sends a NEW message instead of
    editing (there is nothing to edit yet), everything else is passed through.
    """

    async def edit_text(*args, **kwargs):
        return await message.answer(*args, **kwargs)

    async def edit_caption(*args, **kwargs):
        return await message.answer(*args, **kwargs)

    async def noop_answer(*args, **kwargs):
        return None

    proxy_message = SimpleNamespace(
        edit_text=edit_text,
        edit_caption=edit_caption,
        answer=message.answer,
        photo=getattr(message, "photo", None),
        caption=getattr(message, "caption", None),
    )
    return SimpleNamespace(
        message=proxy_message,
        from_user=message.from_user,
        bot=message.bot,
        data="",
        answer=noop_answer,
    )


def extract_custom_emoji_id(message) -> str | None:
    """If the message contains a custom-emoji entity (i.e. the admin sent an
    actual Telegram Premium emoji rather than a code), return its id."""
    entities = message.entities or message.caption_entities or []
    for entity in entities:
        if entity.type == "custom_emoji":
            return entity.custom_emoji_id
    return None
