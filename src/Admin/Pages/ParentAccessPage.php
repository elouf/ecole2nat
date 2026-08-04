<?php

namespace Ecole2Nat\Admin\Pages;

use Ecole2Nat\ParentPortal\ParentAccessService;

if (!defined('ABSPATH')) {
    exit;
}

class ParentAccessPage
{
    private ParentAccessService $service;

    public function __construct()
    {
        $this->service = new ParentAccessService();
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Vous n’avez pas les droits nécessaires.', 'ecole2nat'));
        }

        $swimmerId = isset($_REQUEST['swimmer_id']) ? absint($_REQUEST['swimmer_id']) : 0;
        $swimmer = $this->service->findSwimmer($swimmerId);

        if ($swimmer === null) {
            wp_die(esc_html__('Nageur introuvable.', 'ecole2nat'));
        }

        $this->handleActions($swimmerId);
        $swimmer = $this->service->findSwimmer($swimmerId);
        $transientKey = $this->service->codeTransientKey(get_current_user_id(), $swimmerId);
        $code = get_transient($transientKey);
        delete_transient($transientKey);
        $portalUrl = $this->service->portalUrl();

        ?>
        <div class="wrap e2n-parent-access-admin">
            <h1>
                <?php
                echo esc_html(
                    sprintf(
                        __('Accès parents — %s %s', 'ecole2nat'),
                        $swimmer['first_name'],
                        strtoupper($swimmer['last_name'])
                    )
                );
                ?>
            </h1>

            <?php $this->renderNotice(); ?>

            <?php if (is_string($code) && $code !== '') : ?>
                <div class="notice notice-success inline e2n-parent-code-notice">
                    <p><strong><?php esc_html_e('Code à remettre aux parents :', 'ecole2nat'); ?></strong></p>
                    <p class="e2n-parent-code"><?php echo esc_html($code); ?></p>
                    <?php if ($portalUrl !== '') : ?>
                        <p class="e2n-parent-portal-url"><?php echo esc_html($portalUrl); ?></p>
                    <?php else : ?>
                        <p><strong><?php esc_html_e('Créez une page WordPress contenant le shortcode [e2n_parent_report] pour obtenir l’adresse du portail.', 'ecole2nat'); ?></strong></p>
                    <?php endif; ?>
                    <p><?php esc_html_e('Ce code ne sera plus affiché après avoir quitté cette page. Conservez-le ou imprimez-le maintenant.', 'ecole2nat'); ?></p>
                    <p><button type="button" class="button" onclick="window.print()"><?php esc_html_e('Imprimer le coupon', 'ecole2nat'); ?></button></p>
                </div>
            <?php endif; ?>

            <div class="postbox">
                <div class="postbox-header"><h2 class="hndle"><?php esc_html_e('Code d’accès', 'ecole2nat'); ?></h2></div>
                <div class="inside">
                    <p>
                        <strong><?php esc_html_e('Statut :', 'ecole2nat'); ?></strong>
                        <?php echo (int) $swimmer['parent_access_enabled'] === 1 ? esc_html__('Activé', 'ecole2nat') : esc_html__('Désactivé', 'ecole2nat'); ?>
                    </p>

