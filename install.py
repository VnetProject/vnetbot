"""
Interactive installer.
Run once: python3 install.py
Creates config.json used by config.py / main.py
"""
import json
import os
import re
import sys

CONFIG_PATH = os.path.join(os.path.dirname(__file__), "config.json")


def ask(prompt, validator=None, error_msg="مقدار نامعتبر است، دوباره تلاش کنید."):
    while True:
        val = input(prompt).strip()
        if validator is None or validator(val):
            return val
        print(error_msg)


def main():
    print("=== نصب ربات فروش پنل (PasarGuard Reseller Bot) ===\n")

    if os.path.exists(CONFIG_PATH):
        ans = input("config.json از قبل وجود دارد. بازنویسی شود؟ (y/n): ").strip().lower()
        if ans != "y":
            print("نصب لغو شد.")
            sys.exit(0)

    bot_token = ask("توکن ربات تلگرام: ", lambda v: len(v) > 20)
    owner_id = ask("آیدی عددی ادمین اصلی: ", lambda v: v.isdigit())
    db_user = ask("نام کاربری دیتابیس: ")
    db_pass = ask("پسورد دیتابیس: ")
    db_name = ask("نام دیتابیس: ")
    db_host = input("هاست دیتابیس (Enter برای 127.0.0.1): ").strip() or "127.0.0.1"
    db_port = input("پورت دیتابیس (Enter برای 3306): ").strip() or "3306"
    bot_port = ask(
        "پورت داخلی ربات (برای وبهوک/هلث‌چک - عدد، Enter برای 8081): ",
        lambda v: v == "" or v.isdigit(),
    ) or "8081"

    cfg = {
        "bot_token": bot_token,
        "owner_id": int(owner_id),
        "db_user": db_user,
        "db_pass": db_pass,
        "db_name": db_name,
        "db_host": db_host,
        "db_port": int(db_port),
        "bot_port": int(bot_port),
    }

    with open(CONFIG_PATH, "w", encoding="utf-8") as f:
        json.dump(cfg, f, ensure_ascii=False, indent=2)

    print("\nconfig.json ساخته شد.")
    print("حالا اجرا کنید: python3 main.py")


if __name__ == "__main__":
    main()
