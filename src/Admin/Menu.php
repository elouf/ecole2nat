<?php

namespace Ecole2Nat\Admin;

use Ecole2Nat\Admin\Pages\SeasonPage;
use Ecole2Nat\Admin\Pages\CategoryPage;
use Ecole2Nat\Admin\Pages\ReferencePage;
use Ecole2Nat\Admin\Pages\GroupPage;
use Ecole2Nat\Admin\Pages\SwimmerPage;
use Ecole2Nat\Admin\Pages\SessionListPage;
use Ecole2Nat\Admin\Pages\SessionPage;
use Ecole2Nat\Admin\Pages\ExerciseListPage;
use Ecole2Nat\Admin\Pages\ExercisePage;

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
            __('Groupes', 'ecole2nat'),
            __('Groupes', 'ecole2nat'),
            'manage_options',
            'ecole2nat-groups',
            [new GroupPage(), 'render']
        );

        add_submenu_page(
            'ecole2nat',
            __('Nageurs', 'ecole2nat'),
            __('Nageurs', 'ecole2nat'),
            'manage_options',
            'ecole2nat-swimmers',
            [new SwimmerPage(), 'render']
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

        $referencePage = new ReferencePage();

        add_submenu_page(
            'ecole2nat',
            __('Référentiel pédagogique', 'ecole2nat'),
            __('Référentiel pédagogique', 'ecole2nat'),
            'manage_options',
            'ecole2nat-reference',
            [$referencePage, 'render']
        );

        add_submenu_page(
            'ecole2nat',
            __('Séances', 'ecole2nat'),
            __('Séances', 'ecole2nat'),
            'manage_options',
            'ecole2nat-sessions',
            [new SessionListPage(), 'render']
        );

        $sessionPage = new SessionPage();

        add_submenu_page(
            'ecole2nat',
            __('Modifier une séance', 'ecole2nat'),
            __('Éditeur de séance', 'ecole2nat'),
            'manage_options',
            'ecole2nat-session',
            [$sessionPage, 'render']
        );

        add_submenu_page(
            'ecole2nat',
            __('Bibliothèque d’exercices', 'ecole2nat'),
            __('Bibliothèque d’exercices', 'ecole2nat'),
            'manage_options',
            'ecole2nat-exercises',
            [new ExerciseListPage(), 'render']
        );

        add_submenu_page(
            'ecole2nat',
            __('Exercice', 'ecole2nat'),
            __('Éditeur d’exercice', 'ecole2nat'),
            'manage_options',
            'ecole2nat-exercise',
            [new ExercisePage(), 'render']
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