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
        if (
            isset($_GET['e2n_action'], $_GET['swimmer_id']) &&
            $_GET['e2n_action'] === 'toggle_swimmer'
        ) {
            $swimmerId = (int) $_GET['swimmer_id'];

            check_admin_referer(
                'e2n_toggle_swimmer_' . $swimmerId
            );

            $this->swimmerService->toggleActive($swimmerId);

            wp_redirect(
                add_query_arg(
                    [
                        'page'    => 'ecole2nat-swimmers',
                        'message' => 'updated',
                    ],
                    admin_url('admin.php')
                )
            );

            exit;
        }

        if (
            !isset($_POST['e2n_action']) ||
            $_POST['e2n_action'] !== 'create_swimmer'
        ) {
            return;
        }

        check_admin_referer('e2n_create_swimmer');

        $result = $this->swimmerService->create([
            'group_id' => !empty($_POST['group_id'])
                ? (int) $_POST['group_id']
                : null,

            'registration_date' => !empty($_POST['registration_date'])
                ? sanitize_text_field($_POST['registration_date'])
                : current_time('Y-m-d'),
            'last_name'           => sanitize_text_field($_POST['last_name'] ?? ''),
            'first_name'          => sanitize_text_field($_POST['first_name'] ?? ''),
            'birth_date'          => !empty($_POST['birth_date']) ? $_POST['birth_date'] : null,
            'gender'              => sanitize_text_field($_POST['gender'] ?? ''),
            'responsible_name' => sanitize_text_field(
                $_POST['responsible_name'] ?? ''
            ),

            'responsible_email' => sanitize_email(
                $_POST['responsible_email'] ?? ''
            ),

            'responsible_phone' => sanitize_text_field(
                $_POST['responsible_phone'] ?? ''
            ),
            'licence_number' => sanitize_text_field(
                $_POST['licence_number'] ?? ''
            ),

            'medical_note' => sanitize_textarea_field(
                $_POST['medical_note'] ?? ''
            ),
        ]);

        wp_redirect(
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
        ?>
        <form method="post">
            <input
                type="hidden"
                name="e2n_action"
                value="create_swimmer"
            >

            <?php wp_nonce_field('e2n_create_swimmer'); ?>

            <?php 
            $groups = $this->groupService->active();
            $this->renderIdentityBox(); ?>

            <?php $this->renderAssignmentBox($groups); ?>

            <?php $this->renderResponsibleBox(); ?>

            <?php $this->renderMedicalBox(); ?>

            <?php
            submit_button(
                __('Créer le nageur', 'ecole2nat')
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
                    <?php esc_html_e('Informations du nageur', 'ecole2nat'); ?>
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

                                <option value="F">
                                    <?php esc_html_e('Féminin', 'ecole2nat'); ?>
                                </option>

                                <option value="M">
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
                                    <option value="<?php echo esc_attr($group['id']); ?>">
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
                                value="<?php echo esc_attr(current_time('Y-m-d')); ?>"
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
                            ></textarea>
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
                                        $actionUrl = wp_nonce_url(
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

                                        <a href="<?php echo esc_url($actionUrl); ?>">
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
}