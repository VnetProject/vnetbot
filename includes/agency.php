<?php
// ---------------------------------------------------------------------
// Agency plans (admin-defined reseller tiers)
// ---------------------------------------------------------------------

function list_active_agency_plans(): array
{
    return db()->query("SELECT * FROM agency_plans WHERE is_active = 1 ORDER BY position, id")->fetchAll();
}

function list_all_agency_plans(): array
{
    return db()->query("SELECT * FROM agency_plans ORDER BY position, id")->fetchAll();
}

function list_test_agency_plans(): array
{
    return db()->query("SELECT * FROM agency_plans WHERE is_active = 1 AND test_max_count > 0 ORDER BY position, id")->fetchAll();
}

function get_agency_plan(int $id): ?array
{
    $stmt = db()->prepare("SELECT * FROM agency_plans WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function create_agency_plan(int $panelId, string $name, ?string $role, int $perGbPrice, int $perHourPrice, int $maxUsers, int $minBalance = 0): int
{
    $stmt = db()->prepare(
        "INSERT INTO agency_plans (panel_id, name, role, per_gb_price, per_hour_price, max_users, min_balance_toman, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
    );
    $stmt->execute([$panelId, $name, $role, $perGbPrice, $perHourPrice, $maxUsers, $minBalance]);
    return (int) db()->lastInsertId();
}

function delete_agency_plan(int $id): void
{
    db()->prepare("DELETE FROM agency_plans WHERE id = ?")->execute([$id]);
}

function update_agency_plan_field(int $id, array $fields): void
{
    $allowed = ['name', 'role', 'per_gb_price', 'per_hour_price', 'max_users', 'min_balance_toman', 'is_active', 'button_style', 'button_emoji_id', 'test_max_count', 'test_duration_hours'];
    $sets = [];
    $params = ['id' => $id];
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
    db()->prepare("UPDATE agency_plans SET " . implode(', ', $sets) . " WHERE id = :id")->execute($params);
}

// ---------------------------------------------------------------------
// Agencies (provisioned reseller accounts)
// ---------------------------------------------------------------------

function create_agency(int $customerId, int $agencyPlanId, int $panelId, string $username, string $password, bool $isTest = false, ?string $expiresAt = null): int
{
    $stmt = db()->prepare(
        "INSERT INTO agencies (customer_id, agency_plan_id, panel_id, panel_admin_username, panel_admin_password, is_active, is_test, expires_at, created_at)
         VALUES (?, ?, ?, ?, ?, 1, ?, ?, NOW())"
    );
    $stmt->execute([$customerId, $agencyPlanId, $panelId, $username, $password, $isTest ? 1 : 0, $expiresAt]);
    return (int) db()->lastInsertId();
}

function get_agency(int $id): ?array
{
    $stmt = db()->prepare("SELECT * FROM agencies WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function list_customer_agencies(int $customerId): array
{
    $stmt = db()->prepare("SELECT * FROM agencies WHERE customer_id = ? ORDER BY id DESC");
    $stmt->execute([$customerId]);
    return $stmt->fetchAll();
}

function list_active_agencies(): array
{
    return db()->query("SELECT * FROM agencies WHERE is_active = 1 AND is_test = 0")->fetchAll();
}

function list_expired_test_agencies(): array
{
    $stmt = db()->query(
        "SELECT a.* FROM agencies a JOIN agency_plans p ON p.id = a.agency_plan_id
         WHERE a.is_test = 1 AND a.is_active = 1
         AND TIMESTAMPADD(HOUR, p.test_duration_hours, a.created_at) < NOW()"
    );
    return $stmt->fetchAll();
}

function update_agency_field(int $id, array $fields): void
{
    $allowed = [
        'is_active', 'expires_at', 'usage_limit_gb', 'last_known_usage_bytes',
        'lifetime_usage_gb', 'total_charged_toman', 'active_minutes', 'total_hourly_charged_toman',
        'panel_admin_password',
    ];
    $sets = [];
    $params = ['id' => $id];
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
    db()->prepare("UPDATE agencies SET " . implode(', ', $sets) . " WHERE id = :id")->execute($params);
}

function delete_agency(int $id): void
{
    db()->prepare("DELETE FROM agencies WHERE id = ?")->execute([$id]);
}

function get_test_agency_usage_count(int $customerId, int $agencyPlanId): int
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM agencies WHERE customer_id = ? AND agency_plan_id = ? AND is_test = 1");
    $stmt->execute([$customerId, $agencyPlanId]);
    return (int) $stmt->fetchColumn();
}

// ---------------------------------------------------------------------
// Billing (called every minute by cron_billing.php)
// ---------------------------------------------------------------------

function run_agency_billing_tick(): void
{
    require_once __DIR__ . '/panel_api.php';

    foreach (list_active_agencies() as $agency) {
        $plan = get_agency_plan((int) $agency['agency_plan_id']);
        if (!$plan) {
            continue;
        }
        $panel = get_panel((int) $agency['panel_id']);
        if (!$panel) {
            continue;
        }

        // 1) usage-based (per-GB) charge
        try {
            $currentBytes = pg_total_usage_bytes($panel, $agency['panel_admin_username']);
        } catch (Exception $e) {
            error_log('Agency billing: usage fetch failed for agency ' . $agency['id'] . ': ' . $e->getMessage());
            continue; // don't charge/guess if we can't read real usage
        }
        $deltaBytes = max(0, $currentBytes - (int) $agency['last_known_usage_bytes']);
        $deltaGb = $deltaBytes / 1_000_000_000;
        $gbCharge = (int) round($deltaGb * (int) $plan['per_gb_price']);

        // 2) time-based (per-hour, charged in 1/60th slices every minute)
        $hourlyCharge = (int) round(((int) $plan['per_hour_price']) / 60);

        $totalCharge = $gbCharge + $hourlyCharge;
        $customer = get_customer_by_id((int) $agency['customer_id']);
        if (!$customer) {
            continue;
        }
        if ($totalCharge > 0) {
            adjust_agency_wallet_balance((int) $customer['id'], -$totalCharge);
        }

        update_agency_field((int) $agency['id'], [
            'last_known_usage_bytes' => $currentBytes,
            'lifetime_usage_gb' => (float) $agency['lifetime_usage_gb'] + $deltaGb,
            'total_charged_toman' => (int) $agency['total_charged_toman'] + $gbCharge,
            'active_minutes' => (int) $agency['active_minutes'] + 1,
            'total_hourly_charged_toman' => (int) $agency['total_hourly_charged_toman'] + $hourlyCharge,
        ]);

        // 3) enforcement: zero balance, expiry, usage cap
        $freshCustomer = get_customer_by_id((int) $customer['id']);
        $reasons = [];
        if ((int) $freshCustomer['agency_wallet_balance_toman'] <= 0) {
            $reasons[] = 'zero_balance';
        }
        if ($agency['expires_at'] !== null && strtotime($agency['expires_at']) < time()) {
            $reasons[] = 'expired';
        }
        if ($agency['usage_limit_gb'] !== null && ((float) $agency['lifetime_usage_gb'] + $deltaGb) > (float) $agency['usage_limit_gb']) {
            $reasons[] = 'usage_limit';
        }
        if ($reasons) {
            update_agency_field((int) $agency['id'], ['is_active' => 0]);
            tg_send_message((int) $freshCustomer['telegram_id'], get_text('agency.disabled_zero_balance'));
        }
    }

    // test agencies: auto-remove once their trial window has passed
    require_once __DIR__ . '/panel_api.php';
    foreach (list_expired_test_agencies() as $agency) {
        $panel = get_panel((int) $agency['panel_id']);
        if ($panel) {
            try {
                pg_remove_users_by_admin($panel, $agency['panel_admin_username']);
            } catch (Exception $e) {
                error_log('Test agency cleanup failed: ' . $e->getMessage());
            }
        }
        delete_agency((int) $agency['id']);
    }
}

function adjust_agency_wallet_balance(int $customerId, int $deltaToman): void
{
    db()->prepare("UPDATE customers SET agency_wallet_balance_toman = agency_wallet_balance_toman + ? WHERE id = ?")->execute([$deltaToman, $customerId]);
}

// ---------------------------------------------------------------------
// Agency wallet top-ups (TON auto-detected by memo, or card manually approved)
// ---------------------------------------------------------------------

function fulfill_agency_topup(int $orderId): void
{
    $order = get_order($orderId);
    if (!$order || $order['order_type'] !== 'agency_topup') {
        return;
    }
    mark_order_paid($orderId);
    $customer = get_customer_by_id((int) $order['customer_id']);
    if (!$customer) {
        return;
    }
    adjust_agency_wallet_balance((int) $customer['id'], (int) $order['amount_toman']);
    tg_send_message((int) $customer['telegram_id'], get_text('wallet.topup_success', ['amount' => number_format($order['amount_toman'])]));
}

/** Called every minute by the cron: matches pending TON top-ups by memo. */
function check_ton_topups(): void
{
    require_once __DIR__ . '/ton_checker.php';
    $walletAddress = get_setting('payment.agency_ton_wallet');
    $pending = list_pending_ton_orders();
    if (!$pending) {
        return;
    }
    $usedHashes = array_values(array_filter(array_column($pending, 'tx_hash')));
    foreach ($pending as $order) {
        $match = find_ton_transfer_by_memo($walletAddress, (string) $order['memo'], $usedHashes);
        if ($match === null) {
            continue;
        }
        $usedHashes[] = $match['tx_hash'];
        mark_order_paid((int) $order['id'], $match['tx_hash']);
        fulfill_agency_topup((int) $order['id']);
    }
}
