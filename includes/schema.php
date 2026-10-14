<?php
// Database schema for vnetbot. Safe to run on an existing database: tables
// are only created when missing and missing columns are added, nothing is
// ever dropped or overwritten. Called by installer.php and automatically
// by the bot after an update (see maybe_upgrade_schema()).

const SCHEMA_VERSION = '3';

function schema_tables(): array
{
    return [
        <<<'SQL'
CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            telegram_id BIGINT NOT NULL UNIQUE,
            username VARCHAR(64) NULL,
            full_name VARCHAR(191) NULL,
            wallet_balance_toman INT NOT NULL DEFAULT 0,
            agency_wallet_balance_toman INT NOT NULL DEFAULT 0,
            is_blocked TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL
        ) CHARACTER SET utf8mb4
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS panels (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(64) NOT NULL,
            base_url VARCHAR(255) NOT NULL,
            admin_username VARCHAR(128) NOT NULL,
            admin_password VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            position INT NOT NULL DEFAULT 0,
            button_style VARCHAR(16) NULL,
            button_emoji_id VARCHAR(64) NULL,
            created_at DATETIME NOT NULL
        ) CHARACTER SET utf8mb4
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS plans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            panel_id INT NOT NULL,
            name VARCHAR(64) NOT NULL,
            data_limit_gb DECIMAL(10,2) NOT NULL,
            duration_days INT NOT NULL,
            price_toman INT NOT NULL,
            price_usdt DECIMAL(12,3) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            position INT NOT NULL DEFAULT 0,
            button_style VARCHAR(16) NULL,
            button_emoji_id VARCHAR(64) NULL,
            test_max_count INT NOT NULL DEFAULT 0,
            group_ids TEXT NULL,
            INDEX (panel_id)
        ) CHARACTER SET utf8mb4
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS mandatory_channels (
            id INT AUTO_INCREMENT PRIMARY KEY,
            chat_id VARCHAR(64) NOT NULL,
            title VARCHAR(191) NOT NULL,
            invite_link VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            position INT NOT NULL DEFAULT 0
        ) CHARACTER SET utf8mb4
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS services (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            panel_id INT NOT NULL,
            pasarguard_username VARCHAR(64) NOT NULL,
            subscription_url TEXT NOT NULL,
            data_limit_gb DECIMAL(10,2) NOT NULL,
            duration_days INT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            INDEX (customer_id)
        ) CHARACTER SET utf8mb4
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            plan_id INT NULL,
            service_id INT NULL,
            order_type VARCHAR(16) NOT NULL DEFAULT 'purchase',
            payment_method VARCHAR(16) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'pending',
            amount_toman INT NULL,
            receipt_file_id VARCHAR(255) NULL,
            tx_hash VARCHAR(128) NULL UNIQUE,
            expected_amount_usdt DECIMAL(12,3) NULL,
            memo VARCHAR(32) NULL,
            pasarguard_username VARCHAR(64) NULL,
            subscription_url TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            INDEX (customer_id), INDEX (status), INDEX (payment_method)
        ) CHARACTER SET utf8mb4
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS test_usage (
            customer_id INT NOT NULL,
            plan_id INT NOT NULL,
            used_count INT NOT NULL DEFAULT 0,
            PRIMARY KEY (customer_id, plan_id)
        ) CHARACTER SET utf8mb4
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS agency_plans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            panel_id INT NOT NULL,
            name VARCHAR(64) NOT NULL,
            role VARCHAR(64) NULL,
            per_gb_price INT NOT NULL DEFAULT 0,
            per_hour_price INT NOT NULL DEFAULT 0,
            max_users INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            test_max_count INT NOT NULL DEFAULT 0,
            test_duration_hours INT NOT NULL DEFAULT 0,
            button_style VARCHAR(16) NULL,
            button_emoji_id VARCHAR(64) NULL,
            position INT NOT NULL DEFAULT 0
        ) CHARACTER SET utf8mb4
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS agencies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            agency_plan_id INT NOT NULL,
            panel_id INT NOT NULL,
            panel_admin_username VARCHAR(128) NOT NULL,
            panel_admin_password VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_test TINYINT(1) NOT NULL DEFAULT 0,
            expires_at DATETIME NULL,
            usage_limit_gb DECIMAL(12,2) NULL,
            last_known_usage_bytes BIGINT NOT NULL DEFAULT 0,
            lifetime_usage_gb DECIMAL(14,3) NOT NULL DEFAULT 0,
            total_charged_toman BIGINT NOT NULL DEFAULT 0,
            active_minutes INT NOT NULL DEFAULT 0,
            total_hourly_charged_toman BIGINT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            INDEX (customer_id)
        ) CHARACTER SET utf8mb4
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(128) PRIMARY KEY,
            value TEXT NOT NULL
        ) CHARACTER SET utf8mb4
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS button_configs (
            button_key VARCHAR(64) PRIMARY KEY,
            parent VARCHAR(32) NOT NULL,
            label VARCHAR(191) NOT NULL,
            emoji_custom_id VARCHAR(64) NULL,
            style VARCHAR(16) NULL,
            position INT NOT NULL DEFAULT 0,
            is_visible TINYINT(1) NOT NULL DEFAULT 1
        ) CHARACTER SET utf8mb4
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS user_states (
            telegram_id BIGINT PRIMARY KEY,
            state VARCHAR(64) NULL,
            data TEXT NULL,
            updated_at DATETIME NULL
        ) CHARACTER SET utf8mb4
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS test_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            plan_id INT NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX (customer_id, plan_id)
        ) CHARACTER SET utf8mb4
