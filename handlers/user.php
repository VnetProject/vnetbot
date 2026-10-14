<?php
// ---------------------------------------------------------------------
// Main menu dispatch (buttons are fully customizable, so we compare
// against whatever label is currently stored, not a hardcoded string)
// ---------------------------------------------------------------------

/** Key of the currently visible main-menu button with this label (hidden ones never match). */
function user_menu_key_for(string $text): ?string
{
    foreach (buttons_in('main_menu') as $b) {
        if ($b['label'] === $text) {
            return $b['button_key'];
        }
    }
    return null;
}

function user_dispatch_main_menu(string $text, int $userId, int $chatId, string $fullName): void
{
    $key = user_menu_key_for($text);
    if ($key === null) {
        return;
    }

    switch ($key) {
        case 'buy':
            show_panels($chatId);
            break;
        case 'my_services':
            show_my_services($userId, $chatId);
            break;
        case 'buy_agency':
            show_agency_menu($chatId);
            break;
        case 'test_service':
            show_test_plans($userId, $chatId);
            break;
        case 'wallet':
            show_wallet($userId, $chatId);
            break;
        case 'profile':
            show_profile($userId, $chatId);
            break;
        case 'support':
            set_state($userId, 'user_support');
            tg_send_message($chatId, get_text('support.prompt'));
            break;
        case 'tutorial':
            tg_send_message($chatId, get_text('tutorial.content'));
            break;
        case 'free_connection':
            tg_send_message($chatId, get_text('free_connection.content'));
            break;
    }
}

// ---------------------------------------------------------------------
// Buy: panel -> plan -> payment method
// ---------------------------------------------------------------------

function show_panels(int $chatId, ?int $editMessageId = null): void
{
    $panels = list_active_panels();
    $rows = [];
    foreach ($panels as $p) {
        $rows[] = [dynamic_btn($p['name'], "buy:panel:{$p['id']}", $p['button_style'] ?? null, $p['button_emoji_id'] ?? null)];
    }
    $rows[] = [back_btn('back:main')];
    $markup = tg_inline_markup($rows);
    if ($editMessageId) {
        tg_edit_message($chatId, $editMessageId, get_text('buy.select_panel'), $markup);
    } else {
        tg_send_message($chatId, get_text('buy.select_panel'), $markup);
    }
}

function show_plans_for_panel(int $panelId, int $chatId, int $messageId, string $prefix = 'buy'): void
{
    $plans = list_active_plans_for_panel($panelId);
    $rows = [];
    foreach ($plans as $plan) {
        $label = "{$plan['name']} | " . rtrim(rtrim(number_format($plan['data_limit_gb'], 2), '0'), '.') . "GB | {$plan['duration_days']} روز | " . number_format($plan['price_toman']) . ' تومان';
        $rows[] = [dynamic_btn($label, "$prefix:plan:{$plan['id']}", $plan['button_style'] ?? null, $plan['button_emoji_id'] ?? null)];
    }
    $rows[] = [back_btn($prefix === 'buy' ? 'back:buy:panels' : 'back:main')];
    tg_edit_message($chatId, $messageId, get_text('buy.select_plan'), tg_inline_markup($rows));
}

function show_payment_methods(int $chatId, int $messageId, string $backCallback, string $cardCb, string $cryptoCb, string $walletCb): void
{
    $rows = [
        [inline_btn('pay_card', $cardCb)],
        [inline_btn('pay_crypto', $cryptoCb)],
        [inline_btn('pay_wallet', $walletCb)],
        [back_btn($backCallback)],
    ];
    tg_edit_message($chatId, $messageId, get_text('buy.select_payment'), tg_inline_markup($rows));
}

// ---------------------------------------------------------------------
// Renew
// ---------------------------------------------------------------------

function show_renewable_services(int $userId, int $chatId): void
{
    $customer = get_or_create_customer($userId, null, null);
    $services = list_customer_services($customer['id']);
    if (!$services) {
        tg_send_message($chatId, get_text('renew.no_services'));
        return;
    }
    $rows = [];
    foreach ($services as $s) {
        $rows[] = [dynamic_btn("#{$s['id']} | {$s['pasarguard_username']}", "renew:service:{$s['id']}")];
    }
    $rows[] = [back_btn('back:main')];
    tg_send_message($chatId, get_text('renew.select_service'), tg_inline_markup($rows));
}

// ---------------------------------------------------------------------
// Wallet
// ---------------------------------------------------------------------

function show_wallet(int $userId, int $chatId): void
{
    $customer = get_or_create_customer($userId, null, null);
    $rows = [[inline_btn('wallet_topup', 'wallet:topup')], [back_btn('back:main')]];
    tg_send_message(
        $chatId,
        get_text('wallet.screen', ['balance_label' => get_text('wallet.balance_label'), 'balance' => number_format($customer['wallet_balance_toman'])]),
        tg_inline_markup($rows)
    );
}

// ---------------------------------------------------------------------
// Profile
// ---------------------------------------------------------------------

function show_profile(int $userId, int $chatId): void
{
    $customer = get_or_create_customer($userId, null, null);
    $services = list_customer_services($customer['id']);
    tg_send_message($chatId, get_text('profile.screen', [
        'user_id' => $customer['telegram_id'],
        'full_name' => $customer['full_name'] ?: '-',
        'services_count' => count($services),
        'balance_label' => get_text('wallet.balance_label'),
        'balance' => number_format($customer['wallet_balance_toman']),
    ]));
}

// ---------------------------------------------------------------------
// My services
// ---------------------------------------------------------------------

function service_display_name(array $s): string
{
    $note = trim((string) ($s['note'] ?? ''));
    return $note !== '' ? $note : $s['pasarguard_username'];
}

