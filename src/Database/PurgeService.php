<?php

namespace Ecole2Nat\Database;

if (!defined('ABSPATH')) {
    exit;
}

final class PurgeService
{
    /**
     * Retourne les tables appartenant à Ecole2Nat'.
     *
     * @return string[]
     */
    public function tables(): array
    {
        global $wpdb;

        $prefix = $wpdb->prefix . 'e2n_';
        $like = $wpdb->esc_like($prefix) . '%';

        $tables = $wpdb->get_col(
            $wpdb->prepare('SHOW TABLES LIKE %s', $like)
        );

        if (!is_array($tables)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map('strval', $tables),
                static fn(string $table): bool => str_starts_with($table, $prefix)
            )
        );
    }

    /**
     * Compte les lignes présentes dans chaque table Ecole2Nat'.
     *
     * @return array<string,int>
     */
    public function counts(): array
    {
        global $wpdb;

        $counts = [];

        foreach ($this->tables() as $table) {
            $quotedTable = $this->quoteIdentifier($table);
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$quotedTable}");
            $counts[$table] = $count === null ? 0 : (int) $count;
        }

        return $counts;
    }

    /**
     * Supprime toutes les données Ecole2Nat' sans supprimer les tables.
     *
     * @return array{success:bool,message:string,purged_tables:int,purged_rows:int,errors:string[]}
     */
    public function purge(): array
    {
        global $wpdb;

        $tables = $this->tables();
        $counts = $this->counts();
        $errors = [];

        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ($tables as $table) {
                $quotedTable = $this->quoteIdentifier($table);
                $result = $wpdb->query("TRUNCATE TABLE {$quotedTable}");

                if ($result === false) {
                    $errors[] = sprintf(
                        '%s : %s',
                        $table,
                        $wpdb->last_error !== '' ? $wpdb->last_error : 'erreur SQL inconnue'
                    );
                }
            }
        } finally {
            $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
        }

        $this->clearPluginTransients();
        $this->clearSynchronizationFiles();

        $purgedRows = array_sum($counts);

        return [
            'success' => $errors === [],
            'message' => $errors === [] ? 'purged' : 'purge_error',
            'purged_tables' => count($tables),
            'purged_rows' => $purgedRows,
            'errors' => $errors,
        ];
    }

    private function clearPluginTransients(): void
    {
        global $wpdb;

        $prefixes = [
            '_transient_e2n_',
            '_transient_timeout_e2n_',
        ];

        foreach ($prefixes as $prefix) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $wpdb->esc_like($prefix) . '%'
                )
            );
        }
    }

    private function clearSynchronizationFiles(): void
    {
        $uploads = wp_upload_dir();

        if (!empty($uploads['error']) || empty($uploads['basedir'])) {
            return;
        }

        $directory = trailingslashit($uploads['basedir']) . 'ecole2nat-sync';

        if (!is_dir($directory)) {
            return;
        }

        $files = glob($directory . '/*');

        if (!is_array($files)) {
            return;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