                    <?php if (!empty($swimmer['parent_access_created_at'])) : ?>
                        <p><?php echo esc_html(sprintf(__('Code généré le %s', 'ecole2nat'), wp_date('d/m/Y à H:i', strtotime($swimmer['parent_access_created_at'])))); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($swimmer['parent_access_last_used_at'])) : ?>
                        <p><?php echo esc_html(sprintf(__('Dernier accès le %s · %d consultation(s)', 'ecole2nat'), wp_date('d/m/Y à H:i', strtotime($swimmer['parent_access_last_used_at'])), (int) $swimmer['parent_access_count'])); ?></p>
                    <?php endif; ?>

                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <form method="post">
                            <?php wp_nonce_field('e2n_generate_parent_access_' . $swimmerId); ?>
                            <input type="hidden" name="e2n_action" value="generate_parent_access">
                            <input type="hidden" name="swimmer_id" value="<?php echo esc_attr((string) $swimmerId); ?>">
                            <?php submit_button((int) $swimmer['parent_access_enabled'] === 1 ? __('Régénérer le code', 'ecole2nat') : __('Générer un code', 'ecole2nat'), 'primary', 'submit', false); ?>
                        </form>

                        <?php if ((int) $swimmer['parent_access_enabled'] === 1) : ?>
                            <form method="post" onsubmit="return confirm('Désactiver cet accès parent ?');">
                                <?php wp_nonce_field('e2n_disable_parent_access_' . $swimmerId); ?>
                                <input type="hidden" name="e2n_action" value="disable_parent_access">
                                <input type="hidden" name="swimmer_id" value="<?php echo esc_attr((string) $swimmerId); ?>">
                                <?php submit_button(__('Désactiver', 'ecole2nat'), 'secondary', 'submit', false); ?>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="postbox">
                <div class="postbox-header"><h2 class="hndle"><?php esc_html_e('Message visible par les parents', 'ecole2nat'); ?></h2></div>
                <div class="inside">
                    <form method="post">
                        <?php wp_nonce_field('e2n_save_parent_message_' . $swimmerId); ?>
                        <input type="hidden" name="e2n_action" value="save_parent_message">
                        <input type="hidden" name="swimmer_id" value="<?php echo esc_attr((string) $swimmerId); ?>">
                        <textarea name="parent_message" rows="6" class="large-text"><?php echo esc_textarea((string) ($swimmer['parent_message'] ?? '')); ?></textarea>
                        <p class="description"><?php esc_html_e('Les notes d’évaluation internes ne sont jamais affichées. Seul ce message est visible par les familles.', 'ecole2nat'); ?></p>
                        <?php submit_button(__('Enregistrer le message', 'ecole2nat')); ?>
                    </form>
                </div>
            </div>

            <p><a href="<?php echo esc_url(admin_url('admin.php?page=ecole2nat-swimmers')); ?>" class="button"><?php esc_html_e('Retour aux nageurs', 'ecole2nat'); ?></a></p>
        </div>
        <style>
            .e2n-parent-code{font:700 36px/1.2 monospace;letter-spacing:.18em;margin:10px 0}.e2n-parent-portal-url{font-size:18px;font-weight:600}
            @media print{#adminmenumain,#wpadminbar,#wpfooter,.postbox,.wrap>h1,.wrap>.notice:not(.e2n-parent-code-notice),.e2n-parent-code-notice .button{display:none!important}.e2n-parent-code-notice{display:block!important;border:0!important;box-shadow:none!important;margin:40px!important}.e2n-parent-code{font-size:48px}}
        </style>
        <?php
    }

    private function handleActions(int $swimmerId): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = isset($_POST['e2n_action']) ? sanitize_key(wp_unslash($_POST['e2n_action'])) : '';

        if ($action === 'generate_parent_access') {
            check_admin_referer('e2n_generate_parent_access_' . $swimmerId);
            $result = $this->service->generateCode($swimmerId);

            if ($result['success']) {
                set_transient(
                    $this->service->codeTransientKey(get_current_user_id(), $swimmerId),
                    $result['code'],
                    30 * MINUTE_IN_SECONDS
                );
            }

            $this->redirect($swimmerId, $result['message']);
        }

        if ($action === 'disable_parent_access') {
            check_admin_referer('e2n_disable_parent_access_' . $swimmerId);
            $result = $this->service->disable($swimmerId);
            delete_transient($this->service->codeTransientKey(get_current_user_id(), $swimmerId));
            $this->redirect($swimmerId, $result['message']);
        }

        if ($action === 'save_parent_message') {
            check_admin_referer('e2n_save_parent_message_' . $swimmerId);
            $message = isset($_POST['parent_message']) ? wp_unslash($_POST['parent_message']) : '';
            $result = $this->service->saveParentMessage($swimmerId, $message);
            $this->redirect($swimmerId, $result['message']);
        }
    }

    private function renderNotice(): void
    {
        $notice = isset($_GET['e2n_notice']) ? sanitize_key(wp_unslash($_GET['e2n_notice'])) : '';
        $messages = [
            'access_generated' => ['success', __('Le code d’accès a bien été généré.', 'ecole2nat')],
            'access_disabled' => ['success', __('L’accès parents a bien été désactivé.', 'ecole2nat')],
            'parent_message_saved' => ['success', __('Le message destiné aux parents a bien été enregistré.', 'ecole2nat')],
            'invalid' => ['error', __('Le nageur est introuvable.', 'ecole2nat')],
            'error' => ['error', __('Une erreur est survenue.', 'ecole2nat')],
        ];

        if (!isset($messages[$notice])) {
            return;
        }

        [$type, $message] = $messages[$notice];
        echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    private function redirect(int $swimmerId, string $notice): void
    {
        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => 'ecole2nat-parent-access',
                    'swimmer_id' => $swimmerId,
                    'e2n_notice' => $notice,
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }
}