function show_my_services(int $userId, int $chatId, ?int $editMessageId = null): void
{
    $customer = get_or_create_customer($userId, null, null);
    $services = list_customer_services($customer['id']);
    if (!$services) {
        if ($editMessageId) {
            tg_edit_message($chatId, $editMessageId, get_text('services.empty'));
        } else {
            tg_send_message($chatId, get_text('services.empty'));
        }
        return;
    }
    $rows = [];
    foreach ($services as $s) {
        $rows[] = [dynamic_btn('🔹 ' . service_display_name($s), "svc:view:{$s['id']}")];
    }
    $rows[] = [back_btn('back:main')];
    if ($editMessageId) {
        tg_edit_message($chatId, $editMessageId, get_text('services.list_title'), tg_inline_markup($rows));
    } else {
        tg_send_message($chatId, get_text('services.list_title'), tg_inline_markup($rows));
    }
}

/** The service if it exists and belongs to this Telegram user, otherwise null. */
function user_owned_service(int $serviceId, int $userId): ?array
{
    $service = get_service($serviceId);
    $customer = get_customer_by_telegram_id($userId);
    if (!$service || !$customer || (int) $service['customer_id'] !== (int) $customer['id']) {
        return null;
    }
    return $service;
}

/** Live numbers from the panel for one service (falls back to stored plan values if the panel is unreachable). */
function service_live_info(array $service): array
{
    $info = [
        'ok' => false,
        'limit_bytes' => (int) round((float) $service['data_limit_gb'] * 1024 ** 3),
        'used_bytes' => 0,
        'expire' => null,
        'status' => 'active',
    ];
    $panel = get_panel((int) $service['panel_id']);
    if (!$panel) {
        return $info;
    }
    try {
        $user = pg_get_user($panel, $service['pasarguard_username']);
        $info['ok'] = true;
        $info['limit_bytes'] = (int) ($user['data_limit'] ?? 0);
        $info['used_bytes'] = (int) ($user['used_traffic'] ?? 0);
        $info['expire'] = parse_expire($user['expire'] ?? null);
        $info['status'] = (string) ($user['status'] ?? 'active');
    } catch (Exception $e) {
        bot_log('ServiceInfo', $e->getMessage());
    }
    return $info;
}

function service_status_label(string $status): string
{
    return match ($status) {
        'active' => '✅ فعال',
        'disabled' => '⛔️ غیرفعال',
        'limited' => '📉 حجم تمام شده',
        'expired' => '⌛️ منقضی شده',
        'on_hold' => '⏸ در انتظار شروع',
        default => $status,
    };
}

function service_usage_vars(array $service, array $info): array
{
    $limit = $info['limit_bytes'];
    $used = $info['used_bytes'];
    $percent = $limit > 0 ? $used / $limit * 100 : 0;
    $panel = get_panel((int) $service['panel_id']);
    return [
        'service_name' => htmlspecialchars(service_display_name($service)),
        'status' => service_status_label($info['status']),
        'total' => $limit > 0 ? fmt_bytes($limit) : 'نامحدود',
        'used' => fmt_bytes($used),
        'remaining' => $limit > 0 ? fmt_bytes(max(0, $limit - $used)) : 'نامحدود',
        'bar' => $limit > 0 ? progress_bar($percent) : '♾ بدون محدودیت حجم',
        'time_left' => fmt_time_left($info['expire']),
        'expire_date' => $info['expire'] ? date('Y-m-d', $info['expire']) : 'نامحدود',
        'panel_name' => $panel['name'] ?? '-',
        'subscription_url' => $service['subscription_url'],
    ];
}

function show_service_detail(array $service, int $chatId, int $messageId): void
{
    $info = service_live_info($service);
    $text = get_text('services.detail', service_usage_vars($service, $info));
    if (!$info['ok']) {
        $text .= "\n\n" . get_text('services.panel_error');
    }
    $id = $service['id'];
    $rows = [
        [inline_btn('service_renew', "renew:service:$id")],
        [inline_btn('service_get_link', "svc:link:$id"), inline_copy_btn('service_copy_link', $service['subscription_url'])],
        [inline_btn('service_usage', "svc:usage:$id")],
        [inline_btn('service_back', 'svc:list')],
    ];
    tg_edit_message($chatId, $messageId, $text, tg_inline_markup($rows));
}

// ---------------------------------------------------------------------
// Agency / reseller system
// ---------------------------------------------------------------------

function show_agency_menu(int $chatId): void
{
    $rows = [
        [inline_btn('agency_buy', 'agencymenu:buy'), inline_btn('agency_test', 'agencymenu:test')],
        [inline_btn('agency_wallet', 'agencymenu:wallet'), inline_btn('agency_my_panels', 'agencymenu:mypanels')],
        [back_btn('back:main')],
    ];
    tg_send_message($chatId, get_text('agency.intro'), tg_inline_markup($rows));
}

/** Meets PasarGuard's policy: 2+ uppercase, lowercase, digits and a special character. */
function generate_panel_password(): string
{
    $pick = function (string $set, int $n): string {
        $out = '';
        for ($i = 0; $i < $n; $i++) {
            $out .= $set[random_int(0, strlen($set) - 1)];
        }
        return $out;
    };
    $chars = str_split(
        $pick('ABCDEFGHJKLMNPQRSTUVWXYZ', 3) . $pick('abcdefghijkmnpqrstuvwxyz', 4)
        . $pick('23456789', 3) . $pick('@#$%!', 2)
    );
    for ($i = count($chars) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
    }
    return implode('', $chars);
}

