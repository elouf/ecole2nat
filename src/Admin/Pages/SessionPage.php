<?php

namespace Ecole2Nat\Admin\Pages;

use Ecole2Nat\Category\CategoryRepository;
use Ecole2Nat\Session\SessionExerciseService;
use Ecole2Nat\Session\SessionPartService;
use Ecole2Nat\Session\SessionService;

if (!defined('ABSPATH')) {
    exit;
}

class SessionPage
{
    private SessionService $sessionService;
    private CategoryRepository $categoryRepository;
    private SessionPartService $partService;
    private SessionExerciseService $exerciseService;

    public function __construct()
    {
        $this->sessionService = new SessionService();
        $this->categoryRepository = new CategoryRepository();
        $this->partService = new SessionPartService();
        $this->exerciseService = new SessionExerciseService();
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Vous n’avez pas les droits nécessaires.', 'ecole2nat'));
        }

        $this->handleActions();

        $sessionId = isset($_GET['session_id']) ? absint($_GET['session_id']) : 0;
        $session = $sessionId > 0 ? $this->sessionService->find($sessionId) : null;
        $parts = $session !== null ? $this->partService->allBySession($sessionId) : [];
        $categories = array_values(
            array_filter(
                $this->categoryRepository->all(),
                static fn(array $category): bool =>
                    (int) ($category['is_active'] ?? 1) === 1
            )
        );

        $partsWithExercises = [];
        $sessionDuration = 0;

        foreach ($parts as $part) {
            $exercises = $this->exerciseService->allByPart((int) $part['id']);
            $partDuration = array_sum(
                array_map(
                    static fn(array $exercise): int =>
                        (int) ($exercise['duration'] ?? 0),
                    $exercises
                )
            );
            $sessionDuration += $partDuration;
            $partsWithExercises[] = [
                'part' => $part,
                'exercises' => $exercises,
                'duration' => $partDuration,
            ];
        }
        ?>
        <div class="wrap">
            <h1>
                <?php
                echo esc_html(
                    $session !== null
                        ? sprintf(__('Modifier « %s »', 'ecole2nat'), $session['name'])
                        : __('Nouvelle séance', 'ecole2nat')
                );
                ?>
            </h1>

            <?php $this->renderNotice(); ?>

