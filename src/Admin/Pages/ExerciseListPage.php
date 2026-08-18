<?php

namespace Ecole2Nat\Admin\Pages;

use Ecole2Nat\Admin\Deletion\DeletionController;
use Ecole2Nat\Exercise\ExerciseRepository;

if (!defined('ABSPATH')) { exit; }

class ExerciseListPage
{
    private ExerciseRepository $repository;

    public function __construct()
    {
        $this->repository = new ExerciseRepository();
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Vous n’avez pas les droits nécessaires.', 'ecole2nat'));
        }
        $exercises = $this->repository->all();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Bibliothèque d’exercices', 'ecole2nat'); ?></h1>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=ecole2nat-exercise')); ?>" class="button button-primary"><?php esc_html_e('Nouvel exercice', 'ecole2nat'); ?></a></p>
            <table class="wp-list-table widefat striped e2n-sortable-table">
                <thead><tr><th><?php esc_html_e('Exercice', 'ecole2nat'); ?></th><th><?php esc_html_e('Compétence', 'ecole2nat'); ?></th><th><?php esc_html_e('Domaine', 'ecole2nat'); ?></th><th><?php esc_html_e('Catégorie', 'ecole2nat'); ?></th><th><?php esc_html_e('Actions', 'ecole2nat'); ?></th></tr></thead>
                <tbody>
                <?php if ($exercises === []) : ?>
                    <tr><td colspan="5"><?php esc_html_e('Aucun exercice.', 'ecole2nat'); ?></td></tr>
                <?php else : foreach ($exercises as $exercise) : $exerciseId = (int) $exercise['id']; ?>
                    <tr>
                        <td><strong><?php echo esc_html($exercise['name']); ?></strong><?php if (!empty($exercise['description'])) : ?><p class="description"><?php echo esc_html($exercise['description']); ?></p><?php endif; ?><?php if (!empty($exercise['equipment'])) : ?><p class="description"><strong><?php esc_html_e('Matériel :', 'ecole2nat'); ?></strong> <?php echo esc_html($exercise['equipment']); ?></p><?php endif; ?></td>
                        <td><?php echo esc_html($exercise['skill_name']); ?></td>
                        <td><?php echo esc_html($exercise['domain_name']); ?></td>
                        <td><?php echo esc_html($exercise['category_name']); ?></td>
                        <td>
                            <a href="<?php echo esc_url(add_query_arg(['page' => 'ecole2nat-exercise', 'exercise_id' => $exerciseId], admin_url('admin.php'))); ?>"><?php esc_html_e('Modifier', 'ecole2nat'); ?></a><span aria-hidden="true"> | </span>
                            <a class="e2n-delete-link" href="<?php echo esc_url(DeletionController::url('exercise', $exerciseId, admin_url('admin.php?page=ecole2nat-exercises'))); ?>" onclick="return confirm('<?php echo esc_js(__('Supprimer cet exercice ?', 'ecole2nat')); ?>');"><?php esc_html_e('Supprimer', 'ecole2nat'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