function agency_provision(int $customerId, array $plan, bool $isTest): array
{
    $panel = get_panel((int) $plan['panel_id']);
    $username = 'agent_' . substr(bin2hex(random_bytes(4)), 0, 8);
    $password = generate_panel_password();
    pg_create_admin($panel, $username, $password, false, $plan['role'] ?: null);
    $expiresAt = $isTest && (int) $plan['test_duration_hours'] > 0
        ? date('Y-m-d H:i:s', time() + (int) $plan['test_duration_hours'] * 3600)
        : null;
    $agencyId = create_agency($customerId, (int) $plan['id'], $panel['id'], $username, $password, $isTest, $expiresAt);
    return ['id' => $agencyId, 'username' => $username, 'password' => $password];
}

function agency_handle_buy_plan(int $planId, int $userId, int $chatId, int $messageId, string $callbackId): void
{
    $customer = get_or_create_customer($userId, null, null);
    $plan = get_agency_plan($planId);
    if (!$plan || !$plan['is_active']) {
        tg_answer_callback($callbackId, 'این پلن در دسترس نیست.', true);
        return;
    }
    $minBalance = (int) ($plan['min_balance_toman'] ?? 0);
    if ($minBalance > 0 && (int) $customer['agency_wallet_balance_toman'] < $minBalance) {
        tg_answer_callback($callbackId);
        tg_edit_message($chatId, $messageId, get_text('agency.min_balance', [
            'min_balance' => number_format($minBalance),
            'balance' => number_format((int) $customer['agency_wallet_balance_toman']),
        ]));
        return;
    }
    try {
        $result = agency_provision((int) $customer['id'], $plan, false);
    } catch (Exception $e) {
        tg_answer_callback($callbackId, 'ساخت پنل نمایندگی ناموفق بود. به ادمین اطلاع داده شد.', true);
        notify_admins_error('ساخت نمایندگی (پلن #' . $planId . ', کاربر ' . $userId . ')', $e->getMessage());
        return;
    }
    tg_edit_message($chatId, $messageId, get_text('agency.created', ['username' => $result['username'], 'password' => $result['password']]));
    tg_answer_callback($callbackId);
}

function agency_handle_test_plan(int $planId, int $userId, int $chatId, int $messageId, string $callbackId): void
{
    $customer = get_or_create_customer($userId, null, null);
    $plan = get_agency_plan($planId);
    if (!$plan || !$plan['is_active'] || (int) $plan['test_max_count'] <= 0) {
        tg_answer_callback($callbackId, 'تست این پلن در دسترس نیست.', true);
        return;
    }
    if (get_test_agency_usage_count((int) $customer['id'], $planId) >= (int) $plan['test_max_count']) {
        tg_answer_callback($callbackId, 'سهمیه‌ی تست این پلن تموم شده.', true);
        return;
    }
    try {
        $result = agency_provision((int) $customer['id'], $plan, true);
    } catch (Exception $e) {
        tg_answer_callback($callbackId, 'ساخت پنل تست ناموفق بود. به ادمین اطلاع داده شد.', true);
        notify_admins_error('ساخت نمایندگی تست (پلن #' . $planId . ', کاربر ' . $userId . ')', $e->getMessage());
        return;
    }
    tg_edit_message($chatId, $messageId, get_text('agency.created', ['username' => $result['username'], 'password' => $result['password']]));
    tg_answer_callback($callbackId);
}

function show_agency_wallet(int $userId, int $chatId): void
{
    $customer = get_or_create_customer($userId, null, null);
    $rows = [
        [inline_btn('agency_wallet_topup_ton', 'agencymenu:topup:ton')],
        [inline_btn('agency_wallet_topup_card', 'agencymenu:topup:card')],
        [back_btn('back:main')],
    ];
    tg_send_message($chatId, get_text('agency.wallet_screen', ['balance' => number_format($customer['agency_wallet_balance_toman'])]), tg_inline_markup($rows));
}

function show_agency_my_panels(int $userId, int $chatId): void
{
    $customer = get_or_create_customer($userId, null, null);
    $agencies = list_customer_agencies($customer['id']);
    if (!$agencies) {
        tg_send_message($chatId, get_text('agency.no_panels'));
        return;
    }
    if (count($agencies) === 1) {
        send_agency_card($agencies[0], $chatId);
        return;
    }
    $rows = [];
    foreach ($agencies as $a) {
        $status = $a['is_active'] ? '🟢' : '🔴';
        $rows[] = [dynamic_btn("$status {$a['panel_admin_username']}", "agencymenu:panel:{$a['id']}")];
    }
    $rows[] = [back_btn('back:main')];
    tg_send_message($chatId, get_text('agency.select_my_panel'), tg_inline_markup($rows));
}

