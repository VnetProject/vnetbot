<?php
// Backend of the main-menu layout Mini App (webapp/layout.html).
// Every request carries Telegram's signed "initData"; it is verified with the
// bot token and the user must be one of ADMIN_IDS, so nobody else can use it.

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/content.php';
require __DIR__ . '/includes/schema.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function api_fail(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function verify_init_data(string $initData): ?array
{
    parse_str($initData, $fields);
    $hash = $fields['hash'] ?? '';
    unset($fields['hash']);
    if ($hash === '' || empty($fields['user'])) {
        return null;
    }
    ksort($fields);
    $lines = [];
    foreach ($fields as $k => $v) {
        $lines[] = "$k=$v";
    }
    $secret = hash_hmac('sha256', BOT_TOKEN, 'WebAppData', true);
    if (!hash_equals(hash_hmac('sha256', implode("\n", $lines), $secret), $hash)) {
        return null;
    }
    if (time() - (int) ($fields['auth_date'] ?? 0) > 86400) {
        return null;
    }
    return json_decode($fields['user'], true) ?: null;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$user = verify_init_data((string) ($input['initData'] ?? ''));
if (!$user || !in_array((int) $user['id'], ADMIN_IDS, true)) {
    api_fail(403, 'forbidden');
}

maybe_upgrade_schema();
$action = $input['action'] ?? 'get';

if ($action === 'get') {
    $rows = [];
    $hidden = [];
    foreach (list_buttons_by_parent('main_menu') as $b) {
        $item = ['key' => $b['button_key'], 'label' => $b['label'], 'style' => $b['style']];
        if (!$b['is_visible'] || (int) $b['position'] >= 900) {
            $hidden[] = $item;
            continue;
        }
        $rows[intdiv((int) $b['position'], 10)][] = $item;
    }
    ksort($rows);
    echo json_encode(['ok' => true, 'rows' => array_values($rows), 'hidden' => $hidden], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'save') {
    $known = [];
    foreach (list_buttons_by_parent('main_menu') as $b) {
        $known[$b['button_key']] = true;
    }
    $placed = [];
    $rowIndex = 0;
    foreach (($input['rows'] ?? []) as $row) {
        $col = 0;
        $any = false;
        foreach ((array) $row as $key) {
            if (!isset($known[$key]) || isset($placed[$key]) || $col > 9) {
                continue;
            }
            $placed[$key] = true;
            set_button_field($key, ['position' => $rowIndex * 10 + $col, 'is_visible' => 1]);
            $col++;
            $any = true;
        }
        if ($any) {
            $rowIndex++;
        }
    }
    $slot = 900;
    foreach (array_keys($known) as $key) {
        if (!isset($placed[$key])) {
            set_button_field($key, ['position' => $slot++, 'is_visible' => 0]);
        }
    }
    foreach ((array) ($input['labels'] ?? []) as $key => $label) {
        $label = trim((string) $label);
        if (isset($known[$key]) && $label !== '') {
            set_button_field($key, ['label' => mb_substr($label, 0, 60)]);
        }
    }
    echo json_encode(['ok' => true]);
    exit;
}

api_fail(400, 'bad action');
