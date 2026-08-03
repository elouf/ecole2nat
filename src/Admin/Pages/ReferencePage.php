<?php

namespace Ecole2Nat\Admin\Pages;

use Ecole2Nat\Category\CategoryRepository;
use Ecole2Nat\Exercise\ExerciseRepository;
use Ecole2Nat\Reference\DomainRepository;
use Ecole2Nat\Reference\SkillRepository;

if (!defined('ABSPATH')) {
    exit;
}

class ReferencePage
{
    private CategoryRepository $categoryRepository;
    private DomainRepository $domainRepository;
    private SkillRepository $skillRepository;
    private ExerciseRepository $exerciseRepository;

    public function __construct()
    {
        $this->categoryRepository = new CategoryRepository();
        $this->domainRepository = new DomainRepository();
        $this->skillRepository = new SkillRepository();
        $this->exerciseRepository = new ExerciseRepository();
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__(
                    'Vous ne disposez pas des droits nécessaires.',
                    'ecole2nat'
                )
            );
        }

        $categories = $this->categoryRepository->all();

        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e(
                    'Référentiel pédagogique',
                    'ecole2nat'
                ); ?>
            </h1>

            <?php if ($categories === []) : ?>
                <div class="notice notice-warning inline">
                    <p>
                        <?php esc_html_e(
                            'Vous devez d’abord créer une catégorie.',
                            'ecole2nat'
                        ); ?>
                    </p>
                </div>
            </div>
                <?php
                return;
            endif;

            $categoryId = isset($_GET['category'])
                ? absint($_GET['category'])
                : (int) $categories[0]['id'];

            $this->handleActions($categoryId);

            $domains = $this->domainRepository->allByCategory(
                $categoryId
            );
            ?>

            <?php $this->renderNotice(); ?>

            <?php
            $this->renderCategorySelector(
                $categories,
                $categoryId
            );
            ?>

            <?php $this->renderDomainForm($categoryId); ?>

            <hr>

            <?php if ($domains === []) : ?>
                <p>
                    <?php esc_html_e(
                        'Aucun domaine pour cette catégorie.',
                        'ecole2nat'
                    ); ?>
                </p>
            <?php else : ?>
                <?php foreach ($domains as $domain) : ?>
                    <?php $this->renderDomain($domain); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function handleActions(int $categoryId): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = isset($_POST['e2n_action'])
            ? sanitize_key(wp_unslash($_POST['e2n_action']))
            : '';

        if ($action === 'create_domain') {
            check_admin_referer('e2n_create_domain');

            $name = isset($_POST['domain_name'])
                ? sanitize_text_field(
                    wp_unslash($_POST['domain_name'])
                )
                : '';

            $description = isset($_POST['domain_description'])
                ? sanitize_textarea_field(
                    wp_unslash($_POST['domain_description'])
                )
                : '';

            $sortOrder = isset($_POST['domain_sort_order'])
                ? absint($_POST['domain_sort_order'])
                : 0;

            if ($name === '') {
                $this->redirect(
                    $categoryId,
                    'domain_invalid'
                );
            }

            $created = $this->domainRepository->create(
                $categoryId,
                $name,
                $description,
                $sortOrder
            );

            $this->redirect(
                $categoryId,
                $created ? 'domain_created' : 'error'
            );
        }

        if ($action === 'create_skill') {
            check_admin_referer('e2n_create_skill');

            $domainId = isset($_POST['domain_id'])
                ? absint($_POST['domain_id'])
                : 0;

            $name = isset($_POST['skill_name'])
                ? sanitize_text_field(
                    wp_unslash($_POST['skill_name'])
                )
                : '';

            $description = isset($_POST['skill_description'])
                ? sanitize_textarea_field(
                    wp_unslash($_POST['skill_description'])
                )
                : '';

            $sortOrder = isset($_POST['skill_sort_order'])
                ? absint($_POST['skill_sort_order'])
                : 0;

            if ($domainId <= 0 || $name === '') {
                $this->redirect(
                    $categoryId,
                    'skill_invalid'
                );
            }

            $created = $this->skillRepository->create(
                $domainId,
                $name,
                $description,
                $sortOrder
            );

            $this->redirect(
                $categoryId,
                $created ? 'skill_created' : 'error'
            );
        }
    }

    private function renderCategorySelector(
        array $categories,
        int $categoryId
    ): void {
        ?>
        <form method="get" style="margin-bottom: 20px;">
            <input
                type="hidden"
                name="page"
                value="ecole2nat-reference"
            >

            <label for="e2n-reference-category">
                <strong>
                    <?php esc_html_e('Catégorie :', 'ecole2nat'); ?>
                </strong>
            </label>

            <select
                id="e2n-reference-category"
                name="category"
                onchange="this.form.submit()"
            >
                <?php foreach ($categories as $category) : ?>
                    <option
                        value="<?php echo esc_attr(
                            (string) $category['id']
                        ); ?>"
                        <?php selected(
                            $categoryId,
                            (int) $category['id']
                        ); ?>
                    >
                        <?php echo esc_html($category['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php
    }

    private function renderDomainForm(int $categoryId): void
    {
        ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">
                    <?php esc_html_e(
                        'Ajouter un domaine',
                        'ecole2nat'
                    ); ?>
                </h2>
            </div>

            <div class="inside">
                <form method="post">
                    <?php wp_nonce_field('e2n_create_domain'); ?>

                    <input
                        type="hidden"
                        name="e2n_action"
                        value="create_domain"
                    >

                    <input
                        type="hidden"
                        name="category_id"
                        value="<?php echo esc_attr(
                            (string) $categoryId
                        ); ?>"
                    >

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="e2n-domain-name">
                                        <?php esc_html_e(
                                            'Nom',
                                            'ecole2nat'
                                        ); ?>
                                    </label>
                                </th>

                                <td>
                                    <input
                                        id="e2n-domain-name"
                                        type="text"
                                        name="domain_name"
                                        class="regular-text"
                                        required
                                    >
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="e2n-domain-description">
                                        <?php esc_html_e(
                                            'Description',
                                            'ecole2nat'
                                        ); ?>
                                    </label>
                                </th>

                                <td>
                                    <textarea
                                        id="e2n-domain-description"
                                        name="domain_description"
                                        class="large-text"
                                        rows="3"
                                    ></textarea>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="e2n-domain-sort-order">
                                        <?php esc_html_e(
                                            'Ordre',
                                            'ecole2nat'
                                        ); ?>
                                    </label>
                                </th>

                                <td>
                                    <input
                                        id="e2n-domain-sort-order"
                                        type="number"
                                        name="domain_sort_order"
                                        value="0"
                                        min="0"
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <?php
                    submit_button(
                        __('Ajouter le domaine', 'ecole2nat')
                    );
                    ?>
                </form>
            </div>
        </div>
        <?php
    }

    private function renderDomain(array $domain): void
    {
        $domainId = (int) $domain['id'];

        $skills = $this->skillRepository->allByDomain(
            $domainId
        );

        ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">
                    <?php echo esc_html($domain['name']); ?>
                </h2>
            </div>

            <div class="inside">
                <?php if (!empty($domain['description'])) : ?>
                    <p>
                        <?php echo esc_html($domain['description']); ?>
                    </p>
                <?php endif; ?>

                <?php if ($skills === []) : ?>
                    <p>
                        <?php esc_html_e(
                            'Aucune compétence dans ce domaine.',
                            'ecole2nat'
                        ); ?>
                    </p>
                <?php else : ?>
                    <?php foreach ($skills as $skill) : ?>
                        <?php $this->renderSkill($skill); ?>
                    <?php endforeach; ?>
                <?php endif; ?>

                <hr>

                <?php $this->renderSkillForm($domainId); ?>
            </div>
        </div>
        <?php
    }

    private function renderSkill(array $skill): void
    {
        $skillId = (int) $skill['id'];

        $exercises = $this->exerciseRepository->allBySkill(
            $skillId
        );

        $libraryUrl = add_query_arg(
            [
                'page' => 'ecole2nat-exercises',
            ],
            admin_url('admin.php')
        );

        ?>
        <div
            style="
                margin: 15px 0;
                padding: 15px;
                border: 1px solid #dcdcde;
                background: #fff;
            "
        >
            <h3 style="margin-top: 0;">
                <?php echo esc_html($skill['name']); ?>
            </h3>

            <?php if (!empty($skill['description'])) : ?>
                <p>
                    <?php echo esc_html($skill['description']); ?>
                </p>
            <?php endif; ?>

            <h4>
                <?php
                echo esc_html(
                    sprintf(
                        _n(
                            '%d exercice associé',
                            '%d exercices associés',
                            count($exercises),
                            'ecole2nat'
                        ),
                        count($exercises)
                    )
                );
                ?>
            </h4>

            <?php if ($exercises === []) : ?>
                <p class="description">
                    <?php esc_html_e(
                        'Aucun exercice n’est encore rattaché à cette compétence.',
                        'ecole2nat'
                    ); ?>
                </p>
            <?php else : ?>
                <ul>
                    <?php foreach ($exercises as $exercise) : ?>
                        <?php
                        $editUrl = add_query_arg(
                            [
                                'page' => 'ecole2nat-exercise',
                                'exercise_id' => (int) $exercise['id'],
                            ],
                            admin_url('admin.php')
                        );
                        ?>

                        <li>
                            <a href="<?php echo esc_url($editUrl); ?>">
                                <?php echo esc_html($exercise['name']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <p>
                <a
                    href="<?php echo esc_url($libraryUrl); ?>"
                    class="button button-small"
                >
                    <?php esc_html_e(
                        'Ouvrir la bibliothèque',
                        'ecole2nat'
                    ); ?>
                </a>

                <a
                    href="<?php echo esc_url(
                        add_query_arg(
                            [
                                'page' => 'ecole2nat-exercise',
                                'skill_id' => $skillId,
                            ],
                            admin_url('admin.php')
                        )
                    ); ?>"
                    class="button button-small"
                >
                    <?php esc_html_e(
                        'Créer un exercice',
                        'ecole2nat'
                    ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    private function renderSkillForm(int $domainId): void
    {
        ?>
        <details>
            <summary style="cursor: pointer;">
                <strong>
                    <?php esc_html_e(
                        'Ajouter une compétence',
                        'ecole2nat'
                    ); ?>
                </strong>
            </summary>

            <form method="post" style="margin-top: 15px;">
                <?php wp_nonce_field('e2n_create_skill'); ?>

                <input
                    type="hidden"
                    name="e2n_action"
                    value="create_skill"
                >

                <input
                    type="hidden"
                    name="domain_id"
                    value="<?php echo esc_attr(
                        (string) $domainId
                    ); ?>"
                >

                <p>
                    <label>
                        <strong>
                            <?php esc_html_e('Nom', 'ecole2nat'); ?>
                        </strong>
                    </label>
                    <br>

                    <input
                        type="text"
                        name="skill_name"
                        class="regular-text"
                        required
                    >
                </p>

                <p>
                    <label>
                        <strong>
                            <?php esc_html_e(
                                'Description',
                                'ecole2nat'
                            ); ?>
                        </strong>
                    </label>
                    <br>

                    <textarea
                        name="skill_description"
                        class="large-text"
                        rows="3"
                    ></textarea>
                </p>

                <p>
                    <label>
                        <strong>
                            <?php esc_html_e('Ordre', 'ecole2nat'); ?>
                        </strong>
                    </label>
                    <br>

                    <input
                        type="number"
                        name="skill_sort_order"
                        value="0"
                        min="0"
                    >
                </p>

                <?php
                submit_button(
                    __('Ajouter la compétence', 'ecole2nat'),
                    'secondary',
                    'submit',
                    false
                );
                ?>
            </form>
        </details>
        <?php
    }

    private function renderNotice(): void
    {
        $notice = isset($_GET['e2n_notice'])
            ? sanitize_key(wp_unslash($_GET['e2n_notice']))
            : '';

        $messages = [
            'domain_created' => [
                'success',
                __('Le domaine a bien été ajouté.', 'ecole2nat'),
            ],
            'domain_invalid' => [
                'error',
                __('Le nom du domaine est obligatoire.', 'ecole2nat'),
            ],
            'skill_created' => [
                'success',
                __('La compétence a bien été ajoutée.', 'ecole2nat'),
            ],
            'skill_invalid' => [
                'error',
                __(
                    'Le domaine et le nom de la compétence sont obligatoires.',
                    'ecole2nat'
                ),
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

    private function redirect(
        int $categoryId,
        string $notice
    ): void {
        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => 'ecole2nat-reference',
                    'category' => $categoryId,
                    'e2n_notice' => $notice,
                ],
                admin_url('admin.php')
            )
        );

        exit;
    }
}