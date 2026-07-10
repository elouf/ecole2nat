<?php

namespace Ecole2Nat\Admin\Pages;

use Ecole2Nat\Category\CategoryRepository;

if (!defined('ABSPATH')) {
    exit;
}

class CategoryPage
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

        $repository = new CategoryRepository();
        $message = '';

        if (isset($_POST['e2n_add_category'])) {
            check_admin_referer('e2n_add_category');

            $name = isset($_POST['category_name'])
                ? sanitize_text_field(wp_unslash($_POST['category_name']))
                : '';

            $description = isset($_POST['category_description'])
                ? sanitize_textarea_field(wp_unslash($_POST['category_description']))
                : '';

            $sortOrder = isset($_POST['category_sort_order'])
                ? intval($_POST['category_sort_order'])
                : 0;

            if ($name === '') {
                $message = 'Le nom de la catégorie est obligatoire.';
            } elseif ($repository->create($name, $description, $sortOrder)) {
                $message = 'La catégorie a bien été ajoutée.';
            } else {
                $message = 'Une erreur est survenue lors de l’ajout.';
            }
        }

        if (
            isset($_GET['action'], $_GET['category'])
            && sanitize_key(wp_unslash($_GET['action'])) === 'toggle-active'
        ) {
            $categoryId = absint($_GET['category']);

            check_admin_referer(
                'e2n_toggle_category_' . $categoryId
            );

            if ($categoryId > 0 && $repository->toggleActive($categoryId)) {
                wp_safe_redirect(
                    add_query_arg(
                        [
                            'page'    => 'ecole2nat-categories',
                            'updated' => 'category-status',
                        ],
                        admin_url('admin.php')
                    )
                );

                exit;
            }

            $message = 'Impossible de modifier le statut de cette catégorie.';
        }

        if (
            isset($_GET['updated'])
            && sanitize_key(wp_unslash($_GET['updated'])) === 'category-status'
        ) {
            $message = 'Le statut de la catégorie a bien été mis à jour.';
        }

        $categories = $repository->all();

        echo '<div class="wrap">';
        echo '<h1>Catégories</h1>';

        if ($message !== '') {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . esc_html($message) . '</p>';
            echo '</div>';
        }

        echo '<form method="post">';
        wp_nonce_field('e2n_add_category');

        echo '<table class="form-table">';
        echo '<tbody>';

        echo '<tr>';
        echo '<th scope="row">';
        echo '<label for="e2n-category-name">Nom</label>';
        echo '</th>';
        echo '<td>';
        echo '<input
            id="e2n-category-name"
            type="text"
            name="category_name"
            class="regular-text"
            required
        >';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">';
        echo '<label for="e2n-category-description">Description</label>';
        echo '</th>';
        echo '<td>';
        echo '<textarea
            id="e2n-category-description"
            name="category_description"
            class="large-text"
            rows="4"
        ></textarea>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">';
        echo '<label for="e2n-category-sort-order">Ordre</label>';
        echo '</th>';
        echo '<td>';
        echo '<input
            id="e2n-category-sort-order"
            type="number"
            name="category_sort_order"
            value="0"
            min="0"
        >';
        echo '</td>';
        echo '</tr>';

        echo '</tbody>';
        echo '</table>';

        echo '<p>';
        echo '<button
            type="submit"
            class="button button-primary"
            name="e2n_add_category"
            value="1"
        >Ajouter une catégorie</button>';
        echo '</p>';

        echo '</form>';

        if (empty($categories)) {
            echo '<p>Aucune catégorie.</p>';
            echo '</div>';

            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th scope="col">Nom</th>';
        echo '<th scope="col">Description</th>';
        echo '<th scope="col">Ordre</th>';
        echo '<th scope="col">Statut</th>';
        echo '<th scope="col">Action</th>';
        echo '</tr>';
        echo '</thead>';

        echo '<tbody>';

        foreach ($categories as $category) {
            $categoryId = (int) $category['id'];
            $isActive = !empty($category['is_active']);

            $url = add_query_arg(
                [
                    'page'     => 'ecole2nat-categories',
                    'action'   => 'toggle-active',
                    'category' => $categoryId,
                ],
                admin_url('admin.php')
            );

            $url = wp_nonce_url(
                $url,
                'e2n_toggle_category_' . $categoryId
            );

            echo '<tr>';
            echo '<td><strong>' . esc_html($category['name']) . '</strong></td>';
            echo '<td>' . esc_html($category['description']) . '</td>';
            echo '<td>' . esc_html((string) $category['sort_order']) . '</td>';
            echo '<td>' . ($isActive ? 'Active' : 'Inactive') . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url($url) . '">';
            echo $isActive ? 'Désactiver' : 'Activer';
            echo '</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
}