<?php

const USDT_TRC20_CONTRACT = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
const TRONSCAN_API = 'https://apilist.tronscanapi.com/api/token_trc20/transfers';

function fetch_recent_usdt_transfers(int $limit = 50): array
{
    $walletAddress = get_setting('payment.usdt_wallet_trc20');
    if (!$walletAddress || strpos($walletAddress, 'تنظیم نشده') !== false) {
        return [];
    }

    $params = http_build_query([
        'limit' => $limit,
        'start' => 0,
        'sort' => '-timestamp',
        'count' => 'true',
        'toAddress' => $walletAddress,
        'contract_address' => USDT_TRC20_CONTRACT,
    ]);

    $headers = [];
    $apiKey = defined('TRONSCAN_API_KEY') ? TRONSCAN_API_KEY : '';
    if ($apiKey) {
        $headers[] = 'TRON-PRO-API-KEY: ' . $apiKey;
    }

    $ch = curl_init(TRONSCAN_API . '?' . $params);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        return [];
    }
    $data = json_decode($response, true);
    $transfers = [];
    foreach ($data['token_transfers'] ?? [] as $item) {
        $transfers[] = [
            'tx_hash' => $item['transaction_id'] ?? null,
            'amount_usdt' => ((int) ($item['quant'] ?? 0)) / 1_000_000,
            'confirmed' => (bool) ($item['confirmed'] ?? false),
        ];
    }
    return $transfers;
}

function find_matching_transfer(float $expectedAmountUsdt, array $alreadyUsedHashes): ?array
{
    foreach (fetch_recent_usdt_transfers() as $t) {
        if (!$t['confirmed'] || !$t['tx_hash']) {
            continue;
        }
        if (in_array($t['tx_hash'], $alreadyUsedHashes, true)) {
            continue;
        }
        if (abs($t['amount_usdt'] - $expectedAmountUsdt) < 0.001) {
            return $t;
        }
    }
    return null;
}
