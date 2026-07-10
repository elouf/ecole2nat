<?php

namespace Ecole2Nat\Admin\Pages;

use Ecole2Nat\Category\CategoryRepository;
use Ecole2Nat\Reference\DomainRepository;
use Ecole2Nat\Reference\SkillRepository;
use Ecole2Nat\Exercise\ExerciseRepository;

if (!defined('ABSPATH')) {
    exit;
}

class ReferencePage
{
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

        $categoryRepository = new CategoryRepository();
        $domainRepository = new DomainRepository();
        $skillRepository = new SkillRepository();
        $exerciseRepository = new ExerciseRepository();

        $categories = $categoryRepository->all();
        $message = '';

        echo '<div class="wrap">';
        echo '<h1>Référentiel</h1>';

        if (empty($categories)) {
            echo '<p>Vous devez d’abord créer une catégorie.</p>';
            echo '</div>';

            return;
        }

        $categoryId = isset($_GET['category'])
            ? absint($_GET['category'])
            : (int) $categories[0]['id'];

        /*
         * Ajout d’un domaine.
         */
        if (isset($_POST['e2n_add_domain'])) {
            check_admin_referer('e2n_add_domain');

            $name = isset($_POST['domain_name'])
                ? sanitize_text_field(wp_unslash($_POST['domain_name']))
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
                $message = 'Le nom du domaine est obligatoire.';
            } elseif (
                $domainRepository->create(
                    $categoryId,
                    $name,
                    $description,
                    $sortOrder
                )
            ) {
                $message = 'Le domaine a bien été ajouté.';
            } else {
                $message = 'Impossible d’ajouter le domaine.';
            }
        }

        /*
         * Ajout d’une compétence.
         */
        if (isset($_POST['e2n_add_skill'])) {
            check_admin_referer('e2n_add_skill');

            $domainId = isset($_POST['domain_id'])
                ? absint($_POST['domain_id'])
                : 0;

            $name = isset($_POST['skill_name'])
                ? sanitize_text_field(wp_unslash($_POST['skill_name']))
                : '';

            $description = isset($_POST['skill_description'])
                ? sanitize_textarea_field(
                    wp_unslash($_POST['skill_description'])
                )
                : '';

            $sortOrder = isset($_POST['skill_sort_order'])
                ? absint($_POST['skill_sort_order'])
                : 0;

            if ($domainId === 0 || $name === '') {
                $message = 'Le domaine et le nom de la compétence sont obligatoires.';
            } elseif (
                $skillRepository->create(
                    $domainId,
                    $name,
                    $description,
                    $sortOrder
                )
            ) {
                $message = 'La compétence a bien été ajoutée.';
            } else {
                $message = 'Impossible d’ajouter la compétence.';
            }
        }

        $domains = $domainRepository->allByCategory($categoryId);

        if ($message !== '') {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . esc_html($message) . '</p>';
            echo '</div>';
        }

        if (isset($_POST['e2n_add_exercise'])) {
            check_admin_referer('e2n_add_exercise');

            $skillId = isset($_POST['skill_id'])
                ? absint($_POST['skill_id'])
                : 0;

            $name = isset($_POST['exercise_name'])
                ? sanitize_text_field(wp_unslash($_POST['exercise_name']))
                : '';

            $description = isset($_POST['exercise_description'])
                ? sanitize_textarea_field(
                    wp_unslash($_POST['exercise_description'])
                )
                : '';

            $objectives = isset($_POST['exercise_objectives'])
                ? sanitize_textarea_field(
                    wp_unslash($_POST['exercise_objectives'])
                )
                : '';

            $coachNotes = isset($_POST['exercise_coach_notes'])
                ? sanitize_textarea_field(
                    wp_unslash($_POST['exercise_coach_notes'])
                )
                : '';

            $equipment = isset($_POST['exercise_equipment'])
                ? sanitize_text_field(
                    wp_unslash($_POST['exercise_equipment'])
                )
                : '';

            $duration = isset($_POST['exercise_duration'])
                && $_POST['exercise_duration'] !== ''
                    ? absint($_POST['exercise_duration'])
                    : null;

            $difficulty = isset($_POST['exercise_difficulty'])
                ? min(5, max(1, absint($_POST['exercise_difficulty'])))
                : 1;

            if ($skillId === 0 || $name === '') {
                $message = 'La compétence et le nom de l’exercice sont obligatoires.';
            } elseif (
                $exerciseRepository->create(
                    $skillId,
                    $name,
                    $description,
                    $objectives,
                    $coachNotes,
                    $equipment,
                    $duration,
                    $difficulty
                )
            ) {
                $message = 'L’exercice a bien été ajouté.';
            } else {
                $message = 'Impossible d’ajouter l’exercice.';
            }
        }


        /*
         * Sélection de la catégorie.
         */
        echo '<form method="get" style="margin-bottom: 20px;">';

        echo '<input
            type="hidden"
            name="page"
            value="ecole2nat-reference"
        >';

        echo '<label for="e2n-reference-category">';
        echo '<strong>Catégorie :</strong> ';
        echo '</label>';

        echo '<select
            id="e2n-reference-category"
            name="category"
            onchange="this.form.submit()"
        >';

        foreach ($categories as $category) {
            printf(
                '<option value="%d" %s>%s</option>',
                (int) $category['id'],
                selected(
                    $categoryId,
                    (int) $category['id'],
                    false
                ),
                esc_html($category['name'])
            );
        }

        echo '</select>';
        echo '</form>';

        /*
         * Ajout d’un domaine.
         */
        echo '<div class="postbox">';
        echo '<div class="postbox-header">';
        echo '<h2 class="hndle">Ajouter un domaine</h2>';
        echo '</div>';

        echo '<div class="inside">';
        echo '<form method="post">';

        wp_nonce_field('e2n_add_domain');

        echo '<p>';
        echo '<label for="e2n-domain-name">';
        echo '<strong>Nom</strong>';
        echo '</label><br>';

        echo '<input
            id="e2n-domain-name"
            type="text"
            name="domain_name"
            class="regular-text"
            required
        >';
        echo '</p>';

        echo '<p>';
        echo '<label for="e2n-domain-description">';
        echo '<strong>Description</strong>';
        echo '</label><br>';

        echo '<textarea
            id="e2n-domain-description"
            name="domain_description"
            class="large-text"
            rows="3"
        ></textarea>';
        echo '</p>';

        echo '<p>';
        echo '<label for="e2n-domain-sort-order">';
        echo '<strong>Ordre</strong>';
        echo '</label><br>';

        echo '<input
            id="e2n-domain-sort-order"
            type="number"
            name="domain_sort_order"
            value="0"
            min="0"
        >';
        echo '</p>';

        echo '<p>';
        echo '<button
            type="submit"
            class="button button-primary"
            name="e2n_add_domain"
            value="1"
        >';
        echo 'Ajouter le domaine';
        echo '</button>';
        echo '</p>';

        echo '</form>';
        echo '</div>';
        echo '</div>';

        /*
         * Domaines et compétences.
         */
        if (empty($domains)) {
            echo '<p>Aucun domaine pour cette catégorie.</p>';
            echo '</div>';

            return;
        }

        foreach ($domains as $domain) {
            $domainId = (int) $domain['id'];
            $skills = $skillRepository->allByDomain($domainId);

            echo '<div class="postbox">';

            echo '<div class="postbox-header">';
            echo '<h2 class="hndle">';
            echo esc_html($domain['name']);
            echo '</h2>';
            echo '</div>';

            echo '<div class="inside">';

            if (!empty($domain['description'])) {
                echo '<p>';
                echo esc_html($domain['description']);
                echo '</p>';
            }

            if (empty($skills)) {
                echo '<p>Aucune compétence.</p>';
            } else {
                
                foreach ($skills as $skill) {
                    $skillId = (int) $skill['id'];
                    $exercises = $exerciseRepository->allBySkill($skillId);

                    echo '<div style="
                        margin: 15px 0;
                        padding: 15px;
                        border: 1px solid #dcdcde;
                        background: #fff;
                    ">';

                    echo '<h3 style="margin-top: 0;">';
                    echo esc_html($skill['name']);
                    echo '</h3>';

                    if (!empty($skill['description'])) {
                        echo '<p>' . esc_html($skill['description']) . '</p>';
                    }

                    echo '<h4>Exercices</h4>';

                    if (empty($exercises)) {
                        echo '<p>Aucun exercice.</p>';
                    } else {
                        echo '<table class="wp-list-table widefat fixed striped">';
                        echo '<thead>';
                        echo '<tr>';
                        echo '<th>Nom</th>';
                        echo '<th>Objectif</th>';
                        echo '<th>Matériel</th>';
                        echo '<th>Durée</th>';
                        echo '<th>Difficulté</th>';
                        echo '</tr>';
                        echo '</thead>';
                        echo '<tbody>';

                        foreach ($exercises as $exercise) {
                            echo '<tr>';
                            echo '<td><strong>'
                                . esc_html($exercise['name'])
                                . '</strong></td>';

                            echo '<td>'
                                . esc_html($exercise['objectives'])
                                . '</td>';

                            echo '<td>'
                                . esc_html($exercise['equipment'])
                                . '</td>';

                            echo '<td>';

                            if ($exercise['duration'] !== null) {
                                echo esc_html((string) $exercise['duration']) . ' min';
                            } else {
                                echo '—';
                            }

                            echo '</td>';

                            echo '<td>'
                                . esc_html((string) $exercise['difficulty'])
                                . '/5</td>';

                            echo '</tr>';
                        }

                        echo '</tbody>';
                        echo '</table>';
                    }

                    echo '<details style="margin-top: 15px;">';
                    echo '<summary style="cursor: pointer;">';
                    echo '<strong>Ajouter un exercice</strong>';
                    echo '</summary>';

                    echo '<form method="post" style="margin-top: 15px;">';

                    wp_nonce_field('e2n_add_exercise');

                    echo '<input
                        type="hidden"
                        name="skill_id"
                        value="' . esc_attr((string) $skillId) . '"
                    >';

                    echo '<p>';
                    echo '<label><strong>Nom</strong></label><br>';
                    echo '<input
                        type="text"
                        name="exercise_name"
                        class="regular-text"
                        required
                    >';
                    echo '</p>';

                    echo '<p>';
                    echo '<label><strong>Description</strong></label><br>';
                    echo '<textarea
                        name="exercise_description"
                        class="large-text"
                        rows="3"
                    ></textarea>';
                    echo '</p>';

                    echo '<p>';
                    echo '<label><strong>Objectif</strong></label><br>';
                    echo '<textarea
                        name="exercise_objectives"
                        class="large-text"
                        rows="2"
                    ></textarea>';
                    echo '</p>';

                    echo '<p>';
                    echo '<label><strong>Consignes coach</strong></label><br>';
                    echo '<textarea
                        name="exercise_coach_notes"
                        class="large-text"
                        rows="2"
                    ></textarea>';
                    echo '</p>';

                    echo '<p>';
                    echo '<label><strong>Matériel</strong></label><br>';
                    echo '<input
                        type="text"
                        name="exercise_equipment"
                        class="regular-text"
                    >';
                    echo '</p>';

                    echo '<p>';
                    echo '<label><strong>Durée en minutes</strong></label><br>';
                    echo '<input
                        type="number"
                        name="exercise_duration"
                        min="1"
                    >';
                    echo '</p>';

                    echo '<p>';
                    echo '<label><strong>Difficulté</strong></label><br>';
                    echo '<select name="exercise_difficulty">';

                    for ($difficulty = 1; $difficulty <= 5; $difficulty++) {
                        echo '<option value="' . $difficulty . '">';
                        echo $difficulty . '/5';
                        echo '</option>';
                    }

                    echo '</select>';
                    echo '</p>';

                    echo '<p>';
                    echo '<button
                        type="submit"
                        class="button"
                        name="e2n_add_exercise"
                        value="1"
                    >';
                    echo 'Ajouter l’exercice';
                    echo '</button>';
                    echo '</p>';

                    echo '</form>';
                    echo '</details>';

                    echo '</div>';
                }
            }

            echo '<hr>';

            /*
             * Formulaire d’ajout de compétence.
             */
            echo '<h3>Ajouter une compétence</h3>';

            echo '<form method="post">';

            wp_nonce_field('e2n_add_skill');

            echo '<input
                type="hidden"
                name="domain_id"
                value="' . esc_attr((string) $domainId) . '"
            >';

            echo '<p>';
            echo '<label>';
            echo '<strong>Nom</strong>';
            echo '</label><br>';

            echo '<input
                type="text"
                name="skill_name"
                class="regular-text"
                required
            >';
            echo '</p>';

            echo '<p>';
            echo '<label>';
            echo '<strong>Description</strong>';
            echo '</label><br>';

            echo '<textarea
                name="skill_description"
                class="large-text"
                rows="3"
            ></textarea>';
            echo '</p>';

            echo '<p>';
            echo '<label>';
            echo '<strong>Ordre</strong>';
            echo '</label><br>';

            echo '<input
                type="number"
                name="skill_sort_order"
                value="0"
                min="0"
            >';
            echo '</p>';

            echo '<p>';
            echo '<button
                type="submit"
                class="button"
                name="e2n_add_skill"
                value="1"
            >';
            echo 'Ajouter la compétence';
            echo '</button>';
            echo '</p>';

            echo '</form>';

            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
    }
}