<?php
// Run this EVERY MINUTE via cPanel > Cron Jobs, either as:
//   php /home/USER/public_html/bot/cron.php
// or, if your host doesn't offer CLI cron:
//   wget -q -O /dev/null "https://yourdomain.com/bot/cron.php?key=YOUR_WEBHOOK_SECRET"
// (the ?key= check stops random visitors from spamming this endpoint;
// it is NOT required when run from the command line)

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/telegram.php';
require __DIR__ . '/includes/content.php';
require __DIR__ . '/includes/schema.php';
require __DIR__ . '/includes/format.php';
require __DIR__ . '/includes/crud.php';
require __DIR__ . '/includes/agency.php';
require __DIR__ . '/includes/panel_api.php';
require __DIR__ . '/includes/crypto_checker.php';
require __DIR__ . '/includes/ton_checker.php';
require __DIR__ . '/includes/fulfillment.php';

if (PHP_SAPI !== 'cli') {
    $key = $_GET['key'] ?? '';
    if (!hash_equals(WEBHOOK_SECRET, $key)) {
        http_response_code(403);
        exit('forbidden');
    }
}

maybe_upgrade_schema();

check_crypto_orders();      // USDT-TRC20 for regular service purchases
check_ton_topups();         // TON for agency wallet top-ups
run_agency_billing_tick();  // per-GB / per-hour deduction + auto-suspend

echo "ok\n";
