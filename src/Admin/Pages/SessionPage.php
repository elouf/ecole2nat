<?php

namespace Ecole2Nat\Admin\Pages;

use Ecole2Nat\Category\CategoryRepository;
use Ecole2Nat\Session\SessionService;
use Ecole2Nat\Session\SessionPartService;
use Ecole2Nat\Session\SessionExerciseService;

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
            wp_die(
                esc_html__(
                    'Vous n’avez pas les droits nécessaires.',
                    'ecole2nat'
                )
            );
        }

        $this->handleActions();

        $sessionId = isset($_GET['session_id'])
            ? absint($_GET['session_id'])
            : 0;

        $session = $sessionId > 0
            ? $this->sessionService->find($sessionId)
            : null;

        $parts = $session !== null
            ? $this->partService->allBySession($sessionId)
            : [];

        $categories = array_values(
            array_filter(
                $this->categoryRepository->all(),
                static fn(array $category): bool =>
                    (int) ($category['is_active'] ?? 1) === 1
            )
        );

        ?>
        <div class="wrap">
            <h1>
                <?php
                echo esc_html(
                    $session !== null
                        ? sprintf(
                            __('Modifier « %s »', 'ecole2nat'),
                            $session['name']
                        )
                        : __('Nouvelle séance', 'ecole2nat')
                );
                ?>
            </h1>

            <?php $this->renderNotice(); ?>

            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle">
                        <?php esc_html_e('Informations générales', 'ecole2nat'); ?>
                    </h2>
                </div>

                <div class="inside">
                    <?php $this->renderForm($categories); ?>
                </div>
            </div>

            <div class="inside">
                <?php if ($session === null) : ?>

                    <p>
                        <?php esc_html_e(
                            'Enregistrez d’abord la séance pour pouvoir ajouter ses parties.',
                            'ecole2nat'
                        ); ?>
                    </p>

                <?php else : ?>

                    <?php if ($parts === []) : ?>

                        <p>
                            <?php esc_html_e(
                                'Aucune partie pour le moment.',
                                'ecole2nat'
                            ); ?>
                        </p>

                    <?php else : ?>

                        <?php foreach ($parts as $part) : ?>
                            <?php
                            $partId = (int) $part['id'];
                            $exercises = $this->exerciseService->allByPart($partId);
                            ?>
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h3 class="hndle">
                                        <?php
                                        echo esc_html(
                                            sprintf(
                                                '%d. %s',
                                                (int) $part['position'],
                                                $part['title']
                                            )
                                        );
                                        ?>
                                    </h3>
                                </div>

                                <div class="inside">
                                    <?php if ($exercises === []) : ?>

                                        <p>
                                            <?php esc_html_e(
                                                'Aucun exercice dans cette partie.',
                                                'ecole2nat'
                                            ); ?>
                                        </p>

                                    <?php else : ?>

                                        <ol>
                                            <?php foreach ($exercises as $exercise) : ?>
                                                <?php
                                                $duration = !empty($exercise['custom_duration'])
                                                    ? (int) $exercise['custom_duration']
                                                    : (int) ($exercise['default_duration'] ?? 0);
                                                ?>

                                                <li style="margin-bottom: 12px;">
                                                    <strong>
                                                        <?php echo esc_html($exercise['name']); ?>
                                                    </strong>

                                                    <?php if ($duration > 0) : ?>
                                                        <span>
                                                            — <?php echo esc_html((string) $duration); ?> min
                                                        </span>
                                                    <?php endif; ?>

                                                    <?php if (!empty($exercise['description'])) : ?>
                                                        <p class="description">
                                                            <?php echo esc_html($exercise['description']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ol>

                                    <?php endif; ?>

                                    <p>
                                        <button
                                            type="button"
                                            class="button button-secondary"
                                            disabled
                                        >
                                            <?php esc_html_e(
                                                'Ajouter un exercice',
                                                'ecole2nat'
                                            ); ?>
                                        </button>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>

                    <?php endif; ?>

                    <hr>

                    <h3>
                        <?php esc_html_e('Ajouter une partie', 'ecole2nat'); ?>
                    </h3>

                    <form method="post">
                        <?php wp_nonce_field('e2n_create_session_part'); ?>

                        <input
                            type="hidden"
                            name="e2n_action"
                            value="create_session_part"
                        >

                        <input
                            type="hidden"
                            name="session_id"
                            value="<?php echo esc_attr((string) $sessionId); ?>"
                        >

                        <p>
                            <label for="e2n-part-title">
                                <strong>
                                    <?php esc_html_e('Titre', 'ecole2nat'); ?>
                                </strong>
                            </label>
                        </p>

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

                        <?php
                        submit_button(
                            __('Ajouter la partie', 'ecole2nat'),
                            'secondary',
                            'submit',
                            false
                        );
                        ?>
                    </form>

                <?php endif; ?>
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

        /*
        * Création d'une partie de séance.
        */
        if ($action === 'create_session_part') {
            check_admin_referer('e2n_create_session_part');

            $sessionId = isset($_POST['session_id'])
                ? absint($_POST['session_id'])
                : 0;

            $title = isset($_POST['part_title'])
                ? sanitize_text_field(wp_unslash($_POST['part_title']))
                : '';

            if ($sessionId <= 0 || $title === '') {
                $this->redirectToEditor($sessionId, 'invalid');
            }

            $result = $this->partService->create(
                $sessionId,
                $title
            );

            $this->redirectToEditor(
                $sessionId,
                $result['message']
            );
        }

        /*
        * Création d'une séance.
        */
        if ($action === 'create_session') {
            check_admin_referer('e2n_create_session');

            $categoryId = isset($_POST['category_id'])
                ? absint($_POST['category_id'])
                : 0;

            $name = isset($_POST['name'])
                ? sanitize_text_field(wp_unslash($_POST['name']))
                : '';

            $objectives = isset($_POST['objectives'])
                ? sanitize_textarea_field(wp_unslash($_POST['objectives']))
                : '';

            if ($categoryId <= 0 || $name === '') {
                $this->redirectWithNotice('invalid');
            }

            $result = $this->sessionService->create(
                $categoryId,
                $name,
                $objectives
            );

            $this->redirectWithNotice($result['message']);
        }
    }

    private function renderForm(array $categories): void
    {
        if ($categories === []) {
            ?>
            <div class="notice notice-warning inline">
                <p>
                    <?php esc_html_e(
                        'Vous devez créer au moins une catégorie active.',
                        'ecole2nat'
                    ); ?>
                </p>
            </div>
            <?php

            return;
        }

        ?>
        <form method="post">
            <?php wp_nonce_field('e2n_create_session'); ?>

            <input
                type="hidden"
                name="e2n_action"
                value="create_session"
            >

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="e2n-session-category">
                                <?php esc_html_e('Catégorie', 'ecole2nat'); ?>
                            </label>
                        </th>

                        <td>
                            <select
                                id="e2n-session-category"
                                name="category_id"
                                class="regular-text"
                                required
                            >
                                <option value="">
                                    <?php esc_html_e('— Sélectionner —', 'ecole2nat'); ?>
                                </option>

                                <?php foreach ($categories as $category) : ?>
                                    <option
                                        value="<?php echo esc_attr((string) $category['id']); ?>"
                                    >
                                        <?php echo esc_html($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="e2n-session-name">
                                <?php esc_html_e('Nom', 'ecole2nat'); ?>
                            </label>
                        </th>

                        <td>
                            <input
                                id="e2n-session-name"
                                type="text"
                                name="name"
                                class="regular-text"
                                maxlength="150"
                                required
                            >
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="e2n-session-objectives">
                                <?php esc_html_e('Objectifs', 'ecole2nat'); ?>
                            </label>
                        </th>

                        <td>
                            <textarea
                                id="e2n-session-objectives"
                                name="objectives"
                                class="large-text"
                                rows="5"
                            ></textarea>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button(__('Enregistrer la séance', 'ecole2nat')); ?>
        </form>
        <?php
    }

    private function renderNotice(): void
    {
        $notice = isset($_GET['e2n_notice'])
            ? sanitize_key(wp_unslash($_GET['e2n_notice']))
            : '';

        $messages = [
            'duplicate' => [
                'warning',
                __('Une séance portant ce nom existe déjà pour cette catégorie.', 'ecole2nat'),
            ],
            'invalid' => [
                'error',
                __('Veuillez remplir les champs obligatoires.', 'ecole2nat'),
            ],
            'error' => [
                'error',
                __('Une erreur est survenue.', 'ecole2nat'),
            ],
            'created' => [
                'success',
                __('La partie a bien été ajoutée.', 'ecole2nat'),
            ],
            'duplicate' => [
                'warning',
                __('Une partie portant ce titre existe déjà dans cette séance.', 'ecole2nat'),
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

    private function redirectWithNotice(string $notice): void
    {
        if ($notice === 'created') {
            wp_safe_redirect(
                add_query_arg(
                    [
                        'page' => 'ecole2nat-sessions',
                        'e2n_notice' => 'created',
                    ],
                    admin_url('admin.php')
                )
            );

            exit;
        }

        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => 'ecole2nat-session',
                    'e2n_notice' => $notice,
                ],
                admin_url('admin.php')
            )
        );

        exit;
    }

    private function redirectToEditor(
        int $sessionId,
        string $notice
    ): void {
        $arguments = [
            'page' => 'ecole2nat-session',
            'e2n_notice' => $notice,
        ];

        if ($sessionId > 0) {
            $arguments['session_id'] = $sessionId;
        }

        wp_safe_redirect(
            add_query_arg(
                $arguments,
                admin_url('admin.php')
            )
        );

        exit;
    }
}