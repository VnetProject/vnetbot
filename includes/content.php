<?php
// All user-facing text and all fixed-button appearance is read through this
// file. Values live in the `settings` / `button_configs` tables so the
// in-bot admin panel can change them; nothing here needs editing to
// customize the bot — these arrays are only the *first-run* defaults.

function default_texts(): array
{
    return [
        'payment.card_number' => '⚠️ هنوز تنظیم نشده',
        'payment.card_holder' => '⚠️ هنوز تنظیم نشده',
        'payment.usdt_wallet_trc20' => '⚠️ هنوز تنظیم نشده',
        'payment.ton_wallet' => '⚠️ هنوز تنظیم نشده',
        'payment.agency_card_number' => '⚠️ هنوز تنظیم نشده',
        'payment.agency_card_holder' => '⚠️ هنوز تنظیم نشده',
        'payment.agency_ton_wallet' => '⚠️ هنوز تنظیم نشده',
        'payment.ton_to_toman_rate' => '0',
        'agency.login_url' => '⚠️ هنوز تنظیم نشده',
        'welcome' => "👋 سلام {full_name} عزیز، خوش اومدی!\n\nاز منوی پایین یکی از گزینه‌ها رو انتخاب کن.",
        'wallet.screen' => "💰 {balance_label}: {balance} تومان",
        'wallet.balance_label' => 'موجودی شما',
        'wallet.topup_ask_amount' => 'چه مبلغی (تومان) می‌خوای به کیف پولت اضافه کنی؟ فقط عدد بفرست.',
        'profile.screen' => "👤 پروفایل شما\n\nآیدی عددی: <code>{user_id}</code>\nنام: {full_name}\nتعداد سرویس‌های شما: {services_count}\n{balance_label}: {balance} تومان",
        'services.empty' => 'هنوز هیچ سرویس فعالی نداری. از «خرید سرویس» شروع کن!',
        'support.prompt' => 'پیامت رو بنویس، همین الان برای پشتیبانی ارسال میشه.',
        'support.received' => '✅ پیامت دریافت شد. پشتیبانی به‌زودی جواب می‌ده.',
        'buy.select_panel' => 'یکی از سرورها رو انتخاب کن:',
        'buy.select_plan' => 'یکی از پلن‌ها رو انتخاب کن:',
        'buy.select_payment' => 'روش پرداخت رو انتخاب کن:',
        'buy.card_instructions' => "مبلغ {amount} تومان رو به شماره کارت زیر واریز کن:\n\n<code>{card_number}</code>\nبه نام: {card_holder}\n\nبعد از واریز، عکس رسید رو همینجا بفرست.\nکد سفارش: #{order_id}",
        'buy.crypto_instructions' => "مبلغ دقیق زیر رو به آدرس کیف پول ارسال کن (شبکه TRC20):\n\n<b>{amount} USDT</b>\n\nآدرس:\n<code>{wallet_address}</code>\n\n⚠️ دقیقاً همین مبلغ رو بفرست تا سیستم خودکار تشخیص بده.\nکد سفارش: #{order_id}",
        'renew.select_service' => 'کدوم سرویس رو می‌خوای تمدید کنی؟',
        'renew.no_services' => 'سرویس فعالی برای تمدید نداری.',
        'channels.join_required' => 'برای استفاده از ربات، اول باید توی کانال‌های زیر عضو بشی:',
        'channels.check_button' => '✅ عضو شدم، بررسی کن',
        'channels.not_joined_yet' => 'هنوز توی همه‌ی کانال‌ها عضو نشدی 🙁',
        'order.rejected_customer' => '❌ سفارش #{order_id} رد شد. برای پیگیری با پشتیبانی تماس بگیر.',
        'order.delivered' => "🎉 سرویس شما آماده است!\n\n━━━━━━━━━━━━━━\n🏷 نام سرویس: {service_name}\n📦 حجم: {data_limit}\n⏳ مدت: {duration}\n💵 قیمت: {price}\n🖥 سرور: {panel_name}\n━━━━━━━━━━━━━━\n\n🔗 لینک اشتراک (با یک لمس کپی می‌شه):\n<code>{subscription_url}</code>\n\n📲 این لینک رو توی برنامه‌ی V2ray / Hiddify / Streisand وارد کن.\n🆘 هر مشکلی داشتی از «پشتیبانی» بپرس.",
        'order.renewed' => "✅ سرویس «{service_name}» با موفقیت تمدید شد.\n\n📦 حجم اضافه‌شده: {data_limit}\n⏳ مدت اضافه‌شده: {duration}\n💵 مبلغ: {price}",
        'test.delivered_msg' => "🧪 سرویس تست شما فعال شد!\n\n━━━━━━━━━━━━━━\n🏷 نام سرویس: {service_name}\n📦 حجم: {data_limit}\n⏳ مدت: {duration}\n━━━━━━━━━━━━━━\n\n🔗 لینک اشتراک:\n<code>{subscription_url}</code>\n\nاگه راضی بودی از «خرید سرویس» سرویس اصلی رو بگیر 🌟",
        'test.wait' => '⏳ سهمیه‌ی تست این پلن برای تو پر شده. بعد از {wait} می‌تونی دوباره تست بگیری.',
        'test.limit_final' => 'سهمیه‌ی تست این پلن برای تو تموم شده.',
        'buy.ask_note' => "📝 یه یادداشت یا اسم برای این سرویس بنویس (مثلاً «علی» یا «گوشی من») تا بعداً راحت پیداش کنی.\n\nاگه نمی‌خوای، یه خط تیره (-) بفرست.",
        'buy.card_ready' => '✅ یادداشت ثبت شد. حالا روش پرداخت رو انتخاب کن:',
        'services.list_title' => "📦 سرویس‌های شما\n\nروی هر سرویس بزن تا جزئیاتش رو ببینی:",
        'services.detail' => "🏷 سرویس: <b>{service_name}</b>\n━━━━━━━━━━━━━━\n📊 وضعیت: {status}\n📦 حجم کل: {total}\n📉 مصرف‌شده: {used}\n📈 باقی‌مانده: {remaining}\n{bar}\n\n⏳ زمان باقی‌مانده: {time_left}\n📅 تاریخ انقضا: {expire_date}\n🖥 سرور: {panel_name}",
        'services.link_message' => "🔗 لینک اشتراک سرویس «{service_name}»:\n\n<code>{subscription_url}</code>",
        'services.usage_chart' => "📈 نمودار مصرف سرویس «{service_name}»\n\n{bar}\n\n📉 مصرف: {used}\n📦 کل: {total}\n📈 باقی‌مانده: {remaining}",
        'services.panel_error' => '⚠️ الان نتونستم اطلاعات زنده‌ی سرویس رو از سرور بگیرم، اطلاعات ذخیره‌شده نمایش داده می‌شه.',
        'agency.min_balance' => "برای خرید این پلن حداقل {min_balance} تومان موجودی کیف پول نمایندگی لازمه.\nموجودی فعلی تو: {balance} تومان.\n\nاول «💰 کیف پول نمایندگی» رو شارژ کن.",
        'wallet.topup_success' => '✅ کیف پولت به مبلغ {amount} تومان شارژ شد.',
        'tutorial.content' => 'آموزش استفاده از سرویس هنوز توسط ادمین تنظیم نشده.',
        'free_connection.content' => 'لینک اتصال رایگان هنوز توسط ادمین تنظیم نشده.',
        'test.select_plan' => 'کدوم پلن رو می‌خوای تست کنی؟',
        'test.none_available' => 'در حال حاضر پلن تستی فعال نیست.',
        'agency.intro' => 'پنل نمایندگی، بهترین و آسون‌ترین راه برای شروع فعالیت و کسب درآمد، بدون هیچ کار فنی! پنل خودتو داشته باش.',
        'agency.select_plan' => 'کدوم پلن نمایندگی رو می‌خوای؟',
        'agency.no_plans' => 'در حال حاضر پلن نمایندگی‌ای تعریف نشده.',
        'agency.created' => "✅ پنل نمایندگی شما ساخته شد!\n\nیوزرنیم: <code>{username}</code>\nپسورد: <code>{password}</code>\n\nبرای ورود از دکمه‌ی «ورود به پنل» توی «📋 پنل‌های من» استفاده کن.",
        'agency.wallet_screen' => '💰 موجودی کیف پول نمایندگی: {balance} تومان',
        'agency.wallet_topup_ask_amount' => 'چه مبلغی (تومان) می‌خوای به کیف پول نمایندگی اضافه کنی؟ فقط عدد بفرست.',
        'agency.wallet_topup_ton_instructions' => "مبلغ <b>{ton_amount} TON</b> رو به آدرس زیر بفرست:\n\n<code>{wallet_address}</code>\n\n⚠️ توی قسمت Comment/Memo تراکنش، دقیقاً همین کد رو بنویس:\n<code>{memo}</code>\n\nبدون این کد، پرداختت خودکار تشخیص داده نمی‌شه.\nکد سفارش: #{order_id}",
        'agency.no_panels' => 'هنوز هیچ پنل نمایندگی‌ای نداری.',
        'agency.select_my_panel' => 'کدوم پنل نمایندگی رو می‌خوای ببینی؟',
        'agency.card' => "👤 اطلاعات نمایندگی:\n• نام کاربری: {username}\n• رمز عبور: {password}\n• وضعیت: {status}\n\n📊 ترافیک (GB):\n• مصرف: {used_gb}\n• هزینه‌ی کل: {total_charged} (هر گیگ: {per_gb_price})\n\n💼 حق نمایندگی:\n• انقضاء: {expires_at}\n• کارکرد: {active_hours} ساعت\n• هزینه‌ی ساعتی: {total_hourly_charged} (ساعتی: {per_hour_price})\n• محدودیت کاربر: {max_users}",
        'agency.disabled_zero_balance' => '⚠️ موجودی کیف پول نمایندگی شما تموم شد و پنلتون غیرفعال شد. با شارژ کیف پول، دوباره فعال می‌شه.',
        'agency.ask_new_password' => 'رمز عبور جدید رو بفرست:',
        'agency.ask_expiry' => 'تاریخ انقضا رو به فرم YYYY-MM-DD بفرست (مثلاً 2026-12-31)، یا «حذف» بفرست تا انقضا برداشته بشه:',
        'agency.ask_usage_limit' => 'محدودیت مصرف رو به گیگابایت بفرست، یا «حذف» بفرست تا محدودیت برداشته بشه:',
        'agency.report_prompt' => 'مشکلت رو بنویس، برای پشتیبانی ارسال می‌شه:',
    ];
}

