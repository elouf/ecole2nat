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

        if (empty($seasons)) {
    echo '<p>Aucune saison.</p>';
} else {
    echo '<table class="wp-list-table widefat fixed striped">';

    echo '<thead>';
    echo '<tr>';
    echo '<th>Saison</th>';
    echo '<th>Début</th>';
    echo '<th>Fin</th>';
    echo '<th>Courante</th>';
    echo '</tr>';
    echo '</thead>';

    echo '<tbody>';

    foreach ($seasons as $season) {
        $startDate = !empty($season['start_date'])
            ? esc_html($season['start_date'])
            : '—';

        $endDate = !empty($season['end_date'])
            ? esc_html($season['end_date'])
            : '—';

        $isCurrent = !empty($season['is_current'])
            ? 'Oui'
            : 'Non';

        echo '<tr>';
        echo '<td>' . esc_html($season['name']) . '</td>';
        echo '<td>' . $startDate . '</td>';
        echo '<td>' . $endDate . '</td>';
        echo '<td>' . esc_html($isCurrent) . '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
}
    }
}