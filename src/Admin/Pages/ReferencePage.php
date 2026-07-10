<?php

namespace Ecole2Nat\Admin\Pages;

use Ecole2Nat\Category\CategoryRepository;
use Ecole2Nat\Reference\DomainRepository;
use Ecole2Nat\Reference\SkillRepository;

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
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead>';
                echo '<tr>';
                echo '<th scope="col">Compétence</th>';
                echo '<th scope="col">Description</th>';
                echo '<th scope="col">Ordre</th>';
                echo '</tr>';
                echo '</thead>';

                echo '<tbody>';

                foreach ($skills as $skill) {
                    echo '<tr>';

                    echo '<td>';
                    echo '<strong>';
                    echo esc_html($skill['name']);
                    echo '</strong>';
                    echo '</td>';

                    echo '<td>';
                    echo esc_html($skill['description']);
                    echo '</td>';

                    echo '<td>';
                    echo esc_html((string) $skill['sort_order']);
                    echo '</td>';

                    echo '</tr>';
                }

                echo '</tbody>';
                echo '</table>';
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