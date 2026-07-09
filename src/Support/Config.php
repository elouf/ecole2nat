<?php

namespace Ecole2Nat\Support;

if (!defined('ABSPATH')) {
    exit;
}

class Config
{
    public static function version(): string
    {
        return E2N_VERSION;
    }

    public static function dbVersion(): string
    {
        return E2N_DB_VERSION;
    }

    public static function pluginPath(): string
    {
        return E2N_PLUGIN_PATH;
    }

    public static function pluginUrl(): string
    {
        return E2N_PLUGIN_URL;
    }

    public static function table(string $table): string
    {
        global $wpdb;

        return $wpdb->prefix . 'e2n_' . $table;
    }

    public static function option(string $option): string
    {
        return 'e2n_' . $option;
    }
}