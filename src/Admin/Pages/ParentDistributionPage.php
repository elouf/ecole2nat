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

        $groups = $this->distributionService->groups();
        $groupId = isset($_GET['group_id'])
            ? absint($_GET['group_id'])
            : $this->defaultGroupId($groups);
        $swimmers = $groupId > 0
            ? $this->distributionService->swimmersByGroup($groupId)
            : [];
        $batch = $this->distributionService->batch(get_current_user_id());
        $portalUrl = $this->accessService->portalUrl();

        ?>
        <div class="wrap e2n-parent-distribution">
            <h1><?php esc_html_e('Accès parents', 'ecole2nat'); ?></h1>

            <?php $this->renderNotice(); ?>

            <?php if ($portalUrl === '') : ?>
                <div class="notice notice-warning inline">
                    <p>
                        <?php esc_html_e(
                            'Aucune page publique contenant le shortcode [e2n_parent_report] n’a été trouvée. Créez cette page avant d’envoyer des accès.',
                            'ecole2nat'
                        ); ?>
                    </p>
                </div>
            <?php else : ?>
                <p>
                    <strong><?php esc_html_e('Portail parents :', 'ecole2nat'); ?></strong>
                    <a href="<?php echo esc_url($portalUrl); ?>" target="_blank" rel="noopener noreferrer">
                        <?php echo esc_html($portalUrl); ?>
                    </a>
                </p>
            <?php endif; ?>

            <form method="get" class="e2n-filter-bar" style="margin:20px 0;">
                <input type="hidden" name="page" value="ecole2nat-parent-distribution">
                <label>
                    <span><?php esc_html_e('Groupe', 'ecole2nat'); ?></span>
                    <select name="group_id" onchange="this.form.submit()">
                        <?php foreach ($groups as $group) : ?>
                            <option
                                value="<?php echo esc_attr((string) $group['id']); ?>"
                                <?php selected($groupId, (int) $group['id']); ?>
                            >
                                <?php
                                echo esc_html(
                                    sprintf(
                                        '%s — %s (%s)',
                                        $group['name'],
                                        $group['category_name'],
                                        $group['season_name']
                                    )
                                );
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </form>

            <?php if ($groupId > 0) : ?>
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">
                            <?php echo esc_html(sprintf(_n('%d nageur', '%d nageurs', count($swimmers), 'ecole2nat'), count($swimmers))); ?>
                        </h2>
                    </div>
                    <div class="inside">
                        <?php if ($swimmers === []) : ?>
                            <p><?php esc_html_e('Aucun nageur actif dans ce groupe.', 'ecole2nat'); ?></p>
                        <?php else : ?>
                            <form method="post" id="e2n-parent-bulk-form">
                                <?php wp_nonce_field('e2n_parent_distribution_bulk'); ?>
                                <input type="hidden" name="group_id" value="<?php echo esc_attr((string) $groupId); ?>">

                                <p style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                                    <button
                                        type="submit"
                                        name="e2n_action"
                                        value="send_missing_group"
                                        class="button button-primary"
                                    >
                                        <?php esc_html_e('Envoyer les accès manquants par email', 'ecole2nat'); ?>
                                    </button>

                                    <button
                                        type="submit"
                                        name="e2n_action"
                                        value="send_selected"
                                        class="button"
                                        onclick="return confirm('<?php echo esc_js(__('Les nageurs sélectionnés recevront un nouveau code. Les anciens codes seront invalidés. Continuer ?', 'ecole2nat')); ?>');"
                                    >
                                        <?php esc_html_e('Renvoyer aux sélectionnés', 'ecole2nat'); ?>
                                    </button>

                                    <button
                                        type="submit"
                                        name="e2n_action"
                                        value="prepare_coupons"
                                        class="button"
                                        onclick="return confirm('<?php echo esc_js(__('La préparation des coupons régénère les codes des nageurs sélectionnés et invalide les anciens codes. Continuer ?', 'ecole2nat')); ?>');"
                                    >
                                        <?php esc_html_e('Préparer les coupons sélectionnés', 'ecole2nat'); ?>
                                    </button>
                                </p>

                                <p class="description">
                                    <?php esc_html_e(
                                        'Un renvoi régénère le code, car les codes existants sont stockés uniquement sous forme d’empreinte et ne peuvent pas être relus.',
                                        'ecole2nat'
                                    ); ?>
                                </p>

                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <td class="check-column">
                                                <input type="checkbox" id="e2n-select-all-parent-access">
                                            </td>
                                            <th><?php esc_html_e('Nageur', 'ecole2nat'); ?></th>
                                            <th><?php esc_html_e('Email responsable', 'ecole2nat'); ?></th>
                                            <th><?php esc_html_e('Code', 'ecole2nat'); ?></th>
                                            <th><?php esc_html_e('Dernière distribution', 'ecole2nat'); ?></th>
                                            <th><?php esc_html_e('Actions', 'ecole2nat'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($swimmers as $swimmer) : ?>
                                            <?php
                                            $swimmerId = (int) $swimmer['id'];
                                            $singleUrl = add_query_arg(
                                                [
                                                    'page' => 'ecole2nat-parent-access',
                                                    'swimmer_id' => $swimmerId,
                                                ],
                                                admin_url('admin.php')
                                            );
                                            $previewUrl = $this->accessService->previewUrl($swimmerId);
                                            ?>
                                            <tr>
                                                <th scope="row" class="check-column">
                                                    <input
                                                        type="checkbox"
                                                        class="e2n-parent-row-checkbox"
                                                        name="swimmer_ids[]"
                                                        value="<?php echo esc_attr((string) $swimmerId); ?>"
                                                    >
                                                </th>
                                                <td>
                                                    <strong>
                                                        <?php echo esc_html(strtoupper((string) $swimmer['last_name']) . ' ' . (string) $swimmer['first_name']); ?>
                                                    </strong>
                                                </td>
                                                <td>
                                                    <?php echo !empty($swimmer['responsible_email']) ? esc_html((string) $swimmer['responsible_email']) : '<span class="description">' . esc_html__('Aucun email', 'ecole2nat') . '</span>'; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    echo (int) ($swimmer['parent_access_enabled'] ?? 0) === 1
                                                        ? '<span class="e2n-badge e2n-badge--active">' . esc_html__('Actif', 'ecole2nat') . '</span>'
                                                        : '<span class="e2n-badge e2n-badge--inactive">' . esc_html__('Non généré', 'ecole2nat') . '</span>';
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($swimmer['parent_access_distributed_at'])) : ?>
                                                        <?php echo esc_html(wp_date('d/m/Y H:i', strtotime((string) $swimmer['parent_access_distributed_at']))); ?>
                                                        <?php if (!empty($swimmer['parent_access_distributed_to'])) : ?>
                                                            <br><span class="description"><?php echo esc_html((string) $swimmer['parent_access_distributed_to']); ?></span>
                                                        <?php endif; ?>
                                                    <?php else : ?>
                                                        —
                                                    <?php endif; ?>
                                                </td>
                                                <td class="e2n-table-actions">
                                                    <a href="<?php echo esc_url($singleUrl); ?>">
                                                        <?php esc_html_e('Gérer', 'ecole2nat'); ?>
                                                    </a>
                                                    <?php if ($previewUrl !== '') : ?>
                                                        | <a href="<?php echo esc_url($previewUrl); ?>" target="_blank" rel="noopener noreferrer">
                                                            <?php esc_html_e('Voir le parcours', 'ecole2nat'); ?>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($batch !== []) : ?>
                <?php $this->renderCouponBatch($batch); ?>
            <?php endif; ?>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const toggle = document.getElementById('e2n-select-all-parent-access');
                if (!toggle) return;
                toggle.addEventListener('change', function () {
                    document.querySelectorAll('.e2n-parent-row-checkbox').forEach(function (box) {
                        box.checked = toggle.checked;
                    });
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

        $action = isset($_POST['e2n_action'])
            ? sanitize_key(wp_unslash($_POST['e2n_action']))
            : '';
        $groupId = isset($_POST['group_id']) ? absint($_POST['group_id']) : 0;

        if (!in_array($action, ['send_missing_group', 'send_selected', 'prepare_coupons', 'clear_coupon_batch'], true)) {
            return;
        }

        check_admin_referer('e2n_parent_distribution_bulk');

        if ($action === 'clear_coupon_batch') {
            $this->distributionService->clearBatch(get_current_user_id());
            $this->redirect($groupId, 'batch_cleared');
        }

        $ids = isset($_POST['swimmer_ids']) && is_array($_POST['swimmer_ids'])
            ? array_map('absint', wp_unslash($_POST['swimmer_ids']))
            : [];

        if ($action === 'send_missing_group') {
            $result = $this->distributionService->sendMissingByGroup($groupId);
            $this->storeResult($result);
            $this->redirect($groupId, 'emails_processed');
        }

        if ($ids === []) {
            $this->redirect($groupId, 'nothing_selected');
        }

        if ($action === 'send_selected') {
            $result = $this->distributionService->sendSelected($ids);
            $this->storeResult($result);
            $this->redirect($groupId, 'emails_processed');
        }

        if ($action === 'prepare_coupons') {
            $result = $this->distributionService->prepareCoupons(
                $ids,
                get_current_user_id()
            );
            $this->redirect($groupId, $result['message']);
        }
    }

    private function renderNotice(): void
    {
        $notice = isset($_GET['e2n_notice'])
            ? sanitize_key(wp_unslash($_GET['e2n_notice']))
            : '';
        $messages = [
            'coupons_ready' => ['success', __('Les coupons sont prêts. Les nouveaux codes sont visibles plus bas pendant 30 minutes.', 'ecole2nat')],
            'batch_cleared' => ['success', __('Le lot temporaire de coupons a été effacé.', 'ecole2nat')],
            'nothing_selected' => ['warning', __('Sélectionnez au moins un nageur.', 'ecole2nat')],
            'missing_portal' => ['error', __('Créez d’abord la page publique contenant [e2n_parent_report].', 'ecole2nat')],
            'error' => ['error', __('Une erreur est survenue.', 'ecole2nat')],
        ];

        if ($notice === 'emails_processed') {
            $result = get_transient($this->resultTransientKey());
            delete_transient($this->resultTransientKey());

            if (is_array($result)) {
                $message = sprintf(
                    __('Envoi terminé : %1$d envoyé(s), %2$d échec(s), dont %3$d sans email valide.', 'ecole2nat'),
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

    private function renderCouponBatch(array $batch): void
    {
        $csvUrl = wp_nonce_url(
            admin_url('admin-post.php?action=e2n_parent_distribution_csv'),
            'e2n_parent_distribution_csv'
        );
        ?>
        <div class="postbox e2n-parent-coupon-batch">
            <div class="postbox-header">
                <h2 class="hndle"><?php esc_html_e('Lot de coupons', 'ecole2nat'); ?></h2>
            </div>
            <div class="inside">
                <p class="e2n-parent-batch-actions">
                    <button type="button" class="button button-primary" onclick="window.print()">
                        <?php esc_html_e('Imprimer les coupons', 'ecole2nat'); ?>
                    </button>
                    <a href="<?php echo esc_url($csvUrl); ?>" class="button">
                        <?php esc_html_e('Télécharger le CSV', 'ecole2nat'); ?>
                    </a>
                </p>

                <form method="post" class="e2n-parent-batch-clear">
                    <?php wp_nonce_field('e2n_parent_distribution_bulk'); ?>
                    <input type="hidden" name="e2n_action" value="clear_coupon_batch">
                    <input type="hidden" name="group_id" value="<?php echo esc_attr((string) (isset($_GET['group_id']) ? absint($_GET['group_id']) : 0)); ?>">
                    <?php submit_button(__('Effacer le lot temporaire', 'ecole2nat'), 'secondary', 'submit', false); ?>
                </form>

                <div class="e2n-parent-coupons">
                    <?php foreach ($batch as $row) : ?>
                        <article class="e2n-parent-coupon">
                            <p class="e2n-parent-coupon-brand">Ecole2Nat'</p>
                            <h3><?php esc_html_e('Mon parcours de natation', 'ecole2nat'); ?></h3>
                            <p class="e2n-parent-coupon-swimmer">
                                <?php echo esc_html((string) $row['first_name'] . ' ' . strtoupper((string) $row['last_name'])); ?>
                            </p>
                            <?php if (!empty($row['group_name'])) : ?>
                                <p><?php echo esc_html((string) $row['group_name']); ?></p>
                            <?php endif; ?>
                            <p class="e2n-parent-coupon-code"><?php echo esc_html((string) $row['code']); ?></p>
                            <p class="e2n-parent-coupon-url"><?php echo esc_html((string) $row['portal_url']); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function defaultGroupId(array $groups): int
    {
        return $groups !== [] ? (int) $groups[0]['id'] : 0;
    }

    private function storeResult(array $result): void
    {
        set_transient(
            $this->resultTransientKey(),
            $result,
            10 * MINUTE_IN_SECONDS
        );
    }

    private function resultTransientKey(): string
    {
        return 'e2n_parent_distribution_result_' . get_current_user_id();
    }

    private function redirect(int $groupId, string $notice): void
    {
        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => 'ecole2nat-parent-distribution',
                    'group_id' => $groupId,
                    'e2n_notice' => $notice,
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }
}
