<?php

namespace Ecole2Nat\Admin\Pages;

use Ecole2Nat\Admin\Deletion\DeletionController;
use Ecole2Nat\Admin\UI\Badge;
use Ecole2Nat\Season\SeasonRepository;

if (!defined('ABSPATH')) {
    exit;
}

class SeasonPage
{
    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Vous ne disposez pas des droits nécessaires.', 'ecole2nat'));
        }

        $repository = new SeasonRepository();
        $message = '';
        $noticeType = 'success';

        if (isset($_POST['e2n_add_season'])) {
            check_admin_referer('e2n_add_season');
            $name = isset($_POST['season_name']) ? sanitize_text_field(wp_unslash($_POST['season_name'])) : '';

            if ($name === '') {
                $message = __('Le nom de la saison est obligatoire.', 'ecole2nat');
                $noticeType = 'error';
            } elseif ($repository->create($name)) {
                $message = __('La saison a bien été ajoutée.', 'ecole2nat');
            } else {
                $message = __('Une erreur est survenue lors de l’ajout.', 'ecole2nat');
                $noticeType = 'error';
            }
        }

        if (isset($_GET['action'], $_GET['season'])) {
            $action = sanitize_key(wp_unslash($_GET['action']));
            $seasonId = absint($_GET['season']);

            if ($action === 'set-current') {
                check_admin_referer('e2n_set_current_season_' . $seasonId);
                if ($seasonId > 0 && $repository->setCurrent($seasonId)) {
                    $this->redirect('current-season');
                }
                $message = __('Impossible de définir cette saison comme courante.', 'ecole2nat');
                $noticeType = 'error';
            }

            if ($action === 'toggle-active') {
                check_admin_referer('e2n_toggle_season_' . $seasonId);
                $result = $seasonId > 0 ? $repository->toggleActive($seasonId) : 'error';

                if ($result === 'current') {
                    $message = __('La saison courante ne peut pas être désactivée. Définissez d’abord une autre saison comme courante.', 'ecole2nat');
                    $noticeType = 'warning';
                } elseif ($result === true) {
                    $this->redirect('season-status');
                } else {
                    $message = __('Impossible de modifier le statut de cette saison.', 'ecole2nat');
                    $noticeType = 'error';
                }
            }
        }

        if (isset($_GET['updated'])) {
            $updated = sanitize_key(wp_unslash($_GET['updated']));
            if ($updated === 'current-season') {
                $message = __('La saison courante a bien été mise à jour.', 'ecole2nat');
            } elseif ($updated === 'season-status') {
                $message = __('Le statut de la saison a bien été modifié. Les groupes de cette saison suivent automatiquement sa disponibilité.', 'ecole2nat');
            }
        }

        $seasons = $repository->all();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Saisons', 'ecole2nat'); ?></h1>

            <?php if ($message !== '') : ?>
                <div class="notice notice-<?php echo esc_attr($noticeType); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field('e2n_add_season'); ?>
                <p>
                    <label class="screen-reader-text" for="e2n-season-name"><?php esc_html_e('Nom de la saison', 'ecole2nat'); ?></label>
                    <input id="e2n-season-name" type="text" name="season_name" placeholder="2027-2028" required>
                    <button type="submit" class="button button-primary" name="e2n_add_season" value="1"><?php esc_html_e('Ajouter une saison', 'ecole2nat'); ?></button>
                </p>
            </form>

            <?php if ($seasons === []) : ?>
                <p><?php esc_html_e('Aucune saison.', 'ecole2nat'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr>
                        <th><?php esc_html_e('Saison', 'ecole2nat'); ?></th>
                        <th><?php esc_html_e('Début', 'ecole2nat'); ?></th>
                        <th><?php esc_html_e('Fin', 'ecole2nat'); ?></th>
                        <th><?php esc_html_e('Statut', 'ecole2nat'); ?></th>
                        <th><?php esc_html_e('Courante', 'ecole2nat'); ?></th>
                        <th><?php esc_html_e('Actions', 'ecole2nat'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($seasons as $season) :
                        $seasonId = (int) $season['id'];
                        $isActive = (int) ($season['is_active'] ?? 1) === 1;
                        $isCurrent = (int) ($season['is_current'] ?? 0) === 1;
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($season['name']); ?></strong></td>
                            <td><?php echo !empty($season['start_date']) ? esc_html($season['start_date']) : '—'; ?></td>
                            <td><?php echo !empty($season['end_date']) ? esc_html($season['end_date']) : '—'; ?></td>
                            <td><?php echo wp_kses_post(Badge::status($isActive)); ?></td>
                            <td>
                                <?php if ($isCurrent) : ?>
                                    <strong><?php esc_html_e('Oui', 'ecole2nat'); ?></strong>
                                <?php else :
                                    $url = wp_nonce_url(add_query_arg(['page'=>'ecole2nat-seasons','action'=>'set-current','season'=>$seasonId], admin_url('admin.php')), 'e2n_set_current_season_' . $seasonId);
                                    ?>
                                    <a href="<?php echo esc_url($url); ?>"><?php esc_html_e('Définir comme courante', 'ecole2nat'); ?></a>
                                <?php endif; ?>
                            </td>
                            <td class="e2n-table-actions">
                                <?php
                                $toggleUrl = wp_nonce_url(add_query_arg(['page'=>'ecole2nat-seasons','action'=>'toggle-active','season'=>$seasonId], admin_url('admin.php')), 'e2n_toggle_season_' . $seasonId);
                                ?>
                                <?php if (!$isCurrent || !$isActive) : ?>
                                    <a class="button button-small" href="<?php echo esc_url($toggleUrl); ?>">
                                        <?php echo esc_html($isActive ? __('Désactiver', 'ecole2nat') : __('Activer', 'ecole2nat')); ?>
                                    </a>
                                <?php elseif ($isCurrent) : ?>
                                    <span class="description"><?php esc_html_e('Saison courante', 'ecole2nat'); ?></span>
                                <?php endif; ?>

                                <?php if (!$isCurrent) :
                                    $deleteUrl = DeletionController::url('season', $seasonId, admin_url('admin.php?page=ecole2nat-seasons'));
                                    ?>
                                    <a class="e2n-delete-link" onclick="return confirm('<?php echo esc_js(__('Supprimer cette saison ?', 'ecole2nat')); ?>');" href="<?php echo esc_url($deleteUrl); ?>"><?php esc_html_e('Supprimer', 'ecole2nat'); ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    private function redirect(string $updated): void
    {
        wp_safe_redirect(add_query_arg(['page'=>'ecole2nat-seasons','updated'=>$updated], admin_url('admin.php')));
        exit;
    }
}
