<?php

namespace Ecole2Nat\Admin;

use Ecole2Nat\Admin\Pages\CategoryPage;
use Ecole2Nat\Admin\Pages\CoachPage;
use Ecole2Nat\Admin\Pages\ExerciseListPage;
use Ecole2Nat\Admin\Pages\ExercisePage;
use Ecole2Nat\Admin\Pages\EvaluationPage;
use Ecole2Nat\Admin\Pages\GroupPage;
use Ecole2Nat\Admin\Pages\MaintenancePage;
use Ecole2Nat\Admin\Pages\ParentAccessPage;
use Ecole2Nat\Admin\Pages\ParentDistributionPage;
use Ecole2Nat\Admin\Pages\ReferencePage;
use Ecole2Nat\Admin\Pages\SeasonPage;
use Ecole2Nat\Admin\Pages\SessionListPage;
use Ecole2Nat\Admin\Pages\SessionPage;
use Ecole2Nat\Admin\Pages\SessionPrintPage;
use Ecole2Nat\Admin\Pages\SwimmerPage;
use Ecole2Nat\Admin\Pages\SynchronizationPage;

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
            __('Coachs', 'ecole2nat'),
            __('Coachs', 'ecole2nat'),
            'manage_options',
            'ecole2nat-coaches',
            [new CoachPage(), 'render']
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

        add_submenu_page(
            'ecole2nat',
            __('Saisons', 'ecole2nat'),
            __('Saisons', 'ecole2nat'),
            'manage_options',
            'ecole2nat-seasons',
            [new SeasonPage(), 'render']
        );

        add_submenu_page(
            'ecole2nat',
            __('Catégories', 'ecole2nat'),
            __('Catégories', 'ecole2nat'),
            'manage_options',
            'ecole2nat-categories',
            [new CategoryPage(), 'render']
        );

        add_submenu_page(
            'ecole2nat',
            __('Référentiel pédagogique', 'ecole2nat'),
            __('Référentiel pédagogique', 'ecole2nat'),
            'manage_options',
            'ecole2nat-reference',
            [new ReferencePage(), 'render']
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
            __('Séances', 'ecole2nat'),
            __('Séances', 'ecole2nat'),
            'manage_options',
            'ecole2nat-sessions',
            [new SessionListPage(), 'render']
        );

        add_submenu_page(
            'ecole2nat',
            __('Évaluations', 'ecole2nat'),
            __('Évaluations', 'ecole2nat'),
            'manage_options',
            'ecole2nat-evaluations',
            [new EvaluationPage(), 'render']
        );

        add_submenu_page(
            'ecole2nat',
            __('Accès parents', 'ecole2nat'),
            __('Accès parents', 'ecole2nat'),
            'manage_options',
            'ecole2nat-parent-distribution',
            [new ParentDistributionPage(), 'render']
        );

        add_submenu_page(
            'ecole2nat',
            __('Synchronisation', 'ecole2nat'),
            __('Synchronisation', 'ecole2nat'),
            'manage_options',
            'ecole2nat-synchronization',
            [new SynchronizationPage(), 'render']
        );

        add_submenu_page(
            'ecole2nat',
            __('Maintenance', 'ecole2nat'),
            __('Maintenance', 'ecole2nat'),
            'manage_options',
            'ecole2nat-maintenance',
            [new MaintenancePage(), 'render']
        );

        // Pages internes : elles restent enregistrées pour les permissions,
        // puis sont seulement masquées visuellement via admin_head.
        add_submenu_page(
            'ecole2nat',
            __('Éditeur de séance', 'ecole2nat'),
            __('Éditeur de séance', 'ecole2nat'),
            'manage_options',
            'ecole2nat-session',
            [new SessionPage(), 'render']
        );

        add_submenu_page(
            'ecole2nat',
            __('Impression de séance', 'ecole2nat'),
            __('Impression de séance', 'ecole2nat'),
            'manage_options',
            'ecole2nat-session-print',
            [new SessionPrintPage(), 'render']
        );

        add_submenu_page(
            'ecole2nat',
            __('Éditeur d’exercice', 'ecole2nat'),
            __('Éditeur d’exercice', 'ecole2nat'),
            'manage_options',
            'ecole2nat-exercise',
            [new ExercisePage(), 'render']
        );


        add_submenu_page(
            'ecole2nat',
            __('Accès parents', 'ecole2nat'),
            __('Accès parents', 'ecole2nat'),
            'manage_options',
            'ecole2nat-parent-access',
            [new ParentAccessPage(), 'render']
        );

        add_action('admin_head', [$this, 'hideInternalMenuItems']);
    }

    public function hideInternalMenuItems(): void
    {
        ?>
        <style>
            #toplevel_page_ecole2nat a[href="admin.php?page=ecole2nat-session"],
            #toplevel_page_ecole2nat a[href="admin.php?page=ecole2nat-session-print"],
            #toplevel_page_ecole2nat a[href="admin.php?page=ecole2nat-exercise"],
            #toplevel_page_ecole2nat a[href="admin.php?page=ecole2nat-parent-access"] {
                display: none !important;
            }
        </style>
        <?php
    }

    public function renderDashboard(): void
    {
        echo '<div class="wrap">';
        echo '<h1>Ecole2Nat\'</h1>';
        echo '<p>' . esc_html__('Bienvenue dans le tableau de bord.', 'ecole2nat') . '</p>';
        echo '</div>';
    }
}
