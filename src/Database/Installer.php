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
            PRIMARY KEY  (id),
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
            PRIMARY KEY  (id),
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
            PRIMARY KEY  (id),
            KEY domain_id (domain_id),
            KEY sort_order (sort_order),
            KEY is_active (is_active)
        ) {$charsetCollate};";

        dbDelta($sql);

        $tableName = Config::table('exercises');

        $sql = "CREATE TABLE {$tableName} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            skill_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(150) NOT NULL,
            description TEXT NULL,
            objectives TEXT NULL,
            coach_notes TEXT NULL,
            equipment VARCHAR(255) NULL,
            difficulty TINYINT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY skill_id (skill_id),
            KEY difficulty (difficulty)
        ) {$charsetCollate};";

        dbDelta($sql);

        $tableName = Config::table('groups');

        $sql = "CREATE TABLE {$tableName} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            season_id BIGINT UNSIGNED NOT NULL,
            category_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(150) NOT NULL,
            color VARCHAR(20) NULL,
            weekday TINYINT UNSIGNED NULL,
            start_time TIME NULL,
            end_time TIME NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY season_id (season_id),
            KEY category_id (category_id),
            KEY weekday (weekday),
            KEY is_active (is_active)
        ) {$charsetCollate};";

        dbDelta($sql);

        $tableName = Config::table('swimmers');

        $sql = "CREATE TABLE {$tableName} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id BIGINT UNSIGNED NULL,
            last_name VARCHAR(100) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            birth_date DATE NULL,
            gender CHAR(1) NULL,
            responsible_name VARCHAR(150) NULL,
            responsible_email VARCHAR(150) NULL,
            responsible_phone VARCHAR(30) NULL,
            licence_number VARCHAR(50) NULL,
            registration_date DATE NULL,
            medical_note TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY group_id (group_id),
            KEY last_name (last_name),
            KEY is_active (is_active)

        ) {$charsetCollate};";

        dbDelta($sql);

        $tableName = Config::table('sessions');

        $sql = "CREATE TABLE {$tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            category_id bigint(20) unsigned NOT NULL,
            name varchar(150) NOT NULL,
            objectives text NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NULL,
            PRIMARY KEY  (id),
            KEY category_id (category_id),
            KEY is_active (is_active)
        ) {$charsetCollate};";

        dbDelta($sql);

        $tableName = Config::table('session_parts');

        $sql = "CREATE TABLE {$tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id bigint(20) unsigned NOT NULL,
            title varchar(150) NOT NULL,
            position int(11) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NULL,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY position (position)
        ) {$charsetCollate};";

        dbDelta($sql);

        $tableName = Config::table('session_exercises');

        $sql = "CREATE TABLE {$tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            part_id bigint(20) unsigned NOT NULL,
            exercise_id bigint(20) unsigned NOT NULL,
            position int(11) unsigned NOT NULL DEFAULT 0,
            duration int(11) unsigned NULL,
            coach_notes text NULL,
            created_at datetime NOT NULL,
            updated_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY part_exercise (part_id,exercise_id),
            KEY part_id (part_id),
            KEY exercise_id (exercise_id),
            KEY position (position)
        ) {$charsetCollate};";

        dbDelta($sql);

        $tableName = Config::table('swimmer_skill_levels');

        $sql = "CREATE TABLE {$tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            swimmer_id bigint(20) unsigned NOT NULL,
            skill_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'not_observed',
            evaluated_at datetime NULL,
            evaluated_by bigint(20) unsigned NULL,
            notes text NULL,
            created_at datetime NOT NULL,
            updated_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY swimmer_skill (swimmer_id,skill_id),
            KEY swimmer_id (swimmer_id),
            KEY skill_id (skill_id),
            KEY status (status)
        ) {$charsetCollate};";

        dbDelta($sql);
    }
}