/** Persian titles shown in the admin "edit texts" list (instead of raw keys). */
function text_labels(): array
{
    return [
        'welcome' => '👋 پیام خوش‌آمدگویی (/start)',
        'wallet.screen' => '💰 صفحه‌ی کیف پول',
        'wallet.balance_label' => '💰 عنوان «موجودی»',
        'wallet.topup_ask_amount' => '➕ پیام درخواست مبلغ شارژ کیف پول',
        'wallet.topup_success' => '✅ پیام موفقیت شارژ کیف پول',
        'profile.screen' => '👤 صفحه‌ی پروفایل',
        'services.empty' => '📦 پیام «هنوز سرویسی نداری»',
        'services.list_title' => '📦 عنوان لیست سرویس‌های من',
        'services.detail' => '📋 صفحه‌ی جزئیات سرویس (حجم، زمان، ...)',
        'services.link_message' => '🔗 پیام ارسال دوباره‌ی لینک',
        'services.usage_chart' => '📈 پیام نمودار مصرف',
        'services.panel_error' => '⚠️ هشدار نرسیدن اطلاعات زنده از سرور',
        'support.prompt' => '🆘 پیام ورود به پشتیبانی',
        'support.received' => '🆘 پیام «پیامت دریافت شد»',
        'buy.select_panel' => '🛒 خرید: انتخاب سرور',
        'buy.select_plan' => '🛒 خرید: انتخاب پلن',
        'buy.ask_note' => '📝 خرید: درخواست یادداشت/نام سرویس',
        'buy.card_ready' => '💳 خرید: بعد از ثبت یادداشت',
        'buy.select_payment' => '💳 خرید: انتخاب روش پرداخت',
        'buy.card_instructions' => '💳 راهنمای پرداخت کارت به کارت',
        'buy.crypto_instructions' => '₮ راهنمای پرداخت تتر (USDT)',
        'renew.select_service' => '🔄 تمدید: انتخاب سرویس',
        'renew.no_services' => '🔄 تمدید: پیام نداشتن سرویس',
        'channels.join_required' => '📢 پیام عضویت اجباری در کانال',
        'channels.check_button' => '📢 دکمه‌ی «عضو شدم»',
        'channels.not_joined_yet' => '📢 پیام «هنوز عضو نشدی»',
        'order.delivered' => '🎉 پیام تحویل سرویس بعد از خرید',
        'order.renewed' => '🔄 پیام موفقیت تمدید',
        'order.rejected_customer' => '❌ پیام رد شدن سفارش',
        'tutorial.content' => '📖 متن بخش «آموزش»',
        'free_connection.content' => '🔗 متن بخش «اتصال رایگان»',
        'test.select_plan' => '🧪 تست: انتخاب پلن',
        'test.none_available' => '🧪 تست: پیام نبودن پلن تست',
        'test.delivered_msg' => '🧪 پیام تحویل سرویس تست',
        'test.wait' => '🧪 پیام «باید صبر کنی» برای تست دوره‌ای',
        'test.limit_final' => '🧪 پیام تموم شدن سهمیه‌ی تست',
        'agency.intro' => '🏢 نمایندگی: متن معرفی',
        'agency.select_plan' => '🏢 نمایندگی: انتخاب پلن',
        'agency.no_plans' => '🏢 نمایندگی: پیام نبودن پلن',
        'agency.created' => '🏢 نمایندگی: پیام ساخته شدن پنل',
        'agency.min_balance' => '🏢 نمایندگی: پیام کمبود موجودی برای خرید پلن',
        'agency.wallet_screen' => '🏢 صفحه‌ی کیف پول نمایندگی',
        'agency.wallet_topup_ask_amount' => '🏢 درخواست مبلغ شارژ کیف پول نمایندگی',
        'agency.wallet_topup_ton_instructions' => '🏢 راهنمای پرداخت با تون‌کوین',
        'agency.no_panels' => '🏢 پیام «پنل نمایندگی نداری»',
        'agency.select_my_panel' => '🏢 انتخاب پنل از «پنل‌های من»',
        'agency.card' => '🏢 کارت اطلاعات نمایندگی',
        'agency.disabled_zero_balance' => '🏢 هشدار غیرفعال شدن به‌خاطر صفر شدن موجودی',
        'agency.ask_new_password' => '🏢 درخواست رمز عبور جدید',
        'agency.ask_expiry' => '🏢 درخواست تاریخ انقضا',
        'agency.ask_usage_limit' => '🏢 درخواست محدودیت مصرف',
        'agency.report_prompt' => '🏢 درخواست گزارش مشکل',
    ];
}