function send_agency_card(array $agency, int $chatId, ?int $editMessageId = null): void
{
    $plan = get_agency_plan((int) $agency['agency_plan_id']);
    $panel = get_panel((int) $agency['panel_id']);
    $usedGb = 0;
    if ($panel) {
        try {
            $usedGb = pg_total_usage_bytes($panel, $agency['panel_admin_username']) / 1_000_000_000;
        } catch (Exception $e) {
            // show whatever we have cached below if the panel call fails
            $usedGb = ((int) $agency['last_known_usage_bytes']) / 1_000_000_000;
        }
    }
    $text = get_text('agency.card', [
        'username' => $agency['panel_admin_username'],
        'password' => $agency['panel_admin_password'],
        'status' => $agency['is_active'] ? 'فعال' : 'غیرفعال',
        'used_gb' => number_format($usedGb, 3),
        'total_charged' => number_format($agency['total_charged_toman']),
        'per_gb_price' => number_format($plan['per_gb_price'] ?? 0),
        'expires_at' => $agency['expires_at'] ?? '➖',
        'active_hours' => floor(((int) $agency['active_minutes']) / 60),
        'total_hourly_charged' => number_format($agency['total_hourly_charged_toman']),
        'per_hour_price' => number_format($plan['per_hour_price'] ?? 0),
        'max_users' => $plan['max_users'] ?? '➖',
    ]);

    $id = $agency['id'];
    $loginUrl = get_setting('agency.login_url');
    $rows = [
        [tg_button('🔑 ورود به پنل', null, $loginUrl !== '' && !str_contains($loginUrl, 'تنظیم نشده') ? $loginUrl : null), dynamic_btn($agency['is_active'] ? '⛔️ غیرفعال‌سازی' : '✅ فعال‌سازی', "agencymenu:panel:$id:toggle")],
        [dynamic_btn('🔐 تغییر رمز عبور', "agencymenu:panel:$id:newpass")],
        [dynamic_btn('⛔️ غیرفعال‌سازی کاربران', "agencymenu:panel:$id:usersdisable"), dynamic_btn('✅ فعال‌سازی کاربران', "agencymenu:panel:$id:usersenable")],
        [dynamic_btn('🐞 ارسال گزارش باگ/مشکل', "agencymenu:panel:$id:report")],
        [dynamic_btn('📅 تعیین تاریخ انقضاء', "agencymenu:panel:$id:expiry"), dynamic_btn('📊 تعیین محدودیت مصرف', "agencymenu:panel:$id:usagelimit")],
        [dynamic_btn('🗑 حذف نمایندگی', "agencymenu:panel:$id:delete")],
        [back_btn('back:main'), dynamic_btn('🔄 بروزرسانی اطلاعات', "agencymenu:panel:$id:refresh")],
    ];
    if ($editMessageId) {
        tg_edit_message($chatId, $editMessageId, $text, tg_inline_markup($rows));
    } else {
        tg_send_message($chatId, $text, tg_inline_markup($rows));
    }
}

function agency_handle_panel_action(int $agencyId, string $action, int $userId, int $chatId, int $messageId, string $callbackId): void
{
    $agency = get_agency($agencyId);
    if (!$agency) {
        tg_answer_callback($callbackId, 'پیدا نشد.', true);
        return;
    }
    $panel = get_panel((int) $agency['panel_id']);

    switch ($action) {
        case 'toggle':
            update_agency_field($agencyId, ['is_active' => $agency['is_active'] ? 0 : 1]);
            tg_answer_callback($callbackId, '✅ به‌روزرسانی شد.');
            send_agency_card(get_agency($agencyId), $chatId, $messageId);
            return;

        case 'newpass':
            set_state($userId, 'user_agency_newpass', ['agency_id' => $agencyId]);
            tg_send_message($chatId, get_text('agency.ask_new_password'));
            tg_answer_callback($callbackId);
            return;

        case 'usersdisable':
        case 'usersenable':
            try {
                pg_set_users_status_by_admin($panel, $agency['panel_admin_username'], $action === 'usersenable');
                tg_answer_callback($callbackId, '✅ انجام شد.');
            } catch (Exception $e) {
                tg_answer_callback($callbackId, 'ناموفق بود.', true);
            }
            return;

        case 'report':
            set_state($userId, 'user_agency_report', ['agency_id' => $agencyId]);
            tg_send_message($chatId, get_text('agency.report_prompt'));
            tg_answer_callback($callbackId);
            return;

        case 'expiry':
            set_state($userId, 'user_agency_expiry', ['agency_id' => $agencyId]);
            tg_send_message($chatId, get_text('agency.ask_expiry'));
            tg_answer_callback($callbackId);
            return;

        case 'usagelimit':
            set_state($userId, 'user_agency_usagelimit', ['agency_id' => $agencyId]);
            tg_send_message($chatId, get_text('agency.ask_usage_limit'));
            tg_answer_callback($callbackId);
            return;

        case 'delete':
            if ($panel) {
                try {
                    pg_remove_users_by_admin($panel, $agency['panel_admin_username']);
                } catch (Exception $e) {
                    error_log('Agency delete: failed to remove users: ' . $e->getMessage());
                }
            }
            delete_agency($agencyId);
            tg_answer_callback($callbackId, '✅ نمایندگی و کاربرهاش حذف شدن.');
            tg_edit_message($chatId, $messageId, '🗑 این نمایندگی حذف شد.');
            return;

        case 'refresh':
            tg_answer_callback($callbackId);
            send_agency_card(get_agency($agencyId), $chatId, $messageId);
            return;
    }
}

// ---------------------------------------------------------------------
// Test service (free, limited per plan per user)
// ---------------------------------------------------------------------

function show_test_plans(int $userId, int $chatId): void
{
    $customer = get_or_create_customer($userId, null, null);
    $plans = list_test_plans();
    if (!$plans) {
        tg_send_message($chatId, get_text('test.none_available'));
        return;
    }
    $rows = [];
    foreach ($plans as $plan) {
        $remaining = max(0, (int) $plan['test_max_count'] - count_tests_in_window((int) $customer['id'], $plan));
        $label = "{$plan['name']} | " . fmt_gb((float) $plan['data_limit_gb']) . ' | ' . fmt_days((int) $plan['duration_days']) . " | باقی‌مونده: $remaining";
        $rows[] = [dynamic_btn($label, "test:plan:{$plan['id']}", $plan['button_style'] ?? null, $plan['button_emoji_id'] ?? null)];
    }
    $rows[] = [back_btn('back:main')];
    tg_send_message($chatId, get_text('test.select_plan'), tg_inline_markup($rows));
}

