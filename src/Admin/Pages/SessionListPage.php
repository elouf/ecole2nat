<?php

namespace Ecole2Nat\Admin\Pages;

use Ecole2Nat\Session\SessionService;

if (!defined('ABSPATH')) {
    exit;
}

class SessionListPage
{
    private SessionService $sessionService;

    public function __construct()
    {
        $this->sessionService = new SessionService();
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Vous n’avez pas les droits nécessaires.', 'ecole2nat'));
        }

        $this->handleActions();
        $sessions = $this->sessionService->all();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Séances types', 'ecole2nat'); ?></h1>

            <?php $this->renderNotice(); ?>

            <p>
                <a
                    href="<?php echo esc_url(admin_url('admin.php?page=ecole2nat-session')); ?>"
                    class="button button-primary"
                >
                    <?php esc_html_e('Ajouter une séance', 'ecole2nat'); ?>
                </a>
            </p>

            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle"><?php esc_html_e('Séances existantes', 'ecole2nat'); ?></h2>
                </div>
                <div class="inside">
                    <?php $this->renderTable($sessions); ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function handleActions(): void
    {
        $action = isset($_GET['e2n_action'])
            ? sanitize_key(wp_unslash($_GET['e2n_action']))
            : '';
        $sessionId = isset($_GET['session_id']) ? absint($_GET['session_id']) : 0;

        if ($action === '' || $sessionId <= 0) {
            return;
        }

        if ($action === 'toggle_session') {
            check_admin_referer('e2n_toggle_session_' . $sessionId);
            $updated = $this->sessionService->toggleActive($sessionId);
            $this->redirect($updated ? 'updated' : 'error');
        }

        if ($action === 'duplicate_session') {
            check_admin_referer('e2n_duplicate_session_' . $sessionId);
            $result = $this->sessionService->duplicate($sessionId);

            if ($result['success'] && (int) $result['id'] > 0) {
                wp_safe_redirect(
                    add_query_arg(
                        [
                            'page' => 'ecole2nat-session',
                            'session_id' => (int) $result['id'],
                            'e2n_notice' => 'duplicated',
                        ],
                        admin_url('admin.php')
                    )
                );
                exit;
            }

            $this->redirect('error');
        }
    }

    private function renderTable(array $sessions): void
    {
        if ($sessions === []) {
            echo '<p>' . esc_html__('Aucune séance type n’a encore été créée.', 'ecole2nat') . '</p>';
            return;
        }
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Nom', 'ecole2nat'); ?></th>
                    <th><?php esc_html_e('Catégorie', 'ecole2nat'); ?></th>
                    <th><?php esc_html_e('Parties', 'ecole2nat'); ?></th>
                    <th><?php esc_html_e('Durée', 'ecole2nat'); ?></th>
                    <th><?php esc_html_e('Statut', 'ecole2nat'); ?></th>
                    <th><?php esc_html_e('Actions', 'ecole2nat'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $session) : ?>
                    <?php
                    $sessionId = (int) $session['id'];
                    $isActive = (int) $session['is_active'] === 1;
                    $editUrl = add_query_arg(
                        ['page' => 'ecole2nat-session', 'session_id' => $sessionId],
                        admin_url('admin.php')
                    );
                    $printUrl = add_query_arg(
                        ['page' => 'ecole2nat-session-print', 'session_id' => $sessionId],
                        admin_url('admin.php')
                    );
                    $duplicateUrl = wp_nonce_url(
                        add_query_arg(
                            [
                                'page' => 'ecole2nat-sessions',
                                'e2n_action' => 'duplicate_session',
                                'session_id' => $sessionId,
                            ],
                            admin_url('admin.php')
                        ),
                        'e2n_duplicate_session_' . $sessionId
                    );
                    $toggleUrl = wp_nonce_url(
                        add_query_arg(
                            [
                                'page' => 'ecole2nat-sessions',
                                'e2n_action' => 'toggle_session',
                                'session_id' => $sessionId,
                            ],
                            admin_url('admin.php')
                        ),
                        'e2n_toggle_session_' . $sessionId
                    );
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($session['name']); ?></strong>
                            <?php if (!empty($session['objectives'])) : ?>
                                <p class="description"><?php echo esc_html($session['objectives']); ?></p>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($session['category_name']); ?></td>
                        <td><?php echo esc_html((string) ($session['parts_count'] ?? 0)); ?></td>
                        <td><?php echo esc_html((string) ($session['total_duration'] ?? 0)); ?> min</td>
                        <td>
                            <?php echo $isActive
                                ? esc_html__('Active', 'ecole2nat')
                                : esc_html__('Inactive', 'ecole2nat'); ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url($editUrl); ?>"><?php esc_html_e('Modifier', 'ecole2nat'); ?></a>
                            <span aria-hidden="true"> | </span>
                            <a href="<?php echo esc_url($duplicateUrl); ?>"><?php esc_html_e('Dupliquer', 'ecole2nat'); ?></a>
                            <span aria-hidden="true"> | </span>
                            <a href="<?php echo esc_url($printUrl); ?>" target="_blank"><?php esc_html_e('Imprimer', 'ecole2nat'); ?></a>
                            <span aria-hidden="true"> | </span>
                            <a href="<?php echo esc_url($toggleUrl); ?>">
                                <?php echo $isActive
                                    ? esc_html__('Désactiver', 'ecole2nat')
                                    : esc_html__('Activer', 'ecole2nat'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function renderNotice(): void
    {
        $notice = isset($_GET['e2n_notice'])
            ? sanitize_key(wp_unslash($_GET['e2n_notice']))
            : '';
        $messages = [
            'session_created' => ['success', __('La séance a bien été créée.', 'ecole2nat')],
            'updated' => ['success', __('Le statut de la séance a bien été modifié.', 'ecole2nat')],
            'error' => ['error', __('Une erreur est survenue.', 'ecole2nat')],
        ];

        if (!isset($messages[$notice])) {
            return;
        }

        [$type, $message] = $messages[$notice];
        echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>'
            . esc_html($message) . '</p></div>';
    }

    private function redirect(string $notice): void
    {
        wp_safe_redirect(
            add_query_arg(
                ['page' => 'ecole2nat-sessions', 'e2n_notice' => $notice],
                admin_url('admin.php')
            )
        );
        exit;
    }
}
