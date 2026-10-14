<?php
/**
 * PasarGuard exposes a REST API compatible with the well-known Marzban-family
 * layout (token auth, /api/user CRUD). This file talks to it directly with
 * cURL so no Composer/SDK is needed on shared hosting.
 *
 * ⚠️ IMPORTANT: verify this against your own panel before going live.
 * If your panel version uses different field names (e.g. `group_ids` vs
 * `proxies`) or a different auth path, this is the ONLY file you need to
 * adjust — everything else in the bot talks to these functions, not to the
 * panel directly.
 */

function pg_request(array $panel, string $method, string $path, array $body = [], ?string $token = null)
{
    $url = rtrim($panel['base_url'], '/') . $path;
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $ch = curl_init($url);
    $opts = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
    ];
    if ($method !== 'GET' && $method !== 'DELETE') {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
    }
    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new Exception("Panel request failed ($path): $error");
    }
    $decoded = json_decode($response, true);
    if ($httpCode >= 400) {
        throw new Exception("Panel API error $httpCode on $path: " . $response);
    }
    return $decoded;
}

function pg_get_token(array $panel): string
{
    $url = rtrim($panel['base_url'], '/') . '/api/admin/token';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'username' => $panel['admin_username'],
            'password' => $panel['admin_password'],
            'grant_type' => 'password',
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode >= 400) {
        throw new Exception("Could not authenticate with panel '{$panel['name']}' (HTTP $httpCode): $response");
    }
    $data = json_decode($response, true);
    if (empty($data['access_token'])) {
        throw new Exception("Panel '{$panel['name']}' did not return an access token: $response");
    }
    return $data['access_token'];
}

function pg_random_username(string $prefix = 'bot'): string
{
    return $prefix . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
}

/** Returns [username, subscription_url] */
function pg_create_user(array $panel, float $dataLimitGb, int $durationDays, string $note = '', ?array $groupIds = null): array
{
    $token = pg_get_token($panel);
    $username = pg_random_username();
    $body = [
        'username' => $username,
        'data_limit' => (int) round($dataLimitGb * 1024 ** 3),
        'expire' => time() + $durationDays * 86400,
        'status' => 'active',
        'note' => $note,
    ];
    if ($groupIds) {
        $body['group_ids'] = $groupIds;
    }
    $user = pg_request($panel, 'POST', '/api/user', $body, $token);
    return [$user['username'] ?? $username, $user['subscription_url'] ?? ''];
}

function pg_get_user(array $panel, string $username): array
{
    $token = pg_get_token($panel);
    return pg_request($panel, 'GET', '/api/user/' . urlencode($username), [], $token);
}

function pg_extend_user(array $panel, string $username, float $addDataGb, int $addDays): void
{
    $token = pg_get_token($panel);
    $current = pg_request($panel, 'GET', '/api/user/' . urlencode($username), [], $token);
    $newLimit = (int) ($current['data_limit'] ?? 0) + (int) round($addDataGb * 1024 ** 3);
    $currentExpire = (int) ($current['expire'] ?? time());
    $base = max($currentExpire, time());
    $newExpire = $base + $addDays * 86400;
    pg_request($panel, 'PUT', '/api/user/' . urlencode($username), [
        'data_limit' => $newLimit,
        'expire' => $newExpire,
        'status' => 'active',
    ], $token);
}

function pg_remove_user(array $panel, string $username): void
{
    $token = pg_get_token($panel);
    pg_request($panel, 'DELETE', '/api/user/' . urlencode($username), [], $token);
}

/**
 * Creates a sub-admin account directly on the panel (not the same as a bot
 * admin — this is a PasarGuard/Marzban panel dashboard login). Useful if you
 * want to give a partner their own limited panel login. `isSudo` should
 * almost always be false for anyone but yourself.
 */
function pg_create_admin(array $panel, string $username, string $password, bool $isSudo = false, ?string $role = null): array
{
    $token = pg_get_token($panel);
    $body = ['username' => $username, 'password' => $password, 'is_sudo' => $isSudo];
    // This PasarGuard version requires a numeric role_id on admin creation.
    $roleId = pg_resolve_role_id($panel, $role);
    if ($roleId !== null) {
        $body['role_id'] = $roleId;
    }
    return pg_request($panel, 'POST', '/api/admin', $body, $token);
}

