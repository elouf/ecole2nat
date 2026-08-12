<?php

namespace Ecole2Nat\Admin\Pages;

use Ecole2Nat\Swimmer\SwimmerService;
use Ecole2Nat\Group\GroupService;
use Ecole2Nat\Swimmer\SwimmerSearchCriteria;
use Ecole2Nat\Category\CategoryRepository;
use Ecole2Nat\Season\SeasonRepository;
use Ecole2Nat\Admin\UI\Badge;
use Ecole2Nat\Admin\Deletion\DeletionController;
use Ecole2Nat\ParentPortal\ParentAccessService;

if (!defined('ABSPATH')) {
    exit;
}

class SwimmerPage
{
    private SwimmerService $swimmerService;
    private GroupService $groupService;
    private CategoryRepository $categoryRepository;
    private SeasonRepository $seasonRepository;
    private ParentAccessService $parentAccessService;
    private ?array $editingSwimmer = null;

    public function __construct()
    {
        $this->swimmerService = new SwimmerService();
        $this->groupService = new GroupService();
        $this->categoryRepository = new CategoryRepository();
        $this->seasonRepository = new SeasonRepository();
        $this->parentAccessService = new ParentAccessService();
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

        if (
            isset($_GET['action'], $_GET['swimmer_id']) &&
            $_GET['action'] === 'edit'
        ) {
            $this->editingSwimmer = $this->swimmerService->find(
                (int) $_GET['swimmer_id']
            );
        }
        $criteria = $this->getSearchCriteria();
        $searchResult = $this->swimmerService->search($criteria);
        $swimmers = $searchResult['items'];
        $total = (int) $searchResult['total'];
        $groups = $this->groupService->all();
        $categories = $this->categoryRepository->all();
        $seasons = $this->seasonRepository->all();

        ?>
        <div class="wrap">
            <h1>
                <?php echo esc_html__('Nageurs', 'ecole2nat'); ?>
            </h1>

            <?php $this->renderNotice(); ?>

            <?php if ($this->editingSwimmer !== null) : ?>
                <?php $this->renderCreateForm(); ?>
            <?php endif; ?>

            <?php $this->renderFilters($criteria, $groups, $categories, $seasons); ?>

            <?php $this->renderTable($swimmers, $criteria, $total); ?>

            <?php if ($this->editingSwimmer === null) : ?>
                <details class="postbox" style="margin-top:20px;">
                    <summary class="postbox-header" style="cursor:pointer; padding:12px;">
                        <strong><?php esc_html_e('Ajouter un nageur', 'ecole2nat'); ?></strong>
                    </summary>
                    <div class="inside">
                        <?php $this->renderCreateForm(); ?>
                    </div>
                </details>
            <?php endif; ?>
        </div>
        <?php
    }

    private function handleActions(): void
    {
        /*
        * Activation / désactivation d’un nageur.
        */
        if (
            isset($_GET['e2n_action'], $_GET['swimmer_id'])
            && sanitize_key(wp_unslash($_GET['e2n_action'])) === 'toggle_swimmer'
        ) {
            $swimmerId = absint($_GET['swimmer_id']);

            check_admin_referer(
                'e2n_toggle_swimmer_' . $swimmerId
            );

            $updated = $this->swimmerService->toggleActive($swimmerId);

            wp_safe_redirect(
                add_query_arg(
                    [
                        'page'    => 'ecole2nat-swimmers',
                        'message' => $updated ? 'updated' : 'error',
                    ],
                    admin_url('admin.php')
                )
            );

            exit;
        }

        /*
        * Les actions suivantes concernent uniquement les formulaires POST.
        */
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = isset($_POST['e2n_action'])
            ? sanitize_key(wp_unslash($_POST['e2n_action']))
            : '';

        /*
        * Création d’un nageur.
        */
        if ($action === 'create_swimmer') {
            check_admin_referer('e2n_create_swimmer');

            $result = $this->swimmerService->create(
                $this->getFormData()
            );

            wp_safe_redirect(
                add_query_arg(
                    [
                        'page'    => 'ecole2nat-swimmers',
                        'message' => $result['message'],
                    ],
                    admin_url('admin.php')
                )
            );

            exit;
        }

        /*
        * Modification d’un nageur.
        */
        if ($action === 'update_swimmer') {
            check_admin_referer('e2n_create_swimmer');

            $swimmerId = isset($_POST['swimmer_id'])
                ? absint($_POST['swimmer_id'])
                : 0;

            if ($swimmerId <= 0) {
                wp_safe_redirect(
                    add_query_arg(
                        [
                            'page'    => 'ecole2nat-swimmers',
                            'message' => 'error',
                        ],
                        admin_url('admin.php')
                    )
                );

                exit;
            }

            $result = $this->swimmerService->update(
                $swimmerId,
                $this->getFormData()
            );

            wp_safe_redirect(
                add_query_arg(
                    [
                        'page'    => 'ecole2nat-swimmers',
                        'message' => $result['message'],
                    ],
                    admin_url('admin.php')
                )
            );

            exit;
        }
    }

