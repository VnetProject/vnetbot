<?php
// Small helpers that turn raw numbers into friendly Persian-readable text.

function fmt_gb(float $gb): string
{
    if ($gb <= 0) {
        return 'نامحدود';
    }
    return rtrim(rtrim(number_format($gb, 2, '.', ''), '0'), '.') . ' گیگابایت';
}

function fmt_bytes(int|float $bytes): string
{
    $bytes = max(0, (float) $bytes);
    if ($bytes >= 1024 ** 3) {
        return rtrim(rtrim(number_format($bytes / 1024 ** 3, 2, '.', ''), '0'), '.') . ' گیگابایت';
    }
    if ($bytes >= 1024 ** 2) {
        return rtrim(rtrim(number_format($bytes / 1024 ** 2, 1, '.', ''), '0'), '.') . ' مگابایت';
    }
    return number_format($bytes / 1024, 0) . ' کیلوبایت';
}

function fmt_days(int $days): string
{
    return $days > 0 ? "$days روز" : 'نامحدود';
}

function fmt_price(int $toman): string
{
    return $toman > 0 ? number_format($toman) . ' تومان' : 'رایگان';
}

/** Panel "expire" can be a unix timestamp, an ISO date string, 0 or null. */
function parse_expire(mixed $value): ?int
{
    if ($value === null || $value === '' || $value === 0 || $value === '0') {
        return null;
    }
    if (is_numeric($value)) {
        return (int) $value;
    }
    $ts = strtotime((string) $value);
    return $ts ?: null;
}

function fmt_time_left(?int $expireTs): string
{
    if ($expireTs === null) {
        return 'نامحدود';
    }
    $left = $expireTs - time();
    if ($left <= 0) {
        return 'منقضی شده';
    }
    $days = intdiv($left, 86400);
    $hours = intdiv($left % 86400, 3600);
    if ($days > 0) {
        return "$days روز و $hours ساعت";
    }
    return $hours > 0 ? "$hours ساعت" : 'کمتر از یک ساعت';
}

function fmt_duration_seconds(int $seconds): string
{
    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $parts = [];
    if ($days) {
        $parts[] = "$days روز";
    }
    if ($hours) {
        $parts[] = "$hours ساعت";
    }
    if (!$days && $minutes) {
        $parts[] = "$minutes دقیقه";
    }
    return $parts ? implode(' و ', $parts) : 'چند لحظه';
}

function progress_bar(float $percent, int $width = 12): string
{
    $percent = max(0, min(100, $percent));
    $filled = (int) round($width * $percent / 100);
    return str_repeat('🟦', $filled) . str_repeat('⬜️', $width - $filled) . ' ' . round($percent) . '%';
}