function handle_test_plan_selected(int $planId, int $userId, int $chatId, int $messageId, string $callbackId): void
{
    $customer = get_or_create_customer($userId, null, null);
    $plan = get_plan($planId);
    if (!$plan || (int) $plan['test_max_count'] <= 0 || !$plan['is_active']) {
        tg_answer_callback($callbackId, 'این پلن دیگه برای تست در دسترس نیست.', true);
        return;
    }
    if (count_tests_in_window((int) $customer['id'], $plan) >= (int) $plan['test_max_count']) {
        tg_answer_callback($callbackId);
        $wait = seconds_until_next_test((int) $customer['id'], $plan);
        if ($wait > 0) {
            tg_edit_message($chatId, $messageId, get_text('test.wait', ['wait' => fmt_duration_seconds($wait)]));
        } else {
            tg_edit_message($chatId, $messageId, get_text('test.limit_final'));
        }
        return;
    }

    $panel = get_panel((int) $plan['panel_id']);
    try {
        $groupIds = json_decode($plan['group_ids'] ?? '[]', true) ?: null;
        [$username, $subUrl] = pg_create_user($panel, (float) $plan['data_limit_gb'], (int) $plan['duration_days'], 'test:' . $customer['id'], $groupIds);
    } catch (Exception $e) {
        tg_answer_callback($callbackId, 'ساخت سرویس تست ناموفق بود، بعداً دوباره امتحان کن.', true);
        notify_admins_error('ساخت سرویس تست (پلن #' . $planId . ')', $e->getMessage());
        return;
    }
    $serviceId = create_service($customer['id'], $panel['id'], $username, $subUrl, (float) $plan['data_limit_gb'], (int) $plan['duration_days'], '🧪 ' . $plan['name'], $plan['name'], 0);
    log_test_usage((int) $customer['id'], $planId);

    tg_edit_message($chatId, $messageId, get_text('test.delivered_msg', service_message_vars(get_service($serviceId), $plan, $panel)));
    tg_answer_callback($callbackId);
}

// ---------------------------------------------------------------------
// Callback router for everything not prefixed "adm:"
// ---------------------------------------------------------------------

