<?php

namespace Ecole2Nat\Admin\Pages;

use Ecole2Nat\Swimmer\SwimmerService;
use Ecole2Nat\Group\GroupService;

if (!defined('ABSPATH')) {
    exit;
}

class SwimmerPage
{
    private SwimmerService $swimmerService;
    private GroupService $groupService;
    private ?array $editingSwimmer = null;

    public function __construct()
    {
        $this->swimmerService = new SwimmerService();
        $this->groupService = new GroupService();
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
        $swimmers = $this->swimmerService->all();

        ?>
        <div class="wrap">
            <h1>
                <?php echo esc_html__('Nageurs', 'ecole2nat'); ?>
            </h1>

            <?php $this->renderNotice(); ?>

            <?php $this->renderCreateForm(); ?>

            <?php $this->renderTable($swimmers); ?>
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

    private function renderTable(array $swimmers): void
    {
        ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">
                    <?php
                    echo esc_html__(
                        'Nageurs enregistrés',
                        'ecole2nat'
                    );
                    ?>
                </h2>
            </div>

            <div class="inside">
                <?php if ($swimmers === []) : ?>
                    <p>
                        <?php
                        echo esc_html__(
                            'Aucun nageur n’a encore été enregistré.',
                            'ecole2nat'
                        );
                        ?>
                    </p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Nom', 'ecole2nat'); ?></th>
                                <th><?php esc_html_e('Naissance', 'ecole2nat'); ?></th>
                                <th><?php esc_html_e('Groupe', 'ecole2nat'); ?></th>
                                <th><?php esc_html_e('Responsable', 'ecole2nat'); ?></th>
                                <th><?php esc_html_e('Licence', 'ecole2nat'); ?></th>
                                <th><?php esc_html_e('Statut', 'ecole2nat'); ?></th>
                                <th><?php esc_html_e('Actions', 'ecole2nat'); ?></th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($swimmers as $swimmer) : ?>
                                <tr>

                                    <td>
                                        <?php
                                        echo esc_html(
                                            trim(
                                                strtoupper($swimmer['last_name'])
                                                . ' '
                                                . $swimmer['first_name']
                                            )
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo !empty($swimmer['birth_date'])
                                            ? esc_html(wp_date('d/m/Y', strtotime($swimmer['birth_date'])))
                                            : '—';
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo esc_html(
                                            $swimmer['group_name']
                                                ?: __('Non affecté', 'ecole2nat')
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo esc_html(
                                            $swimmer['responsible_name'] ?: '—'
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo esc_html(
                                            $swimmer['licence_number'] ?: '—'
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo (int) $swimmer['is_active'] === 1
                                            ? esc_html__('Actif', 'ecole2nat')
                                            : esc_html__('Inactif', 'ecole2nat');
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        $editUrl = add_query_arg(
                                            [
                                                'page'       => 'ecole2nat-swimmers',
                                                'action'     => 'edit',
                                                'swimmer_id' => (int) $swimmer['id'],
                                            ],
                                            admin_url('admin.php')
                                        );

                                        $toggleUrl = wp_nonce_url(
                                            add_query_arg(
                                                [
                                                    'page'       => 'ecole2nat-swimmers',
                                                    'e2n_action' => 'toggle_swimmer',
                                                    'swimmer_id' => (int) $swimmer['id'],
                                                ],
                                                admin_url('admin.php')
                                            ),
                                            'e2n_toggle_swimmer_' . (int) $swimmer['id']
                                        );
                                        ?>

                                        <a href="<?php echo esc_url($editUrl); ?>">
                                            <?php esc_html_e('Modifier', 'ecole2nat'); ?>
                                        </a>

                                        <span aria-hidden="true"> | </span>

                                        <a href="<?php echo esc_url(
                                            add_query_arg(
                                                [
                                                    'page' => 'ecole2nat-parent-access',
                                                    'swimmer_id' => (int) $swimmer['id'],
                                                ],
                                                admin_url('admin.php')
                                            )
                                        ); ?>">
                                            <?php esc_html_e('Accès parents', 'ecole2nat'); ?>
                                        </a>

                                        <span aria-hidden="true"> | </span>

                                        <a href="<?php echo esc_url($toggleUrl); ?>">
                                            <?php
                                            echo (int) $swimmer['is_active'] === 1
                                                ? esc_html__('Désactiver', 'ecole2nat')
                                                : esc_html__('Activer', 'ecole2nat');
                                            ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
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