/**
 * Turns whatever is stored on the agency plan (a number like "2", or a role
 * name like "reseller", or nothing) into the numeric role id the panel wants.
 * With nothing stored, the first role the panel lists is used.
 */
function pg_resolve_role_id(array $panel, ?string $role): ?int
{
    $role = $role !== null ? trim($role) : '';
    if ($role !== '' && ctype_digit($role)) {
        return (int) $role;
    }
    $roles = [];
    try {
        $roles = pg_list_roles($panel);
    } catch (Exception $e) {
        if ($role !== '') {
            throw new Exception('نقش پیدا نشد و لیست نقش‌ها از پنل گرفته نشد: ' . $e->getMessage());
        }
        return null; // let the panel answer with its own error
    }
    foreach ($roles as $r) {
        if (!is_array($r) || !isset($r['id'])) {
            continue;
        }
        if ($role === '' || strcasecmp((string) ($r['name'] ?? ''), $role) === 0) {
            return (int) $r['id'];
        }
    }
    return null;
}

/**
 * ⚠️ UNVERIFIED endpoint path: tries the likely ones in order. Returns a list
 * of ['id' => .., 'name' => ..].
 */
function pg_list_roles(array $panel): array
{
    $token = pg_get_token($panel);
    $lastError = null;
    foreach (['/api/roles', '/api/role', '/api/admin/roles'] as $path) {
        try {
            $result = pg_request($panel, 'GET', $path, [], $token);
        } catch (Exception $e) {
            $lastError = $e;
            continue;
        }
        if (isset($result['roles']) && is_array($result['roles'])) {
            return $result['roles'];
        }
        if (is_array($result)) {
            return $result;
        }
    }
    if ($lastError) {
        throw $lastError;
    }
    return [];
}

/**
 * ⚠️ UNVERIFIED: same caveat as pg_list_roles() — tries the most likely
 * endpoint for PasarGuard's "config groups" (what configs/inbounds a new
 * user gets). If this doesn't match your panel version, plan creation still
 * works fine without group selection (new users just get the panel's
 * default groups) until you fix this one function.
 */
function pg_list_groups(array $panel): array
{
    $token = pg_get_token($panel);
    $result = pg_request($panel, 'GET', '/api/groups', [], $token);
    return $result['groups'] ?? (is_array($result) ? $result : []);
}
/** New password for an existing panel admin account. */
function pg_set_admin_password(array $panel, string $username, string $newPassword): void
{
    $token = pg_get_token($panel);
    pg_request($panel, 'PUT', '/api/admin/' . urlencode($username), ['password' => $newPassword], $token);
}

/** All panel users that belong to a given admin (sub-admin) account. */
function pg_list_users_by_admin(array $panel, string $adminUsername): array
{
    $token = pg_get_token($panel);
    $result = pg_request($panel, 'GET', '/api/users?admin=' . urlencode($adminUsername) . '&limit=1000', [], $token);
    return $result['users'] ?? [];
}

/** Sum of used_traffic (bytes) across all of a reseller's users on the panel. */
function pg_total_usage_bytes(array $panel, string $adminUsername): int
{
    $total = 0;
    foreach (pg_list_users_by_admin($panel, $adminUsername) as $user) {
        $total += (int) ($user['used_traffic'] ?? 0);
    }
    return $total;
}

/** Enable or disable every user belonging to a reseller in one pass. */
function pg_set_users_status_by_admin(array $panel, string $adminUsername, bool $active): void
{
    $token = pg_get_token($panel);
    foreach (pg_list_users_by_admin($panel, $adminUsername) as $user) {
        try {
            pg_request($panel, 'PUT', '/api/user/' . urlencode($user['username']), ['status' => $active ? 'active' : 'disabled'], $token);
        } catch (Exception $e) {
            error_log('Bulk status update failed for ' . $user['username'] . ': ' . $e->getMessage());
        }
    }
}

/** Deletes every user belonging to a reseller (used when an agency is deleted). */
function pg_remove_users_by_admin(array $panel, string $adminUsername): void
{
    $token = pg_get_token($panel);
    foreach (pg_list_users_by_admin($panel, $adminUsername) as $user) {
        try {
            pg_request($panel, 'DELETE', '/api/user/' . urlencode($user['username']), [], $token);
        } catch (Exception $e) {
            error_log('Bulk user delete failed for ' . $user['username'] . ': ' . $e->getMessage());
        }
    }
}
