import random
import re
import string

SPECIAL_CHARS = "@&%$!#"


def is_valid_username(username: str) -> bool:
    """Username must be > 5 chars (i.e. at least 6), letters/digits/underscore."""
    return bool(re.fullmatch(r"[A-Za-z0-9_]{6,32}", username))


def password_errors(password: str) -> list[str]:
    """Returns list of unmet rules (empty list = valid).
    Rules: >=2 uppercase, >=2 lowercase, >=4 digits, >=1 special char (@ & % $ ! #).
    """
    errors = []
    if len(re.findall(r"[A-Z]", password)) < 2:
        errors.append("حداقل ۲ حرف بزرگ انگلیسی")
    if len(re.findall(r"[a-z]", password)) < 2:
        errors.append("حداقل ۲ حرف کوچک انگلیسی")
    if len(re.findall(r"[0-9]", password)) < 4:
        errors.append("حداقل ۴ عدد")
    if not any(ch in SPECIAL_CHARS for ch in password):
        errors.append(f"حداقل ۱ کاراکتر ویژه ({SPECIAL_CHARS})")
    if len(password) < 8:
        errors.append("حداقل ۸ کاراکتر طول رمز")
    return errors


def generate_valid_password(length: int = 12) -> str:
    while True:
        upper = random.choices(string.ascii_uppercase, k=2)
        lower = random.choices(string.ascii_lowercase, k=3)
        digits = random.choices(string.digits, k=4)
        special = random.choices(SPECIAL_CHARS, k=1)
        rest_len = max(0, length - (2 + 3 + 4 + 1))
        rest = random.choices(string.ascii_letters + string.digits, k=rest_len)
        chars = upper + lower + digits + special + rest
        random.shuffle(chars)
        pwd = "".join(chars)
        if not password_errors(pwd):
            return pwd


def generate_valid_username(prefix: str = "res") -> str:
    suffix = "".join(random.choices(string.digits, k=6))
    return f"{prefix}_{suffix}"
