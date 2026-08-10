<?php

namespace Ecole2Nat\Admin\Pages;

use Ecole2Nat\ParentPortal\ParentAccessService;
use Ecole2Nat\ParentPortal\ParentDistributionService;

if (!defined('ABSPATH')) {
    exit;
}

class ParentDistributionPage
{
    private ParentDistributionService $distributionService;
    private ParentAccessService $accessService;

    public function __construct()
    {
        $this->distributionService = new ParentDistributionService();
        $this->accessService = new ParentAccessService();
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Vous n’avez pas les droits nécessaires.', 'ecole2nat'));
        }

        $this->handleActions();

        $filters = $this->filtersFromRequest($_GET);
        $categories = $this->distributionService->categories();
        $groups = $this->distributionService->groups($filters['category_ids']);
        $swimmers = $this->distributionService->swimmers($filters);
        $batch = $this->distributionService->batch(get_current_user_id());
        $portalUrl = $this->accessService->portalUrl();
        ?>
        <div class="wrap e2n-parent-distribution">
            <h1><?php esc_html_e('Accès parents', 'ecole2nat'); ?></h1>
            <?php $this->renderNotice(); ?>

            <?php if ($portalUrl === '') : ?>
                <div class="notice notice-warning inline"><p>
                    <?php esc_html_e('Aucune page publique contenant le shortcode [e2n_parent_report] n’a été trouvée. Créez cette page avant d’envoyer des accès.', 'ecole2nat'); ?>
                </p></div>
            <?php else : ?>
                <p><strong><?php esc_html_e('Portail parents :', 'ecole2nat'); ?></strong>
                    <a href="<?php echo esc_url($portalUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($portalUrl); ?></a>
                </p>
            <?php endif; ?>

            <div class="postbox">
                <div class="postbox-header"><h2 class="hndle"><?php esc_html_e('Filtrer les nageurs', 'ecole2nat'); ?></h2></div>
                <div class="inside">
                    <form method="get" class="e2n-parent-global-filters">
                        <input type="hidden" name="page" value="ecole2nat-parent-distribution">

