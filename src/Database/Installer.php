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
        self::migrateSeasonHistory();

        update_option('e2n_version', E2N_VERSION);
        update_option('e2n_db_version', E2N_DB_VERSION);
    }

    public static function deactivate(): void
    {
        // On ne supprime pas les données à la désactivation.
    }

    /**
     * Applique automatiquement les migrations dbDelta lors d'une mise à jour
     * du plugin, sans imposer une désactivation/réactivation manuelle.
     */
    public static function maybeUpgrade(): void
    {
        $installedDbVersion = (string) get_option('e2n_db_version', '');
        $installedVersion = (string) get_option('e2n_version', '');

        if ($installedDbVersion !== E2N_DB_VERSION) {
            self::createTables();
            self::migrateSeasonHistory();
            update_option('e2n_db_version', E2N_DB_VERSION);
        }

        if ($installedVersion !== E2N_VERSION) {
            update_option('e2n_version', E2N_VERSION);
        }
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
            responsible_phone VARCHAR(100) NULL,
            licence_number VARCHAR(50) NULL,
            registration_date DATE NULL,
            medical_note TEXT NULL,
            parent_message TEXT NULL,
            parent_access_code_hash CHAR(64) NULL,
            parent_access_enabled TINYINT(1) NOT NULL DEFAULT 0,
            parent_access_created_at DATETIME NULL,
            parent_access_last_used_at DATETIME NULL,
            parent_access_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            parent_access_distributed_at DATETIME NULL,
            parent_access_distribution_method VARCHAR(30) NULL,
            parent_access_distributed_to VARCHAR(150) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY parent_access_code_hash (parent_access_code_hash),
            KEY group_id (group_id),
            KEY last_name (last_name),
            KEY parent_access_enabled (parent_access_enabled),
            KEY parent_access_distributed_at (parent_access_distributed_at),
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
            season_id bigint(20) unsigned NOT NULL DEFAULT 0,
            skill_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'not_observed',
            evaluated_at datetime NULL,
            evaluated_by bigint(20) unsigned NULL,
            notes text NULL,
            created_at datetime NOT NULL,
            updated_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY swimmer_season_skill (swimmer_id,season_id,skill_id),
            KEY swimmer_id (swimmer_id),
            KEY season_id (season_id),
            KEY skill_id (skill_id),
            KEY status (status)
        ) {$charsetCollate};";

        dbDelta($sql);


        $tableName = Config::table('season_skills');

        $sql = "CREATE TABLE {$tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            season_id bigint(20) unsigned NOT NULL,
            skill_id bigint(20) unsigned NOT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY season_skill (season_id,skill_id),
            KEY season_id (season_id),
            KEY skill_id (skill_id),
            KEY is_active (is_active)
        ) {$charsetCollate};";

        dbDelta($sql);

        $tableName = Config::table('swimmer_group_memberships');

        $sql = "CREATE TABLE {$tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            swimmer_id bigint(20) unsigned NOT NULL,
            season_id bigint(20) unsigned NOT NULL,
            group_id bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY swimmer_season (swimmer_id,season_id),
            KEY swimmer_id (swimmer_id),
            KEY season_id (season_id),
            KEY group_id (group_id)
        ) {$charsetCollate};";

        dbDelta($sql);

        $tableName = Config::table('parent_access_logs');

        $sql = "CREATE TABLE {$tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            swimmer_id bigint(20) unsigned NULL,
            success tinyint(1) NOT NULL DEFAULT 0,
            ip_hash char(64) NOT NULL,
            attempted_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY swimmer_id (swimmer_id),
            KEY success (success),
            KEY attempted_at (attempted_at)
        ) {$charsetCollate};";

        dbDelta($sql);
        $tableName = Config::table('synchronization_logs');

        $sql = "CREATE TABLE {$tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            filename varchar(255) NOT NULL,
            status varchar(20) NOT NULL,
            summary longtext NULL,
            errors longtext NULL,
            created_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charsetCollate};";

        dbDelta($sql);
    }

    /**
     * Rattache les données historiques existantes à une saison lors du passage
     * depuis les versions antérieures à 0.9.0. Cette migration est idempotente.
     */
    private static function migrateSeasonHistory(): void
    {
        global $wpdb;

        $levels = Config::table('swimmer_skill_levels');
        $swimmers = Config::table('swimmers');
        $groups = Config::table('groups');
        $seasons = Config::table('seasons');
        $domains = Config::table('skill_domains');
        $skills = Config::table('skills');
        $seasonSkills = Config::table('season_skills');
        $memberships = Config::table('swimmer_group_memberships');

        $currentSeasonId = (int) $wpdb->get_var(
            "SELECT id FROM {$seasons} WHERE is_current = 1 ORDER BY id DESC LIMIT 1"
        );

        $wpdb->query(
            "UPDATE {$levels} levels
             INNER JOIN {$swimmers} swimmers ON swimmers.id = levels.swimmer_id
             LEFT JOIN {$groups} groups ON groups.id = swimmers.group_id
             SET levels.season_id = COALESCE(groups.season_id, {$currentSeasonId})
             WHERE levels.season_id = 0"
        );

        $oldIndex = $wpdb->get_var(
            "SELECT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = '" . esc_sql($levels) . "'
             AND INDEX_NAME = 'swimmer_skill' LIMIT 1"
        );
        if ($oldIndex !== null) {
            $wpdb->query("ALTER TABLE {$levels} DROP INDEX swimmer_skill");
        }

        $newIndex = $wpdb->get_var(
            "SELECT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = '" . esc_sql($levels) . "'
             AND INDEX_NAME = 'swimmer_season_skill' LIMIT 1"
        );
        if ($newIndex === null) {
            $wpdb->query(
                "ALTER TABLE {$levels}
                 ADD UNIQUE KEY swimmer_season_skill (swimmer_id, season_id, skill_id)"
            );
        }

        $wpdb->query(
            "INSERT IGNORE INTO {$memberships} (swimmer_id, season_id, group_id, created_at)
             SELECT swimmers.id, groups.season_id, groups.id, NOW()
             FROM {$swimmers} swimmers
             INNER JOIN {$groups} groups ON groups.id = swimmers.group_id
             WHERE swimmers.group_id IS NOT NULL"
        );

        $wpdb->query(
            "INSERT IGNORE INTO {$seasonSkills} (season_id, skill_id, is_active, created_at)
             SELECT DISTINCT groups.season_id, skills.id, 1, NOW()
             FROM {$groups} groups
             INNER JOIN {$domains} domains ON domains.category_id = groups.category_id
             INNER JOIN {$skills} skills ON skills.domain_id = domains.id"
        );
    }

}