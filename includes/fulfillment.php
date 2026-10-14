<?php

function fulfill_order(int $orderId): void
{
    $order = get_order($orderId);
    if (!$order) {
        return;
    }
    $customer = get_customer_by_id($order['customer_id']);
    if (!$customer) {
        return;
    }
    $customerTelegramId = (int) $customer['telegram_id'];

    if ($order['order_type'] === 'topup') {
        adjust_wallet_balance($order['customer_id'], (int) $order['amount_toman']);
        tg_send_message($customerTelegramId, get_text('wallet.topup_success', ['amount' => number_format($order['amount_toman'])]));
        return;
    }

    if ($order['order_type'] === 'renew') {
        $service = get_service($order['service_id']);
        $plan = get_plan($order['plan_id']);
        $panel = get_panel($service['panel_id']);
        try {
            pg_extend_user($panel, $service['pasarguard_username'], (float) $plan['data_limit_gb'], (int) $plan['duration_days']);
        } catch (Exception $e) {
            notify_admins_error('تمدید سفارش #' . $orderId, $e->getMessage());
            return;
        }
        mark_order_delivered($orderId, $service['pasarguard_username'], $service['subscription_url']);
        tg_send_message($customerTelegramId, get_text('order.renewed', service_message_vars($service, $plan, $panel)));
        return;
    }

    // purchase
    $plan = get_plan($order['plan_id']);
    $panel = get_panel($plan['panel_id']);
    $note = trim((string) ($order['note'] ?? ''));
    try {
        $groupIds = json_decode($plan['group_ids'] ?? '[]', true) ?: null;
        [$username, $subUrl] = pg_create_user($panel, (float) $plan['data_limit_gb'], (int) $plan['duration_days'], $note !== '' ? $note : 'order:' . $orderId, $groupIds);
    } catch (Exception $e) {
        notify_admins_error('تحویل سفارش #' . $orderId, $e->getMessage());
        return;
    }
    $serviceId = create_service($order['customer_id'], $panel['id'], $username, $subUrl, (float) $plan['data_limit_gb'], (int) $plan['duration_days'], $note !== '' ? $note : null, $plan['name'], (int) $plan['price_toman']);
    mark_order_delivered($orderId, $username, $subUrl);
    tg_send_message($customerTelegramId, get_text('order.delivered', service_message_vars(get_service($serviceId), $plan, $panel)));
}

/** Placeholder values shared by the delivery / renewal / test messages. */
function service_message_vars(array $service, ?array $plan, ?array $panel): array
{
    $name = trim((string) ($service['note'] ?? '')) !== '' ? $service['note'] : $service['pasarguard_username'];
    $gb = (float) ($plan['data_limit_gb'] ?? $service['data_limit_gb']);
    $days = (int) ($plan['duration_days'] ?? $service['duration_days']);
    return [
        'service_name' => htmlspecialchars($name),
        'username' => $service['pasarguard_username'],
        'data_limit' => fmt_gb($gb),
        'duration' => fmt_days($days),
        'price' => fmt_price((int) ($plan['price_toman'] ?? $service['price_toman'] ?? 0)),
        'panel_name' => $panel['name'] ?? '-',
        'subscription_url' => $service['subscription_url'],
    ];
}

function get_customer_by_id(int $customerId): ?array
{
    $stmt = db()->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customerId]);
    return $stmt->fetch() ?: null;
}

/** Called from cron_crypto.php on a schedule (e.g. every 1-2 minutes). */
function check_crypto_orders(): void
{
    $ttl = defined('CRYPTO_ORDER_TTL_MINUTES') ? CRYPTO_ORDER_TTL_MINUTES : 30;
    $pending = list_pending_crypto_orders();

    if ($pending) {
        $usedHashes = array_values(array_filter(array_column($pending, 'tx_hash')));
        foreach ($pending as $order) {
            $match = find_matching_transfer((float) $order['expected_amount_usdt'], $usedHashes);
            if ($match === null) {
                continue;
            }
            $usedHashes[] = $match['tx_hash'];
            mark_order_paid((int) $order['id'], $match['tx_hash']);
            fulfill_order((int) $order['id']);
        }
    }

    expire_stale_crypto_orders($ttl);
}
