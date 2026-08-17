<?php

namespace Ecole2Nat\Admin\Pages;

use Ecole2Nat\Category\CategoryRepository;
use Ecole2Nat\Group\GroupService;
use Ecole2Nat\Admin\UI\Badge;
use Ecole2Nat\Admin\Deletion\DeletionController;
use Ecole2Nat\Season\SeasonRepository;

if (!defined('ABSPATH')) {
    exit;
}

class GroupPage
{
    private GroupService $groupService;
    private SeasonRepository $seasonRepository;
    private CategoryRepository $categoryRepository;

    public function __construct()
    {
        $this->groupService = new GroupService();
        $this->seasonRepository = new SeasonRepository();
        $this->categoryRepository = new CategoryRepository();
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('Vous n’avez pas les droits nécessaires.', 'ecole2nat')
            );
        }

        $this->handleActions();

        $groups = $this->groupService->all();
        $editingId = isset($_GET['group_id']) ? absint($_GET['group_id']) : 0;
        $editingGroup = $editingId > 0 ? $this->groupService->find($editingId) : null;
        $seasons = array_values(
            array_filter(
                $this->seasonRepository->all(),
                static fn(array $season): bool =>
                    (int) ($season['is_active'] ?? 1) === 1
                    || (int) $season['id'] === (int) ($editingGroup['season_id'] ?? 0)
            )
        );

        $categories = array_values(
            array_filter(
                $this->categoryRepository->all(),
                static fn(array $category): bool =>
                    (int) ($category['is_active'] ?? 1) === 1
                    || (int) $category['id'] === (int) ($editingGroup['category_id'] ?? 0)
            )
        );

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Groupes', 'ecole2nat'); ?></h1>

            <?php $this->renderNotice(); ?>

            <div class="e2n-group-layout">
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">
                            <?php echo esc_html($editingGroup ? __('Modifier le groupe', 'ecole2nat') : __('Créer un groupe', 'ecole2nat')); ?>
                        </h2>
                    </div>

                    <div class="inside">
                        <?php
                        $this->renderForm(
                            $seasons,
                            $categories,
                            $editingGroup
                        );
                        ?>
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">
                            <?php echo esc_html__('Groupes existants', 'ecole2nat'); ?>
                        </h2>
                    </div>

                    <div class="inside">
                        <?php $this->renderGroupsTable($groups); ?>
                    </div>
                </div>
            </div>
        </div>

        <?php $this->renderScripts(); ?>
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

        if ($action === 'create_group') {
            $this->handleCreate();
            return;
        }

        if ($action === 'update_group') {
            $this->handleUpdate();
            return;
        }

        if ($action === 'toggle_group') {
            $this->handleToggle();
        }
    }

    private function handleCreate(): void
    {
        check_admin_referer('e2n_create_group');

        $seasonId = isset($_POST['season_id'])
            ? absint($_POST['season_id'])
            : 0;

        $categoryId = isset($_POST['category_id'])
            ? absint($_POST['category_id'])
            : 0;

        $name = isset($_POST['name'])
            ? sanitize_text_field(wp_unslash($_POST['name']))
            : '';

        $color = isset($_POST['color'])
            ? sanitize_hex_color(wp_unslash($_POST['color']))
            : '';

        $weekday = isset($_POST['weekday'])
            ? absint($_POST['weekday'])
            : 0;

        $startRaw = wp_unslash((string) ($_POST['start_time'] ?? ''));
        $endRaw = wp_unslash((string) ($_POST['end_time'] ?? ''));
        $startTime = $this->sanitizeTime($startRaw);
        $endTime = $this->sanitizeTime($endRaw);

        if (
            $seasonId <= 0
            || $categoryId <= 0
            || $name === ''
            || $weekday < 1
            || $weekday > 7
            || (trim($startRaw) !== '' && $startTime === null)
            || (trim($endRaw) !== '' && $endTime === null)
        ) {
            $this->redirectWithNotice('invalid');
        }

        if (
            $startTime !== null
            && $endTime !== null
            && $endTime <= $startTime
        ) {
            $this->redirectWithNotice('invalid_time');
        }

        $result = $this->groupService->create(
            $seasonId,
            $categoryId,
            $name,
            $color ?: '',
            $weekday,
            $startTime,
            $endTime
        );

        $this->redirectWithNotice($result['message']);
    }

    private function handleToggle(): void
    {
        check_admin_referer('e2n_toggle_group');

        $groupId = isset($_POST['group_id'])
            ? absint($_POST['group_id'])
            : 0;

        if ($groupId <= 0) {
            $this->redirectWithNotice('invalid');
        }

        $updated = $this->groupService->toggleActive($groupId);

        $this->redirectWithNotice(
            $updated ? 'updated' : 'error'
        );
    }

    private function handleUpdate(): void
    {
        $groupId = absint($_POST['group_id'] ?? 0);
        check_admin_referer('e2n_update_group_' . $groupId);
        $seasonId = absint($_POST['season_id'] ?? 0);
        $categoryId = absint($_POST['category_id'] ?? 0);
        $name = sanitize_text_field(wp_unslash((string) ($_POST['name'] ?? '')));
        $color = sanitize_hex_color(wp_unslash((string) ($_POST['color'] ?? ''))) ?: '';
        $weekday = absint($_POST['weekday'] ?? 0);
        $startRaw = wp_unslash((string) ($_POST['start_time'] ?? ''));
        $endRaw = wp_unslash((string) ($_POST['end_time'] ?? ''));
        $startTime = $this->sanitizeTime($startRaw);
        $endTime = $this->sanitizeTime($endRaw);
        if ($groupId <= 0 || $seasonId <= 0 || $categoryId <= 0 || $name === '' || $weekday < 1 || $weekday > 7 || (trim($startRaw) !== '' && $startTime === null) || (trim($endRaw) !== '' && $endTime === null)) {
            $this->redirectWithNotice('invalid', $groupId);
        }
        if ($startTime !== null && $endTime !== null && $endTime <= $startTime) {
            $this->redirectWithNotice('invalid_time', $groupId);
        }
        $result = $this->groupService->update($groupId, $seasonId, $categoryId, $name, $color, $weekday, $startTime, $endTime);
        $this->redirectWithNotice($result['message']);
    }

    private function renderForm(
        array $seasons,
        array $categories,
        ?array $group = null
    ): void {
        if ($seasons === [] || $categories === []) {
            ?>
            <div class="notice notice-warning inline">
                <p>
                    <?php
                    echo esc_html__(
                        'Vous devez créer au moins une saison et une catégorie avant de créer un groupe.',
                        'ecole2nat'
                    );
                    ?>
                </p>
            </div>
            <?php

            return;
        }

        $colors = [
            '#2271b1' => 'Bleu',
            '#00a32a' => 'Vert',
            '#dba617' => 'Orange',
            '#8c5ac4' => 'Violet',
            '#d63638' => 'Rouge',
            '#f0c33c' => 'Jaune',
            '#646970' => 'Gris',
            '#8b5e3c' => 'Marron',
        ];
        $selectedColor = (string) ($group['color'] ?? '');
        if ($selectedColor === '') $selectedColor = (string) array_key_first($colors);

        $weekdays = $this->getWeekdays();
        ?>
        <form method="post">
            <?php $groupId = (int) ($group['id'] ?? 0); wp_nonce_field($group ? 'e2n_update_group_' . $groupId : 'e2n_create_group'); ?>

            <input
                type="hidden"
                name="e2n_action"
                value="<?php echo esc_attr($group ? 'update_group' : 'create_group'); ?>"
            >
            <?php if ($group) : ?><input type="hidden" name="group_id" value="<?php echo $groupId; ?>"><?php endif; ?>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="e2n-season-id">
                                <?php echo esc_html__('Saison', 'ecole2nat'); ?>
                            </label>
                        </th>

                        <td>
                            <select
                                id="e2n-season-id"
                                name="season_id"
                                class="regular-text"
                                required
                            >
                                <option value="">
                                    <?php echo esc_html__('Sélectionner', 'ecole2nat'); ?>
                                </option>

                                <?php foreach ($seasons as $season) : ?>
                                    <option
                                        value="<?php echo esc_attr((string) $season['id']); ?>"
                                        <?php selected($group ? (int) $group['season_id'] : ((int) ($season['is_current'] ?? 0) === 1 ? (int) $season['id'] : 0), (int) $season['id']); ?>
                                    >
                                        <?php echo esc_html($season['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="e2n-category-id">
                                <?php echo esc_html__('Catégorie', 'ecole2nat'); ?>
                            </label>
                        </th>

                        <td>
                            <select
                                id="e2n-category-id"
                                name="category_id"
                                class="regular-text"
                                required
                            >
                                <option value="">
                                    <?php echo esc_html__('Sélectionner', 'ecole2nat'); ?>
                                </option>

                                <?php foreach ($categories as $category) : ?>
                                    <option
                                        value="<?php echo esc_attr((string) $category['id']); ?>"
                                        data-category-name="<?php echo esc_attr($category['name']); ?>"
                                        <?php selected((int) ($group['category_id'] ?? 0), (int) $category['id']); ?>
                                    >
                                        <?php echo esc_html($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="e2n-weekday">
                                <?php echo esc_html__('Jour', 'ecole2nat'); ?>
                            </label>
                        </th>

                        <td>
                            <select
                                id="e2n-weekday"
                                name="weekday"
                                class="regular-text"
                                required
                            >
                                <option value="">
                                    <?php echo esc_html__('Sélectionner', 'ecole2nat'); ?>
                                </option>

                                <?php foreach ($weekdays as $value => $label) : ?>
                                    <option
                                        value="<?php echo esc_attr((string) $value); ?>"
                                        data-weekday-name="<?php echo esc_attr($label); ?>"
                                        <?php selected((int) ($group['weekday'] ?? 0), (int) $value); ?>
                                    >
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="e2n-start-time">
                                <?php echo esc_html__('Horaire', 'ecole2nat'); ?>
                            </label>
                        </th>

                        <td>
                            <input
                                id="e2n-start-time"
                                type="time"
                                name="start_time"
                                value="<?php echo esc_attr(isset($group['start_time']) ? substr((string) $group['start_time'], 0, 5) : ''); ?>"
                            >

                            <span style="margin: 0 8px;">
                                <?php echo esc_html__('à', 'ecole2nat'); ?>
                            </span>

                            <input
                                id="e2n-end-time"
                                type="time"
                                name="end_time"
                                value="<?php echo esc_attr(isset($group['end_time']) ? substr((string) $group['end_time'], 0, 5) : ''); ?>"
                            >
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="e2n-group-name">
                                <?php echo esc_html__('Nom', 'ecole2nat'); ?>
                            </label>
                        </th>

                        <td>
                            <input
                                id="e2n-group-name"
                                type="text"
                                name="name"
                                class="regular-text"
                                maxlength="150"
                                required
                                value="<?php echo esc_attr((string) ($group['name'] ?? '')); ?>"
                            >

                            <p class="description">
                                <?php
                                echo esc_html__(
                                    'Le nom est généré automatiquement, mais reste modifiable.',
                                    'ecole2nat'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php echo esc_html__('Couleur', 'ecole2nat'); ?>
                        </th>

                        <td>
                            <div
                                style="
                                    display: flex;
                                    flex-wrap: wrap;
                                    gap: 10px;
                                "
                            >
                                <?php
                                $first = true;

                                foreach ($colors as $value => $label) :
                                    ?>
                                    <label
                                        title="<?php echo esc_attr($label); ?>"
                                        style="
                                            display: inline-flex;
                                            align-items: center;
                                            gap: 6px;
                                            cursor: pointer;
                                        "
                                    >
                                        <input
                                            type="radio"
                                            name="color"
                                            value="<?php echo esc_attr($value); ?>"
                                            <?php checked($selectedColor, $value); ?>
                                        >

                                        <span
                                            style="
                                                display: inline-block;
                                                width: 24px;
                                                height: 24px;
                                                border-radius: 50%;
                                                background: <?php echo esc_attr($value); ?>;
                                                border: 2px solid #ffffff;
                                                box-shadow: 0 0 0 1px #8c8f94;
                                            "
                                        ></span>

                                        <span class="screen-reader-text">
                                            <?php echo esc_html($label); ?>
                                        </span>
                                    </label>
                                    <?php
                                    $first = false;
                                endforeach;
                                ?>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php
            submit_button(
                $group ? __('Enregistrer le groupe', 'ecole2nat') : __('Créer le groupe', 'ecole2nat')
            );
            if ($group) echo ' <a class="button" href="' . esc_url(admin_url('admin.php?page=ecole2nat-groups')) . '">' . esc_html__('Annuler', 'ecole2nat') . '</a>';
            ?>
        </form>
        <?php
    }

    private function renderGroupsTable(array $groups): void
    {
        if ($groups === []) {
            ?>
            <p>
                <?php
                echo esc_html__(
                    'Aucun groupe n’a encore été créé.',
                    'ecole2nat'
                );
                ?>
            </p>
            <?php

            return;
        }

        $weekdays = $this->getWeekdays();
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Groupe', 'ecole2nat'); ?></th>
                    <th><?php echo esc_html__('Saison', 'ecole2nat'); ?></th>
                    <th><?php echo esc_html__('Catégorie', 'ecole2nat'); ?></th>
                    <th><?php echo esc_html__('Créneau', 'ecole2nat'); ?></th>
                    <th><?php echo esc_html__('Statut', 'ecole2nat'); ?></th>
                    <th><?php echo esc_html__('Action', 'ecole2nat'); ?></th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($groups as $group) : ?>
                    <?php
                    $weekday = isset($group['weekday'])
                        ? (int) $group['weekday']
                        : 0;

                    $isActive = (int) $group['is_active'] === 1;
                    $seasonIsActive = (int) ($group['season_is_active'] ?? 1) === 1;
                    $isEffectivelyActive = $isActive && $seasonIsActive;
                    ?>
                    <tr>
                        <td>
                            <strong
                                style="
                                    display: inline-flex;
                                    align-items: center;
                                    gap: 8px;
                                "
                            >
                                <span
                                    aria-hidden="true"
                                    style="
                                        display: inline-block;
                                        width: 12px;
                                        height: 12px;
                                        border-radius: 50%;
                                        background: <?php echo esc_attr(
                                            $group['color'] ?: '#646970'
                                        ); ?>;
                                    "
                                ></span>

                                <?php echo esc_html($group['name']); ?>
                            </strong>
                        </td>

                        <td>
                            <?php echo esc_html($group['season_name']); ?>
                        </td>

                        <td>
                            <?php echo esc_html($group['category_name']); ?>
                        </td>

                        <td>
                            <?php
                            echo esc_html(
                                $this->formatSchedule(
                                    $weekday,
                                    $group['start_time'] ?? null,
                                    $group['end_time'] ?? null,
                                    $weekdays
                                )
                            );
                            ?>
                        </td>

                        <td>
                            <?php echo wp_kses_post(Badge::status($isEffectivelyActive)); ?>
                            <?php if (!$seasonIsActive) : ?>
                                <span class="description e2n-status-note"><?php esc_html_e('Saison inactive', 'ecole2nat'); ?></span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <a class="button button-small" href="<?php echo esc_url(add_query_arg(['page' => 'ecole2nat-groups', 'group_id' => (int) $group['id']], admin_url('admin.php'))); ?>"><?php esc_html_e('Modifier', 'ecole2nat'); ?></a>
                            <?php if ($seasonIsActive) : ?>
                                <form method="post">
                                    <?php wp_nonce_field('e2n_toggle_group'); ?>

                                    <input type="hidden" name="e2n_action" value="toggle_group">
                                    <input type="hidden" name="group_id" value="<?php echo esc_attr((string) $group['id']); ?>">

                                    <button type="submit" class="button button-small">
                                        <?php echo esc_html($isActive ? __('Désactiver', 'ecole2nat') : __('Activer', 'ecole2nat')); ?>
                                    </button>
                                </form>
                            <?php else : ?>
                                <span class="description"><?php esc_html_e('Géré par la saison', 'ecole2nat'); ?></span>
                            <?php endif; ?>
                            <?php
                            $deleteUrl = DeletionController::url(
                                'group',
                                (int) $group['id'],
                                admin_url('admin.php?page=ecole2nat-groups')
                            );
                            ?>
                            <a class="e2n-delete-link" href="<?php echo esc_url($deleteUrl); ?>"
                               onclick="return confirm('<?php echo esc_js(__('Supprimer ce groupe ?', 'ecole2nat')); ?>');">
                                <?php esc_html_e('Supprimer', 'ecole2nat'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function renderNotice(): void
    {
        $notice = isset($_GET['e2n_notice'])
            ? sanitize_key(wp_unslash($_GET['e2n_notice']))
            : '';

        $messages = [
            'created' => [
                'success',
                __('Le groupe a bien été créé.', 'ecole2nat'),
            ],
            'updated' => [
                'success',
                __('Le statut du groupe a bien été modifié.', 'ecole2nat'),
            ],
            'group_saved' => [
                'success',
                __('Le groupe a bien été modifié.', 'ecole2nat'),
            ],
            'invalid' => [
                'error',
                __('Veuillez remplir correctement les champs obligatoires.', 'ecole2nat'),
            ],
            'invalid_time' => [
                'error',
                __('L’heure de fin doit être postérieure à l’heure de début.', 'ecole2nat'),
            ],
            'duplicate' => [
                'error',
                __('Un groupe portant ce nom existe déjà pour cette saison.', 'ecole2nat'),
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

    private function redirectWithNotice(string $notice, int $groupId = 0): void
    {
        $args = ['page' => 'ecole2nat-groups', 'e2n_notice' => $notice];
        if ($groupId > 0) $args['group_id'] = $groupId;
        $url = add_query_arg(
            $args,
            admin_url('admin.php')
        );

        wp_safe_redirect($url);
        exit;
    }

    private function sanitizeTime(string $time): ?string
    {
        $time = trim($time);

        if ($time === '') {
            return null;
        }

        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
            return null;
        }

        return $time;
    }

    private function formatSchedule(
        int $weekday,
        ?string $startTime,
        ?string $endTime,
        array $weekdays
    ): string {
        $parts = [];

        if (isset($weekdays[$weekday])) {
            $parts[] = $weekdays[$weekday];
        }

        if ($startTime) {
            $schedule = substr($startTime, 0, 5);

            if ($endTime) {
                $schedule .= ' – ' . substr($endTime, 0, 5);
            }

            $parts[] = $schedule;
        }

        return $parts !== []
            ? implode(' · ', $parts)
            : '—';
    }

    private function getWeekdays(): array
    {
        return [
            1 => __('Lundi', 'ecole2nat'),
            2 => __('Mardi', 'ecole2nat'),
            3 => __('Mercredi', 'ecole2nat'),
            4 => __('Jeudi', 'ecole2nat'),
            5 => __('Vendredi', 'ecole2nat'),
            6 => __('Samedi', 'ecole2nat'),
            7 => __('Dimanche', 'ecole2nat'),
        ];
    }

    private function renderScripts(): void
    {
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const categorySelect = document.getElementById('e2n-category-id');
                const weekdaySelect = document.getElementById('e2n-weekday');
                const startTimeInput = document.getElementById('e2n-start-time');
                const nameInput = document.getElementById('e2n-group-name');

                if (
                    !categorySelect
                    || !weekdaySelect
                    || !startTimeInput
                    || !nameInput
                ) {
                    return;
                }

                let generatedName = '';

                function buildGroupName() {
                    const categoryOption =
                        categorySelect.options[categorySelect.selectedIndex];

                    const weekdayOption =
                        weekdaySelect.options[weekdaySelect.selectedIndex];

                    const categoryName =
                        categoryOption?.dataset.categoryName || '';

                    const weekdayName =
                        weekdayOption?.dataset.weekdayName || '';

                    const startTime = startTimeInput.value || '';

                    const parts = [];

                    if (categoryName) {
                        parts.push(categoryName);
                    }

                    if (weekdayName) {
                        parts.push(weekdayName);
                    }

                    if (startTime) {
                        parts.push(startTime);
                    }

                    const newGeneratedName = parts.join(' - ');

                    if (
                        nameInput.value === ''
                        || nameInput.value === generatedName
                    ) {
                        nameInput.value = newGeneratedName;
                    }

                    generatedName = newGeneratedName;
                }

                categorySelect.addEventListener('change', buildGroupName);
                weekdaySelect.addEventListener('change', buildGroupName);
                startTimeInput.addEventListener('change', buildGroupName);
            });
        </script>
        <?php
    }
}
