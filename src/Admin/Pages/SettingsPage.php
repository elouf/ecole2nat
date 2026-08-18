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
        $parentPortalTitle = Config::portalTitle();
        $parentPortalLogoId = Config::portalLogoId();
        $parentPortalLogo = $parentPortalLogoId > 0
            ? wp_get_attachment_image($parentPortalLogoId, 'thumbnail', false, ['class' => 'e2n-parent-brand-preview-image'])
            : '';
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
                    <tr>
                        <th scope="row"><label for="e2n-portal-title"><?php esc_html_e('Nom des portails', 'ecole2nat'); ?></label></th>
                        <td>
                            <input id="e2n-portal-title" name="portal_title" type="text" class="regular-text" value="<?php echo esc_attr($parentPortalTitle); ?>" required>
                            <p class="description"><?php esc_html_e('Nom affiché à côté du logo dans les en-têtes Coach et Parents.', 'ecole2nat'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Logo des portails', 'ecole2nat'); ?></th>
                        <td>
                            <input type="hidden" name="portal_logo_id" value="<?php echo (int) $parentPortalLogoId; ?>" data-e2n-parent-logo-id>
                            <div class="e2n-parent-brand-preview" data-e2n-parent-logo-preview><?php echo $parentPortalLogo !== '' ? wp_kses_post($parentPortalLogo) : '<span>E2N</span>'; ?></div>
                            <p>
                                <button type="button" class="button" data-e2n-parent-logo-select><?php esc_html_e('Choisir un logo', 'ecole2nat'); ?></button>
                                <button type="button" class="button-link-delete" data-e2n-parent-logo-remove <?php echo $parentPortalLogoId <= 0 ? 'hidden' : ''; ?>><?php esc_html_e('Retirer le logo', 'ecole2nat'); ?></button>
                            </p>
                            <p class="description"><?php esc_html_e('Choisissez une image de la médiathèque WordPress. Sans image, le monogramme E2N est utilisé dans les deux portails.', 'ecole2nat'); ?></p>
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

        $parentPortalTitle = sanitize_text_field(wp_unslash((string) ($_POST['portal_title'] ?? '')));
        if (trim($parentPortalTitle) === '') {
            $parentPortalTitle = Config::DEFAULT_PORTAL_TITLE;
        }

        $parentPortalLogoId = absint($_POST['portal_logo_id'] ?? 0);
        if ($parentPortalLogoId > 0 && !wp_attachment_is_image($parentPortalLogoId)) {
            $parentPortalLogoId = 0;
        }

        update_option(Config::option('portal_title'), $parentPortalTitle);
        update_option(Config::option('portal_logo_id'), $parentPortalLogoId);
        return true;
    }
}
