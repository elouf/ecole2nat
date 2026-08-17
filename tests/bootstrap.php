<?php

declare(strict_types=1);

define('ABSPATH', dirname(__DIR__) . '/');

$GLOBALS['e2n_test_caps'] = [];
$GLOBALS['e2n_test_user_id'] = 10;
$GLOBALS['e2n_test_today'] = '2026-08-17';

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

require dirname(__DIR__) . '/vendor/autoload.php';
