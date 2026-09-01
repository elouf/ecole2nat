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
        $invoiceLogoId = Config::invoiceLogoId();
        $invoiceLogo = $invoiceLogoId > 0
            ? wp_get_attachment_image($invoiceLogoId, 'thumbnail', false, ['class' => 'e2n-parent-brand-preview-image'])
            : '';
        $invoiceRibId = Config::invoiceRibId();
        $invoiceRibName = $invoiceRibId > 0 ? basename((string) get_attached_file($invoiceRibId)) : '';
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
                    <tr><th colspan="2"><h2><?php esc_html_e('Facturation des compétitions', 'ecole2nat'); ?></h2></th></tr>
                    <tr>
                        <th scope="row"><label for="e2n-invoice-meal-price"><?php esc_html_e('Prix d’un repas', 'ecole2nat'); ?></label></th>
                        <td><input id="e2n-invoice-meal-price" name="invoice_meal_price" type="number" min="0" step="0.01" value="<?php echo esc_attr(Config::invoiceMealPrice()); ?>"> €</td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="e2n-invoice-night-price"><?php esc_html_e('Prix d’une nuitée', 'ecole2nat'); ?></label></th>
                        <td><input id="e2n-invoice-night-price" name="invoice_night_price" type="number" min="0" step="0.01" value="<?php echo esc_attr(Config::invoiceNightPrice()); ?>"> €</td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="e2n-invoice-issuer-name"><?php esc_html_e('Nom de l’émetteur', 'ecole2nat'); ?></label></th>
                        <td><input id="e2n-invoice-issuer-name" name="invoice_issuer_name" type="text" class="regular-text" value="<?php echo esc_attr(Config::invoiceIssuerName()); ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="e2n-invoice-issuer-address"><?php esc_html_e('Adresse de l’émetteur', 'ecole2nat'); ?></label></th>
                        <td><textarea id="e2n-invoice-issuer-address" name="invoice_issuer_address" rows="4" class="large-text" required><?php echo esc_textarea(Config::invoiceIssuerAddress()); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="e2n-invoice-issuer-siret"><?php esc_html_e('SIRET', 'ecole2nat'); ?></label></th>
                        <td><input id="e2n-invoice-issuer-siret" name="invoice_issuer_siret" type="text" class="regular-text" value="<?php echo esc_attr(Config::invoiceIssuerSiret()); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="e2n-treasurer-email"><?php esc_html_e('Email de la trésorière', 'ecole2nat'); ?></label></th>
                        <td><input id="e2n-treasurer-email" name="treasurer_email" type="email" class="regular-text" value="<?php echo esc_attr(Config::treasurerEmail()); ?>" required><p class="description"><?php esc_html_e('Cette adresse recevra les déclarations de paiement depuis le portail Nageurs.', 'ecole2nat'); ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Logo des factures', 'ecole2nat'); ?></th>
                        <td>
                            <input type="hidden" name="invoice_logo_id" value="<?php echo (int) $invoiceLogoId; ?>" data-e2n-invoice-logo-id>
                            <div class="e2n-parent-brand-preview" data-e2n-invoice-logo-preview><?php echo $invoiceLogo !== '' ? wp_kses_post($invoiceLogo) : '<span>E2N</span>'; ?></div>
                            <p><button type="button" class="button" data-e2n-invoice-logo-select><?php esc_html_e('Choisir un logo', 'ecole2nat'); ?></button> <button type="button" class="button-link-delete" data-e2n-invoice-logo-remove <?php echo $invoiceLogoId <= 0 ? 'hidden' : ''; ?>><?php esc_html_e('Retirer le logo', 'ecole2nat'); ?></button></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('RIB du club', 'ecole2nat'); ?></th>
                        <td>
                            <input type="hidden" name="invoice_rib_id" value="<?php echo (int) $invoiceRibId; ?>" data-e2n-invoice-rib-id>
                            <p data-e2n-invoice-rib-name><?php echo $invoiceRibName !== '' ? esc_html($invoiceRibName) : esc_html__('Aucun RIB sélectionné.', 'ecole2nat'); ?></p>
                            <p><button type="button" class="button" data-e2n-invoice-rib-select><?php esc_html_e('Choisir un PDF', 'ecole2nat'); ?></button> <button type="button" class="button-link-delete" data-e2n-invoice-rib-remove <?php echo $invoiceRibId <= 0 ? 'hidden' : ''; ?>><?php esc_html_e('Retirer le RIB', 'ecole2nat'); ?></button></p>
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

        update_option(Config::option('invoice_meal_price'), $this->decimal($_POST['invoice_meal_price'] ?? '', Config::DEFAULT_INVOICE_MEAL_PRICE));
        update_option(Config::option('invoice_night_price'), $this->decimal($_POST['invoice_night_price'] ?? '', Config::DEFAULT_INVOICE_NIGHT_PRICE));
        update_option(Config::option('invoice_issuer_name'), sanitize_text_field(wp_unslash((string) ($_POST['invoice_issuer_name'] ?? Config::DEFAULT_INVOICE_ISSUER_NAME))));
        update_option(Config::option('invoice_issuer_address'), sanitize_textarea_field(wp_unslash((string) ($_POST['invoice_issuer_address'] ?? Config::DEFAULT_INVOICE_ISSUER_ADDRESS))));
        update_option(Config::option('invoice_issuer_siret'), sanitize_text_field(wp_unslash((string) ($_POST['invoice_issuer_siret'] ?? Config::DEFAULT_INVOICE_ISSUER_SIRET))));
        $treasurerEmail = sanitize_email(wp_unslash((string) ($_POST['treasurer_email'] ?? '')));
        update_option(Config::option('treasurer_email'), is_email($treasurerEmail) ? $treasurerEmail : Config::DEFAULT_TREASURER_EMAIL);

        $invoiceLogoId = absint($_POST['invoice_logo_id'] ?? 0);
        update_option(Config::option('invoice_logo_id'), $invoiceLogoId > 0 && wp_attachment_is_image($invoiceLogoId) ? $invoiceLogoId : 0);
        $invoiceRibId = absint($_POST['invoice_rib_id'] ?? 0);
        update_option(Config::option('invoice_rib_id'), $invoiceRibId > 0 && get_post_mime_type($invoiceRibId) === 'application/pdf' ? $invoiceRibId : 0);
        return true;
    }

    private function decimal(mixed $value, string $default): string
    {
        $value = str_replace(',', '.', trim((string) wp_unslash($value)));
        return is_numeric($value) && (float) $value >= 0 ? number_format((float) $value, 2, '.', '') : $default;
    }
}
