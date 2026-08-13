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
        $coaches = array_values(array_filter(
            $users,
            static fn(\WP_User $user): bool => user_can($user, 'e2n_coach_access')
        ));
        $groups = (new GroupService())->active();
        $substitutions = $service->substitutions(current_time('Y-m-d'));
        ?>
        <div class="wrap e2n-admin">
            <h1><?php esc_html_e('Coachs', 'ecole2nat'); ?></h1>
            <?php if ($notice === 'saved') : ?>
                <div class="notice notice-success"><p><?php esc_html_e('Modifications enregistrées.', 'ecole2nat'); ?></p></div>
            <?php elseif ($notice === 'error') : ?>
                <div class="notice notice-error"><p><?php esc_html_e('La modification n’a pas pu être enregistrée.', 'ecole2nat'); ?></p></div>
            <?php endif; ?>

            <p><?php esc_html_e('Tous les coachs peuvent consulter les groupes actifs. Les titulaires peuvent modifier leurs groupes en permanence ; les remplaçants uniquement le jour prévu.', 'ecole2nat'); ?></p>

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

            <h2><?php esc_html_e('Remplacements datés', 'ecole2nat'); ?></h2>
            <p><?php esc_html_e('Le coach remplaçant peut modifier le groupe uniquement à la date indiquée. Il doit déjà posséder le rôle Coach.', 'ecole2nat'); ?></p>
            <?php if ($coaches !== [] && $groups !== []) : ?>
                <form method="post" class="e2n-filter-bar">
                    <?php wp_nonce_field('e2n_coach_admin'); ?>
                    <input type="hidden" name="e2n_coach_action" value="add_substitution">
                    <label>
                        <span><?php esc_html_e('Coach remplaçant', 'ecole2nat'); ?></span>
                        <select name="user_id" required>
                            <option value=""><?php esc_html_e('Choisir un coach', 'ecole2nat'); ?></option>
                            <?php foreach ($coaches as $coach) : ?>
                                <option value="<?php echo (int) $coach->ID; ?>"><?php echo esc_html($coach->display_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span><?php esc_html_e('Groupe', 'ecole2nat'); ?></span>
                        <select name="group_id" required>
                            <option value=""><?php esc_html_e('Choisir un groupe', 'ecole2nat'); ?></option>
                            <?php foreach ($groups as $group) : ?>
                                <option value="<?php echo (int) $group['id']; ?>"><?php echo esc_html($group['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span><?php esc_html_e('Date', 'ecole2nat'); ?></span>
                        <input type="date" name="substitution_date" min="<?php echo esc_attr(current_time('Y-m-d')); ?>" required>
                    </label>
                    <button class="button button-primary" type="submit"><?php esc_html_e('Ajouter le remplacement', 'ecole2nat'); ?></button>
                </form>
            <?php else : ?>
                <p><?php esc_html_e('Créez au moins un coach et un groupe actif avant d’ajouter un remplacement.', 'ecole2nat'); ?></p>
            <?php endif; ?>

            <table class="widefat striped e2n-sortable-table">
                <thead><tr><th><?php esc_html_e('Date', 'ecole2nat'); ?></th><th><?php esc_html_e('Coach', 'ecole2nat'); ?></th><th><?php esc_html_e('Groupe', 'ecole2nat'); ?></th><th><?php esc_html_e('Actions', 'ecole2nat'); ?></th></tr></thead>
                <tbody>
                <?php if ($substitutions === []) : ?>
                    <tr><td colspan="4"><?php esc_html_e('Aucun remplacement à venir.', 'ecole2nat'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($substitutions as $substitution) : ?>
                        <tr>
                            <td data-e2n-sort-value="<?php echo esc_attr($substitution['substitution_date']); ?>"><?php echo esc_html(wp_date('d/m/Y', strtotime((string) $substitution['substitution_date']))); ?></td>
                            <td><strong><?php echo esc_html($substitution['user_name']); ?></strong><br><small><?php echo esc_html($substitution['user_email']); ?></small></td>
                            <td><?php echo esc_html($substitution['group_name'] . ' · ' . $substitution['season_name']); ?></td>
                            <td>
                                <form method="post">
                                    <?php wp_nonce_field('e2n_coach_admin'); ?>
                                    <input type="hidden" name="e2n_coach_action" value="delete_substitution">
                                    <input type="hidden" name="substitution_id" value="<?php echo (int) $substitution['id']; ?>">
                                    <button class="button e2n-delete-link" type="submit"><?php esc_html_e('Supprimer', 'ecole2nat'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
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

        if ($action === 'delete_substitution') {
            return $service->deleteSubstitution(absint($_POST['substitution_id'] ?? 0)) ? 'saved' : 'error';
        }

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

        if ($action === 'add_substitution') {
            $date = sanitize_text_field(wp_unslash((string) ($_POST['substitution_date'] ?? '')));
            if ($date < current_time('Y-m-d')) {
                return 'error';
            }

            return $service->addSubstitution(
                $userId,
                absint($_POST['group_id'] ?? 0),
                $date,
                get_current_user_id()
            ) ? 'saved' : 'error';
        }

        return 'error';
    }
}
