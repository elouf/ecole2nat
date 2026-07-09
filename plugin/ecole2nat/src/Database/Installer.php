<?php

namespace Ecole2Nat\Database;

if (!defined('ABSPATH')) {
    exit;
}

class Installer
{
    public static function activate(): void
    {
        update_option('e2n_version', E2N_VERSION);
        update_option('e2n_db_version', E2N_DB_VERSION);
    }

    public static function deactivate(): void
    {
        // Rien pour l’instant.
    }
}