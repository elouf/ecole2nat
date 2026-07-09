<?php

namespace Ecole2Nat\Database;

if (!defined('ABSPATH')) {
    exit;
}

class Installer
{
    public static function activate(): void
    {
        self::createTables();

        update_option('e2n_version', E2N_VERSION);
        update_option('e2n_db_version', E2N_DB_VERSION);
    }

    public static function deactivate(): void
    {
        // On ne supprime pas les données à la désactivation.
    }

    private static function createTables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();
        $tableName = $wpdb->prefix . 'e2n_seasons';

        $sql = "CREATE TABLE {$tableName} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            start_date DATE NULL,
            end_date DATE NULL,
            is_current TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY is_current (is_current)
        ) {$charsetCollate};";

        dbDelta($sql);
    }
}