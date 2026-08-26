<?php

namespace Ecole2Nat\Admin\Pages;

use Ecole2Nat\Coach\CoachAccessService;
use Ecole2Nat\Group\GroupService;

if (!defined('ABSPATH')) {
    exit;
}

class CoachPage
{
    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Accès refusé.', 'ecole2nat'));
        }

        $service = new CoachAccessService();
        $notice = $this->handlePost($service);
        $users = get_users(['orderby' => 'display_name']);
        $groups = (new GroupService())->active();
        ?>
        <div class="wrap e2n-admin">
            <h1 class="wp-heading-inline"><?php esc_html_e('Coachs', 'ecole2nat'); ?></h1>
            <?php $coachPageId=(int)get_option('e2n_coach_page_id',0);$coachUrl=$coachPageId>0?get_permalink($coachPageId):false;if(is_string($coachUrl)&&$coachUrl!==''): ?><a class="page-title-action" href="<?php echo esc_url($coachUrl); ?>"><?php esc_html_e('Ouvrir le portail Coach','ecole2nat'); ?></a><?php endif; ?>
            <hr class="wp-header-end">
            <?php if ($notice === 'saved') : ?>
                <div class="notice notice-success"><p><?php esc_html_e('Modifications enregistrées.', 'ecole2nat'); ?></p></div>
            <?php elseif ($notice === 'error') : ?>
                <div class="notice notice-error"><p><?php esc_html_e('La modification n’a pas pu être enregistrée.', 'ecole2nat'); ?></p></div>
            <?php endif; ?>

            <p><?php esc_html_e('Tous les coachs peuvent consulter les groupes actifs et mettre à jour les progressions. Les titulaires servent uniquement à présenter les intervenants habituels dans la semaine type.', 'ecole2nat'); ?></p>

            <h2><?php esc_html_e('Titulaires', 'ecole2nat'); ?></h2>
            <table class="widefat striped e2n-sortable-table">
                <thead><tr><th><?php esc_html_e('Utilisateur', 'ecole2nat'); ?></th><th><?php esc_html_e('Statut', 'ecole2nat'); ?></th><th><?php esc_html_e('Groupes titulaires', 'ecole2nat'); ?></th><th><?php esc_html_e('Actions', 'ecole2nat'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($users as $user) :
                    $isCoach = user_can($user, 'e2n_coach_access');
                    $assigned = $service->titularGroupIds((int) $user->ID);
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($user->display_name); ?></strong><br><small><?php echo esc_html($user->user_email); ?></small></td>
                        <td><?php echo $isCoach ? esc_html__('Coach', 'ecole2nat') : '—'; ?></td>
                        <td>
                            <?php if ($isCoach) : ?>
                                <form method="post">
                                    <?php wp_nonce_field('e2n_coach_admin'); ?>
                                    <input type="hidden" name="e2n_coach_action" value="groups">
                                    <input type="hidden" name="user_id" value="<?php echo (int) $user->ID; ?>">
                                    <?php foreach ($groups as $group) : ?>
                                        <label style="display:block">
                                            <input type="checkbox" name="group_ids[]" value="<?php echo (int) $group['id']; ?>" <?php checked(in_array((int) $group['id'], $assigned, true)); ?>>
                                            <?php echo esc_html($group['name']); ?>
                                        </label>
                                    <?php endforeach; ?>
                                    <button class="button" type="submit"><?php esc_html_e('Enregistrer les groupes', 'ecole2nat'); ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post">
                                <?php wp_nonce_field('e2n_coach_admin'); ?>
                                <input type="hidden" name="user_id" value="<?php echo (int) $user->ID; ?>">
                                <input type="hidden" name="e2n_coach_action" value="<?php echo $isCoach ? 'disable' : 'enable'; ?>">
                                <button class="button" type="submit"><?php echo esc_html($isCoach ? __('Retirer le rôle Coach', 'ecole2nat') : __('Définir comme Coach', 'ecole2nat')); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

        </div>
        <?php
    }

    private function handlePost(CoachAccessService $service): string
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['e2n_coach_action'])) {
            return '';
        }

        check_admin_referer('e2n_coach_admin');
        $action = sanitize_key(wp_unslash((string) $_POST['e2n_coach_action']));

        $userId = absint($_POST['user_id'] ?? 0);
        $user = $userId > 0 ? get_user_by('id', $userId) : false;
        if (!$user instanceof \WP_User) {
            return 'error';
        }

        if ($action === 'enable') {
            $user->add_role('e2n_coach');
            return 'saved';
        }

        if ($action === 'disable') {
            $user->remove_role('e2n_coach');
            return $service->clearUserAccess($userId) ? 'saved' : 'error';
        }

        if ($action === 'groups') {
            return $service->saveAssignments($userId, (array) ($_POST['group_ids'] ?? [])) ? 'saved' : 'error';
        }

        return 'error';
    }
}