/** Placeholders each text understands, shown to the admin while editing. */
function text_placeholders(): array
{
    $service = '{service_name} {data_limit} {duration} {price} {panel_name} {username} {subscription_url}';
    return [
        'welcome' => '{full_name}',
        'wallet.screen' => '{balance_label} {balance}',
        'wallet.topup_success' => '{amount}',
        'profile.screen' => '{user_id} {full_name} {services_count} {balance_label} {balance}',
        'services.detail' => '{service_name} {status} {total} {used} {remaining} {bar} {time_left} {expire_date} {panel_name}',
        'services.link_message' => '{service_name} {subscription_url}',
        'services.usage_chart' => '{service_name} {bar} {used} {total} {remaining}',
        'buy.card_instructions' => '{amount} {card_number} {card_holder} {order_id}',
        'buy.crypto_instructions' => '{amount} {wallet_address} {order_id}',
        'order.delivered' => $service,
        'order.renewed' => '{service_name} {data_limit} {duration} {price}',
        'order.rejected_customer' => '{order_id}',
        'test.delivered_msg' => $service,
        'test.wait' => '{wait}',
        'agency.created' => '{username} {password}',
        'agency.min_balance' => '{min_balance} {balance}',
        'agency.wallet_screen' => '{balance}',
        'agency.wallet_topup_ton_instructions' => '{ton_amount} {wallet_address} {memo} {order_id}',
        'agency.card' => '{username} {password} {status} {used_gb} {total_charged} {per_gb_price} {expires_at} {active_hours} {total_hourly_charged} {per_hour_price} {max_users}',
    ];
}