function user_handle_callback(string $data, int $userId, int $chatId, int $messageId, string $callbackId, string $fullName, ?string $username): void
{
    $parts = explode(':', $data);

    if ($data === 'back:main') {
        tg_edit_message($chatId, $messageId, 'منوی اصلی — از دکمه‌های پایین صفحه استفاده کن. 👇');
        tg_answer_callback($callbackId);
        return;
    }

    if ($parts[0] === 'svc') {
        if ($parts[1] === 'list') {
            show_my_services($userId, $chatId, $messageId);
            tg_answer_callback($callbackId);
            return;
        }
        $service = user_owned_service((int) ($parts[2] ?? 0), $userId);
        if (!$service) {
            tg_answer_callback($callbackId, 'سرویس پیدا نشد.', true);
            return;
        }
        if ($parts[1] === 'view') {
            show_service_detail($service, $chatId, $messageId);
        } elseif ($parts[1] === 'link') {
            tg_send_message($chatId, get_text('services.link_message', [
                'service_name' => htmlspecialchars(service_display_name($service)),
                'subscription_url' => $service['subscription_url'],
            ]));
        } elseif ($parts[1] === 'usage') {
            $vars = service_usage_vars($service, service_live_info($service));
            tg_edit_message($chatId, $messageId, get_text('services.usage_chart', $vars), tg_inline_markup([[inline_btn('back', "svc:view:{$service['id']}")]]));
        }
        tg_answer_callback($callbackId);
        return;
    }

    if ($data === 'back:buy:panels') {
        show_panels($chatId, $messageId);
        tg_answer_callback($callbackId);
        return;
    }

    if ($parts[0] === 'buy' && $parts[1] === 'panel') {
        show_plans_for_panel((int) $parts[2], $chatId, $messageId, 'buy');
        tg_answer_callback($callbackId);
        return;
    }

    if ($parts[0] === 'buy' && $parts[1] === 'plan') {
        $planId = (int) $parts[2];
        set_state($userId, 'user_buy_note', ['plan_id' => $planId]);
        tg_edit_message($chatId, $messageId, get_text('buy.ask_note'));
        tg_answer_callback($callbackId);
        return;
    }

    if ($parts[0] === 'back' && $parts[1] === 'buy' && $parts[2] === 'plan_list') {
        $plan = get_plan((int) $parts[3]);
        show_plans_for_panel((int) $plan['panel_id'], $chatId, $messageId, 'buy');
        tg_answer_callback($callbackId);
        return;
    }

    if ($parts[0] === 'buy' && $parts[1] === 'pay') {
        $method = $parts[2];
        $planId = (int) $parts[3];
        handle_purchase_payment($method, $planId, $userId, $chatId, $messageId, $callbackId);
        return;
    }

    if ($parts[0] === 'renew' && $parts[1] === 'service') {
        $serviceId = (int) $parts[2];
        show_renew_plan_list($serviceId, $chatId, $messageId);
        tg_answer_callback($callbackId);
        return;
    }

    if ($parts[0] === 'renew' && $parts[1] === 'plan') {
        $planId = (int) $parts[2];
        $serviceId = (int) $parts[3];
        $rows = [
            [inline_btn('pay_card', "renew:pay:card:$planId:$serviceId")],
            [inline_btn('pay_crypto', "renew:pay:crypto:$planId:$serviceId")],
            [inline_btn('pay_wallet', "renew:pay:wallet:$planId:$serviceId")],
            [back_btn('back:main')],
        ];
        tg_edit_message($chatId, $messageId, get_text('buy.select_payment'), tg_inline_markup($rows));
        tg_answer_callback($callbackId);
        return;
    }

    if ($parts[0] === 'renew' && $parts[1] === 'pay') {
        $method = $parts[2];
        $planId = (int) $parts[3];
        $serviceId = (int) $parts[4];
        handle_renew_payment($method, $planId, $serviceId, $userId, $chatId, $messageId, $callbackId);
        return;
    }

    if ($parts[0] === 'test' && $parts[1] === 'plan') {
        handle_test_plan_selected((int) $parts[2], $userId, $chatId, $messageId, $callbackId);
        return;
    }

    if ($data === 'agencymenu:buy') {
        $plans = list_active_agency_plans();
        $rows = [];
        foreach ($plans as $plan) {
            $rows[] = [dynamic_btn($plan['name'], "agencymenu:buyplan:{$plan['id']}")];
        }
        $rows[] = [back_btn('back:main')];
        tg_edit_message($chatId, $messageId, $plans ? get_text('agency.select_plan') : get_text('agency.no_plans'), tg_inline_markup($rows));
        tg_answer_callback($callbackId);
        return;
    }
    if ($parts[0] === 'agencymenu' && $parts[1] === 'buyplan') {
        agency_handle_buy_plan((int) $parts[2], $userId, $chatId, $messageId, $callbackId);
        return;
    }
    if ($data === 'agencymenu:test') {
        $plans = list_test_agency_plans();
        $rows = [];
        foreach ($plans as $plan) {
            $rows[] = [dynamic_btn($plan['name'], "agencymenu:testplan:{$plan['id']}")];
        }
        $rows[] = [back_btn('back:main')];
        tg_edit_message($chatId, $messageId, $plans ? get_text('agency.select_plan') : get_text('agency.no_plans'), tg_inline_markup($rows));
        tg_answer_callback($callbackId);
        return;
    }
    if ($parts[0] === 'agencymenu' && $parts[1] === 'testplan') {
        agency_handle_test_plan((int) $parts[2], $userId, $chatId, $messageId, $callbackId);
        return;
    }
    if ($data === 'agencymenu:wallet') {
        show_agency_wallet($userId, $chatId);
        tg_answer_callback($callbackId);
        return;
    }
    if ($data === 'agencymenu:topup:ton') {
        set_state($userId, 'user_agency_topup_ton_amount');
        tg_send_message($chatId, get_text('agency.wallet_topup_ask_amount'));
        tg_answer_callback($callbackId);
        return;
    }
    if ($data === 'agencymenu:topup:card') {
        set_state($userId, 'user_agency_topup_card_amount');
        tg_send_message($chatId, get_text('agency.wallet_topup_ask_amount'));
        tg_answer_callback($callbackId);
        return;
    }
    if ($data === 'agencymenu:mypanels') {
        show_agency_my_panels($userId, $chatId);
        tg_answer_callback($callbackId);
        return;
    }
    if ($parts[0] === 'agencymenu' && $parts[1] === 'panel') {
        $agencyId = (int) $parts[2];
        if (isset($parts[3])) {
            agency_handle_panel_action($agencyId, $parts[3], $userId, $chatId, $messageId, $callbackId);
        } else {
            send_agency_card(get_agency($agencyId), $chatId, $messageId);
            tg_answer_callback($callbackId);
        }
        return;
    }

    if ($data === 'wallet:topup') {
        set_state($userId, 'user_topup_amount');
        tg_send_message($chatId, get_text('wallet.topup_ask_amount'));
        tg_answer_callback($callbackId);
        return;
    }

    if ($parts[0] === 'myservices' && $parts[1] === 'newlink') {
        $service = get_service((int) $parts[2]);
        tg_send_message($chatId, "لینک سابسکریپشن شما:\n<code>{$service['subscription_url']}</code>");
        tg_answer_callback($callbackId);
        return;
    }

    tg_answer_callback($callbackId);
}

function show_renew_plan_list(int $serviceId, int $chatId, int $messageId): void
{
    $service = get_service($serviceId);
    $plans = list_active_plans_for_panel((int) $service['panel_id']);
    $rows = [];
    foreach ($plans as $plan) {
        $label = "{$plan['name']} | +" . rtrim(rtrim(number_format($plan['data_limit_gb'], 2), '0'), '.') . "GB | +{$plan['duration_days']} روز | " . number_format($plan['price_toman']) . ' تومان';
        $rows[] = [dynamic_btn($label, "renew:plan:{$plan['id']}:$serviceId")];
    }
    $rows[] = [back_btn("svc:view:$serviceId")];
    tg_edit_message($chatId, $messageId, get_text('buy.select_plan'), tg_inline_markup($rows));
}

