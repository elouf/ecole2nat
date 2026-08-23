<?php

namespace Ecole2Nat\Admin\Pages;

use Ecole2Nat\Admin\Deletion\DeletionController;
use Ecole2Nat\Competition\CompetitionRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class CompetitionPage
{
    public function __construct(private ?CompetitionRepository $repository = null)
    {
        $this->repository ??= new CompetitionRepository();
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Accès refusé.', 'ecole2nat'));
        }

        $competitionId = absint($_GET['competition_id'] ?? 0);
        if ($competitionId > 0 && isset($_POST['e2n_save_competition'])) {
            check_admin_referer('e2n_save_competition_' . $competitionId);
            $this->save($competitionId);
        }

        echo '<div class="wrap"><h1>' . esc_html__('Compétitions', 'ecole2nat') . '</h1>';
        if ($competitionId > 0) {
            $this->renderEdit($competitionId);
        } else {
            $this->renderList();
        }
        echo '</div>';
    }

    private function renderList(): void
    {
        $rows = $this->repository->adminList();
        echo '<p>' . esc_html__('Les compétitions sont créées par la synchronisation du classeur. Cet écran permet de corriger leurs informations.', 'ecole2nat') . '</p>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Compétition', 'ecole2nat') . '</th><th>' . esc_html__('Date', 'ecole2nat') . '</th><th>' . esc_html__('Saison', 'ecole2nat') . '</th><th>' . esc_html__('Statut', 'ecole2nat') . '</th><th></th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $url = admin_url('admin.php?page=ecole2nat-competitions&competition_id=' . (int) $row['id']);
            $deleteUrl = DeletionController::url('competition', (int) $row['id'], admin_url('admin.php?page=ecole2nat-competitions'));
            $dates = wp_date('d/m/Y', strtotime($row['start_date']));
            if (!empty($row['end_date']) && $row['end_date'] !== $row['start_date']) {
                $dates .= ' — ' . wp_date('d/m/Y', strtotime($row['end_date']));
            }
            echo '<tr><td>' . esc_html($row['name']) . '</td><td>' . esc_html($dates) . '</td><td>' . esc_html($row['season_name'] ?? '') . '</td><td>' . esc_html($row['status']) . '</td><td><a href="' . esc_url($url) . '">' . esc_html__('Modifier', 'ecole2nat') . '</a> | <a class="e2n-delete-link" href="' . esc_url($deleteUrl) . '" onclick="return confirm(\'' . esc_js(__('Supprimer définitivement cette compétition, toutes les réponses et tous les engagements associés ?', 'ecole2nat')) . '\');">' . esc_html__('Supprimer', 'ecole2nat') . '</a></td></tr>';
        }
        if ($rows === []) {
            echo '<tr><td colspan="5">' . esc_html__('Aucune compétition synchronisée.', 'ecole2nat') . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private function renderEdit(int $competitionId): void
    {
        $row = $this->repository->find($competitionId);
        if ($row === null) {
            echo '<p>' . esc_html__('Compétition introuvable.', 'ecole2nat') . '</p>';
            return;
        }
        $targets = $this->repository->targetCategories($competitionId);
        ?>
        <form method="post">
            <?php wp_nonce_field('e2n_save_competition_' . $competitionId); ?>
            <input type="hidden" name="competition_id" value="<?php echo $competitionId; ?>">
            <table class="form-table">
                <tr><th><label for="name"><?php esc_html_e('Nom', 'ecole2nat'); ?></label></th><td><input class="regular-text" id="name" name="name" value="<?php echo esc_attr($row['name']); ?>" required></td></tr>
                <tr><th><?php esc_html_e('Dates', 'ecole2nat'); ?></th><td><input type="date" name="start_date" value="<?php echo esc_attr($row['start_date']); ?>" required> — <input type="date" name="end_date" value="<?php echo esc_attr($row['end_date']); ?>"></td></tr>
                <tr><th><?php esc_html_e('Inscriptions', 'ecole2nat'); ?></th><td><input type="date" name="registration_start" value="<?php echo esc_attr(substr($row['registration_opens_at'], 0, 10)); ?>" required> — <input type="date" name="registration_end" value="<?php echo esc_attr(substr($row['registration_closes_at'], 0, 10)); ?>" required></td></tr>
                <tr><th><?php esc_html_e('Lieu', 'ecole2nat'); ?></th><td><input class="regular-text" name="location" value="<?php echo esc_attr($row['location']); ?>"></td></tr>
                <tr><th><?php esc_html_e('Bassin', 'ecole2nat'); ?></th><td><select name="pool_length"><option value=""><?php esc_html_e('Non renseigné', 'ecole2nat'); ?></option><option value="25m" <?php selected($row['pool_length']??'', '25m'); ?>>25m</option><option value="50m" <?php selected($row['pool_length']??'', '50m'); ?>>50m</option></select></td></tr>
                <tr><th><?php esc_html_e('Fiche technique', 'ecole2nat'); ?></th><td><input class="large-text" type="url" name="technical_document_url" value="<?php echo esc_attr($row['technical_document_url']); ?>"></td></tr>
                <tr><th><?php esc_html_e('Programme', 'ecole2nat'); ?></th><td><input class="large-text" type="url" name="program_url" value="<?php echo esc_attr($row['program_url']); ?>"></td></tr>
                <tr><th><?php esc_html_e('Covoiturage', 'ecole2nat'); ?></th><td><input class="large-text" type="url" name="carpool_url" value="<?php echo esc_attr($row['carpool_url']); ?>"></td></tr>
                <tr><th><?php esc_html_e('liveFFN', 'ecole2nat'); ?></th><td><input class="large-text" type="url" name="liveffn_url" value="<?php echo esc_attr($row['liveffn_url']); ?>"></td></tr>
                <tr><th><?php esc_html_e('Album photo', 'ecole2nat'); ?></th><td><input class="large-text" type="url" name="photo_album_url" value="<?php echo esc_attr($row['photo_album_url']); ?>"></td></tr>
                <tr><th><?php esc_html_e('Public concerné', 'ecole2nat'); ?></th><td><label><input type="checkbox" name="target_all" value="1" <?php checked((int) $row['target_all'], 1); ?>> <?php esc_html_e('Tous les nageurs', 'ecole2nat'); ?></label><p><input class="regular-text" name="competition_categories" value="<?php echo esc_attr(implode(';', $targets)); ?>" placeholder="U11;HANDI"></p><p class="description"><?php esc_html_e('Sinon, saisir les catégories de compétiteur séparées par des points-virgules. Une correspondance suffit.', 'ecole2nat'); ?></p></td></tr>
                <tr><th><?php esc_html_e('Statut', 'ecole2nat'); ?></th><td><select name="status"><option value="draft" <?php selected($row['status'], 'draft'); ?>><?php esc_html_e('Brouillon', 'ecole2nat'); ?></option><option value="published" <?php selected($row['status'], 'published'); ?>><?php esc_html_e('Publiée', 'ecole2nat'); ?></option><option value="cancelled" <?php selected($row['status'], 'cancelled'); ?>><?php esc_html_e('Annulée', 'ecole2nat'); ?></option></select></td></tr>
                <tr><th><?php esc_html_e('Informations', 'ecole2nat'); ?></th><td><textarea class="large-text" rows="4" name="information"><?php echo esc_textarea($row['information']); ?></textarea></td></tr>
            </table>
            <?php submit_button(__('Enregistrer', 'ecole2nat'), 'primary', 'e2n_save_competition'); ?>
        </form>
        <p><a href="<?php echo esc_url(admin_url('admin.php?page=ecole2nat-competitions')); ?>">← <?php esc_html_e('Retour', 'ecole2nat'); ?></a></p>
        <?php
    }

    private function save(int $competitionId): void
    {
        $targetAll = isset($_POST['target_all']) ? 1 : 0;
        $targets = $targetAll ? [] : array_values(array_unique(array_filter(array_map(
            'trim',
            preg_split('/[;\n]+/u', sanitize_text_field(wp_unslash($_POST['competition_categories'] ?? ''))) ?: []
        ))));
        $data = [
            'name' => sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
            'start_date' => sanitize_text_field($_POST['start_date'] ?? ''),
            'end_date' => sanitize_text_field($_POST['end_date'] ?? ''),
            'location' => sanitize_text_field(wp_unslash($_POST['location'] ?? '')),
            'pool_length' => in_array($_POST['pool_length'] ?? '', ['25m','50m'], true) ? $_POST['pool_length'] : '',
            'registration_opens_at' => sanitize_text_field($_POST['registration_start'] ?? '') . ' 00:00:00',
            'registration_closes_at' => sanitize_text_field($_POST['registration_end'] ?? '') . ' 23:59:59',
            'technical_document_url' => esc_url_raw(wp_unslash($_POST['technical_document_url'] ?? '')),
            'program_url' => esc_url_raw(wp_unslash($_POST['program_url'] ?? '')),
            'carpool_url' => esc_url_raw(wp_unslash($_POST['carpool_url'] ?? '')),
            'liveffn_url' => esc_url_raw(wp_unslash($_POST['liveffn_url'] ?? '')),
            'photo_album_url' => esc_url_raw(wp_unslash($_POST['photo_album_url'] ?? '')),
            'information' => sanitize_textarea_field(wp_unslash($_POST['information'] ?? '')),
            'target_all' => $targetAll,
            'status' => in_array($_POST['status'] ?? '', ['draft', 'published', 'cancelled'], true) ? $_POST['status'] : 'draft',
            'updated_at' => current_time('mysql'),
        ];
        if ($this->repository->updateCompetition($competitionId, $data, $targets)) {
            wp_safe_redirect(admin_url('admin.php?page=ecole2nat-competitions'));
            exit;
        }
        add_settings_error('ecole2nat', 'competition_save', __('Impossible d’enregistrer la compétition.', 'ecole2nat'));
        settings_errors('ecole2nat');
    }
}