SQL,
    ];
}

/** [table => [column => DDL]] — added only when the column does not exist yet. */
function schema_columns(): array
{
    return [
        'customers' => [
            'username' => 'VARCHAR(64) NULL',
            'full_name' => 'VARCHAR(191) NULL',
            'wallet_balance_toman' => 'INT NOT NULL DEFAULT 0',
            'agency_wallet_balance_toman' => 'INT NOT NULL DEFAULT 0',
            'is_blocked' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'created_at' => 'DATETIME NULL',
        ],
        'panels' => [
            'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'position' => 'INT NOT NULL DEFAULT 0',
            'button_style' => 'VARCHAR(16) NULL',
            'button_emoji_id' => 'VARCHAR(64) NULL',
            'created_at' => 'DATETIME NULL',
        ],
        'plans' => [
            'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'position' => 'INT NOT NULL DEFAULT 0',
            'button_style' => 'VARCHAR(16) NULL',
            'button_emoji_id' => 'VARCHAR(64) NULL',
            'test_max_count' => 'INT NOT NULL DEFAULT 0',
            'test_period' => "VARCHAR(8) NOT NULL DEFAULT 'once'",
            'test_only' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'group_ids' => 'TEXT NULL',
        ],
        'services' => [
            'note' => 'VARCHAR(191) NULL',
            'plan_name' => 'VARCHAR(64) NULL',
            'price_toman' => 'INT NOT NULL DEFAULT 0',
            'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'created_at' => 'DATETIME NULL',
        ],
        'orders' => [
            'note' => 'VARCHAR(191) NULL',
            'service_id' => 'INT NULL',
            'amount_toman' => 'INT NULL',
            'receipt_file_id' => 'VARCHAR(255) NULL',
            'expected_amount_usdt' => 'DECIMAL(12,3) NULL',
            'memo' => 'VARCHAR(32) NULL',
            'pasarguard_username' => 'VARCHAR(64) NULL',
            'subscription_url' => 'TEXT NULL',
            'updated_at' => 'DATETIME NULL',
        ],
        'agency_plans' => [
            'role' => 'VARCHAR(64) NULL',
            'min_balance_toman' => 'INT NOT NULL DEFAULT 0',
            'test_max_count' => 'INT NOT NULL DEFAULT 0',
            'test_duration_hours' => 'INT NOT NULL DEFAULT 0',
            'button_style' => 'VARCHAR(16) NULL',
            'button_emoji_id' => 'VARCHAR(64) NULL',
            'position' => 'INT NOT NULL DEFAULT 0',
        ],
        'agencies' => [
            'is_test' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'expires_at' => 'DATETIME NULL',
            'usage_limit_gb' => 'DECIMAL(12,2) NULL',
            'last_known_usage_bytes' => 'BIGINT NOT NULL DEFAULT 0',
            'lifetime_usage_gb' => 'DECIMAL(14,3) NOT NULL DEFAULT 0',
            'total_charged_toman' => 'BIGINT NOT NULL DEFAULT 0',
            'active_minutes' => 'INT NOT NULL DEFAULT 0',
            'total_hourly_charged_toman' => 'BIGINT NOT NULL DEFAULT 0',
        ],
    ];
}

function ensure_schema(PDO $pdo): void
{
    foreach (schema_tables() as $sql) {
        $pdo->exec($sql);
    }
    foreach (schema_columns() as $table => $columns) {
        $existing = [];
        foreach ($pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $existing[strtolower($c['Field'])] = true;
        }
        foreach ($columns as $name => $ddl) {
            if (!isset($existing[strtolower($name)])) {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$name` $ddl");
            }
        }
    }

    // Main-menu button positions used to be "slot 0..15 of a 4x4 grid".
    // They are now row*10+column so rows can have any number of buttons.
    $flag = $pdo->query("SELECT value FROM settings WHERE setting_key = 'layout.v2'")->fetchColumn();
    if ($flag === false) {
        $pdo->exec("UPDATE button_configs SET position = (position DIV 4) * 10 + (position MOD 4) WHERE parent = 'main_menu' AND position < 16");
        $pdo->prepare("INSERT INTO settings (setting_key, value) VALUES ('layout.v2', '1')")->execute();
    }
}

/** Runs ensure_schema() once per SCHEMA_VERSION, so updates need no manual migrate step. */
function maybe_upgrade_schema(): void
{
    try {
        $pdo = db();
        $current = null;
        try {
            $current = $pdo->query("SELECT value FROM settings WHERE setting_key = 'schema.version'")->fetchColumn();
        } catch (Throwable $e) {
            // settings table missing: fall through and build everything
        }
        if ($current === SCHEMA_VERSION) {
            return;
        }
        ensure_schema($pdo);
        seed_defaults();
        $pdo->prepare("INSERT INTO settings (setting_key, value) VALUES ('schema.version', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)")->execute([SCHEMA_VERSION]);
    } catch (Throwable $e) {
        if (function_exists('bot_log')) {
            bot_log('Schema', $e->getMessage());
        }
    }
}
