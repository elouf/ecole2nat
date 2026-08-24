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
        $healthAlertMigrated = self::migrateHealthAlert();
        self::migrateSeasonHistory();
        self::ensureCoachRoleAndPage();
        $sessionExercisesMigrated = self::allowRepeatedSessionExercises();

        update_option('e2n_version', E2N_VERSION);
        if ($sessionExercisesMigrated && $healthAlertMigrated) {
            update_option('e2n_db_version', E2N_DB_VERSION);
        }
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
            $healthAlertMigrated = self::migrateHealthAlert();
            self::migrateSeasonHistory();
            self::ensureCoachRoleAndPage();
            if (self::allowRepeatedSessionExercises() && $healthAlertMigrated) {
                update_option('e2n_db_version', E2N_DB_VERSION);
            }
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
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY is_current (is_current),
            KEY is_active (is_active)
        ) {$charsetCollate};";

        dbDelta($sql);

        $tableName = Config::table('competitions');
        $sql = "CREATE TABLE {$tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            season_id bigint(20) unsigned NOT NULL,
            code varchar(100) NOT NULL,
            name varchar(180) NOT NULL,
            start_date date NOT NULL,
            end_date date NULL,
            location varchar(180) NULL,
            pool_length varchar(3) NULL,
            registration_opens_at datetime NOT NULL,
            registration_closes_at datetime NOT NULL,
            technical_document_url text NULL,
            program_url text NULL,
            carpool_url text NULL,
            liveffn_url text NULL,
            photo_album_url text NULL,
            information text NULL,
            target_all tinyint(1) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'published',
            started_at datetime NULL,
            started_by bigint(20) unsigned NULL,
            start_forced tinyint(1) NOT NULL DEFAULT 0,
            closed_at datetime NULL,
            closed_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY season_code (season_id,code),
            KEY start_date (start_date),
            KEY status (status)
        ) {$charsetCollate};";
        dbDelta($sql);

        $tableName = Config::table('competition_participants');
        $sql = "CREATE TABLE {$tableName} (
            competition_id bigint(20) unsigned NOT NULL,
            swimmer_id bigint(20) unsigned NOT NULL,
            added_manually tinyint(1) NOT NULL DEFAULT 0,
            added_at datetime NOT NULL,
            added_by bigint(20) unsigned NULL,
            PRIMARY KEY  (competition_id,swimmer_id),
            KEY swimmer_id (swimmer_id)
        ) {$charsetCollate};";
        dbDelta($sql);

        $tableName = Config::table('competition_performances');
        $sql = "CREATE TABLE {$tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            competition_id bigint(20) unsigned NOT NULL,
            swimmer_id bigint(20) unsigned NOT NULL,
            series_key varchar(36) NULL,
            event_code varchar(20) NOT NULL,
            elapsed_time varchar(30) NULL,
            comment text NULL,
            is_disqualified tinyint(1) NOT NULL DEFAULT 0,
            time_rating tinyint(1) NULL,
            created_by bigint(20) unsigned NOT NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NULL,
            PRIMARY KEY  (id),
            KEY competition_swimmer (competition_id,swimmer_id),
            KEY series_key (series_key),
            KEY event_code (event_code)
        ) {$charsetCollate};";
        dbDelta($sql);

        $tableName = Config::table('training_performances');
        $sql = "CREATE TABLE {$tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            group_id bigint(20) unsigned NOT NULL,
            season_id bigint(20) unsigned NOT NULL,
            swimmer_id bigint(20) unsigned NOT NULL,
            series_key varchar(36) NULL,
            event_code varchar(20) NOT NULL,
            elapsed_time varchar(30) NOT NULL,
            comment text NULL,
            is_disqualified tinyint(1) NOT NULL DEFAULT 0,
            time_rating tinyint(1) NULL,
            created_by bigint(20) unsigned NOT NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NULL,
            PRIMARY KEY  (id),
            KEY swimmer_date (swimmer_id,created_at),
            KEY group_date (group_id,created_at),
            KEY series_key (series_key),
            KEY season_id (season_id),
            KEY event_code (event_code)
        ) {$charsetCollate};";
        dbDelta($sql);

        $tableName = Config::table('competition_target_categories');
        $sql = "CREATE TABLE {$tableName} (
            competition_id bigint(20) unsigned NOT NULL,
            category_name varchar(100) NOT NULL,
            category_key varchar(100) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (competition_id,category_key),
            KEY category_key (category_key)
        ) {$charsetCollate};";
        dbDelta($sql);

        $tableName = Config::table('swimmer_competition_category_states');
        $sql = "CREATE TABLE {$tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            swimmer_id bigint(20) unsigned NOT NULL,
            effective_from date NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY swimmer_effective_from (swimmer_id,effective_from),
            KEY effective_from (effective_from)
        ) {$charsetCollate};";
        dbDelta($sql);

        $tableName = Config::table('swimmer_competition_state_categories');
        $sql = "CREATE TABLE {$tableName} (
            state_id bigint(20) unsigned NOT NULL,
            category_name varchar(100) NOT NULL,
            category_key varchar(100) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (state_id,category_key),
            KEY category_key (category_key)
        ) {$charsetCollate};";
        dbDelta($sql);

        $tableName = Config::table('competition_registrations');
        $sql = "CREATE TABLE {$tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            competition_id bigint(20) unsigned NOT NULL,
            swimmer_id bigint(20) unsigned NOT NULL,
            response varchar(10) NOT NULL,
            comment text NULL,
            response_source varchar(20) NOT NULL DEFAULT 'parent',
            responded_at datetime NOT NULL,
            responded_by bigint(20) unsigned NULL,
            parents_official tinyint(1) NULL,
            attendance_days varchar(20) NULL,
            is_engaged tinyint(1) NOT NULL DEFAULT 0,
            engaged_at datetime NULL,
            engaged_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY competition_swimmer (competition_id,swimmer_id),
            KEY swimmer_id (swimmer_id),
            KEY response (response),
            KEY is_engaged (is_engaged)
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

        $tableName = Config::table('group_coaches');

        $sql = "CREATE TABLE {$tableName} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY group_user (group_id,user_id),
            KEY group_id (group_id),
            KEY user_id (user_id)
        ) {$charsetCollate};";
        dbDelta($sql);

        $tableName = Config::table('group_substitutions');

        $sql = "CREATE TABLE {$tableName} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            substitution_date DATE NOT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY group_user_date (group_id,user_id,substitution_date),
            KEY group_id (group_id),
            KEY user_id (user_id),
            KEY substitution_date (substitution_date)
        ) {$charsetCollate};";
        dbDelta($sql);

        $tableName = Config::table('scheduled_sessions');
        $sql = "CREATE TABLE {$tableName} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id BIGINT UNSIGNED NOT NULL,
            session_id BIGINT UNSIGNED NOT NULL,
            session_date DATE NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'planned',
            created_by BIGINT UNSIGNED NULL,
            completed_by BIGINT UNSIGNED NULL,
            completed_at DATETIME NULL,
            coach_editable_copy TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY group_date (group_id,session_date),
            KEY group_id (group_id),
            KEY session_id (session_id),
            KEY session_date (session_date),
            KEY status (status)
        ) {$charsetCollate};";
        dbDelta($sql);

        $tableName = Config::table('attendance');
        $sql = "CREATE TABLE {$tableName} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id BIGINT UNSIGNED NOT NULL,
            swimmer_id BIGINT UNSIGNED NOT NULL,
            session_date DATE NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'present',
            recorded_by BIGINT UNSIGNED NULL,
            recorded_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY group_swimmer_date (group_id,swimmer_id,session_date),
            KEY group_id (group_id),
            KEY swimmer_id (swimmer_id),
            KEY session_date (session_date),
            KEY status (status)
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
            health_alert TINYINT(1) NOT NULL DEFAULT 0,
            image_rights TINYINT(1) NULL,
            parent_message TEXT NULL,
            parent_access_code_hash CHAR(64) NULL,
            parent_access_code_generation BIGINT UNSIGNED NOT NULL DEFAULT 0,
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
            is_library tinyint(1) NOT NULL DEFAULT 1,
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

        $tableName = Config::table('skill_level_history');

        $sql = "CREATE TABLE {$tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            swimmer_id bigint(20) unsigned NOT NULL,
            season_id bigint(20) unsigned NOT NULL,
            skill_id bigint(20) unsigned NOT NULL,
            previous_status varchar(20) NOT NULL DEFAULT 'not_observed',
            status varchar(20) NOT NULL,
            changed_at datetime NOT NULL,
            changed_by bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (id),
            KEY swimmer_season (swimmer_id,season_id),
            KEY swimmer_skill (swimmer_id,skill_id),
            KEY changed_at (changed_at),
            KEY changed_by (changed_by)
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

    private static function allowRepeatedSessionExercises(): bool
    {
        global $wpdb;

        $table = Config::table('session_exercises');
        $legacyIndex = $wpdb->get_var("SHOW INDEX FROM {$table} WHERE Key_name = 'part_exercise'");
        if ($legacyIndex === null) {
            return true;
        }

        return $wpdb->query("ALTER TABLE {$table} DROP INDEX part_exercise") !== false;
    }

    /**
     * Convertit l'ancienne note médicale en simple indicateur puis supprime le
     * texte sensible. La colonne booléenne est créée auparavant par dbDelta().
     */
    private static function migrateHealthAlert(): bool
    {
        global $wpdb;

        $table = Config::table('swimmers');
        $legacyColumn = $wpdb->get_var(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = '" . esc_sql($table) . "'
             AND COLUMN_NAME = 'medical_note' LIMIT 1"
        );
        if ($legacyColumn === null) {
            return true;
        }

        if ($wpdb->query("UPDATE {$table} SET health_alert = 1 WHERE medical_note IS NOT NULL AND TRIM(medical_note) <> ''") === false) {
            return false;
        }

        return $wpdb->query("ALTER TABLE {$table} DROP COLUMN medical_note") !== false;
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
            "UPDATE {$levels} lvl
             INNER JOIN {$swimmers} sw ON sw.id = lvl.swimmer_id
             LEFT JOIN {$groups} grp ON grp.id = sw.group_id
             SET lvl.season_id = COALESCE(grp.season_id, {$currentSeasonId})
             WHERE lvl.season_id = 0"
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
             SELECT sw.id, grp.season_id, grp.id, NOW()
             FROM {$swimmers} sw
             INNER JOIN {$groups} grp ON grp.id = sw.group_id
             WHERE sw.group_id IS NOT NULL"
        );

        $wpdb->query(
            "INSERT IGNORE INTO {$seasonSkills} (season_id, skill_id, is_active, created_at)
             SELECT DISTINCT grp.season_id, skill.id, 1, NOW()
             FROM {$groups} grp
             INNER JOIN {$domains} domain ON domain.category_id = grp.category_id
             INNER JOIN {$skills} skill ON skill.domain_id = domain.id"
        );
    }


    private static function ensureCoachRoleAndPage(): void
    {
        $role = get_role('e2n_coach');
        if ($role === null) {
            $role = add_role('e2n_coach', __('Coach Ecole2Nat', 'ecole2nat'), ['read' => true, 'e2n_coach_access' => true]);
        } elseif (!$role->has_cap('e2n_coach_access')) {
            $role->add_cap('e2n_coach_access');
        }

        $pageId = (int) get_option('e2n_coach_page_id', 0);
        if ($pageId <= 0 || get_post_status($pageId) === false) {
            $existing = get_page_by_path('espace-coach');
            if ($existing instanceof \WP_Post) {
                $pageId = (int) $existing->ID;
            } else {
                $pageId = (int) wp_insert_post([
                    'post_title' => __('Espace coach', 'ecole2nat'),
                    'post_name' => 'espace-coach',
                    'post_content' => '[e2n_coach_portal]',
                    'post_status' => 'publish',
                    'post_type' => 'page',
                ]);
            }
            if ($pageId > 0) update_option('e2n_coach_page_id', $pageId);
        }
    }

}