                        <fieldset style="margin-bottom:16px;">
                            <legend><strong><?php esc_html_e('Catégories', 'ecole2nat'); ?></strong></legend>
                            <p class="description"><?php esc_html_e('Aucune catégorie cochée = toutes les catégories.', 'ecole2nat'); ?></p>
                            <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:8px;">
                                <?php foreach ($categories as $category) : ?>
                                    <label>
                                        <input type="checkbox" name="category_ids[]" value="<?php echo esc_attr((string) $category['id']); ?>"
                                            <?php checked(in_array((int) $category['id'], $filters['category_ids'], true)); ?>>
                                        <?php echo esc_html((string) $category['name']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>

                        <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:end;">
                            <label>
                                <span style="display:block;font-weight:600;margin-bottom:4px;"><?php esc_html_e('Groupe', 'ecole2nat'); ?></span>
                                <select name="group_id">
                                    <option value="0"><?php esc_html_e('Tous les groupes', 'ecole2nat'); ?></option>
                                    <?php foreach ($groups as $group) : ?>
                                        <option value="<?php echo esc_attr((string) $group['id']); ?>" <?php selected($filters['group_id'], (int) $group['id']); ?>>
                                            <?php echo esc_html(sprintf('%s — %s (%s)', $group['name'], $group['category_name'], $group['season_name'])); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                <span style="display:block;font-weight:600;margin-bottom:4px;"><?php esc_html_e('Accès', 'ecole2nat'); ?></span>
                                <select name="access_status">
                                    <option value="all" <?php selected($filters['access_status'], 'all'); ?>><?php esc_html_e('Tous', 'ecole2nat'); ?></option>
                                    <option value="missing" <?php selected($filters['access_status'], 'missing'); ?>><?php esc_html_e('Code manquant / inactif', 'ecole2nat'); ?></option>
                                    <option value="active" <?php selected($filters['access_status'], 'active'); ?>><?php esc_html_e('Code actif', 'ecole2nat'); ?></option>
                                    <option value="sent" <?php selected($filters['access_status'], 'sent'); ?>><?php esc_html_e('Déjà distribué', 'ecole2nat'); ?></option>
                                    <option value="not_sent" <?php selected($filters['access_status'], 'not_sent'); ?>><?php esc_html_e('Non distribué', 'ecole2nat'); ?></option>
                                </select>
                            </label>

                            <label>
                                <span style="display:block;font-weight:600;margin-bottom:4px;"><?php esc_html_e('Email', 'ecole2nat'); ?></span>
                                <select name="email_status">
                                    <option value="all" <?php selected($filters['email_status'], 'all'); ?>><?php esc_html_e('Tous', 'ecole2nat'); ?></option>
                                    <option value="with" <?php selected($filters['email_status'], 'with'); ?>><?php esc_html_e('Avec email', 'ecole2nat'); ?></option>
                                    <option value="without" <?php selected($filters['email_status'], 'without'); ?>><?php esc_html_e('Sans email', 'ecole2nat'); ?></option>
                                </select>
                            </label>

                            <button class="button button-primary" type="submit"><?php esc_html_e('Appliquer les filtres', 'ecole2nat'); ?></button>
                            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=ecole2nat-parent-distribution')); ?>"><?php esc_html_e('Réinitialiser', 'ecole2nat'); ?></a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle"><?php echo esc_html(sprintf(_n('%d nageur trouvé', '%d nageurs trouvés', count($swimmers), 'ecole2nat'), count($swimmers))); ?></h2>
                </div>
                <div class="inside">
                    <?php if ($swimmers === []) : ?>
                        <p><?php esc_html_e('Aucun nageur ne correspond aux filtres.', 'ecole2nat'); ?></p>
                    <?php else : ?>
                        <form method="post" id="e2n-parent-bulk-form">
                            <?php wp_nonce_field('e2n_parent_distribution_bulk'); ?>
                            <?php $this->renderFilterHiddenInputs($filters); ?>

                            <p style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                                <button type="submit" name="e2n_action" value="send_missing_results" class="button button-primary"
                                    onclick="return confirm('<?php echo esc_js(__('Envoyer un nouveau code à tous les nageurs non distribués correspondant aux filtres ?', 'ecole2nat')); ?>');">
                                    <?php esc_html_e('Envoyer les accès non distribués', 'ecole2nat'); ?>
                                </button>

                                <button type="submit" name="e2n_action" value="send_selected" class="button"
                                    onclick="return confirm('<?php echo esc_js(__('Les nageurs sélectionnés recevront un nouveau code. Les anciens codes seront invalidés. Continuer ?', 'ecole2nat')); ?>');">
                                    <?php esc_html_e('Renvoyer aux sélectionnés', 'ecole2nat'); ?>
                                </button>

                                <button type="submit" name="e2n_action" value="prepare_all_coupons" class="button"
                                    onclick="return confirm('<?php echo esc_js(__('Tous les nageurs affichés recevront un nouveau code pour les coupons. Les anciens codes seront invalidés. Continuer ?', 'ecole2nat')); ?>');">
                                    <?php esc_html_e('Préparer tous les coupons affichés', 'ecole2nat'); ?>
                                </button>

                                <button type="submit" name="e2n_action" value="prepare_coupons" class="button"
                                    onclick="return confirm('<?php echo esc_js(__('La préparation régénère les codes des nageurs sélectionnés et invalide les anciens codes. Continuer ?', 'ecole2nat')); ?>');">
                                    <?php esc_html_e('Préparer les coupons sélectionnés', 'ecole2nat'); ?>
                                </button>
                            </p>

                            <p class="description">
                                <?php esc_html_e('Après un envoi email, le lot de coupons utilise exactement les mêmes codes : vous pouvez donc imprimer immédiatement sans invalider les codes envoyés.', 'ecole2nat'); ?>
                            </p>

                            <table class="wp-list-table widefat fixed striped">
                                <thead><tr>
                                    <td class="check-column"><input type="checkbox" id="e2n-select-all-parent-access"></td>
                                    <th><?php esc_html_e('Nageur', 'ecole2nat'); ?></th>
                                    <th><?php esc_html_e('Catégorie', 'ecole2nat'); ?></th>
                                    <th><?php esc_html_e('Groupe', 'ecole2nat'); ?></th>
                                    <th><?php esc_html_e('Email responsable', 'ecole2nat'); ?></th>
                                    <th><?php esc_html_e('Code', 'ecole2nat'); ?></th>
                                    <th><?php esc_html_e('Dernière distribution', 'ecole2nat'); ?></th>
                                    <th><?php esc_html_e('Actions', 'ecole2nat'); ?></th>
                                </tr></thead>
                                <tbody>
                                <?php foreach ($swimmers as $swimmer) :
                                    $swimmerId = (int) $swimmer['id'];
                                    $singleUrl = add_query_arg(['page' => 'ecole2nat-parent-access', 'swimmer_id' => $swimmerId], admin_url('admin.php'));
                                    $previewUrl = $this->accessService->previewUrl($swimmerId);
                                    ?>
                                    <tr>
                                        <th scope="row" class="check-column"><input type="checkbox" class="e2n-parent-row-checkbox" name="swimmer_ids[]" value="<?php echo esc_attr((string) $swimmerId); ?>"></th>
                                        <td><strong><?php echo esc_html(strtoupper((string) $swimmer['last_name']) . ' ' . (string) $swimmer['first_name']); ?></strong></td>
                                        <td><?php echo esc_html((string) ($swimmer['category_name'] ?? '')); ?></td>
                                        <td><?php echo esc_html((string) ($swimmer['group_name'] ?? '')); ?></td>
                                        <td><?php echo !empty($swimmer['responsible_email']) ? esc_html((string) $swimmer['responsible_email']) : '<span class="description">' . esc_html__('Aucun email', 'ecole2nat') . '</span>'; ?></td>
                                        <td>
                                            <?php if (!empty($swimmer['parent_access_code_hash']) && (int) ($swimmer['parent_access_enabled'] ?? 0) === 1) : ?>
                                                <span class="e2n-status e2n-status--active"><?php esc_html_e('Actif', 'ecole2nat'); ?></span>
                                            <?php else : ?>
                                                <span class="description"><?php esc_html_e('Non généré', 'ecole2nat'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($swimmer['parent_access_distributed_at'])) : ?>
                                                <?php echo esc_html(wp_date('d/m/Y H:i', strtotime((string) $swimmer['parent_access_distributed_at']))); ?>
                                                <?php if (!empty($swimmer['parent_access_distributed_to'])) : ?><br><span class="description"><?php echo esc_html((string) $swimmer['parent_access_distributed_to']); ?></span><?php endif; ?>
                                            <?php else : ?>—<?php endif; ?>
                                        </td>
                                        <td class="e2n-table-actions">
                                            <a href="<?php echo esc_url($singleUrl); ?>"><?php esc_html_e('Gérer', 'ecole2nat'); ?></a>
                                            <?php if ($previewUrl !== '') : ?> | <a href="<?php echo esc_url($previewUrl); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Voir le parcours', 'ecole2nat'); ?></a><?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($batch !== []) : $this->renderCouponBatch($batch, $filters); endif; ?>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggle = document.getElementById('e2n-select-all-parent-access');
            if (!toggle) return;
            toggle.addEventListener('change', function () {
                document.querySelectorAll('.e2n-parent-row-checkbox').forEach(function (box) { box.checked = toggle.checked; });
            });
        });
        </script>
        <?php
    }

    private function handleActions(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        $action = isset($_POST['e2n_action']) ? sanitize_key(wp_unslash($_POST['e2n_action'])) : '';
        $allowed = ['send_missing_results', 'send_selected', 'prepare_all_coupons', 'prepare_coupons', 'clear_coupon_batch'];
        if (!in_array($action, $allowed, true)) {
            return;
        }
        check_admin_referer('e2n_parent_distribution_bulk');
        $filters = $this->filtersFromRequest($_POST);

        if ($action === 'clear_coupon_batch') {
            $this->distributionService->clearBatch(get_current_user_id());
            $this->redirect($filters, 'batch_cleared');
        }

        $ids = isset($_POST['swimmer_ids']) && is_array($_POST['swimmer_ids'])
            ? array_values(array_filter(array_map('absint', wp_unslash($_POST['swimmer_ids']))))
            : [];
        $allResultIds = array_map(
            static fn(array $swimmer): int => (int) $swimmer['id'],
            $this->distributionService->swimmers($filters)
        );

        if ($action === 'send_missing_results') {
            $result = $this->distributionService->sendMissingByFilters($filters, get_current_user_id());
            $this->storeResult($result);
            $this->redirect($filters, 'emails_processed');
        }
        if ($action === 'prepare_all_coupons') {
            if ($allResultIds === []) {
                $this->redirect($filters, 'nothing_selected');
            }
            $result = $this->distributionService->prepareCoupons($allResultIds, get_current_user_id());
            $this->redirect($filters, (string) $result['message']);
        }
        if ($ids === []) {
            $this->redirect($filters, 'nothing_selected');
        }
        if ($action === 'send_selected') {
            $result = $this->distributionService->sendSelected($ids, get_current_user_id());
            $this->storeResult($result);
            $this->redirect($filters, 'emails_processed');
        }
        if ($action === 'prepare_coupons') {
            $result = $this->distributionService->prepareCoupons($ids, get_current_user_id());
            $this->redirect($filters, (string) $result['message']);
        }
    }

    private function filtersFromRequest(array $source): array
    {
        $rawCategories = isset($source['category_ids']) && is_array($source['category_ids']) ? $source['category_ids'] : [];
        return [
            'category_ids' => array_values(array_filter(array_unique(array_map('absint', wp_unslash($rawCategories))))),
            'group_id' => isset($source['group_id']) ? absint($source['group_id']) : 0,
            'access_status' => isset($source['access_status']) ? sanitize_key(wp_unslash($source['access_status'])) : 'all',
            'email_status' => isset($source['email_status']) ? sanitize_key(wp_unslash($source['email_status'])) : 'all',
        ];
    }

    private function renderFilterHiddenInputs(array $filters): void
    {
        foreach ($filters['category_ids'] as $categoryId) {
            echo '<input type="hidden" name="category_ids[]" value="' . esc_attr((string) $categoryId) . '">';
        }
        echo '<input type="hidden" name="group_id" value="' . esc_attr((string) $filters['group_id']) . '">';
        echo '<input type="hidden" name="access_status" value="' . esc_attr($filters['access_status']) . '">';
        echo '<input type="hidden" name="email_status" value="' . esc_attr($filters['email_status']) . '">';
    }

    private function renderNotice(): void
    {
        $notice = isset($_GET['e2n_notice']) ? sanitize_key(wp_unslash($_GET['e2n_notice'])) : '';
        $messages = [
            'coupons_ready' => ['success', __('Les coupons sont prêts. Les nouveaux codes sont visibles plus bas pendant 30 minutes.', 'ecole2nat')],
            'batch_cleared' => ['success', __('Le lot temporaire de coupons a été effacé.', 'ecole2nat')],
            'nothing_selected' => ['warning', __('Aucun nageur ne correspond à cette action.', 'ecole2nat')],
            'missing_portal' => ['error', __('Créez d’abord la page publique contenant [e2n_parent_report].', 'ecole2nat')],
            'error' => ['error', __('Une erreur est survenue.', 'ecole2nat')],
        ];

        if ($notice === 'emails_processed') {
            $result = get_transient($this->resultTransientKey());
            delete_transient($this->resultTransientKey());
            if (is_array($result)) {
                $message = sprintf(
                    __('Envoi terminé : %1$d envoyé(s), %2$d échec(s), dont %3$d sans email valide. Les codes envoyés sont disponibles dans le lot de coupons ci-dessous.', 'ecole2nat'),
                    (int) ($result['sent'] ?? 0),
                    (int) ($result['failed'] ?? 0),
                    (int) ($result['missing_email'] ?? 0)
                );
                echo '<div class="notice notice-' . ((int) ($result['failed'] ?? 0) > 0 ? 'warning' : 'success') . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
            }
        }
        if (!isset($messages[$notice])) {
            return;
        }
        [$type, $message] = $messages[$notice];
        echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    private function renderCouponBatch(array $batch, array $filters): void
    {
        $csvUrl = wp_nonce_url(admin_url('admin-post.php?action=e2n_parent_distribution_csv'), 'e2n_parent_distribution_csv');
        ?>
        <div class="postbox e2n-parent-coupon-batch">
            <div class="postbox-header"><h2 class="hndle"><?php echo esc_html(sprintf(__('Lot de coupons — %d', 'ecole2nat'), count($batch))); ?></h2></div>
            <div class="inside">
                <p class="e2n-parent-batch-actions">
                    <button type="button" class="button button-primary" onclick="window.print()"><?php esc_html_e('Imprimer tous les coupons', 'ecole2nat'); ?></button>
                    <a href="<?php echo esc_url($csvUrl); ?>" class="button"><?php esc_html_e('Télécharger le CSV', 'ecole2nat'); ?></a>
                </p>
                <form method="post" class="e2n-parent-batch-clear">
                    <?php wp_nonce_field('e2n_parent_distribution_bulk'); ?>
                    <input type="hidden" name="e2n_action" value="clear_coupon_batch">
                    <?php $this->renderFilterHiddenInputs($filters); ?>
                    <?php submit_button(__('Effacer le lot temporaire', 'ecole2nat'), 'secondary', 'submit', false); ?>
                </form>
                <div class="e2n-parent-coupons">
                    <?php foreach ($batch as $row) : ?>
                        <article class="e2n-parent-coupon">
                            <p class="e2n-parent-coupon-brand">Ecole2Nat'</p>
                            <h3><?php esc_html_e('Mon parcours de natation', 'ecole2nat'); ?></h3>
                            <p class="e2n-parent-coupon-swimmer"><?php echo esc_html((string) $row['first_name'] . ' ' . strtoupper((string) $row['last_name'])); ?></p>
                            <?php if (!empty($row['group_name'])) : ?><p><?php echo esc_html((string) $row['group_name']); ?></p><?php endif; ?>
                            <p class="e2n-parent-coupon-code"><?php echo esc_html((string) $row['code']); ?></p>
                            <p class="e2n-parent-coupon-url"><?php echo esc_html((string) $row['portal_url']); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function storeResult(array $result): void
    {
        set_transient($this->resultTransientKey(), $result, 10 * MINUTE_IN_SECONDS);
    }

    private function resultTransientKey(): string
    {
        return 'e2n_parent_distribution_result_' . get_current_user_id();
    }

    private function redirect(array $filters, string $notice): void
    {
        $args = [
            'page' => 'ecole2nat-parent-distribution',
            'group_id' => $filters['group_id'],
            'access_status' => $filters['access_status'],
            'email_status' => $filters['email_status'],
            'e2n_notice' => $notice,
        ];
        if ($filters['category_ids'] !== []) {
            $args['category_ids'] = $filters['category_ids'];
        }
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }
}
