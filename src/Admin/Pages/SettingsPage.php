<?php

namespace Ecole2Nat\Admin\Pages;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class SettingsPage
{
    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Accès refusé.', 'ecole2nat'));
        }

        $saved = $this->handlePost();
        $signature = Config::parentEmailSignature();
        ?>
        <div class="wrap e2n-admin">
            <h1><?php esc_html_e('Réglages Ecole2Nat’', 'ecole2nat'); ?></h1>
            <?php if ($saved) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Réglages enregistrés.', 'ecole2nat'); ?></p></div><?php endif; ?>
            <form method="post">
                <?php wp_nonce_field('e2n_save_settings'); ?>
                <input type="hidden" name="e2n_action" value="save_settings">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="e2n-parent-email-signature"><?php esc_html_e('Signature des emails Parents', 'ecole2nat'); ?></label></th>
                        <td>
                            <textarea id="e2n-parent-email-signature" name="parent_email_signature" rows="4" class="large-text" required><?php echo esc_textarea($signature); ?></textarea>
                            <p class="description"><?php esc_html_e('Cette signature termine tous les emails contenant un code d’accès Parents, y compris ceux envoyés depuis le portail Coach.', 'ecole2nat'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Enregistrer les réglages', 'ecole2nat')); ?>
            </form>
        </div>
        <?php
    }

    private function handlePost(): bool
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['e2n_action'] ?? '') !== 'save_settings') {
            return false;
        }

        check_admin_referer('e2n_save_settings');
        $signature = sanitize_textarea_field(wp_unslash((string) ($_POST['parent_email_signature'] ?? '')));
        if (trim($signature) === '') {
            $signature = Config::DEFAULT_PARENT_EMAIL_SIGNATURE;
        }
        update_option(Config::option('parent_email_signature'), $signature);
        return true;
    }
}
