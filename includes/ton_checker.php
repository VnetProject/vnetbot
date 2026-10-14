<?php
/**
 * Unlike the USDT-TRC20 checker (which matches by a near-unique amount),
 * TON transfers can carry a free-text comment/memo, which is a far more
 * reliable way to match a payment to an order — so every TON top-up order
 * gets a short random memo code the payer is asked to include.
 */

const TONCENTER_API = 'https://toncenter.com/api/v2/getTransactions';

function generate_ton_memo(): string
{
    return 'TOP' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

function fetch_recent_ton_transfers(string $walletAddress, int $limit = 30): array
{
    if (!$walletAddress || strpos($walletAddress, 'تنظیم نشده') !== false) {
        return [];
    }
    $params = http_build_query(['address' => $walletAddress, 'limit' => $limit, 'archival' => 'false']);
    $ch = curl_init(TONCENTER_API . '?' . $params);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response === false) {
        return [];
    }
    $data = json_decode($response, true);
    $transfers = [];
    foreach ($data['result'] ?? [] as $tx) {
        $inMsg = $tx['in_msg'] ?? null;
        if (!$inMsg || (int) ($inMsg['value'] ?? 0) <= 0) {
            continue; // outgoing or zero-value tx
        }
        $transfers[] = [
            'tx_hash' => $tx['transaction_id']['hash'] ?? ($inMsg['hash'] ?? null),
            'amount_ton' => ((float) $inMsg['value']) / 1_000_000_000,
            'memo' => trim($inMsg['message'] ?? ''),
        ];
    }
    return $transfers;
}

function find_ton_transfer_by_memo(string $walletAddress, string $memo, array $alreadyUsedHashes): ?array
{
    foreach (fetch_recent_ton_transfers($walletAddress) as $t) {
        if (!$t['tx_hash'] || in_array($t['tx_hash'], $alreadyUsedHashes, true)) {
            continue;
        }
        if ($t['memo'] === $memo) {
            return $t;
        }
    }
    return null;
}
