#!/usr/bin/env bash
#
# VNET Bot (PasarGuard Reseller Bot) - installer / service manager
#
# Usage (after uploading this project to your own GitHub repo, replace
# REPO_URL below with your repo's clone URL, then on a fresh Ubuntu VPS):
#
#   bash <(curl -Ls https://raw.githubusercontent.com/<you>/<repo>/main/install.sh) install
#
# Or, if you already cloned the repo yourself:
#
#   cd pasarguard_bot && sudo bash install.sh install
#
# After install, use the `vnetbot` shortcut command instead:
#   vnetbot {install|reconfigure|update|uninstall|start|stop|restart|status|logs}
#
set -e

REPO_URL="https://github.com/VnetProject/vnetbot.git"   # <-- change this after you push to GitHub
APP_DIR="/opt/vnetbot"
SERVICE_NAME="vnetbot"
VENV_DIR="$APP_DIR/venv"

C_GREEN="\033[0;32m"; C_RED="\033[0;31m"; C_YELLOW="\033[0;33m"; C_RESET="\033[0m"
info()  { echo -e "${C_GREEN}[+]${C_RESET} $1"; }
warn()  { echo -e "${C_YELLOW}[!]${C_RESET} $1"; }
error() { echo -e "${C_RED}[x]${C_RESET} $1"; }

require_root() {
    if [ "$EUID" -ne 0 ]; then
        error "این اسکریپت باید با دسترسی root اجرا شود (sudo bash install.sh ...)"
        exit 1
    fi
}

install_system_deps() {
    info "نصب پیش‌نیازهای سیستمی (python3, venv, pip, mysql-server, git)..."
    apt-get update -y
    apt-get install -y python3 python3-venv python3-pip mysql-server git curl rsync
}

setup_database() {
    if [ -f "$APP_DIR/.db_created" ]; then
        info "دیتابیس قبلا ساخته شده - رد شدن از این مرحله."
        return
    fi

    info "تنظیم دیتابیس MySQL..."
    read -rp "نام دیتابیس [resellerbot]: " DB_NAME
    DB_NAME=${DB_NAME:-resellerbot}
    read -rp "نام کاربری دیتابیس [resellerbot]: " DB_USER
    DB_USER=${DB_USER:-resellerbot}
    DB_PASS=$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 20)
    read -rp "پسورد دیتابیس (Enter برای تولید خودکار رمز تصادفی امن): " DB_PASS_INPUT
    DB_PASS=${DB_PASS_INPUT:-$DB_PASS}

    systemctl enable --now mysql
    mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4;"
    mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
    mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';"
    mysql -e "FLUSH PRIVILEGES;"

    echo "$DB_NAME" > "$APP_DIR/.db_name"
    echo "$DB_USER" > "$APP_DIR/.db_user"
    echo "$DB_PASS" > "$APP_DIR/.db_pass"
    touch "$APP_DIR/.db_created"

    info "دیتابیس ساخته شد. (نام: ${DB_NAME} | یوزر: ${DB_USER})"
    warn "پسورد دیتابیس: ${DB_PASS}  <-- این را جایی امن ذخیره کنید"
}

fetch_source() {
    if [ -f "./main.py" ] && [ -f "./requirements.txt" ]; then
        info "اجرا از داخل پوشه پروژه تشخیص داده شد - کپی به ${APP_DIR}"
        mkdir -p "$APP_DIR"
        rsync -a --exclude 'venv' --exclude '__pycache__' --exclude '.git' ./ "$APP_DIR"/
    elif [ -d "$APP_DIR/.git" ]; then
        info "بروزرسانی سورس از گیت‌هاب..."
        git -C "$APP_DIR" pull
    else
        info "دریافت سورس از گیت‌هاب: ${REPO_URL}"
        git clone "$REPO_URL" "$APP_DIR"
    fi
}

setup_python_env() {
    info "ساخت virtualenv و نصب کتابخانه‌های پایتون..."
    cd "$APP_DIR"
    python3 -m venv "$VENV_DIR"
    "$VENV_DIR/bin/pip" install --upgrade pip -q
    "$VENV_DIR/bin/pip" install -r requirements.txt -q
}

run_bot_installer() {
    cd "$APP_DIR"
    if [ -f "config.json" ]; then
        info "config.json از قبل موجود است - از تنظیمات فعلی استفاده می‌شود."
        return
    fi

    echo
    info "حالا اطلاعات ربات را وارد کنید:"
    read -rp "توکن ربات تلگرام: " BOT_TOKEN
    read -rp "آیدی عددی ادمین اصلی: " OWNER_ID
    read -rp "پورت داخلی ربات (Enter برای 8081): " BOT_PORT
    BOT_PORT=${BOT_PORT:-8081}

    DB_NAME=$(cat "$APP_DIR/.db_name" 2>/dev/null || echo "resellerbot")
    DB_USER=$(cat "$APP_DIR/.db_user" 2>/dev/null || echo "resellerbot")
    DB_PASS=$(cat "$APP_DIR/.db_pass" 2>/dev/null || echo "")

    cat > config.json << JSONEOF
{
  "bot_token": "${BOT_TOKEN}",
  "owner_id": ${OWNER_ID},
  "db_user": "${DB_USER}",
  "db_pass": "${DB_PASS}",
  "db_name": "${DB_NAME}",
  "db_host": "127.0.0.1",
  "db_port": 3306,
  "bot_port": ${BOT_PORT}
}
JSONEOF
    info "config.json ساخته شد."
}

