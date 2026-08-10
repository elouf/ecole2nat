<?php

namespace Ecole2Nat\Admin\Pages;

use Ecole2Nat\Database\PurgeService;

if (!defined('ABSPATH')) {
    exit;
}

final class MaintenancePage
{
    private const CONFIRMATION = 'PURGER ECOLE2NAT';

    private PurgeService $purgeService;

    public function __construct()
    {
        $this->purgeService = new PurgeService();
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('Vous n’avez pas les droits nécessaires.', 'ecole2nat')
            );
        }

        $this->handleActions();
        $counts = $this->purgeService->counts();
        $totalRows = array_sum($counts);

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Maintenance', 'ecole2nat'); ?></h1>

            <?php $this->renderNotice(); ?>

            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle">
                        <?php esc_html_e('Purger toutes les données', 'ecole2nat'); ?>
                    </h2>
                </div>

                <div class="inside">
                    <div class="notice notice-error inline">
                        <p>
                            <strong>
                                <?php esc_html_e('Attention : cette opération est irréversible.', 'ecole2nat'); ?>
                            </strong>
                        </p>
                        <p>
                            <?php esc_html_e(
                                'Toutes les données Ecole2Nat’ seront supprimées : saisons, catégories, référentiel, exercices, groupes, nageurs, séances, évaluations, accès parents et journaux de synchronisation.',
                                'ecole2nat'
                            ); ?>
                        </p>
                        <p>
                            <?php esc_html_e(
                                'Le plugin, ses tables et sa configuration technique resteront installés afin de pouvoir repartir immédiatement sur une base vide.',
                                'ecole2nat'
                            ); ?>
                        </p>
                    </div>

                    <p>
                        <?php
                        echo esc_html(
                            sprintf(
                                _n(
                                    '%d ligne de données sera supprimée dans %d table.',
                                    '%d lignes de données seront supprimées dans %d tables.',
                                    $totalRows,
                                    'ecole2nat'
                                ),
                                $totalRows,
                                count($counts)
                            )
                        );
                        ?>
                    </p>

                    <?php if ($counts !== []) : ?>
                        <details style="margin: 16px 0;">
                            <summary style="cursor:pointer;">
                                <?php esc_html_e('Voir le détail des données détectées', 'ecole2nat'); ?>
                            </summary>

                            <table class="widefat striped" style="max-width:720px;margin-top:12px;">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Table', 'ecole2nat'); ?></th>
                                        <th><?php esc_html_e('Lignes', 'ecole2nat'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($counts as $table => $count) : ?>
                                        <tr>
                                            <td><code><?php echo esc_html($table); ?></code></td>
                                            <td><?php echo esc_html((string) $count); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </details>
                    <?php endif; ?>

                    <form method="post" style="margin-top:24px;">
                        <?php wp_nonce_field('e2n_purge_all_data'); ?>

                        <input
                            type="hidden"
                            name="e2n_action"
                            value="purge_all_data"
                        >

                        <p>
                            <label for="e2n-purge-confirmation">
                                <strong>
                                    <?php
                                    echo esc_html(
                                        sprintf(
                                            __('Pour confirmer, saisissez exactement : %s', 'ecole2nat'),
                                            self::CONFIRMATION
                                        )
                                    );
                                    ?>
                                </strong>
                            </label>
                        </p>

                        <p>
                            <input
                                id="e2n-purge-confirmation"
                                type="text"
                                name="purge_confirmation"
                                class="regular-text"
                                autocomplete="off"
                                required
                            >
                        </p>

                        <p>
                            <label>
                                <input
                                    type="checkbox"
                                    name="purge_acknowledged"
                                    value="1"
                                    required
                                >
                                <?php esc_html_e(
                                    'Je comprends que toutes les données seront définitivement supprimées.',
                                    'ecole2nat'
                                ); ?>
                            </label>
                        </p>

                        <?php
                        submit_button(
                            __('Purger définitivement toutes les données', 'ecole2nat'),
                            'delete',
                            'submit',
                            false
                        );
                        ?>
                    </form>
                </div>
            </div>
        </div>
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

        if ($action !== 'purge_all_data') {
            return;
        }

        check_admin_referer('e2n_purge_all_data');

        $confirmation = isset($_POST['purge_confirmation'])
            ? sanitize_text_field(wp_unslash($_POST['purge_confirmation']))
            : '';

        $acknowledged = isset($_POST['purge_acknowledged'])
            && (string) $_POST['purge_acknowledged'] === '1';

        if ($confirmation !== self::CONFIRMATION || !$acknowledged) {
            $this->redirect('confirmation_error');
        }

        $result = $this->purgeService->purge();

        set_transient(
            'e2n_purge_result_' . get_current_user_id(),
            $result,
            5 * MINUTE_IN_SECONDS
        );

        $this->redirect($result['success'] ? 'purged' : 'purge_error');
    }

    private function renderNotice(): void
    {
        $notice = isset($_GET['e2n_notice'])
            ? sanitize_key(wp_unslash($_GET['e2n_notice']))
            : '';

        $messages = [
            'purged' => [
                'success',
                __('Toutes les données Ecole2Nat’ ont été supprimées.', 'ecole2nat'),
            ],
            'confirmation_error' => [
                'error',
                __('La confirmation est incorrecte. Aucune donnée n’a été supprimée.', 'ecole2nat'),
            ],
            'purge_error' => [
                'error',
                __('La purge a rencontré une erreur. Consultez le détail ci-dessous.', 'ecole2nat'),
            ],
        ];

        if (isset($messages[$notice])) {
            [$type, $message] = $messages[$notice];
            ?>
            <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
                <p><?php echo esc_html($message); ?></p>
            </div>
            <?php
        }

        $key = 'e2n_purge_result_' . get_current_user_id();
        $result = get_transient($key);

        if (!is_array($result)) {
            return;
        }

        delete_transient($key);

        if (!empty($result['success'])) {
            ?>
            <div class="notice notice-info">
                <p>
                    <?php
                    echo esc_html(
                        sprintf(
                            __('%1$d lignes supprimées dans %2$d tables.', 'ecole2nat'),
                            (int) ($result['purged_rows'] ?? 0),
                            (int) ($result['purged_tables'] ?? 0)
                        )
                    );
                    ?>
                </p>
            </div>
            <?php
        }

        if (!empty($result['errors']) && is_array($result['errors'])) {
            ?>
            <div class="notice notice-error">
                <ul>
                    <?php foreach ($result['errors'] as $error) : ?>
                        <li><?php echo esc_html((string) $error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php
        }
    }

    private function redirect(string $notice): never
    {
        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => 'ecole2nat-maintenance',
                    'e2n_notice' => $notice,
                ],
                admin_url('admin.php')
            )
        );

        exit;
    }
}
