<?php

namespace Ecole2Nat\Admin;

use Ecole2Nat\Admin\Pages\SeasonPage;
use Ecole2Nat\Admin\Pages\CategoryPage;

if (!defined('ABSPATH')) {
    exit;
}

class Menu
{
    public function register(): void
    {
        add_menu_page(
            "Ecole2Nat'",
            "Ecole2Nat'",
            'manage_options',
            'ecole2nat',
            [$this, 'renderDashboard'],
            'dashicons-swimming',
            26
        );

        add_submenu_page(
            'ecole2nat',
            'Tableau de bord',
            'Tableau de bord',
            'manage_options',
            'ecole2nat',
            [$this, 'renderDashboard']
        );

        $seasonPage = new SeasonPage();

        add_submenu_page(
            'ecole2nat',
            'Saisons',
            'Saisons',
            'manage_options',
            'ecole2nat-seasons',
            [$seasonPage, 'render']
        );

        $categoryPage = new CategoryPage();

        add_submenu_page(
            'ecole2nat',
            'Catégories',
            'Catégories',
            'manage_options',
            'ecole2nat-categories',
            [$categoryPage, 'render']
        );
    }

    public function renderDashboard(): void
    {
        echo '<div class="wrap">';
        echo '<h1>Ecole2Nat\'</h1>';
        echo '<p>Bienvenue dans le tableau de bord.</p>';
        echo '</div>';
    }
}