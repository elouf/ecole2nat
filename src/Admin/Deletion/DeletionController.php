<?php

namespace Ecole2Nat\Admin\Deletion;

if (!defined('ABSPATH')) {
    exit;
}

final class DeletionController
{
    public function register(): void
    {
        add_action('admin_post_e2n_delete_entity', [$this, 'handle']);
        add_action('admin_notices', [$this, 'renderNotice']);
    }

    public function handle(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Vous n’avez pas les droits nécessaires.', 'ecole2nat'));
        }

        $type = isset($_GET['entity_type']) ? sanitize_key(wp_unslash($_GET['entity_type'])) : '';
        $id = isset($_GET['entity_id']) ? absint($_GET['entity_id']) : 0;
        check_admin_referer('e2n_delete_' . $type . '_' . $id);

        $service = new EntityDeletionService();
        $result = $service->delete($type, $id);

        $redirect = isset($_GET['redirect'])
            ? wp_validate_redirect(wp_unslash($_GET['redirect']), admin_url('admin.php?page=ecole2nat'))
            : admin_url('admin.php?page=ecole2nat');

        $arguments = ['e2n_notice' => $result['message'] ?? 'error'];
        if (!empty($result['reason'])) {
            set_transient('e2n_delete_reason_' . get_current_user_id(), (string) $result['reason'], 60);
        }

        wp_safe_redirect(add_query_arg($arguments, $redirect));
        exit;
    }



    public function renderNotice(): void
    {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $notice = isset($_GET['e2n_notice']) ? sanitize_key(wp_unslash($_GET['e2n_notice'])) : '';
        if (strpos($page, 'ecole2nat') !== 0 || !in_array($notice, ['deleted', 'delete_blocked'], true)) {
            return;
        }

        if ($notice === 'deleted') {
            echo '<div class="notice notice-success is-dismissible"><p>'
                . esc_html__('L’élément a bien été supprimé.', 'ecole2nat')
                . '</p></div>';
            return;
        }

        $key = 'e2n_delete_reason_' . get_current_user_id();
        $reason = get_transient($key);
        delete_transient($key);
        echo '<div class="notice notice-error is-dismissible"><p>'
            . esc_html($reason ?: __('La suppression est impossible car cet élément est encore utilisé.', 'ecole2nat'))
            . '</p></div>';
    }
    public static function url(string $type, int $id, string $redirect): string
    {
        return wp_nonce_url(
            add_query_arg([
                'action' => 'e2n_delete_entity',
                'entity_type' => $type,
                'entity_id' => $id,
                'redirect' => $redirect,
            ], admin_url('admin-post.php')),
            'e2n_delete_' . $type . '_' . $id
        );
    }
}
