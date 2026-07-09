<?php

namespace Ecole2Nat\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin
{
    public function boot(): void
    {
        if (is_admin()) {
            add_action('admin_menu', [$this, 'registerAdminMenu']);
        }
    }

    public function registerAdminMenu(): void
    {
        add_menu_page(
            "Ecole2Nat'",
            "Ecole2Nat'",
            'manage_options',
            'ecole2nat',
            [$this, 'renderAdminPage'],
            'dashicons-swimming',
            26
        );
    }

    public function renderAdminPage(): void
    {
        echo '<div class="wrap">';
        echo '<h1>Ecole2Nat\'</h1>';
        echo '<p>Le plugin est bien chargé.</p>';
        echo '</div>';
    }
}