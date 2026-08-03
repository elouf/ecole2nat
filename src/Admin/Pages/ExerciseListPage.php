<?php

namespace Ecole2Nat\Admin\Pages;

use Ecole2Nat\Exercise\ExerciseRepository;

if (!defined('ABSPATH')) {
    exit;
}

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
            wp_die(
                esc_html__(
                    'Vous n’avez pas les droits nécessaires.',
                    'ecole2nat'
                )
            );
        }

        $exercises = $this->repository->all();

        ?>
        <div class="wrap">

            <h1>
                <?php esc_html_e(
                    'Bibliothèque d’exercices',
                    'ecole2nat'
                ); ?>
            </h1>

            <?php
            $notice = isset($_GET['e2n_notice'])
                ? sanitize_key(wp_unslash($_GET['e2n_notice']))
                : '';

            if ($notice === 'created') :
            ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php esc_html_e(
                            'L’exercice a bien été créé.',
                            'ecole2nat'
                        ); ?>
                    </p>
                </div>
            <?php elseif ($notice === 'updated') : ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php esc_html_e(
                            'L’exercice a bien été modifié.',
                            'ecole2nat'
                        ); ?>
                    </p>
                </div>
            <?php elseif ($notice === 'error') : ?>
                <div class="notice notice-error is-dismissible">
                    <p>
                        <?php esc_html_e(
                            'Une erreur est survenue.',
                            'ecole2nat'
                        ); ?>
                    </p>
                </div>
            <?php endif; ?>

            <p>
                <a
                    href="<?php echo esc_url(
                        admin_url('admin.php?page=ecole2nat-exercise')
                    ); ?>"
                    class="button button-primary"
                >
                    <?php esc_html_e(
                        'Nouvel exercice',
                        'ecole2nat'
                    ); ?>
                </a>
            </p>

            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Exercice', 'ecole2nat'); ?></th>
                        <th><?php esc_html_e('Compétence', 'ecole2nat'); ?></th>
                        <th><?php esc_html_e('Domaine', 'ecole2nat'); ?></th>
                        <th><?php esc_html_e('Catégorie', 'ecole2nat'); ?></th>
                        <th><?php esc_html_e('Durée', 'ecole2nat'); ?></th>
                        <th><?php esc_html_e('Actions', 'ecole2nat'); ?></th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($exercises === []) : ?>

                    <tr>
                        <td colspan="6">
                            <?php esc_html_e(
                                'Aucun exercice.',
                                'ecole2nat'
                            ); ?>
                        </td>
                    </tr>

                <?php else : ?>

                    <?php foreach ($exercises as $exercise) : ?>

                        <tr>
                            <td>
                                <strong>
                                    <?php echo esc_html($exercise['name']); ?>
                                </strong>
                            </td>

                            <td>
                                <?php echo esc_html($exercise['skill_name']); ?>
                            </td>

                            <td>
                                <?php echo esc_html($exercise['domain_name']); ?>
                            </td>

                            <td>
                                <?php echo esc_html($exercise['category_name']); ?>
                            </td>

                            <td>
                                <?php
                                echo esc_html(
                                    (string) ($exercise['duration'] ?? '')
                                );
                                ?>
                            </td>
                            <td>
                                <?php
                                $editUrl = add_query_arg(
                                    [
                                        'page' => 'ecole2nat-exercise',
                                        'exercise_id' => (int) $exercise['id'],
                                    ],
                                    admin_url('admin.php')
                                );
                                ?>

                                <a href="<?php echo esc_url($editUrl); ?>">
                                    <?php esc_html_e('Modifier', 'ecole2nat'); ?>
                                </a>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>
            </table>

        </div>
        <?php
    }
}