setup_systemd() {
    info "ساخت سرویس systemd..."
    cat > "/etc/systemd/system/${SERVICE_NAME}.service" << SERVICEEOF
[Unit]
Description=VNET Bot (PasarGuard Reseller Bot)
After=network.target mysql.service

[Service]
Type=simple
WorkingDirectory=${APP_DIR}
ExecStart=${VENV_DIR}/bin/python3 main.py
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
SERVICEEOF

    systemctl daemon-reload
    systemctl enable "${SERVICE_NAME}"
    systemctl restart "${SERVICE_NAME}"
}

# Symlink so you never need `bash install.sh ...` from a specific folder
# again - just run `vnetbot <command>` from anywhere, exactly like
# the well-known VPS installer scripts (marzban, x-ui, ...).
setup_cli_shortcut() {
    ln -sf "${APP_DIR}/install.sh" /usr/local/bin/vnetbot
    chmod +x "${APP_DIR}/install.sh"
}

# Waits a moment then reports the REAL status - `systemctl restart` returns
# immediately even if the process crashes 1 second later, so without this
# check "نصب کامل شد" can print even when the bot is actually down.
check_service_health() {
    sleep 3
    if systemctl is-active --quiet "${SERVICE_NAME}"; then
        info "سرویس ${SERVICE_NAME} فعال است و در حال اجراست ✅"
        return 0
    else
        error "سرویس ${SERVICE_NAME} بالا نیامد یا کرش کرد ❌ - این ۳۰ خط آخر لاگ دلیلش را نشان می‌دهد:"
        echo "----------------------------------------------------------------"
        journalctl -u "${SERVICE_NAME}" -n 30 --no-pager
        echo "----------------------------------------------------------------"
        warn "معمولاً دلیلش توکن/آیدی اشتباه یا خطای دیتابیس در config.json است."
        warn "برای اصلاح توکن/آیدی و تلاش مجدد: vnetbot reconfigure"
        return 1
    fi
}

cmd_install() {
    require_root
    install_system_deps
    fetch_source
    setup_database
    setup_python_env
    run_bot_installer
    setup_systemd
    setup_cli_shortcut
    echo
    check_service_health && {
        info "نصب کامل شد ✅"
        echo "دستور /admin را در ربات خود در تلگرام ارسال کنید تا پنل مدیریت باز شود."
        echo "از این به بعد، از هرجای سرور می‌توانید بنویسید: vnetbot logs | status | restart | ..."
    }
}

cmd_reconfigure() {
    require_root
    cd "$APP_DIR" || { error "پوشه ${APP_DIR} پیدا نشد - ابتدا install را اجرا کنید."; exit 1; }
    warn "توکن و آیدی ادمین فعلی حذف می‌شود و دوباره از شما پرسیده می‌شود (تنظیمات دیتابیس دست‌نخورده می‌ماند)."
    rm -f config.json
    run_bot_installer
    systemctl restart "${SERVICE_NAME}"
    check_service_health
}

cmd_update() {
    require_root
    info "بروزرسانی سورس و کتابخانه‌ها..."
    fetch_source
    setup_python_env
    setup_cli_shortcut
    systemctl restart "${SERVICE_NAME}"
    check_service_health
}

cmd_uninstall() {
    require_root
    read -rp "این کار سرویس و فایل‌های ربات را حذف می‌کند (دیتابیس حذف نمی‌شود). ادامه می‌دهید؟ [y/N] " CONFIRM
    if [ "$CONFIRM" != "y" ] && [ "$CONFIRM" != "Y" ]; then
        echo "لغو شد."
        exit 0
    fi
    systemctl stop "${SERVICE_NAME}" 2>/dev/null || true
    systemctl disable "${SERVICE_NAME}" 2>/dev/null || true
    rm -f "/etc/systemd/system/${SERVICE_NAME}.service"
    systemctl daemon-reload
    rm -f /usr/local/bin/vnetbot
    rm -rf "$APP_DIR"
    info "حذف شد."
}

case "${1:-}" in
    install)      cmd_install ;;
    update)       cmd_update ;;
    reconfigure)  cmd_reconfigure ;;
    uninstall)    cmd_uninstall ;;
    start)     require_root; systemctl start "${SERVICE_NAME}"; info "started" ;;
    stop)      require_root; systemctl stop "${SERVICE_NAME}"; info "stopped" ;;
    restart)   require_root; systemctl restart "${SERVICE_NAME}"; info "restarted" ;;
    status)    systemctl status "${SERVICE_NAME}" --no-pager ;;
    logs)      journalctl -u "${SERVICE_NAME}" -f ;;
    *)
        echo "استفاده: vnetbot {install|reconfigure|update|uninstall|start|stop|restart|status|logs}"
        exit 1
        ;;
esac