function text_label(string $key): string
{
    return text_labels()[$key] ?? $key;
}

/** Persian names for button groups (shown in the admin "other buttons" list). */
function button_parent_labels(): array
{
    return [
        'main_menu' => 'منوی اصلی',
        'global' => 'عمومی',
        'wallet_menu' => 'کیف پول',
        'service_actions' => 'صفحه‌ی سرویس',
        'payment_menu' => 'روش پرداخت',
        'agency_menu' => 'منوی نمایندگی',
        'agency_wallet_menu' => 'کیف پول نمایندگی',
    ];
}

function default_buttons(): array
{
    // key => [parent, label, position]
    return [
        'buy' => ['main_menu', '🛒 خرید سرویس', 0],
        'my_services' => ['main_menu', '📦 سرویس‌های من', 1],
        'buy_agency' => ['main_menu', '🏢 خرید پنل نمایندگی', 10],
        'test_service' => ['main_menu', '🧪 سرویس تست', 11],
        'wallet' => ['main_menu', '💰 کیف پول', 20],
        'profile' => ['main_menu', '👤 پروفایل', 21],
        'support' => ['main_menu', '🆘 پشتیبانی', 30],
        'tutorial' => ['main_menu', '📖 آموزش', 31],
        'free_connection' => ['main_menu', '🔗 اتصال رایگان', 40],
        'back' => ['global', '🔙 بازگشت', 99],
        'wallet_topup' => ['wallet_menu', '➕ شارژ کیف پول', 1],
        'service_renew' => ['service_actions', '🔄 تمدید این سرویس', 1],
        'service_get_link' => ['service_actions', '📩 دریافت دوباره لینک', 2],
        'service_copy_link' => ['service_actions', '📋 کپی لینک', 3],
        'service_usage' => ['service_actions', '📈 نمودار مصرف', 4],
        'service_back' => ['service_actions', '🔙 لیست سرویس‌ها', 5],
        'pay_card' => ['payment_menu', '💳 کارت به کارت', 1],
        'pay_crypto' => ['payment_menu', '₮ تتر (USDT-TRC20)', 2],
        'pay_wallet' => ['payment_menu', '👛 کیف پول', 3],
        'agency_buy' => ['agency_menu', '🏢 خرید پنل نمایندگی', 1],
        'agency_test' => ['agency_menu', '🧪 تست نمایندگی', 2],
        'agency_wallet' => ['agency_menu', '💰 کیف پول نمایندگی', 3],
        'agency_my_panels' => ['agency_menu', '📋 پنل‌های من', 4],
        'agency_wallet_topup_ton' => ['agency_wallet_menu', '➕ شارژ با تون‌کوین', 1],
        'agency_wallet_topup_card' => ['agency_wallet_menu', '➕ شارژ با کارت به کارت', 2],
    ];
}

