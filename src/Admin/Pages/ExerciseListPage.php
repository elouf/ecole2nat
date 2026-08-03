<?php

namespace Ecole2Nat\Admin\Pages;

use Ecole2Nat\Exercise\ExerciseRepository;
use Ecole2Nat\Session\SessionExerciseService;

if (!defined('ABSPATH')) {
    exit;
}

class ExerciseListPage
{
    private ExerciseRepository $repository;
    private SessionExerciseService $sessionExerciseService;

    public function __construct()
    {
        $this->repository = new ExerciseRepository();
        $this->sessionExerciseService = new SessionExerciseService();
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

        $this->handleActions();

        $exercises = $this->repository->all();

        $isSelectionMode = isset($_GET['mode'])
            && sanitize_key(wp_unslash($_GET['mode'])) === 'select';

        $partId = isset($_GET['part_id'])
            ? absint($_GET['part_id'])
            : 0;

        $sessionId = isset($_GET['session_id'])
            ? absint($_GET['session_id'])
            : 0;

        ?>
        <div class="wrap">
            <h1>
                <?php
                echo esc_html(
                    $isSelectionMode
                        ? __('Choisir un exercice', 'ecole2nat')
                        : __('Bibliothèque d’exercices', 'ecole2nat')
                );
                ?>
            </h1>

            <?php $this->renderNotice(); ?>

            <?php if ($isSelectionMode) : ?>
                <p>
                    <?php
                    esc_html_e(
                        'Choisissez un exercice, indiquez sa durée dans la séance, puis ajoutez-le.',
                        'ecole2nat'
                    );
                    ?>
                </p>

                <p>
                    <a
                        href="<?php echo esc_url(
                            $this->getSessionUrl($sessionId)
                        ); ?>"
                        class="button"
                    >
                        <?php esc_html_e('Retour à la séance', 'ecole2nat'); ?>
                    </a>
                </p>
            <?php else : ?>
                <p>
                    <a
                        href="<?php echo esc_url(
                            admin_url('admin.php?page=ecole2nat-exercise')
                        ); ?>"
                        class="button button-primary"
                    >
                        <?php esc_html_e('Nouvel exercice', 'ecole2nat'); ?>
                    </a>
                </p>
            <?php endif; ?>

            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Exercice', 'ecole2nat'); ?></th>
                        <th><?php esc_html_e('Compétence', 'ecole2nat'); ?></th>
                        <th><?php esc_html_e('Domaine', 'ecole2nat'); ?></th>
                        <th><?php esc_html_e('Catégorie', 'ecole2nat'); ?></th>
                        <th><?php esc_html_e('Actions', 'ecole2nat'); ?></th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($exercises === []) : ?>
                        <tr>
                            <td colspan="5">
                                <?php esc_html_e('Aucun exercice.', 'ecole2nat'); ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($exercises as $exercise) : ?>
                            <?php $exerciseId = (int) $exercise['id']; ?>

                            <tr>
                                <td>
                                    <strong>
                                        <?php echo esc_html($exercise['name']); ?>
                                    </strong>

                                    <?php if (!empty($exercise['description'])) : ?>
                                        <p class="description">
                                            <?php
                                            echo esc_html(
                                                $exercise['description']
                                            );
                                            ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if (!empty($exercise['equipment'])) : ?>
                                        <p class="description">
                                            <strong>
                                                <?php esc_html_e('Matériel :', 'ecole2nat'); ?>
                                            </strong>

                                            <?php echo esc_html($exercise['equipment']); ?>
                                        </p>
                                    <?php endif; ?>
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
                                    <?php if ($isSelectionMode) : ?>
                                        <details>
                                            <summary style="cursor: pointer;">
                                                <strong>
                                                    <?php esc_html_e(
                                                        'Ajouter à la séance',
                                                        'ecole2nat'
                                                    ); ?>
                                                </strong>
                                            </summary>

                                            <form
                                                method="post"
                                                style="margin-top: 12px;"
                                            >
                                                <?php
                                                wp_nonce_field(
                                                    'e2n_add_session_exercise_'
                                                    . $partId
                                                    . '_'
                                                    . $exerciseId
                                                );
                                                ?>

                                                <input
                                                    type="hidden"
                                                    name="e2n_action"
                                                    value="add_session_exercise"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="part_id"
                                                    value="<?php echo esc_attr(
                                                        (string) $partId
                                                    ); ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="session_id"
                                                    value="<?php echo esc_attr(
                                                        (string) $sessionId
                                                    ); ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="exercise_id"
                                                    value="<?php echo esc_attr(
                                                        (string) $exerciseId
                                                    ); ?>"
                                                >

                                                <p>
                                                    <label>
                                                        <strong>
                                                            <?php
                                                            esc_html_e(
                                                                'Durée prévue',
                                                                'ecole2nat'
                                                            );
                                                            ?>
                                                        </strong>
                                                    </label>
                                                    <br>

                                                    <input
                                                        type="number"
                                                        name="duration"
                                                        min="1"
                                                        required
                                                        style="width: 80px;"
                                                    >

                                                    <?php
                                                    esc_html_e(
                                                        'minutes',
                                                        'ecole2nat'
                                                    );
                                                    ?>
                                                </p>

                                                <p>
                                                    <label>
                                                        <strong>
                                                            <?php
                                                            esc_html_e(
                                                                'Consignes spécifiques',
                                                                'ecole2nat'
                                                            );
                                                            ?>
                                                        </strong>
                                                    </label>
                                                    <br>

                                                    <textarea
                                                        name="coach_notes"
                                                        rows="3"
                                                        style="width: 100%;"
                                                    ></textarea>
                                                </p>

                                                <?php
                                                submit_button(
                                                    __('Ajouter', 'ecole2nat'),
                                                    'primary small',
                                                    'submit',
                                                    false
                                                );
                                                ?>
                                            </form>
                                        </details>
                                    <?php else : ?>
                                        <?php
                                        $editUrl = add_query_arg(
                                            [
                                                'page' => 'ecole2nat-exercise',
                                                'exercise_id' => $exerciseId,
                                            ],
                                            admin_url('admin.php')
                                        );
                                        ?>

                                        <a href="<?php echo esc_url($editUrl); ?>">
                                            <?php esc_html_e('Modifier', 'ecole2nat'); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function handleActions(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = isset($_POST['e2n_action'])
            ? sanitize_key(wp_unslash($_POST['e2n_action']))
            : '';

        if ($action !== 'add_session_exercise') {
            return;
        }

        $partId = isset($_POST['part_id'])
            ? absint($_POST['part_id'])
            : 0;

        $sessionId = isset($_POST['session_id'])
            ? absint($_POST['session_id'])
            : 0;

        $exerciseId = isset($_POST['exercise_id'])
            ? absint($_POST['exercise_id'])
            : 0;

        $duration = isset($_POST['duration'])
            && $_POST['duration'] !== ''
                ? absint($_POST['duration'])
                : null;

        $coachNotes = isset($_POST['coach_notes'])
            ? sanitize_textarea_field(
                wp_unslash($_POST['coach_notes'])
            )
            : '';

        check_admin_referer(
            'e2n_add_session_exercise_'
            . $partId
            . '_'
            . $exerciseId
        );

        if (
            $partId <= 0
            || $sessionId <= 0
            || $exerciseId <= 0
            || $duration === null
            || $duration <= 0
        ) {
            $this->redirectToSelection(
                $partId,
                $sessionId,
                'invalid'
            );
        }

        $result = $this->sessionExerciseService->create(
            $partId,
            $exerciseId,
            $duration,
            $coachNotes
        );

        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => 'ecole2nat-session',
                    'session_id' => $sessionId,
                    'e2n_notice' => $result['message'],
                ],
                admin_url('admin.php')
            )
        );

        exit;
    }

    private function renderNotice(): void
    {
        $notice = isset($_GET['e2n_notice'])
            ? sanitize_key(wp_unslash($_GET['e2n_notice']))
            : '';

        $messages = [
            'created' => [
                'success',
                __('L’exercice a bien été créé.', 'ecole2nat'),
            ],
            'updated' => [
                'success',
                __('L’exercice a bien été modifié.', 'ecole2nat'),
            ],
            'invalid' => [
                'error',
                __('Veuillez indiquer une durée valide.', 'ecole2nat'),
            ],
            'error' => [
                'error',
                __('Une erreur est survenue.', 'ecole2nat'),
            ],
        ];

        if (!isset($messages[$notice])) {
            return;
        }

        [$type, $message] = $messages[$notice];
        ?>
        <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
        <?php
    }

    private function getSessionUrl(int $sessionId): string
    {
        return add_query_arg(
            [
                'page' => 'ecole2nat-session',
                'session_id' => $sessionId,
            ],
            admin_url('admin.php')
        );
    }

    private function redirectToSelection(
        int $partId,
        int $sessionId,
        string $notice
    ): void {
        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => 'ecole2nat-exercises',
                    'mode' => 'select',
                    'part_id' => $partId,
                    'session_id' => $sessionId,
                    'e2n_notice' => $notice,
                ],
                admin_url('admin.php')
            )
        );

        exit;
    }
}