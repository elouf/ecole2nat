<?php

namespace Ecole2Nat\Database;

use Ecole2Nat\Support\Config;

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
        $tableName = Config::table('seasons');

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

        $tableName = Config::table('categories');

        $sql = "CREATE TABLE {$tableName} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY sort_order (sort_order),
            KEY is_active (is_active)
        ) {$charsetCollate};";

        dbDelta($sql);

        $tableName = Config::table('skill_domains');

        $sql = "CREATE TABLE {$tableName} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            category_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(150) NOT NULL,
            description TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY category_id (category_id),
            KEY sort_order (sort_order),
            KEY is_active (is_active)
        ) {$charsetCollate};";

        dbDelta($sql);

        $tableName = Config::table('skills');

        $sql = "CREATE TABLE {$tableName} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            domain_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(150) NOT NULL,
            description TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY domain_id (domain_id),
            KEY sort_order (sort_order),
            KEY is_active (is_active)
        ) {$charsetCollate};";

        dbDelta($sql);
    }
}