            <?php if ($session !== null) : ?>
                <p>
                    <a
                        href="<?php echo esc_url(admin_url('admin.php?page=ecole2nat-sessions')); ?>"
                        class="button"
                    >
                        <?php esc_html_e('Retour aux séances', 'ecole2nat'); ?>
                    </a>
                    <a
                        href="<?php echo esc_url(
                            add_query_arg(
                                ['page' => 'ecole2nat-session-print', 'session_id' => $sessionId],
                                admin_url('admin.php')
                            )
                        ); ?>"
                        class="button"
                        target="_blank"
                    >
                        <?php esc_html_e('Imprimer la séance', 'ecole2nat'); ?>
                    </a>
                    <strong style="margin-left:12px;">
                        <?php
                        echo esc_html(
                            sprintf(__('Durée totale : %d min', 'ecole2nat'), $sessionDuration)
                        );
                        ?>
                    </strong>
                </p>
            <?php endif; ?>

            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle"><?php esc_html_e('Informations générales', 'ecole2nat'); ?></h2>
                </div>
                <div class="inside">
                    <?php $this->renderForm($categories, $session); ?>
                </div>
            </div>

            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle"><?php esc_html_e('Parties de la séance', 'ecole2nat'); ?></h2>
                </div>
                <div class="inside">
                    <?php if ($session === null) : ?>
                        <p><?php esc_html_e('Enregistrez d’abord la séance pour pouvoir ajouter ses parties.', 'ecole2nat'); ?></p>
                    <?php else : ?>
                        <?php if ($partsWithExercises === []) : ?>
                            <p><?php esc_html_e('Aucune partie pour le moment.', 'ecole2nat'); ?></p>
                        <?php else : ?>
                            <?php foreach ($partsWithExercises as $partIndex => $item) : ?>
                                <?php
                                $part = $item['part'];
                                $partId = (int) $part['id'];
                                $exercises = $item['exercises'];
                                $partDuration = (int) $item['duration'];
                                ?>
                                <div class="postbox" style="margin-bottom:16px;">
                                    <div class="postbox-header">
                                        <h3 class="hndle">
                                            <?php
                                            echo esc_html(
                                                sprintf(
                                                    '%d. %s — %d min',
                                                    (int) $part['position'],
                                                    $part['title'],
                                                    $partDuration
                                                )
                                            );
                                            ?>
                                        </h3>
                                    </div>
                                    <div class="inside">
                                        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;">
                                            <?php if ($partIndex > 0) : ?>
                                                <?php $this->renderPartMoveForm($sessionId, $partId, 'up', '↑'); ?>
                                            <?php endif; ?>
                                            <?php if ($partIndex < count($partsWithExercises) - 1) : ?>
                                                <?php $this->renderPartMoveForm($sessionId, $partId, 'down', '↓'); ?>
                                            <?php endif; ?>

                                            <details>
                                                <summary class="button button-small" style="cursor:pointer;">
                                                    <?php esc_html_e('Renommer', 'ecole2nat'); ?>
                                                </summary>
                                                <form method="post" style="margin-top:10px;min-width:320px;">
                                                    <?php wp_nonce_field('e2n_update_session_part_' . $partId); ?>
                                                    <input type="hidden" name="e2n_action" value="update_session_part">
                                                    <input type="hidden" name="session_id" value="<?php echo esc_attr((string) $sessionId); ?>">
                                                    <input type="hidden" name="part_id" value="<?php echo esc_attr((string) $partId); ?>">
                                                    <p>
                                                        <input
                                                            type="text"
                                                            name="part_title"
                                                            class="regular-text"
                                                            value="<?php echo esc_attr($part['title']); ?>"
                                                            required
                                                        >
                                                    </p>
                                                    <?php submit_button(__('Enregistrer', 'ecole2nat'), 'primary small', 'submit', false); ?>
                                                </form>
                                            </details>

                                            <form
                                                method="post"
                                                onsubmit="return confirm('<?php echo esc_js(__('Supprimer cette partie et tous ses exercices ?', 'ecole2nat')); ?>');"
                                            >
                                                <?php wp_nonce_field('e2n_delete_session_part_' . $partId); ?>
                                                <input type="hidden" name="e2n_action" value="delete_session_part">
                                                <input type="hidden" name="session_id" value="<?php echo esc_attr((string) $sessionId); ?>">
                                                <input type="hidden" name="part_id" value="<?php echo esc_attr((string) $partId); ?>">
                                                <button type="submit" class="button button-small">
                                                    <?php esc_html_e('Supprimer la partie', 'ecole2nat'); ?>
                                                </button>
                                            </form>
                                        </div>

                                        <?php if ($exercises === []) : ?>
                                            <p><?php esc_html_e('Aucun exercice dans cette partie.', 'ecole2nat'); ?></p>
                                        <?php else : ?>
                                            <ol>
                                                <?php foreach ($exercises as $exerciseIndex => $exercise) : ?>
                                                    <?php $this->renderExercise($sessionId, $exercises, $exerciseIndex, $exercise); ?>
                                                <?php endforeach; ?>
                                            </ol>
                                        <?php endif; ?>

                                        <?php
                                        $libraryUrl = add_query_arg(
                                            [
                                                'page' => 'ecole2nat-exercises',
                                                'mode' => 'select',
                                                'part_id' => $partId,
                                                'session_id' => $sessionId,
                                            ],
                                            admin_url('admin.php')
                                        );
                                        ?>
                                        <p>
                                            <a href="<?php echo esc_url($libraryUrl); ?>" class="button button-secondary">
                                                <?php esc_html_e('Choisir dans la bibliothèque', 'ecole2nat'); ?>
                                            </a>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <hr>
                        <h3><?php esc_html_e('Ajouter une partie', 'ecole2nat'); ?></h3>
                        <form method="post">
                            <?php wp_nonce_field('e2n_create_session_part'); ?>
                            <input type="hidden" name="e2n_action" value="create_session_part">
                            <input type="hidden" name="session_id" value="<?php echo esc_attr((string) $sessionId); ?>">
                            <p>
                                <input
                                    id="e2n-part-title"
                                    type="text"
                                    name="part_title"
                                    class="regular-text"
                                    placeholder="Ex. Échauffement"
                                    maxlength="150"
                                    required
                                >
                            </p>
                            <?php submit_button(__('Ajouter la partie', 'ecole2nat'), 'secondary', 'submit', false); ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
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

        if ($action === 'create_session') {
            check_admin_referer('e2n_save_session');
            $result = $this->sessionService->create(
                isset($_POST['category_id']) ? absint($_POST['category_id']) : 0,
                isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '',
                isset($_POST['objectives']) ? sanitize_textarea_field(wp_unslash($_POST['objectives'])) : ''
            );

            if ($result['success']) {
                $this->redirectToEditor((int) $result['id'], $result['message']);
            }

            $this->redirectToEditor(0, $result['message']);
        }

        if ($action === 'update_session') {
            check_admin_referer('e2n_save_session');
            $sessionId = isset($_POST['session_id']) ? absint($_POST['session_id']) : 0;
            $result = $this->sessionService->update(
                $sessionId,
                isset($_POST['category_id']) ? absint($_POST['category_id']) : 0,
                isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '',
                isset($_POST['objectives']) ? sanitize_textarea_field(wp_unslash($_POST['objectives'])) : ''
            );
            $this->redirectToEditor($sessionId, $result['message']);
        }

        if ($action === 'create_session_part') {
            check_admin_referer('e2n_create_session_part');
            $sessionId = isset($_POST['session_id']) ? absint($_POST['session_id']) : 0;
            $result = $this->partService->create(
                $sessionId,
                isset($_POST['part_title']) ? sanitize_text_field(wp_unslash($_POST['part_title'])) : ''
            );
            $this->redirectToEditor($sessionId, $result['message']);
        }

        if ($action === 'update_session_part') {
            $partId = isset($_POST['part_id']) ? absint($_POST['part_id']) : 0;
            check_admin_referer('e2n_update_session_part_' . $partId);
            $sessionId = isset($_POST['session_id']) ? absint($_POST['session_id']) : 0;
            $result = $this->partService->update(
                $partId,
                $sessionId,
                isset($_POST['part_title']) ? sanitize_text_field(wp_unslash($_POST['part_title'])) : ''
            );
            $this->redirectToEditor($sessionId, $result['message']);
        }

        if ($action === 'delete_session_part') {
            $partId = isset($_POST['part_id']) ? absint($_POST['part_id']) : 0;
            check_admin_referer('e2n_delete_session_part_' . $partId);
            $sessionId = isset($_POST['session_id']) ? absint($_POST['session_id']) : 0;
            $result = $this->partService->delete($partId);
            $this->redirectToEditor($sessionId, $result['message']);
        }

        if ($action === 'move_session_part') {
            $partId = isset($_POST['part_id']) ? absint($_POST['part_id']) : 0;
            check_admin_referer('e2n_move_session_part_' . $partId);
            $sessionId = isset($_POST['session_id']) ? absint($_POST['session_id']) : 0;
            $direction = isset($_POST['direction']) ? sanitize_key(wp_unslash($_POST['direction'])) : '';
            $result = $this->partService->move($partId, $direction);
            $this->redirectToEditor($sessionId, $result['message']);
        }

        if ($action === 'update_session_exercise') {
            $sessionExerciseId = isset($_POST['session_exercise_id'])
                ? absint($_POST['session_exercise_id'])
                : 0;
            check_admin_referer('e2n_update_session_exercise_' . $sessionExerciseId);
            $sessionId = isset($_POST['session_id']) ? absint($_POST['session_id']) : 0;
            $result = $this->exerciseService->update(
                $sessionExerciseId,
                isset($_POST['duration']) ? absint($_POST['duration']) : 0,
                isset($_POST['coach_notes'])
                    ? sanitize_textarea_field(wp_unslash($_POST['coach_notes']))
                    : ''
            );
            $this->redirectToEditor($sessionId, $result['message']);
        }

        if ($action === 'delete_session_exercise') {
            $sessionExerciseId = isset($_POST['session_exercise_id'])
                ? absint($_POST['session_exercise_id'])
                : 0;
            check_admin_referer('e2n_delete_session_exercise_' . $sessionExerciseId);
            $sessionId = isset($_POST['session_id']) ? absint($_POST['session_id']) : 0;
            $result = $this->exerciseService->delete($sessionExerciseId);
            $this->redirectToEditor($sessionId, $result['message']);
        }

        if ($action === 'move_session_exercise') {
            $sessionExerciseId = isset($_POST['session_exercise_id'])
                ? absint($_POST['session_exercise_id'])
                : 0;
            check_admin_referer('e2n_move_session_exercise_' . $sessionExerciseId);
            $sessionId = isset($_POST['session_id']) ? absint($_POST['session_id']) : 0;
            $direction = isset($_POST['direction']) ? sanitize_key(wp_unslash($_POST['direction'])) : '';
            $result = $this->exerciseService->move($sessionExerciseId, $direction);
            $this->redirectToEditor($sessionId, $result['message']);
        }
    }

    private function renderForm(array $categories, ?array $session): void
    {
        $isEditing = $session !== null;
        $selectedCategoryId = $isEditing ? (int) $session['category_id'] : 0;
        $name = $isEditing ? (string) $session['name'] : '';
        $objectives = $isEditing ? (string) ($session['objectives'] ?? '') : '';

        if ($categories === []) {
            echo '<div class="notice notice-warning inline"><p>'
                . esc_html__('Vous devez créer au moins une catégorie active.', 'ecole2nat')
                . '</p></div>';
            return;
        }
        ?>
        <form method="post">
            <?php wp_nonce_field('e2n_save_session'); ?>
            <input
                type="hidden"
                name="e2n_action"
                value="<?php echo esc_attr($isEditing ? 'update_session' : 'create_session'); ?>"
            >
            <?php if ($isEditing) : ?>
                <input type="hidden" name="session_id" value="<?php echo esc_attr((string) $session['id']); ?>">
            <?php endif; ?>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="e2n-session-category"><?php esc_html_e('Catégorie', 'ecole2nat'); ?></label></th>
                        <td>
                            <select id="e2n-session-category" name="category_id" class="regular-text" required>
                                <option value=""><?php esc_html_e('— Sélectionner —', 'ecole2nat'); ?></option>
                                <?php foreach ($categories as $category) : ?>
                                    <option
                                        value="<?php echo esc_attr((string) $category['id']); ?>"
                                        <?php selected($selectedCategoryId, (int) $category['id']); ?>
                                    >
                                        <?php echo esc_html($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="e2n-session-name"><?php esc_html_e('Nom', 'ecole2nat'); ?></label></th>
                        <td>
                            <input
                                id="e2n-session-name"
                                type="text"
                                name="name"
                                class="regular-text"
                                maxlength="150"
                                value="<?php echo esc_attr($name); ?>"
                                required
                            >
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="e2n-session-objectives"><?php esc_html_e('Objectifs', 'ecole2nat'); ?></label></th>
                        <td>
                            <textarea
                                id="e2n-session-objectives"
                                name="objectives"
                                class="large-text"
                                rows="5"
                            ><?php echo esc_textarea($objectives); ?></textarea>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php
            submit_button(
                $isEditing
                    ? __('Enregistrer les modifications', 'ecole2nat')
                    : __('Créer la séance', 'ecole2nat')
            );
            ?>
        </form>
        <?php
    }

    private function renderExercise(
        int $sessionId,
        array $exercises,
        int $index,
        array $exercise
    ): void {
        $sessionExerciseId = (int) $exercise['id'];
        $duration = (int) ($exercise['duration'] ?? 0);
        ?>
        <li style="margin-bottom:20px;">
            <p>
                <strong><?php echo esc_html($exercise['name']); ?></strong>
                <?php if ($duration > 0) : ?>
                    — <?php echo esc_html((string) $duration); ?> min
                <?php endif; ?>
            </p>
            <?php if (!empty($exercise['description'])) : ?>
                <p class="description"><?php echo esc_html($exercise['description']); ?></p>
            <?php endif; ?>
            <?php if (!empty($exercise['coach_notes'])) : ?>
                <p><strong><?php esc_html_e('Consignes :', 'ecole2nat'); ?></strong> <?php echo esc_html($exercise['coach_notes']); ?></p>
            <?php endif; ?>

            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <?php if ($index > 0) : ?>
                    <?php $this->renderExerciseMoveForm($sessionId, $sessionExerciseId, 'up', '↑'); ?>
                <?php endif; ?>
                <?php if ($index < count($exercises) - 1) : ?>
                    <?php $this->renderExerciseMoveForm($sessionId, $sessionExerciseId, 'down', '↓'); ?>
                <?php endif; ?>

                <details>
                    <summary class="button button-small" style="cursor:pointer;">
                        <?php esc_html_e('Modifier', 'ecole2nat'); ?>
                    </summary>
                    <form method="post" style="margin-top:10px;min-width:360px;">
                        <?php wp_nonce_field('e2n_update_session_exercise_' . $sessionExerciseId); ?>
                        <input type="hidden" name="e2n_action" value="update_session_exercise">
                        <input type="hidden" name="session_id" value="<?php echo esc_attr((string) $sessionId); ?>">
                        <input type="hidden" name="session_exercise_id" value="<?php echo esc_attr((string) $sessionExerciseId); ?>">
                        <p>
                            <label><strong><?php esc_html_e('Durée', 'ecole2nat'); ?></strong></label><br>
                            <input type="number" name="duration" min="1" value="<?php echo esc_attr((string) $duration); ?>" required> minutes
                        </p>
                        <p>
                            <label><strong><?php esc_html_e('Consignes spécifiques', 'ecole2nat'); ?></strong></label><br>
                            <textarea name="coach_notes" rows="3" class="large-text"><?php echo esc_textarea((string) ($exercise['coach_notes'] ?? '')); ?></textarea>
                        </p>
                        <?php submit_button(__('Enregistrer', 'ecole2nat'), 'primary small', 'submit', false); ?>
                    </form>
                </details>

                <form
                    method="post"
                    onsubmit="return confirm('<?php echo esc_js(__('Retirer cet exercice de la séance ?', 'ecole2nat')); ?>');"
                >
                    <?php wp_nonce_field('e2n_delete_session_exercise_' . $sessionExerciseId); ?>
                    <input type="hidden" name="e2n_action" value="delete_session_exercise">
                    <input type="hidden" name="session_id" value="<?php echo esc_attr((string) $sessionId); ?>">
                    <input type="hidden" name="session_exercise_id" value="<?php echo esc_attr((string) $sessionExerciseId); ?>">
                    <button class="button button-small" type="submit"><?php esc_html_e('Retirer', 'ecole2nat'); ?></button>
                </form>
            </div>
        </li>
        <?php
    }

    private function renderPartMoveForm(
        int $sessionId,
        int $partId,
        string $direction,
        string $label
    ): void {
        ?>
        <form method="post">
            <?php wp_nonce_field('e2n_move_session_part_' . $partId); ?>
            <input type="hidden" name="e2n_action" value="move_session_part">
            <input type="hidden" name="session_id" value="<?php echo esc_attr((string) $sessionId); ?>">
            <input type="hidden" name="part_id" value="<?php echo esc_attr((string) $partId); ?>">
            <input type="hidden" name="direction" value="<?php echo esc_attr($direction); ?>">
            <button class="button button-small" type="submit"><?php echo esc_html($label); ?></button>
        </form>
        <?php
    }

    private function renderExerciseMoveForm(
        int $sessionId,
        int $sessionExerciseId,
        string $direction,
        string $label
    ): void {
        ?>
        <form method="post">
            <?php wp_nonce_field('e2n_move_session_exercise_' . $sessionExerciseId); ?>
            <input type="hidden" name="e2n_action" value="move_session_exercise">
            <input type="hidden" name="session_id" value="<?php echo esc_attr((string) $sessionId); ?>">
            <input type="hidden" name="session_exercise_id" value="<?php echo esc_attr((string) $sessionExerciseId); ?>">
            <input type="hidden" name="direction" value="<?php echo esc_attr($direction); ?>">
            <button class="button button-small" type="submit"><?php echo esc_html($label); ?></button>
        </form>
        <?php
    }

    private function renderNotice(): void
    {
        $notice = isset($_GET['e2n_notice'])
            ? sanitize_key(wp_unslash($_GET['e2n_notice']))
            : '';
        $messages = [
            'session_created' => ['success', __('La séance a bien été créée.', 'ecole2nat')],
            'session_updated' => ['success', __('La séance a bien été modifiée.', 'ecole2nat')],
            'duplicated' => ['success', __('La séance a bien été dupliquée.', 'ecole2nat')],
            'duplicate' => ['warning', __('Une séance portant ce nom existe déjà pour cette catégorie.', 'ecole2nat')],
            'part_created' => ['success', __('La partie a bien été ajoutée.', 'ecole2nat')],
            'part_updated' => ['success', __('La partie a bien été renommée.', 'ecole2nat')],
            'part_deleted' => ['success', __('La partie a bien été supprimée.', 'ecole2nat')],
            'part_moved' => ['success', __('L’ordre des parties a bien été modifié.', 'ecole2nat')],
            'part_duplicate' => ['warning', __('Une partie portant ce titre existe déjà dans cette séance.', 'ecole2nat')],
            'exercise_created' => ['success', __('L’exercice a bien été ajouté à la séance.', 'ecole2nat')],
            'exercise_updated' => ['success', __('L’exercice a bien été modifié.', 'ecole2nat')],
            'exercise_deleted' => ['success', __('L’exercice a bien été retiré de la séance.', 'ecole2nat')],
            'exercise_moved' => ['success', __('L’ordre des exercices a bien été modifié.', 'ecole2nat')],
            'exercise_duplicate' => ['warning', __('Cet exercice est déjà présent dans cette partie.', 'ecole2nat')],
            'invalid' => ['error', __('Veuillez remplir correctement les champs obligatoires.', 'ecole2nat')],
            'error' => ['error', __('Une erreur est survenue.', 'ecole2nat')],
        ];

        if (!isset($messages[$notice])) {
            return;
        }

        [$type, $message] = $messages[$notice];
        echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>'
            . esc_html($message) . '</p></div>';
    }

    private function redirectToEditor(int $sessionId, string $notice): void
    {
        $arguments = ['page' => 'ecole2nat-session', 'e2n_notice' => $notice];
        if ($sessionId > 0) {
            $arguments['session_id'] = $sessionId;
        }

        wp_safe_redirect(add_query_arg($arguments, admin_url('admin.php')));
        exit;
    }
}
