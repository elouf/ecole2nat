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

function get_option(string $name, mixed $default = false): mixed
{
    return $GLOBALS['e2n_test_options'][$name] ?? $default;
}

function update_option(string $name, mixed $value): bool
{
    $GLOBALS['e2n_test_options'][$name] = $value;
    return true;
}

require dirname(__DIR__) . '/vendor/autoload.php';