function seed_defaults(): void
{
    $pdo = db();
    $stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, value) VALUES (:k, :v)");
    foreach (default_texts() as $key => $value) {
        $stmt->execute(['k' => $key, 'v' => $value]);
    }
    $stmt = $pdo->prepare("INSERT IGNORE INTO button_configs (button_key, parent, label, position) VALUES (:k, :p, :l, :pos)");
    foreach (default_buttons() as $key => [$parent, $label, $position]) {
        $stmt->execute(['k' => $key, 'p' => $parent, 'l' => $label, 'pos' => $position]);
    }
}

function get_setting(string $key, string $default = ''): string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = db()->prepare("SELECT value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    if ($value === false) {
        $defaults = default_texts();
        $value = $defaults[$key] ?? $default;
    }
    return $cache[$key] = $value;
}

function get_text(string $key, array $vars = []): string
{
    $template = get_setting($key, $key);
    if (empty($vars)) {
        return $template;
    }
    $replacements = [];
    foreach ($vars as $k => $v) {
        $replacements['{' . $k . '}'] = $v;
    }
    return strtr($template, $replacements);
}

function set_text(string $key, string $value): void
{
    $stmt = db()->prepare("INSERT INTO settings (setting_key, value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE value = :v2");
    $stmt->execute(['k' => $key, 'v' => $value, 'v2' => $value]);
}

