<?php

declare(strict_types=1);

define('ABSPATH', dirname(__DIR__) . '/');

$GLOBALS['e2n_test_caps'] = [];
$GLOBALS['e2n_test_user_id'] = 10;
$GLOBALS['e2n_test_today'] = '2026-08-17';
$GLOBALS['e2n_test_options'] = [];

function current_user_can(string $capability): bool
{
    return in_array($capability, $GLOBALS['e2n_test_caps'], true);
}

function get_current_user_id(): int
{
    return (int) $GLOBALS['e2n_test_user_id'];
}

function current_time(string $type): string
{
    return $type === 'Y-m-d' ? $GLOBALS['e2n_test_today'] : $GLOBALS['e2n_test_today'] . ' 12:00:00';
}

function __(string $text, ?string $domain = null): string
{
    return $text;
}

function wp_timezone(): DateTimeZone
{
    return new DateTimeZone('Europe/Paris');
}

function remove_accents(string $text): string
{
    return strtr($text, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'ù' => 'u', 'î' => 'i', 'ô' => 'o', 'ç' => 'c']);
}

function sanitize_email(string $email): string
{
    return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false ? trim($email) : '';
}

function is_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function sanitize_text_field(string $value): string
{
    return trim(strip_tags($value));
}

function sanitize_key(string $value): string
{
    return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower($value));
}

function absint($value): int
{
    return abs((int) $value);
}

function sanitize_textarea_field(string $value): string
{
    return trim(strip_tags($value));
}

function esc_url_raw(string $url): string
{
    return filter_var(trim($url), FILTER_VALIDATE_URL) !== false ? trim($url) : '';
}

function get_option(string $name, mixed $default = false): mixed
{
    return $GLOBALS['e2n_test_options'][$name] ?? $default;
}

function update_option(string $name, mixed $value): bool
{
    $GLOBALS['e2n_test_options'][$name] = $value;
    return true;
}

function wp_salt(string $scheme = 'auth'): string
{
    return 'e2n-test-salt-' . $scheme;
}

require dirname(__DIR__) . '/vendor/autoload.php';
