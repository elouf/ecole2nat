<?php

namespace Ecole2Nat\Admin\Pages;

use Ecole2Nat\Season\SeasonRepository;

if (!defined('ABSPATH')) {
    exit;
}

class SeasonPage
{
    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__(
                    'Vous ne disposez pas des droits nécessaires.',
                    'ecole2nat'
                )
            );
        }

        $repository = new SeasonRepository();
        $message = '';

        /*
         * Ajout d'une saison.
         */
        if (isset($_POST['e2n_add_season'])) {
            check_admin_referer('e2n_add_season');

            $name = isset($_POST['season_name'])
                ? sanitize_text_field(wp_unslash($_POST['season_name']))
                : '';

            if ($name === '') {
                $message = 'Le nom de la saison est obligatoire.';
            } elseif ($repository->create($name)) {
                $message = 'La saison a bien été ajoutée.';
            } else {
                $message = 'Une erreur est survenue lors de l’ajout.';
            }
        }

        /*
         * Définition de la saison courante.
         */
        if (
            isset($_GET['action'], $_GET['season'])
            && sanitize_key(wp_unslash($_GET['action'])) === 'set-current'
        ) {
            $seasonId = absint($_GET['season']);

            check_admin_referer(
                'e2n_set_current_season_' . $seasonId
            );

            if ($seasonId > 0 && $repository->setCurrent($seasonId)) {
                wp_safe_redirect(
                    add_query_arg(
                        [
                            'page'    => 'ecole2nat-seasons',
                            'updated' => 'current-season',
                        ],
                        admin_url('admin.php')
                    )
                );

                exit;
            }

            $message = 'Impossible de définir cette saison comme courante.';
        }

        if (
            isset($_GET['updated'])
            && sanitize_key(wp_unslash($_GET['updated'])) === 'current-season'
        ) {
            $message = 'La saison courante a bien été mise à jour.';
        }

        $seasons = $repository->all();

        echo '<div class="wrap">';
        echo '<h1>Saisons</h1>';

        if ($message !== '') {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . esc_html($message) . '</p>';
            echo '</div>';
        }

        /*
         * Formulaire d'ajout.
         */
        echo '<form method="post">';

        wp_nonce_field('e2n_add_season');

        echo '<p>';
        echo '<label class="screen-reader-text" for="e2n-season-name">';
        echo 'Nom de la saison';
        echo '</label>';

        echo '<input
            id="e2n-season-name"
            type="text"
            name="season_name"
            placeholder="2026-2027"
            required
        >';

        echo ' ';

        echo '<button
            type="submit"
            class="button button-primary"
            name="e2n_add_season"
            value="1"
        >';
        echo 'Ajouter une saison';
        echo '</button>';

        echo '</p>';
        echo '</form>';

        /*
         * Tableau des saisons.
         */
        if (empty($seasons)) {
            echo '<p>Aucune saison.</p>';
            echo '</div>';

            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th scope="col">Saison</th>';
        echo '<th scope="col">Début</th>';
        echo '<th scope="col">Fin</th>';
        echo '<th scope="col">Courante</th>';
        echo '</tr>';
        echo '</thead>';

        echo '<tbody>';

        foreach ($seasons as $season) {
            $seasonId = (int) $season['id'];

            $startDate = !empty($season['start_date'])
                ? esc_html($season['start_date'])
                : '—';

            $endDate = !empty($season['end_date'])
                ? esc_html($season['end_date'])
                : '—';

            echo '<tr>';

            echo '<td>';
            echo '<strong>' . esc_html($season['name']) . '</strong>';
            echo '</td>';

            echo '<td>' . $startDate . '</td>';
            echo '<td>' . $endDate . '</td>';

            echo '<td>';

            if (!empty($season['is_current'])) {
                echo '<strong>Oui</strong>';
            } else {
                $url = add_query_arg(
                    [
                        'page'   => 'ecole2nat-seasons',
                        'action' => 'set-current',
                        'season' => $seasonId,
                    ],
                    admin_url('admin.php')
                );

                $url = wp_nonce_url(
                    $url,
                    'e2n_set_current_season_' . $seasonId
                );

                echo '<a href="' . esc_url($url) . '">';
                echo 'Définir comme courante';
                echo '</a>';
            }

            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
}