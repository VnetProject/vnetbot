"""
Loads config.json created by install.py
Run install.py once before running main.py
"""
import json
import os
import sys

CONFIG_PATH = os.path.join(os.path.dirname(__file__), "config.json")

if not os.path.exists(CONFIG_PATH):
    print("config.json not found. Run: python3 install.py  first.")
    sys.exit(1)

with open(CONFIG_PATH, "r", encoding="utf-8") as f:
    _cfg = json.load(f)

BOT_TOKEN: str = _cfg["bot_token"]
OWNER_ID: int = int(_cfg["owner_id"])
DB_USER: str = _cfg["db_user"]
DB_PASS: str = _cfg["db_pass"]
DB_NAME: str = _cfg["db_name"]
DB_HOST: str = _cfg.get("db_host", "127.0.0.1")
DB_PORT: int = int(_cfg.get("db_port", 3306))
BOT_WEBHOOK_PORT: int = int(_cfg.get("bot_port", 8081))

DATABASE_URL = (
    f"mysql+aiomysql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}"
)