    private function renderNotice(): void
    {
        if (!isset($_GET['message'])) {
            return;
        }

        $message = sanitize_key($_GET['message']);

        switch ($message) {
            case 'created':
                ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php esc_html_e('Le nageur a été créé avec succès.', 'ecole2nat'); ?>
                    </p>
                </div>
                <?php
                break;

            case 'duplicate':
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p>
                        <?php esc_html_e('Un nageur avec le même nom, prénom et date de naissance existe déjà.', 'ecole2nat'); ?>
                    </p>
                </div>
                <?php
                break;

            case 'error':
                ?>
                <div class="notice notice-error is-dismissible">
                    <p>
                        <?php esc_html_e('Une erreur est survenue lors de l’enregistrement du nageur.', 'ecole2nat'); ?>
                    </p>
                </div>
                <?php
                break;

            case 'updated':
                ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php esc_html_e(
                            'Le statut du nageur a été mis à jour.',
                            'ecole2nat'
                        ); ?>
                    </p>
                </div>
                <?php
                break;

            case 'deleted':
                echo '<div class="notice notice-success is-dismissible"><p>'
                    . esc_html__('Le nageur a bien été supprimé.', 'ecole2nat')
                    . '</p></div>';
                break;

            case 'delete_blocked':
                $reason = get_transient('e2n_delete_reason_' . get_current_user_id());
                delete_transient('e2n_delete_reason_' . get_current_user_id());
                echo '<div class="notice notice-error is-dismissible"><p>'
                    . esc_html($reason ?: __('La suppression est impossible.', 'ecole2nat'))
                    . '</p></div>';
                break;
        }
    }

    private function renderCreateForm(): void
    {
        $isEditing = $this->editingSwimmer !== null;
        ?>
        <form method="post">
            <input
                type="hidden"
                name="e2n_action"
                value="<?php echo esc_attr(
                    $isEditing
                        ? 'update_swimmer'
                        : 'create_swimmer'
                ); ?>"
            >
            <?php if ($isEditing) : ?>
                <input
                    type="hidden"
                    name="swimmer_id"
                    value="<?php echo esc_attr((string) $this->editingSwimmer['id']); ?>"
                >
            <?php endif; ?>

            <?php wp_nonce_field('e2n_create_swimmer'); ?>

            <?php 
            $groups = $this->groupService->active();
            $this->renderIdentityBox(); ?>

            <?php $this->renderAssignmentBox($groups); ?>

            <?php $this->renderResponsibleBox(); ?>

            <?php $this->renderMedicalBox(); ?>