function list_text_keys(): array
{
    $stmt = db()->query("SELECT setting_key FROM settings ORDER BY setting_key");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function get_button(string $key): array
{
    $stmt = db()->prepare("SELECT * FROM button_configs WHERE button_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    if ($row) {
        return $row;
    }
    $defaults = default_buttons();
    if (isset($defaults[$key])) {
        [$parent, $label, $position] = $defaults[$key];
        return ['button_key' => $key, 'parent' => $parent, 'label' => $label, 'emoji_custom_id' => null, 'style' => null, 'position' => $position, 'is_visible' => 1];
    }
    return ['button_key' => $key, 'parent' => 'unknown', 'label' => $key, 'emoji_custom_id' => null, 'style' => null, 'position' => 0, 'is_visible' => 1];
}

function buttons_in(string $parent): array
{
    $stmt = db()->prepare("SELECT * FROM button_configs WHERE parent = ? AND is_visible = 1 ORDER BY position");
    $stmt->execute([$parent]);
    return $stmt->fetchAll();
}

function list_all_buttons(): array
{
    $stmt = db()->query("SELECT * FROM button_configs ORDER BY parent, position");
    return $stmt->fetchAll();
}

/** All buttons in a group, in display order, hidden ones included (for admin reordering). */
function list_buttons_by_parent(string $parent): array
{
    $stmt = db()->prepare("SELECT * FROM button_configs WHERE parent = ? ORDER BY position, button_key");
    $stmt->execute([$parent]);
    return $stmt->fetchAll();
}

/** Moves/swaps a button into a specific grid slot (used by the 4x4 main-menu editor). */
function move_button_to_slot(string $key, int $targetSlot): void
{
    $siblings = list_buttons_by_parent('main_menu');
    $occupant = null;
    $current = null;
    foreach ($siblings as $s) {
        if ((int) $s['position'] === $targetSlot) {
            $occupant = $s;
        }
        if ($s['button_key'] === $key) {
            $current = $s;
        }
    }
    if ($current === null) {
        return;
    }
    if ($occupant !== null && $occupant['button_key'] !== $key) {
        set_button_field($occupant['button_key'], ['position' => (int) $current['position']]);
    }
    set_button_field($key, ['position' => $targetSlot]);
}
function move_button(string $key, string $direction): void
{
    $button = get_button($key);
    $siblings = list_buttons_by_parent($button['parent']);
    $index = null;
    foreach ($siblings as $i => $s) {
        if ($s['button_key'] === $key) {
            $index = $i;
            break;
        }
    }
    if ($index === null) {
        return;
    }
    $swapWith = $direction === 'up' ? $index - 1 : $index + 1;
    if ($swapWith < 0 || $swapWith >= count($siblings)) {
        return; // already at the edge
    }
    $a = $siblings[$index];
    $b = $siblings[$swapWith];
    set_button_field($a['button_key'], ['position' => $b['position']]);
    set_button_field($b['button_key'], ['position' => $a['position']]);
}

function set_button_field(string $key, array $fields): void
{
    $allowed = ['label', 'emoji_custom_id', 'style', 'position', 'is_visible'];
    $sets = [];
    $params = ['k' => $key];
    foreach ($fields as $field => $value) {
        if (!in_array($field, $allowed, true)) {
            continue;
        }
        $sets[] = "$field = :$field";
        $params[$field] = $value;
    }
    if (!$sets) {
        return;
    }
    $sql = "UPDATE button_configs SET " . implode(', ', $sets) . " WHERE button_key = :k";
    db()->prepare($sql)->execute($params);
}
