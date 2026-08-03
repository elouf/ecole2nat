<?php

namespace Ecole2Nat\Admin\Pages;

use Ecole2Nat\Category\CategoryRepository;
use Ecole2Nat\Exercise\ExerciseRepository;
use Ecole2Nat\Reference\SkillRepository;

if (!defined('ABSPATH')) {
    exit;
}

class ExercisePage
{
    private ExerciseRepository $exerciseRepository;
    private CategoryRepository $categoryRepository;
    private SkillRepository $skillRepository;

    private ?array $editingExercise = null;

    public function __construct()
    {
        $this->exerciseRepository = new ExerciseRepository();
        $this->categoryRepository = new CategoryRepository();
        $this->skillRepository = new SkillRepository();
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

        $exerciseId = isset($_GET['exercise_id'])
            ? absint($_GET['exercise_id'])
            : 0;

        if ($exerciseId > 0) {
            $this->editingExercise = $this->exerciseRepository->find(
                $exerciseId
            );
        }

        $categories = array_values(
            array_filter(
                $this->categoryRepository->all(),
                static fn(array $category): bool =>
                    (int) ($category['is_active'] ?? 1) === 1
            )
        );

        $skillsByCategory = [];

        foreach ($categories as $category) {
            $categoryId = (int) $category['id'];

            $skillsByCategory[$categoryId] =
                $this->skillRepository->allByCategory($categoryId);
        }

        $isEditing = $this->editingExercise !== null;

        ?>
        <div class="wrap">
            <h1>
                <?php
                echo esc_html(
                    $isEditing
                        ? sprintf(
                            __('Modifier « %s »', 'ecole2nat'),
                            $this->editingExercise['name']
                        )
                        : __('Nouvel exercice', 'ecole2nat')
                );
                ?>
            </h1>

            <?php $this->renderNotice(); ?>

            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle">
                        <?php esc_html_e(
                            'Informations de l’exercice',
                            'ecole2nat'
                        ); ?>
                    </h2>
                </div>

                <div class="inside">
                    <?php
                    $this->renderForm(
                        $categories,
                        $skillsByCategory
                    );
                    ?>
                </div>
            </div>

            <p>
                <a
                    href="<?php echo esc_url(
                        admin_url(
                            'admin.php?page=ecole2nat-exercises'
                        )
                    ); ?>"
                    class="button"
                >
                    <?php esc_html_e(
                        'Retour à la bibliothèque',
                        'ecole2nat'
                    ); ?>
                </a>
            </p>
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

        if (
            $action !== 'create_exercise'
            && $action !== 'update_exercise'
        ) {
            return;
        }

        check_admin_referer('e2n_save_exercise');

        $data = $this->getFormData();

        if (
            $data['skill_id'] <= 0
            || $data['name'] === ''
        ) {
            $exerciseId = isset($_POST['exercise_id'])
                ? absint($_POST['exercise_id'])
                : 0;

            $this->redirectToEditor(
                $exerciseId,
                'invalid'
            );
        }

        if ($action === 'create_exercise') {
            $created = $this->exerciseRepository->create(
                $data['skill_id'],
                $data['name'],
                $data['description'],
                $data['objectives'],
                $data['coach_notes'],
                $data['equipment'],
                $data['difficulty']
            );

            $this->redirectToList(
                $created ? 'created' : 'error'
            );
        }

        $exerciseId = isset($_POST['exercise_id'])
            ? absint($_POST['exercise_id'])
            : 0;

        if ($exerciseId <= 0) {
            $this->redirectToEditor(0, 'error');
        }

        $updated = $this->exerciseRepository->update(
            $exerciseId,
            $data
        );

