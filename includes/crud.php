<?php
// ---------------------------------------------------------------------
// Customers / wallet
// ---------------------------------------------------------------------

function get_or_create_customer(int $telegramId, ?string $username, ?string $fullName): array
{
    $stmt = db()->prepare("SELECT * FROM customers WHERE telegram_id = ?");
    $stmt->execute([$telegramId]);
    $customer = $stmt->fetch();
    if ($customer) {
        return $customer;
    }
    $stmt = db()->prepare("INSERT INTO customers (telegram_id, username, full_name, wallet_balance_toman, created_at) VALUES (?, ?, ?, 0, NOW())");
    $stmt->execute([$telegramId, $username, $fullName]);
    return get_or_create_customer($telegramId, $username, $fullName);
}

function get_customer_by_telegram_id(int $telegramId): ?array
{
    $stmt = db()->prepare("SELECT * FROM customers WHERE telegram_id = ?");
    $stmt->execute([$telegramId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function adjust_wallet_balance(int $customerId, int $deltaToman): void
{
    $stmt = db()->prepare("UPDATE customers SET wallet_balance_toman = wallet_balance_toman + ? WHERE id = ?");
    $stmt->execute([$deltaToman, $customerId]);
}

function list_all_customer_telegram_ids(): array
{
    return db()->query("SELECT telegram_id FROM customers WHERE is_blocked = 0")->fetchAll(PDO::FETCH_COLUMN);
}

// ---------------------------------------------------------------------
// Panels
// ---------------------------------------------------------------------

function list_active_panels(): array
{
    return db()->query("SELECT * FROM panels WHERE is_active = 1 ORDER BY position, id")->fetchAll();
}

function list_all_panels(): array
{
    return db()->query("SELECT * FROM panels ORDER BY position, id")->fetchAll();
}

function get_panel(int $id): ?array
{
    $stmt = db()->prepare("SELECT * FROM panels WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function create_panel(string $name, string $baseUrl, string $adminUsername, string $adminPassword): void
{
    $stmt = db()->prepare("INSERT INTO panels (name, base_url, admin_username, admin_password, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
    $stmt->execute([$name, $baseUrl, $adminUsername, $adminPassword]);
}

function delete_panel(int $id): void
{
    db()->prepare("DELETE FROM plans WHERE panel_id = ?")->execute([$id]);
    db()->prepare("DELETE FROM panels WHERE id = ?")->execute([$id]);
}

function update_panel_field(int $id, array $fields): void
{
    $allowed = ['name', 'button_style', 'button_emoji_id', 'is_active'];
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
    db()->prepare("UPDATE panels SET " . implode(', ', $sets) . " WHERE id = :id")->execute($params);
}

// ---------------------------------------------------------------------
// Plans
// ---------------------------------------------------------------------

function list_active_plans_for_panel(int $panelId): array
{
    $stmt = db()->prepare("SELECT * FROM plans WHERE panel_id = ? AND is_active = 1 AND test_only = 0 ORDER BY position, id");
    $stmt->execute([$panelId]);
    return $stmt->fetchAll();
}

function list_test_plans(): array
{
    $stmt = db()->query("SELECT * FROM plans WHERE is_active = 1 AND test_max_count > 0 ORDER BY panel_id, position, id");
    return $stmt->fetchAll();
}

/** Seconds of the rolling window a test quota applies to; 0 = lifetime ("once"). */
function test_period_seconds(string $period): int
{
    return match ($period) {
        'daily' => 86400,
        'weekly' => 7 * 86400,
        'monthly' => 30 * 86400,
        default => 0,
    };
}

function test_period_label(string $period): string
{
    return match ($period) {
        'daily' => 'هر روز',
        'weekly' => 'هر هفته',
        'monthly' => 'هر ماه',
        default => 'فقط یک‌بار (کلاً)',
    };
}

/** Tests this customer took from this plan inside the plan's window (or ever, for "once"). */
function count_tests_in_window(int $customerId, array $plan): int
{
    $seconds = test_period_seconds($plan['test_period'] ?? 'once');
    if ($seconds > 0) {
        $stmt = db()->prepare("SELECT COUNT(*) FROM test_log WHERE customer_id = ? AND plan_id = ? AND created_at > (NOW() - INTERVAL ? SECOND)");
        $stmt->execute([$customerId, $plan['id'], $seconds]);
        return (int) $stmt->fetchColumn();
    }
    $stmt = db()->prepare("SELECT COUNT(*) FROM test_log WHERE customer_id = ? AND plan_id = ?");
    $stmt->execute([$customerId, $plan['id']]);
    return max((int) $stmt->fetchColumn(), get_test_usage_count($customerId, (int) $plan['id']));
}

/** Seconds until the customer's oldest in-window test falls out of the window (0 = lifetime/none). */
function seconds_until_next_test(int $customerId, array $plan): int
{
    $seconds = test_period_seconds($plan['test_period'] ?? 'once');
    if ($seconds <= 0) {
        return 0;
    }
    $stmt = db()->prepare("SELECT UNIX_TIMESTAMP(MIN(created_at)) FROM test_log WHERE customer_id = ? AND plan_id = ? AND created_at > (NOW() - INTERVAL ? SECOND)");
    $stmt->execute([$customerId, $plan['id'], $seconds]);
    $oldest = (int) $stmt->fetchColumn();
    return $oldest > 0 ? max(60, $oldest + $seconds - time()) : 0;
}

function log_test_usage(int $customerId, int $planId): void
{
    db()->prepare("INSERT INTO test_log (customer_id, plan_id, created_at) VALUES (?, ?, NOW())")->execute([$customerId, $planId]);
}

function get_test_usage_count(int $customerId, int $planId): int
{
    $stmt = db()->prepare("SELECT used_count FROM test_usage WHERE customer_id = ? AND plan_id = ?");
    $stmt->execute([$customerId, $planId]);
    $count = $stmt->fetchColumn();
    return $count === false ? 0 : (int) $count;
}

function list_all_plans(): array
{
    return db()->query("SELECT * FROM plans ORDER BY panel_id, position, id")->fetchAll();
}

function get_plan(int $id): ?array
{
    $stmt = db()->prepare("SELECT * FROM plans WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function create_plan(int $panelId, string $name, float $dataLimitGb, int $durationDays, int $priceToman, float $priceUsdt): int
{
    $stmt = db()->prepare("INSERT INTO plans (panel_id, name, data_limit_gb, duration_days, price_toman, price_usdt, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
    $stmt->execute([$panelId, $name, $dataLimitGb, $durationDays, $priceToman, $priceUsdt]);
    return (int) db()->lastInsertId();
}

function delete_plan(int $id): void
{
    db()->prepare("DELETE FROM plans WHERE id = ?")->execute([$id]);
}

function update_plan_field(int $id, array $fields): void
{
    $allowed = ['name', 'button_style', 'button_emoji_id', 'is_active', 'test_max_count', 'test_period', 'test_only', 'group_ids'];
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
    db()->prepare("UPDATE plans SET " . implode(', ', $sets) . " WHERE id = :id")->execute($params);
}

// ---------------------------------------------------------------------
// Mandatory channels
// ---------------------------------------------------------------------

function list_active_channels(): array
{
    return db()->query("SELECT * FROM mandatory_channels WHERE is_active = 1 ORDER BY position, id")->fetchAll();
}

function list_all_channels(): array
{
    return db()->query("SELECT * FROM mandatory_channels ORDER BY position, id")->fetchAll();
}

function create_channel(string $chatId, string $title, string $inviteLink): void
{
    $stmt = db()->prepare("INSERT INTO mandatory_channels (chat_id, title, invite_link, is_active) VALUES (?, ?, ?, 1)");
    $stmt->execute([$chatId, $title, $inviteLink]);
}

function delete_channel(int $id): void
{
    db()->prepare("DELETE FROM mandatory_channels WHERE id = ?")->execute([$id]);
}

// ---------------------------------------------------------------------
// Services
// ---------------------------------------------------------------------

function create_service(int $customerId, int $panelId, string $username, string $subscriptionUrl, float $dataLimitGb, int $durationDays, ?string $note = null, ?string $planName = null, int $priceToman = 0): int
{
    $stmt = db()->prepare("INSERT INTO services (customer_id, panel_id, pasarguard_username, subscription_url, data_limit_gb, duration_days, note, plan_name, price_toman, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
    $stmt->execute([$customerId, $panelId, $username, $subscriptionUrl, $dataLimitGb, $durationDays, $note, $planName, $priceToman]);
    return (int) db()->lastInsertId();
}

function list_customer_services(int $customerId): array
{
    $stmt = db()->prepare("SELECT * FROM services WHERE customer_id = ? AND is_active = 1");
    $stmt->execute([$customerId]);
    return $stmt->fetchAll();
}

function get_service(int $id): ?array
{
    $stmt = db()->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function deactivate_service(int $id): void
{
    db()->prepare("UPDATE services SET is_active = 0 WHERE id = ?")->execute([$id]);
}

// ---------------------------------------------------------------------
// Orders
// ---------------------------------------------------------------------
// order_type: purchase | renew | topup
// payment_method: card | crypto | wallet
// status: pending | paid | delivered | rejected | expired

function create_order(int $customerId, string $paymentMethod, string $orderType = 'purchase', ?int $planId = null, ?int $serviceId = null, ?int $amountToman = null, ?float $expectedUsdt = null, ?string $memo = null, ?string $note = null): int
{
    $stmt = db()->prepare(
        "INSERT INTO orders (customer_id, plan_id, service_id, order_type, payment_method, status, amount_toman, expected_amount_usdt, memo, note, created_at)
         VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$customerId, $planId, $serviceId, $orderType, $paymentMethod, $amountToman, $expectedUsdt, $memo, $note]);
    return (int) db()->lastInsertId();
}

function list_pending_ton_orders(): array
{
    return db()->query("SELECT * FROM orders WHERE payment_method = 'ton' AND status = 'pending' ORDER BY id")->fetchAll();
}

function get_order(int $id): ?array
{
    $stmt = db()->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function set_order_expected_usdt(int $orderId, float $amount): void
{
    db()->prepare("UPDATE orders SET expected_amount_usdt = ? WHERE id = ?")->execute([$amount, $orderId]);
}

function set_order_receipt(int $orderId, string $fileId): void
{
    db()->prepare("UPDATE orders SET receipt_file_id = ? WHERE id = ?")->execute([$fileId, $orderId]);
}

function list_latest_pending_card_order_for_customer(int $customerId): ?array
{
    $stmt = db()->prepare("SELECT * FROM orders WHERE customer_id = ? AND status = 'pending' AND payment_method IN ('card', 'agency_card') ORDER BY id DESC LIMIT 1");
    $stmt->execute([$customerId]);
    return $stmt->fetch() ?: null;
}

function list_pending_card_orders(): array
{
    return db()->query("SELECT * FROM orders WHERE payment_method IN ('card', 'agency_card') AND status = 'pending' ORDER BY id")->fetchAll();
}

function list_pending_crypto_orders(): array
{
    return db()->query("SELECT * FROM orders WHERE payment_method = 'crypto' AND status = 'pending' ORDER BY id")->fetchAll();
}

function mark_order_paid(int $orderId, ?string $txHash = null): void
{
    if ($txHash) {
        db()->prepare("UPDATE orders SET status = 'paid', tx_hash = ? WHERE id = ?")->execute([$txHash, $orderId]);
    } else {
        db()->prepare("UPDATE orders SET status = 'paid' WHERE id = ?")->execute([$orderId]);
    }
}

function mark_order_delivered(int $orderId, string $username, string $subscriptionUrl): void
{
    $stmt = db()->prepare("UPDATE orders SET status = 'delivered', pasarguard_username = ?, subscription_url = ? WHERE id = ?");
    $stmt->execute([$username, $subscriptionUrl, $orderId]);
}

function mark_order_rejected(int $orderId): void
{
    db()->prepare("UPDATE orders SET status = 'rejected' WHERE id = ?")->execute([$orderId]);
}

function expire_stale_crypto_orders(int $ttlMinutes): array
{
    $stmt = db()->prepare("SELECT id FROM orders WHERE payment_method = 'crypto' AND status = 'pending' AND created_at < (NOW() - INTERVAL ? MINUTE)");
    $stmt->execute([$ttlMinutes]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        db()->prepare("UPDATE orders SET status = 'expired' WHERE id IN ($in)")->execute($ids);
    }
    return $ids;
}