            <?php
            submit_button(
                $isEditing
                    ? __('Enregistrer les modifications', 'ecole2nat')
                    : __('Créer le nageur', 'ecole2nat')
            );
            ?>
        </form>
        <?php
    }

    private function renderIdentityBox(): void
    {
        ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">
                    <?php
                    echo esc_html(
                        $this->editingSwimmer === null
                            ? __('Informations du nageur', 'ecole2nat')
                            : __('Modification du nageur', 'ecole2nat')
                    );
                    ?>
                </h2>
            </div>

            <div class="inside">

                <table class="form-table" role="presentation">

                    <tr>
                        <th scope="row">
                            <label for="last_name">
                                <?php esc_html_e('Nom *', 'ecole2nat'); ?>
                            </label>
                        </th>

                        <td>
                            <input
                                type="text"
                                id="last_name"
                                name="last_name"
                                class="regular-text"
                                value="<?php echo esc_attr($this->fieldValue('last_name')); ?>"
                                required
                            >
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="first_name">
                                <?php esc_html_e('Prénom *', 'ecole2nat'); ?>
                            </label>
                        </th>

                        <td>
                            <input
                                type="text"
                                id="first_name"
                                name="first_name"
                                class="regular-text"
                                value="<?php echo esc_attr($this->fieldValue('first_name')); ?>"
                                required
                            >
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="birth_date">
                                <?php esc_html_e('Date de naissance', 'ecole2nat'); ?>
                            </label>
                        </th>

                        <td>
                            <input
                                type="date"
                                id="birth_date"
                                name="birth_date"
                                value="<?php echo esc_attr($this->fieldValue('birth_date')); ?>"
                            >
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="gender">
                                <?php esc_html_e('Sexe', 'ecole2nat'); ?>
                            </label>
                        </th>

                        <td>
                            <select
                                id="gender"
                                name="gender"
                            >
                                <option value="">
                                    <?php esc_html_e('— Sélectionner —', 'ecole2nat'); ?>
                                </option>

                                <option
                                    value="F"
                                    <?php selected($this->fieldValue('gender'), 'F'); ?>
                                >
                                    <?php esc_html_e('Féminin', 'ecole2nat'); ?>
                                </option>

                                <option
                                    value="M"
                                    <?php selected($this->fieldValue('gender'), 'M'); ?>
                                >
                                    <?php esc_html_e('Masculin', 'ecole2nat'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>

                </table>

            </div>
        </div>
        <?php
    }

    private function renderAssignmentBox(array $groups): void
    {
        ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">
                    <?php esc_html_e('Affectation', 'ecole2nat'); ?>
                </h2>
            </div>

            <div class="inside">

                <table class="form-table" role="presentation">

                    <tr>
                        <th scope="row">
                            <label for="group_id">
                                <?php esc_html_e('Groupe', 'ecole2nat'); ?>
                            </label>
                        </th>

                        <td>
                            <select
                                id="group_id"
                                name="group_id"
                            >
                                <option value="">
                                    <?php esc_html_e('— Non affecté —', 'ecole2nat'); ?>
                                </option>

                                <?php foreach ($groups as $group) : ?>
                                    <option
                                        value="<?php echo esc_attr((string) $group['id']); ?>"
                                        <?php selected(
                                            (int) $this->fieldValue('group_id', '0'),
                                            (int) $group['id']
                                        ); ?>
                                    >
                                        <?php echo esc_html($group['name']); ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="registration_date">
                                <?php esc_html_e('Date d\'inscription', 'ecole2nat'); ?>
                            </label>
                        </th>

                        <td>
                            <input
                                type="date"
                                id="registration_date"
                                name="registration_date"
                                value="<?php echo esc_attr(
                                    $this->fieldValue(
                                        'registration_date',
                                        current_time('Y-m-d')
                                    )
                                ); ?>"
                            >
                        </td>
                    </tr>

                </table>

            </div>
        </div>
        <?php
    }

    private function renderResponsibleBox(): void
    {
        ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">
                    <?php esc_html_e('Responsable', 'ecole2nat'); ?>
                </h2>
            </div>

            <div class="inside">

                <table class="form-table" role="presentation">

                    <tr>
                        <th scope="row">
                            <label for="responsible_name">
                                <?php esc_html_e('Nom du responsable', 'ecole2nat'); ?>
                            </label>
                        </th>

                        <td>
                            <input
                                type="text"
                                id="responsible_name"
                                name="responsible_name"
                                class="regular-text"
                                value="<?php echo esc_attr($this->fieldValue('responsible_name')); ?>"
                            >
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="responsible_email">
                                <?php esc_html_e('Email', 'ecole2nat'); ?>
                            </label>
                        </th>

                        <td>
                            <input
                                type="email"
                                id="responsible_email"
                                name="responsible_email"
                                class="regular-text"
                                value="<?php echo esc_attr($this->fieldValue('responsible_email')); ?>"
                            >
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="responsible_phone">
                                <?php esc_html_e('Téléphone', 'ecole2nat'); ?>
                            </label>
                        </th>

                        <td>
                            <input
                                type="text"
                                id="responsible_phone"
                                name="responsible_phone"
                                class="regular-text"
                                value="<?php echo esc_attr($this->fieldValue('responsible_phone')); ?>"
                            >
                        </td>
                    </tr>

                </table>

            </div>
        </div>
        <?php
    }

    private function renderMedicalBox(): void
    {
        ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">
                    <?php esc_html_e('Informations complémentaires', 'ecole2nat'); ?>
                </h2>
            </div>

            <div class="inside">

                <table class="form-table" role="presentation">

                    <tr>
                        <th scope="row">
                            <label for="licence_number">
                                <?php esc_html_e('N° de licence', 'ecole2nat'); ?>
                            </label>
                        </th>

                        <td>
                            <input
                                type="text"
                                id="licence_number"
                                name="licence_number"
                                class="regular-text"
                                value="<?php echo esc_attr($this->fieldValue('licence_number')); ?>"
                            >
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="image_rights">
                                <?php esc_html_e('Droit à l’image', 'ecole2nat'); ?>
                            </label>
                        </th>

                        <td>
                            <select id="image_rights" name="image_rights">
                                <option value="" <?php selected($this->fieldValue('image_rights'), ''); ?>>
                                    <?php esc_html_e('Non renseigné', 'ecole2nat'); ?>
                                </option>
                                <option value="1" <?php selected($this->fieldValue('image_rights'), '1'); ?>>
                                    <?php esc_html_e('Oui', 'ecole2nat'); ?>
                                </option>
                                <option value="0" <?php selected($this->fieldValue('image_rights'), '0'); ?>>
                                    <?php esc_html_e('Non', 'ecole2nat'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="medical_note">
                                <?php esc_html_e('Notes médicales', 'ecole2nat'); ?>
                            </label>
                        </th>

                        <td>
                            <textarea
                                id="medical_note"
                                name="medical_note"
                                rows="4"
                                class="large-text"
                            ><?php echo esc_textarea($this->fieldValue('medical_note')); ?></textarea>
                        </td>
                    </tr>

                </table>

            </div>
        </div>
        <?php
    }

    private function renderFilters(
        SwimmerSearchCriteria $criteria,
        array $groups,
        array $categories,
        array $seasons
    ): void {
        ?>
        <form method="get" class="e2n-filter-bar">
            <input type="hidden" name="page" value="ecole2nat-swimmers">

            <label>
                <span><?php esc_html_e('Recherche', 'ecole2nat'); ?></span>
                <input type="search" name="s" value="<?php echo esc_attr($criteria->search); ?>"
                    placeholder="<?php esc_attr_e('Nom, licence, responsable…', 'ecole2nat'); ?>">
            </label>

            <label>
                <span><?php esc_html_e('Groupe', 'ecole2nat'); ?></span>
                <select name="group_id">
                    <option value="0"><?php esc_html_e('Tous', 'ecole2nat'); ?></option>
                    <?php foreach ($groups as $group) : ?>
                        <option value="<?php echo esc_attr((string) $group['id']); ?>"
                            <?php selected($criteria->groupId, (int) $group['id']); ?>>
                            <?php echo esc_html($group['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span><?php esc_html_e('Catégorie', 'ecole2nat'); ?></span>
                <select name="category_id">
                    <option value="0"><?php esc_html_e('Toutes', 'ecole2nat'); ?></option>
                    <?php foreach ($categories as $category) : ?>
                        <option value="<?php echo esc_attr((string) $category['id']); ?>"
                            <?php selected($criteria->categoryId, (int) $category['id']); ?>>
                            <?php echo esc_html($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span><?php esc_html_e('Saison', 'ecole2nat'); ?></span>
                <select name="season_id">
                    <option value="0"><?php esc_html_e('Toutes', 'ecole2nat'); ?></option>
                    <?php foreach ($seasons as $season) : ?>
                        <option value="<?php echo esc_attr((string) $season['id']); ?>"
                            <?php selected($criteria->seasonId, (int) $season['id']); ?>>
                            <?php echo esc_html($season['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span><?php esc_html_e('Statut', 'ecole2nat'); ?></span>
                <select name="status">
                    <option value=""><?php esc_html_e('Tous', 'ecole2nat'); ?></option>
                    <option value="active" <?php selected($criteria->status, 'active'); ?>><?php esc_html_e('Actifs', 'ecole2nat'); ?></option>
                    <option value="inactive" <?php selected($criteria->status, 'inactive'); ?>><?php esc_html_e('Inactifs', 'ecole2nat'); ?></option>
                </select>
            </label>

            <label>
                <span><?php esc_html_e('Affectation', 'ecole2nat'); ?></span>
                <select name="assignment">
                    <option value=""><?php esc_html_e('Toutes', 'ecole2nat'); ?></option>
                    <option value="assigned" <?php selected($criteria->assignment, 'assigned'); ?>><?php esc_html_e('Affectés', 'ecole2nat'); ?></option>
                    <option value="unassigned" <?php selected($criteria->assignment, 'unassigned'); ?>><?php esc_html_e('Non affectés', 'ecole2nat'); ?></option>
                </select>
            </label>

            <label>
                <span><?php esc_html_e('Par page', 'ecole2nat'); ?></span>
                <select name="per_page">
                    <?php foreach ([25, 50, 100] as $perPage) : ?>
                        <option value="<?php echo esc_attr((string) $perPage); ?>" <?php selected($criteria->perPage, $perPage); ?>>
                            <?php echo esc_html((string) $perPage); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <button type="submit" class="button button-primary"><?php esc_html_e('Filtrer', 'ecole2nat'); ?></button>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=ecole2nat-swimmers')); ?>">
                <?php esc_html_e('Réinitialiser', 'ecole2nat'); ?>
            </a>
        </form>
        <?php
    }

    private function renderTable(array $swimmers, SwimmerSearchCriteria $criteria, int $total): void
    {
        $baseArguments = $this->currentFilterArguments($criteria);
        ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">
                    <?php echo esc_html(sprintf(_n('%d nageur', '%d nageurs', $total, 'ecole2nat'), $total)); ?>
                </h2>
            </div>
            <div class="inside">
                <?php if ($swimmers === []) : ?>
                    <p><?php esc_html_e('Aucun nageur ne correspond aux critères.', 'ecole2nat'); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped" data-e2n-sort="server">
                        <thead><tr>
                            <?php $this->renderSortableHeader('Nom', 'last_name', $criteria, $baseArguments); ?>
                            <?php $this->renderSortableHeader('Prénom', 'first_name', $criteria, $baseArguments); ?>
                            <?php $this->renderSortableHeader('Naissance', 'birth_date', $criteria, $baseArguments); ?>
                            <?php $this->renderSortableHeader('Groupe', 'group_name', $criteria, $baseArguments); ?>
                            <th><?php esc_html_e('Catégorie / saison', 'ecole2nat'); ?></th>
                            <?php $this->renderSortableHeader('Inscription', 'registration_date', $criteria, $baseArguments); ?>
                            <th><?php esc_html_e('Statut', 'ecole2nat'); ?></th>
                            <th><?php esc_html_e('Actions', 'ecole2nat'); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($swimmers as $swimmer) :
                            $id = (int) $swimmer['id'];
                            $editUrl = add_query_arg(['page' => 'ecole2nat-swimmers', 'action' => 'edit', 'swimmer_id' => $id], admin_url('admin.php'));
                            $toggleUrl = wp_nonce_url(add_query_arg(['page' => 'ecole2nat-swimmers', 'e2n_action' => 'toggle_swimmer', 'swimmer_id' => $id], admin_url('admin.php')), 'e2n_toggle_swimmer_' . $id);
                            $parentUrl = add_query_arg(['page' => 'ecole2nat-parent-access', 'swimmer_id' => $id], admin_url('admin.php'));
                            $evaluationUrl = !empty($swimmer['group_id'])
                                ? add_query_arg(
                                    [
                                        'page' => 'ecole2nat-evaluations',
                                        'group_id' => (int) $swimmer['group_id'],
                                        'swimmer_id' => $id,
                                    ],
                                    admin_url('admin.php')
                                )
                                : '';
                            $previewUrl = $this->parentAccessService->previewUrl($id);
                            $deleteUrl = DeletionController::url('swimmer', $id, add_query_arg($baseArguments, admin_url('admin.php')));
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html(strtoupper((string) $swimmer['last_name'])); ?></strong></td>
                                <td><?php echo esc_html($swimmer['first_name']); ?></td>
                                <td><?php echo !empty($swimmer['birth_date']) ? esc_html(wp_date('d/m/Y', strtotime($swimmer['birth_date']))) : '—'; ?></td>
                                <td><?php echo esc_html($swimmer['group_name'] ?: __('Non affecté', 'ecole2nat')); ?></td>
                                <td><?php echo esc_html(trim(($swimmer['category_name'] ?? '') . ' · ' . ($swimmer['season_name'] ?? ''), ' ·')); ?></td>
                                <td><?php echo !empty($swimmer['registration_date']) ? esc_html(wp_date('d/m/Y', strtotime($swimmer['registration_date']))) : '—'; ?></td>
                                <td><?php echo wp_kses_post(Badge::status((int) $swimmer['is_active'] === 1)); ?></td>
                                <td class="e2n-table-actions">
                                    <a href="<?php echo esc_url($editUrl); ?>"><?php esc_html_e('Modifier', 'ecole2nat'); ?></a> |
                                    <?php if ($evaluationUrl !== '') : ?>
                                        <a href="<?php echo esc_url($evaluationUrl); ?>"><?php esc_html_e('Évaluer', 'ecole2nat'); ?></a> |
                                    <?php endif; ?>
                                    <?php if ($previewUrl !== '') : ?>
                                        <a href="<?php echo esc_url($previewUrl); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Voir le parcours', 'ecole2nat'); ?></a> |
                                    <?php endif; ?>
                                    <a href="<?php echo esc_url($parentUrl); ?>"><?php esc_html_e('Accès parents', 'ecole2nat'); ?></a> |
                                    <a href="<?php echo esc_url($toggleUrl); ?>"><?php echo (int) $swimmer['is_active'] === 1 ? esc_html__('Désactiver', 'ecole2nat') : esc_html__('Activer', 'ecole2nat'); ?></a> |
                                    <a class="e2n-delete-link" href="<?php echo esc_url($deleteUrl); ?>"
                                        onclick="return confirm('<?php echo esc_js(__('Supprimer définitivement ce nageur et ses évaluations ?', 'ecole2nat')); ?>');">
                                        <?php esc_html_e('Supprimer', 'ecole2nat'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php $this->renderPagination($criteria, $total, $baseArguments); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function renderSortableHeader(string $label, string $field, SwimmerSearchCriteria $criteria, array $arguments): void
    {
        $nextOrder = $criteria->orderBy === $field && $criteria->order === 'asc' ? 'desc' : 'asc';
        $url = add_query_arg(array_merge($arguments, ['orderby' => $field, 'order' => $nextOrder, 'paged' => 1]), admin_url('admin.php'));
        echo '<th><a href="' . esc_url($url) . '">' . esc_html__($label, 'ecole2nat');
        if ($criteria->orderBy === $field) {
            echo $criteria->order === 'asc' ? ' ↑' : ' ↓';
        }
        echo '</a></th>';
    }

    private function renderPagination(SwimmerSearchCriteria $criteria, int $total, array $arguments): void
    {
        $pages = max(1, (int) ceil($total / $criteria->perPage));
        if ($pages <= 1) {
            return;
        }
        echo '<div class="e2n-pagination"><span>'
            . esc_html(sprintf(__('Page %1$d sur %2$d', 'ecole2nat'), $criteria->page, $pages))
            . '</span><div class="e2n-pagination__links">';
        for ($page = 1; $page <= $pages; $page++) {
            $url = add_query_arg(array_merge($arguments, ['paged' => $page]), admin_url('admin.php'));
            $class = $page === $criteria->page ? 'button button-primary' : 'button';
            echo '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '">' . esc_html((string) $page) . '</a>';
        }
        echo '</div></div>';
    }

    private function getSearchCriteria(): SwimmerSearchCriteria
    {
        $criteria = new SwimmerSearchCriteria();
        $criteria->search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $criteria->groupId = isset($_GET['group_id']) ? absint($_GET['group_id']) : 0;
        $criteria->categoryId = isset($_GET['category_id']) ? absint($_GET['category_id']) : 0;
        $criteria->seasonId = isset($_GET['season_id']) ? absint($_GET['season_id']) : 0;
        $criteria->status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
        $criteria->assignment = isset($_GET['assignment']) ? sanitize_key(wp_unslash($_GET['assignment'])) : '';
        $criteria->orderBy = isset($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : 'last_name';
        $criteria->order = isset($_GET['order']) && strtolower((string) $_GET['order']) === 'desc' ? 'desc' : 'asc';
        $criteria->page = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
        $perPage = isset($_GET['per_page']) ? absint($_GET['per_page']) : 25;
        $criteria->perPage = in_array($perPage, [25, 50, 100], true) ? $perPage : 25;
        return $criteria;
    }

    private function currentFilterArguments(SwimmerSearchCriteria $criteria): array
    {
        return [
            'page' => 'ecole2nat-swimmers', 's' => $criteria->search,
            'group_id' => $criteria->groupId, 'category_id' => $criteria->categoryId,
            'season_id' => $criteria->seasonId, 'status' => $criteria->status,
            'assignment' => $criteria->assignment, 'orderby' => $criteria->orderBy,
            'order' => $criteria->order, 'per_page' => $criteria->perPage,
        ];
    }

    private function getFormData(): array
    {
        return [
            'group_id' => !empty($_POST['group_id'])
                ? (int) $_POST['group_id']
                : null,

            'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),

            'birth_date' => !empty($_POST['birth_date'])
                ? sanitize_text_field($_POST['birth_date'])
                : null,

            'gender' => sanitize_text_field($_POST['gender'] ?? ''),

            'responsible_name' => sanitize_text_field($_POST['responsible_name'] ?? ''),
            'responsible_email' => sanitize_email($_POST['responsible_email'] ?? ''),
            'responsible_phone' => sanitize_text_field($_POST['responsible_phone'] ?? ''),

            'licence_number' => sanitize_text_field($_POST['licence_number'] ?? ''),

            'registration_date' => !empty($_POST['registration_date'])
                ? sanitize_text_field($_POST['registration_date'])
                : current_time('Y-m-d'),

            'medical_note' => sanitize_textarea_field($_POST['medical_note'] ?? ''),

            'image_rights' => isset($_POST['image_rights']) && $_POST['image_rights'] !== ''
                ? ((int) $_POST['image_rights'] === 1 ? 1 : 0)
                : null,
        ];
    }

    private function fieldValue(string $field, string $default = ''): string
    {
        if ($this->editingSwimmer === null) {
            return $default;
        }

        return isset($this->editingSwimmer[$field])
            ? (string) $this->editingSwimmer[$field]
            : $default;
    }
}