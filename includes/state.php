<?php

function get_state(int $telegramId): array
{
    $stmt = db()->prepare("SELECT state, data FROM user_states WHERE telegram_id = ?");
    $stmt->execute([$telegramId]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['state' => null, 'data' => []];
    }
    return ['state' => $row['state'], 'data' => json_decode($row['data'] ?? '{}', true) ?: []];
}

function set_state(int $telegramId, ?string $state, array $data = []): void
{
    $stmt = db()->prepare(
        "INSERT INTO user_states (telegram_id, state, data, updated_at) VALUES (:tid, :state, :data, NOW())
         ON DUPLICATE KEY UPDATE state = :state2, data = :data2, updated_at = NOW()"
    );
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    $stmt->execute(['tid' => $telegramId, 'state' => $state, 'data' => $json, 'state2' => $state, 'data2' => $json]);
}

function clear_state(int $telegramId): void
{
    $stmt = db()->prepare("DELETE FROM user_states WHERE telegram_id = ?");
    $stmt->execute([$telegramId]);
}