        $this->redirectToList(
            $updated ? 'updated' : 'error'
        );
    }

    private function renderForm(
        array $categories,
        array $skillsByCategory
    ): void {
        $isEditing = $this->editingExercise !== null;

        ?>
        <form method="post">
            <?php wp_nonce_field('e2n_save_exercise'); ?>

            <input
                type="hidden"
                name="e2n_action"
                value="<?php echo esc_attr(
                    $isEditing
                        ? 'update_exercise'
                        : 'create_exercise'
                ); ?>"
            >

            <?php if ($isEditing) : ?>
                <input
                    type="hidden"
                    name="exercise_id"
                    value="<?php echo esc_attr(
                        (string) $this->editingExercise['id']
                    ); ?>"
                >
            <?php endif; ?>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="e2n-exercise-skill">
                                <?php esc_html_e(
                                    'Compétence',
                                    'ecole2nat'
                                ); ?>
                            </label>
                        </th>

                        <td>
                            <select
                                id="e2n-exercise-skill"
                                name="skill_id"
                                class="regular-text"
                                required
                            >
                                <option value="">
                                    <?php esc_html_e(
                                        '— Sélectionner —',
                                        'ecole2nat'
                                    ); ?>
                                </option>

                                <?php foreach ($categories as $category) : ?>
                                    <?php
                                    $categoryId = (int) $category['id'];
                                    $skills = $skillsByCategory[$categoryId]
                                        ?? [];
                                    ?>

                                    <?php if ($skills !== []) : ?>
                                        <optgroup
                                            label="<?php echo esc_attr(
                                                $category['name']
                                            ); ?>"
                                        >
                                            <?php foreach ($skills as $skill) : ?>
                                                <option
                                                    value="<?php echo esc_attr(
                                                        (string) $skill['id']
                                                    ); ?>"
                                                    <?php selected(
                                                        (int) $this->fieldValue(
                                                            'skill_id',
                                                            '0'
                                                        ),
                                                        (int) $skill['id']
                                                    ); ?>
                                                >
                                                    <?php
                                                    echo esc_html(
                                                        $skill['name']
                                                    );
                                                    ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="e2n-exercise-name">
                                <?php esc_html_e(
                                    'Nom',
                                    'ecole2nat'
                                ); ?>
                            </label>
                        </th>

                        <td>
                            <input
                                id="e2n-exercise-name"
                                type="text"
                                name="name"
                                class="regular-text"
                                maxlength="150"
                                value="<?php echo esc_attr(
                                    $this->fieldValue('name')
                                ); ?>"
                                required
                            >
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="e2n-exercise-description">
                                <?php esc_html_e(
                                    'Description',
                                    'ecole2nat'
                                ); ?>
                            </label>
                        </th>

                        <td>
                            <textarea
                                id="e2n-exercise-description"
                                name="description"
                                class="large-text"
                                rows="5"
                            ><?php
                                echo esc_textarea(
                                    $this->fieldValue('description')
                                );
                            ?></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="e2n-exercise-objectives">
                                <?php esc_html_e(
                                    'Objectifs',
                                    'ecole2nat'
                                ); ?>
                            </label>
                        </th>

                        <td>
                            <textarea
                                id="e2n-exercise-objectives"
                                name="objectives"
                                class="large-text"
                                rows="4"
                            ><?php
                                echo esc_textarea(
                                    $this->fieldValue('objectives')
                                );
                            ?></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="e2n-exercise-coach-notes">
                                <?php esc_html_e(
                                    'Consignes coach',
                                    'ecole2nat'
                                ); ?>
                            </label>
                        </th>

                        <td>
                            <textarea
                                id="e2n-exercise-coach-notes"
                                name="coach_notes"
                                class="large-text"
                                rows="4"
                            ><?php
                                echo esc_textarea(
                                    $this->fieldValue('coach_notes')
                                );
                            ?></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="e2n-exercise-equipment">
                                <?php esc_html_e(
                                    'Matériel',
                                    'ecole2nat'
                                ); ?>
                            </label>
                        </th>

                        <td>
                            <input
                                id="e2n-exercise-equipment"
                                type="text"
                                name="equipment"
                                class="regular-text"
                                value="<?php echo esc_attr(
                                    $this->fieldValue('equipment')
                                ); ?>"
                            >
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="e2n-exercise-difficulty">
                                <?php esc_html_e(
                                    'Difficulté',
                                    'ecole2nat'
                                ); ?>
                            </label>
                        </th>

                        <td>
                            <select
                                id="e2n-exercise-difficulty"
                                name="difficulty"
                            >
                                <?php
                                for (
                                    $difficulty = 1;
                                    $difficulty <= 5;
                                    $difficulty++
                                ) :
                                    ?>
                                    <option
                                        value="<?php echo esc_attr(
                                            (string) $difficulty
                                        ); ?>"
                                        <?php selected(
                                            (int) $this->fieldValue(
                                                'difficulty',
                                                '1'
                                            ),
                                            $difficulty
                                        ); ?>
                                    >
                                        <?php
                                        echo esc_html(
                                            $difficulty . '/5'
                                        );
                                        ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php
            submit_button(
                $isEditing
                    ? __('Enregistrer les modifications', 'ecole2nat')
                    : __('Créer l’exercice', 'ecole2nat')
            );
            ?>
        </form>
        <?php
    }

    private function getFormData(): array
    {
        return [
            'skill_id' => isset($_POST['skill_id'])
                ? absint($_POST['skill_id'])
                : 0,

            'name' => isset($_POST['name'])
                ? sanitize_text_field(
                    wp_unslash($_POST['name'])
                )
                : '',

            'description' => isset($_POST['description'])
                ? sanitize_textarea_field(
                    wp_unslash($_POST['description'])
                )
                : '',

            'objectives' => isset($_POST['objectives'])
                ? sanitize_textarea_field(
                    wp_unslash($_POST['objectives'])
                )
                : '',

            'coach_notes' => isset($_POST['coach_notes'])
                ? sanitize_textarea_field(
                    wp_unslash($_POST['coach_notes'])
                )
                : '',

            'equipment' => isset($_POST['equipment'])
                ? sanitize_text_field(
                    wp_unslash($_POST['equipment'])
                )
                : '',

            'difficulty' => isset($_POST['difficulty'])
                ? min(
                    5,
                    max(
                        1,
                        absint($_POST['difficulty'])
                    )
                )
                : 1,
        ];
    }

    private function fieldValue(
        string $field,
        string $default = ''
    ): string {
        if (
            $this->editingExercise === null
            && $field === 'skill_id'
            && isset($_GET['skill_id'])
        ) {
            return (string) absint($_GET['skill_id']);
        }

        if ($this->editingExercise === null) {
            return $default;
        }

        return isset($this->editingExercise[$field])
            ? (string) $this->editingExercise[$field]
            : $default;
    }

    private function renderNotice(): void
    {
        $notice = isset($_GET['e2n_notice'])
            ? sanitize_key(
                wp_unslash($_GET['e2n_notice'])
            )
            : '';

        $messages = [
            'invalid' => [
                'error',
                __('Veuillez remplir les champs obligatoires.', 'ecole2nat'),
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
        <div
            class="notice notice-<?php echo esc_attr($type); ?> is-dismissible"
        >
            <p><?php echo esc_html($message); ?></p>
        </div>
        <?php
    }

    private function redirectToEditor(
        int $exerciseId,
        string $notice
    ): void {
        $arguments = [
            'page' => 'ecole2nat-exercise',
            'e2n_notice' => $notice,
        ];

        if ($exerciseId > 0) {
            $arguments['exercise_id'] = $exerciseId;
        }

        wp_safe_redirect(
            add_query_arg(
                $arguments,
                admin_url('admin.php')
            )
        );

        exit;
    }

    private function redirectToList(string $notice): void
    {
        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => 'ecole2nat-exercises',
                    'e2n_notice' => $notice,
                ],
                admin_url('admin.php')
            )
        );

        exit;
    }
}