function handle_purchase_payment(string $method, int $planId, int $userId, int $chatId, int $messageId, string $callbackId): void
{
    $customer = get_or_create_customer($userId, null, null);
    $plan = get_plan($planId);
    $ctx = get_state($userId);
    $note = ($ctx['state'] === 'buyctx' && (int) ($ctx['data']['plan_id'] ?? 0) === $planId) ? ($ctx['data']['note'] ?? '') : '';
    $note = $note !== '' ? $note : null;

    if ($method === 'card') {
        $orderId = create_order($customer['id'], 'card', 'purchase', $planId, null, (int) $plan['price_toman'], null, null, $note);
        tg_edit_message($chatId, $messageId, get_text('buy.card_instructions', [
            'amount' => number_format($plan['price_toman']),
            'card_number' => get_setting('payment.card_number'),
            'card_holder' => get_setting('payment.card_holder'),
            'order_id' => $orderId,
        ]));
    } elseif ($method === 'crypto') {
        $orderId = create_order($customer['id'], 'crypto', 'purchase', $planId, null, null, (float) $plan['price_usdt'], null, $note);
        $uniqueAmount = round((float) $plan['price_usdt'] + ($orderId % 97) / 1000, 3);
        set_order_expected_usdt($orderId, $uniqueAmount);
        tg_edit_message($chatId, $messageId, get_text('buy.crypto_instructions', [
            'amount' => $uniqueAmount,
            'wallet_address' => get_setting('payment.usdt_wallet_trc20'),
            'order_id' => $orderId,
        ]));
    } elseif ($method === 'wallet') {
        if ((int) $customer['wallet_balance_toman'] < (int) $plan['price_toman']) {
            tg_answer_callback($callbackId, 'موجودی کیف پولت کافی نیست.', true);
            return;
        }
        adjust_wallet_balance($customer['id'], -(int) $plan['price_toman']);
        $orderId = create_order($customer['id'], 'wallet', 'purchase', $planId, null, (int) $plan['price_toman'], null, null, $note);
        mark_order_paid($orderId);
        fulfill_order($orderId);
        tg_edit_message($chatId, $messageId, '✅ از کیف پولت کسر شد و سرویس فعال شد.');
    }
    clear_state($userId);
    tg_answer_callback($callbackId);
}

function handle_renew_payment(string $method, int $planId, int $serviceId, int $userId, int $chatId, int $messageId, string $callbackId): void
{
    $customer = get_or_create_customer($userId, null, null);
    $plan = get_plan($planId);

    if ($method === 'card') {
        $orderId = create_order($customer['id'], 'card', 'renew', $planId, $serviceId, (int) $plan['price_toman']);
        tg_edit_message($chatId, $messageId, get_text('buy.card_instructions', [
            'amount' => number_format($plan['price_toman']),
            'card_number' => get_setting('payment.card_number'),
            'card_holder' => get_setting('payment.card_holder'),
            'order_id' => $orderId,
        ]));
    } elseif ($method === 'crypto') {
        $orderId = create_order($customer['id'], 'crypto', 'renew', $planId, $serviceId, null, (float) $plan['price_usdt']);
        $uniqueAmount = round((float) $plan['price_usdt'] + ($orderId % 97) / 1000, 3);
        set_order_expected_usdt($orderId, $uniqueAmount);
        tg_edit_message($chatId, $messageId, get_text('buy.crypto_instructions', [
            'amount' => $uniqueAmount,
            'wallet_address' => get_setting('payment.usdt_wallet_trc20'),
            'order_id' => $orderId,
        ]));
    } elseif ($method === 'wallet') {
        if ((int) $customer['wallet_balance_toman'] < (int) $plan['price_toman']) {
            tg_answer_callback($callbackId, 'موجودی کیف پولت کافی نیست.', true);
            return;
        }
        adjust_wallet_balance($customer['id'], -(int) $plan['price_toman']);
        $orderId = create_order($customer['id'], 'wallet', 'renew', $planId, $serviceId, (int) $plan['price_toman']);
        mark_order_paid($orderId);
        fulfill_order($orderId);
        tg_edit_message($chatId, $messageId, '✅ از کیف پولت کسر شد و سرویس تمدید شد.');
    }
    tg_answer_callback($callbackId);
}

// ---------------------------------------------------------------------
// Stateful text messages (top-up amount / support message)
// ---------------------------------------------------------------------

