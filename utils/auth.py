from config import OWNER_ID


def is_admin(telegram_id: int) -> bool:
    # TODO: extend with a DB-backed list of sub-admins if needed; owner only for now.
    return telegram_id == OWNER_ID
