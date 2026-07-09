<?php
namespace Ecole2Nat\Admin;

use Ecole2Nat\Season\SeasonRepository;

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
    }

    public function renderDashboard(): void
{
    $repository = new SeasonRepository();

    if (
        isset($_POST['e2n_add_season'])
        && check_admin_referer('e2n_add_season')
    ) {
        $name = sanitize_text_field($_POST['season_name'] ?? '');

        if ($name !== '') {
            $repository->create($name);
        }
    }

    $seasons = $repository->all();

    echo '<div class="wrap">';
    echo '<h1>Ecole2Nat\'</h1>';

    echo '<h2>Saisons</h2>';

    echo '<form method="post">';

wp_nonce_field('e2n_add_season');

echo '<p>';

echo '<input
        type="text"
        name="season_name"
        placeholder="2026-2027"
        required>';

echo ' ';

echo '<button
        type="submit"
        class="button button-primary"
        name="e2n_add_season">
        Ajouter une saison
      </button>';

echo '</p>';

echo '</form>';

    if (empty($seasons)) {
        echo '<p>Aucune saison.</p>';
    } else {
        echo '<ul>';

        foreach ($seasons as $season) {
            echo '<li>' . esc_html($season['name']) . '</li>';
        }

        echo '</ul>';
    }

    echo '</div>';
}
}