function user_handle_state_message(string $state, array $data, array $message, int $userId, int $chatId, string $fullName, ?string $username): void
{
    $text = $message['text'] ?? null;

    if ($state === 'user_buy_note') {
        $note = trim($text ?? '');
        if ($note === '') {
            tg_send_message($chatId, get_text('buy.ask_note'));
            return;
        }
        if ($note === '-') {
            $note = '';
        }
        $note = function_exists('mb_substr') ? mb_substr($note, 0, 60) : substr($note, 0, 60);
        $planId = (int) $data['plan_id'];
        set_state($userId, 'buyctx', ['plan_id' => $planId, 'note' => $note]);
        $rows = [
            [inline_btn('pay_card', "buy:pay:card:$planId")],
            [inline_btn('pay_crypto', "buy:pay:crypto:$planId")],
            [inline_btn('pay_wallet', "buy:pay:wallet:$planId")],
            [back_btn('back:buy:panels')],
        ];
        tg_send_message($chatId, get_text('buy.card_ready') . "\n\n" . get_text('buy.select_payment'), tg_inline_markup($rows));
        return;
    }

    if ($state === 'user_topup_amount') {
        if (!$text || !ctype_digit($text)) {
            tg_send_message($chatId, 'لطفاً فقط عدد بفرست (تومان).');
            return;
        }
        $amount = (int) $text;
        clear_state($userId);
        $customer = get_or_create_customer($userId, $username, $fullName);
        $orderId = create_order($customer['id'], 'card', 'topup', null, null, $amount);
        tg_send_message($chatId, get_text('buy.card_instructions', [
            'amount' => number_format($amount),
            'card_number' => get_setting('payment.card_number'),
            'card_holder' => get_setting('payment.card_holder'),
            'order_id' => $orderId,
        ]));
        return;
    }

    if ($state === 'user_support') {
        clear_state($userId);
        $rows = [[tg_button('↩️ پاسخ به این کاربر', "adm:reply:$userId")]];
        foreach (ADMIN_IDS as $adminId) {
            tg_send_message($adminId, "📩 پیام پشتیبانی از کاربر {$userId} (@{$username}):\n\n" . ($text ?: '[پیام غیرمتنی]'), tg_inline_markup($rows));
        }
        tg_send_message($chatId, get_text('support.received'));
        return;
    }

    if ($state === 'user_agency_topup_ton_amount') {
        if (!$text || !ctype_digit($text)) { tg_send_message($chatId, 'لطفاً فقط عدد بفرست (تومان).'); return; }
        $amount = (int) $text;
        clear_state($userId);
        $rate = (float) get_setting('payment.ton_to_toman_rate');
        if ($rate <= 0) {
            tg_send_message($chatId, 'نرخ تبدیل تون به تومان هنوز توسط ادمین تنظیم نشده. لطفاً از کارت به کارت استفاده کن یا با پشتیبانی تماس بگیر.');
            return;
        }
        $tonAmount = round($amount / $rate, 3);
        $memo = generate_ton_memo();
        $customer = get_or_create_customer($userId, $username, $fullName);
        $orderId = create_order($customer['id'], 'ton', 'agency_topup', null, null, $amount, null, $memo);
        tg_send_message($chatId, get_text('agency.wallet_topup_ton_instructions', [
            'ton_amount' => $tonAmount,
            'wallet_address' => get_setting('payment.agency_ton_wallet'),
            'memo' => $memo,
            'order_id' => $orderId,
        ]));
        return;
    }

    if ($state === 'user_agency_topup_card_amount') {
        if (!$text || !ctype_digit($text)) { tg_send_message($chatId, 'لطفاً فقط عدد بفرست (تومان).'); return; }
        $amount = (int) $text;
        clear_state($userId);
        $customer = get_or_create_customer($userId, $username, $fullName);
        $orderId = create_order($customer['id'], 'agency_card', 'agency_topup', null, null, $amount);
        tg_send_message($chatId, get_text('buy.card_instructions', [
            'amount' => number_format($amount),
            'card_number' => get_setting('payment.agency_card_number'),
            'card_holder' => get_setting('payment.agency_card_holder'),
            'order_id' => $orderId,
        ]));
        return;
    }

    if ($state === 'user_agency_newpass') {
        $newPassword = trim($text ?? '');
        if ($newPassword === '') { tg_send_message($chatId, 'یه رمز معتبر بفرست.'); return; }
        clear_state($userId);
        $agency = get_agency((int) $data['agency_id']);
        $panel = get_panel((int) $agency['panel_id']);
        try {
            pg_set_admin_password($panel, $agency['panel_admin_username'], $newPassword);
            update_agency_field((int) $agency['id'], ['panel_admin_password' => $newPassword]);
            tg_send_message($chatId, '✅ رمز عبور تغییر کرد.');
        } catch (Exception $e) {
            tg_send_message($chatId, '❌ تغییر رمز ناموفق بود.');
        }
        return;
    }

    if ($state === 'user_agency_report') {
        clear_state($userId);
        $agencyId = (int) $data['agency_id'];
        foreach (ADMIN_IDS as $adminId) {
            tg_send_message($adminId, "🐞 گزارش از نماینده (آیدی نمایندگی #$agencyId) - کاربر {$userId}:\n\n" . ($text ?: '[پیام غیرمتنی]'));
        }
        tg_send_message($chatId, get_text('support.received'));
        return;
    }

    if ($state === 'user_agency_expiry') {
        $value = trim($text ?? '');
        clear_state($userId);
        $agencyId = (int) $data['agency_id'];
        if ($value === 'حذف') {
            update_agency_field($agencyId, ['expires_at' => null]);
            tg_send_message($chatId, '✅ تاریخ انقضا برداشته شد.');
            return;
        }
        $timestamp = strtotime($value);
        if (!$timestamp) {
            tg_send_message($chatId, 'فرمت تاریخ درست نیست. دوباره امتحان کن (YYYY-MM-DD).');
            set_state($userId, 'user_agency_expiry', $data);
            return;
        }
        update_agency_field($agencyId, ['expires_at' => date('Y-m-d H:i:s', $timestamp)]);
        tg_send_message($chatId, '✅ تاریخ انقضا تنظیم شد.');
        return;
    }

    if ($state === 'user_agency_usagelimit') {
        $value = trim($text ?? '');
        clear_state($userId);
        $agencyId = (int) $data['agency_id'];
        if ($value === 'حذف') {
            update_agency_field($agencyId, ['usage_limit_gb' => null]);
            tg_send_message($chatId, '✅ محدودیت مصرف برداشته شد.');
            return;
        }
        if (!is_numeric($value)) {
            tg_send_message($chatId, 'فقط عدد بفرست.');
            set_state($userId, 'user_agency_usagelimit', $data);
            return;
        }
        update_agency_field($agencyId, ['usage_limit_gb' => (float) $value]);
        tg_send_message($chatId, '✅ محدودیت مصرف تنظیم شد.');
        return;
    }
}

// ---------------------------------------------------------------------
// Card receipt photo
// ---------------------------------------------------------------------

function user_handle_receipt(array $message, int $userId, int $chatId): void
{
    $customer = get_customer_by_telegram_id($userId);
    if (!$customer) {
        return;
    }
    $order = list_latest_pending_card_order_for_customer($customer['id']);
    if (!$order) {
        tg_send_message($chatId, 'سفارش در انتظار پرداخت پیدا نشد.');
        return;
    }
    $photos = $message['photo'];
    $fileId = end($photos)['file_id'];
    set_order_receipt((int) $order['id'], $fileId);

    tg_send_message($chatId, "رسید سفارش #{$order['id']} دریافت شد. منتظر تایید ادمین بمون ⏳");
    $rows = [[tg_button('✅ تایید', "adm:order:approve:{$order['id']}"), tg_button('❌ رد', "adm:order:reject:{$order['id']}")]];
    foreach (ADMIN_IDS as $adminId) {
        tg_send_photo($adminId, $fileId, "رسید جدید برای سفارش #{$order['id']} از کاربر {$userId}", tg_inline_markup($rows));
    }
}
