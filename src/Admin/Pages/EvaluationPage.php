<?php

namespace Ecole2Nat\Admin\Pages;

use Ecole2Nat\Evaluation\EvaluationService;

if (!defined('ABSPATH')) {
    exit;
}

class EvaluationPage
{
    private EvaluationService $evaluationService;

    public function __construct()
    {
        $this->evaluationService = new EvaluationService();
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

        $groups = $this->evaluationService->groups();
        $groupId = isset($_GET['group_id'])
            ? absint($_GET['group_id'])
            : $this->defaultGroupId($groups);
        $swimmerId = isset($_GET['swimmer_id'])
            ? absint($_GET['swimmer_id'])
            : 0;

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Évaluations', 'ecole2nat'); ?></h1>

            <?php $this->renderNotice(); ?>

            <?php if ($groups === []) : ?>
                <div class="notice notice-warning inline">
                    <p>
                        <?php esc_html_e(
                            'Vous devez créer au moins un groupe actif avant de pouvoir évaluer les nageurs.',
                            'ecole2nat'
                        ); ?>
                    </p>
                </div>
            </div>
                <?php
                return;
            endif;

            $this->renderGroupSelector($groups, $groupId);

            if ($groupId <= 0) {
                echo '</div>';

                return;
            }

            if ($swimmerId > 0) {
                $evaluation = $this->evaluationService->swimmerEvaluation(
                    $groupId,
                    $swimmerId
                );

                if ($evaluation === null) {
                    $this->renderInvalidContext();
                } else {
                    $this->renderSwimmerEditor($evaluation);
                }
            } else {
                $context = $this->evaluationService->groupContext($groupId);

                if ($context === null) {
                    $this->renderInvalidContext();
                } else {
                    $this->renderGroupOverview($context);
                }
            }
            ?>
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

        if ($action !== 'save_swimmer_levels') {
            return;
        }

        $groupId = isset($_POST['group_id'])
            ? absint($_POST['group_id'])
            : 0;
        $swimmerId = isset($_POST['swimmer_id'])
            ? absint($_POST['swimmer_id'])
            : 0;

        check_admin_referer(
            'e2n_save_swimmer_levels_' . $swimmerId
        );

        $statuses = isset($_POST['statuses'])
            && is_array($_POST['statuses'])
                ? wp_unslash($_POST['statuses'])
                : [];
        $notes = isset($_POST['notes'])
            && is_array($_POST['notes'])
                ? wp_unslash($_POST['notes'])
                : [];

        $result = $this->evaluationService->save(
            $groupId,
            $swimmerId,
            $statuses,
            $notes,
            get_current_user_id()
        );

        $this->redirect(
            $groupId,
            $swimmerId,
            $result['message']
        );
    }

    private function renderGroupSelector(
        array $groups,
        int $groupId
    ): void {
        ?>
        <form method="get" style="margin: 20px 0;">
            <input
                type="hidden"
                name="page"
                value="ecole2nat-evaluations"
            >

            <label for="e2n-evaluation-group">
                <strong><?php esc_html_e('Groupe :', 'ecole2nat'); ?></strong>
            </label>

            <select
                id="e2n-evaluation-group"
                name="group_id"
                onchange="this.form.submit()"
            >
                <?php foreach ($groups as $group) : ?>
                    <option
                        value="<?php echo esc_attr((string) $group['id']); ?>"
                        <?php selected($groupId, (int) $group['id']); ?>
                    >
                        <?php
                        echo esc_html(
                            sprintf(
                                '%s — %s (%s)',
                                $group['name'],
                                $group['category_name'],
                                $group['season_name']
                            )
                        );
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php
    }

    private function renderGroupOverview(array $context): void
    {
        $group = $context['group'];
        $skills = $context['skills'];
        $swimmers = $context['swimmers'];
        $skillCount = count($skills);

        ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">
                    <?php echo esc_html($group['name']); ?>
                </h2>
            </div>

            <div class="inside">
                <p>
                    <strong><?php esc_html_e('Catégorie :', 'ecole2nat'); ?></strong>
                    <?php echo esc_html($group['category_name']); ?>
                    &nbsp;—&nbsp;
                    <strong><?php esc_html_e('Référentiel :', 'ecole2nat'); ?></strong>
                    <?php
                    echo esc_html(
                        sprintf(
                            _n(
                                '%d compétence',
                                '%d compétences',
                                $skillCount,
                                'ecole2nat'
                            ),
                            $skillCount
                        )
                    );
                    ?>
                </p>

                <?php if ($skillCount === 0) : ?>
                    <div class="notice notice-warning inline">
                        <p>
                            <?php esc_html_e(
                                'Aucune compétence active n’est définie dans le référentiel de cette catégorie.',
                                'ecole2nat'
                            ); ?>
                        </p>
                    </div>
                <?php elseif ($swimmers === []) : ?>
                    <p>
                        <?php esc_html_e(
                            'Aucun nageur actif n’est affecté à ce groupe.',
                            'ecole2nat'
                        ); ?>
                    </p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Nageur', 'ecole2nat'); ?></th>
                                <th><?php esc_html_e('Non observé', 'ecole2nat'); ?></th>
                                <th><?php esc_html_e('En cours', 'ecole2nat'); ?></th>
                                <th><?php esc_html_e('Acquis', 'ecole2nat'); ?></th>
                                <th><?php esc_html_e('Dernière mise à jour', 'ecole2nat'); ?></th>
                                <th><?php esc_html_e('Action', 'ecole2nat'); ?></th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($swimmers as $swimmer) : ?>
                                <?php
                                $editUrl = add_query_arg(
                                    [
                                        'page' => 'ecole2nat-evaluations',
                                        'group_id' => (int) $group['id'],
                                        'swimmer_id' => (int) $swimmer['id'],
                                    ],
                                    admin_url('admin.php')
                                );
                                ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <?php
                                            echo esc_html(
                                                trim(
                                                    strtoupper($swimmer['last_name'])
                                                    . ' '
                                                    . $swimmer['first_name']
                                                )
                                            );
                                            ?>
                                        </strong>
                                    </td>
                                    <td><?php echo esc_html((string) $swimmer['not_observed_count']); ?></td>
                                    <td><?php echo esc_html((string) $swimmer['in_progress_count']); ?></td>
                                    <td><?php echo esc_html((string) $swimmer['acquired_count']); ?></td>
                                    <td>
                                        <?php
                                        echo !empty($swimmer['last_evaluated_at'])
                                            ? esc_html(
                                                wp_date(
                                                    'd/m/Y H:i',
                                                    strtotime($swimmer['last_evaluated_at'])
                                                )
                                            )
                                            : '—';
                                        ?>
                                    </td>
                                    <td>
                                        <a
                                            href="<?php echo esc_url($editUrl); ?>"
                                            class="button button-small"
                                        >
                                            <?php esc_html_e('Évaluer', 'ecole2nat'); ?>
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

    private function renderSwimmerEditor(array $evaluation): void
    {
        $group = $evaluation['group'];
        $swimmer = $evaluation['swimmer'];
        $skills = $evaluation['skills'];
        $statuses = $this->evaluationService->statuses();
        $groupedSkills = $this->groupSkillsByDomain($skills);
        $backUrl = add_query_arg(
            [
                'page' => 'ecole2nat-evaluations',
                'group_id' => (int) $group['id'],
            ],
            admin_url('admin.php')
        );

        ?>
        <p>
            <a href="<?php echo esc_url($backUrl); ?>" class="button">
                <?php esc_html_e('Retour au groupe', 'ecole2nat'); ?>
            </a>
        </p>

        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">
                    <?php
                    echo esc_html(
                        trim(
                            strtoupper($swimmer['last_name'])
                            . ' '
                            . $swimmer['first_name']
                        )
                    );
                    ?>
                </h2>
            </div>

            <div class="inside">
                <p>
                    <strong><?php esc_html_e('Groupe :', 'ecole2nat'); ?></strong>
                    <?php echo esc_html($group['name']); ?>
                    &nbsp;—&nbsp;
                    <strong><?php esc_html_e('Catégorie :', 'ecole2nat'); ?></strong>
                    <?php echo esc_html($group['category_name']); ?>
                </p>

                <?php if ($skills === []) : ?>
                    <div class="notice notice-warning inline">
                        <p>
                            <?php esc_html_e(
                                'Aucune compétence active n’est définie pour cette catégorie.',
                                'ecole2nat'
                            ); ?>
                        </p>
                    </div>
                <?php else : ?>
                    <form method="post">
                        <?php
                        wp_nonce_field(
                            'e2n_save_swimmer_levels_' . (int) $swimmer['id']
                        );
                        ?>

                        <input
                            type="hidden"
                            name="e2n_action"
                            value="save_swimmer_levels"
                        >
                        <input
                            type="hidden"
                            name="group_id"
                            value="<?php echo esc_attr((string) $group['id']); ?>"
                        >
                        <input
                            type="hidden"
                            name="swimmer_id"
                            value="<?php echo esc_attr((string) $swimmer['id']); ?>"
                        >

                        <?php foreach ($groupedSkills as $domainName => $domainSkills) : ?>
                            <h3><?php echo esc_html($domainName); ?></h3>

                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th style="width: 30%;">
                                            <?php esc_html_e('Compétence', 'ecole2nat'); ?>
                                        </th>
                                        <th style="width: 20%;">
                                            <?php esc_html_e('Niveau actuel', 'ecole2nat'); ?>
                                        </th>
                                        <th>
                                            <?php esc_html_e('Note', 'ecole2nat'); ?>
                                        </th>
                                        <th style="width: 16%;">
                                            <?php esc_html_e('Mise à jour', 'ecole2nat'); ?>
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($domainSkills as $skill) : ?>
                                        <?php $skillId = (int) $skill['id']; ?>
                                        <tr>
                                            <td>
                                                <strong>
                                                    <?php echo esc_html($skill['name']); ?>
                                                </strong>

                                                <?php if (!empty($skill['description'])) : ?>
                                                    <p class="description">
                                                        <?php echo esc_html($skill['description']); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <select
                                                    name="statuses[<?php echo esc_attr((string) $skillId); ?>]"
                                                >
                                                    <?php foreach ($statuses as $value => $label) : ?>
                                                        <option
                                                            value="<?php echo esc_attr($value); ?>"
                                                            <?php selected($skill['status'], $value); ?>
                                                        >
                                                            <?php echo esc_html($label); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <textarea
                                                    name="notes[<?php echo esc_attr((string) $skillId); ?>]"
                                                    rows="2"
                                                    class="large-text"
                                                ><?php echo esc_textarea($skill['notes']); ?></textarea>
                                            </td>
                                            <td>
                                                <?php
                                                if (!empty($skill['evaluated_at'])) {
                                                    echo esc_html(
                                                        wp_date(
                                                            'd/m/Y H:i',
                                                            strtotime($skill['evaluated_at'])
                                                        )
                                                    );

                                                    if (!empty($skill['evaluator_name'])) {
                                                        echo '<br><span class="description">';
                                                        echo esc_html($skill['evaluator_name']);
                                                        echo '</span>';
                                                    }
                                                } else {
                                                    echo '—';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endforeach; ?>

                        <?php
                        submit_button(
                            __('Enregistrer les niveaux', 'ecole2nat')
                        );
                        ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function renderNotice(): void
    {
        $notice = isset($_GET['e2n_notice'])
            ? sanitize_key(wp_unslash($_GET['e2n_notice']))
            : '';

        $messages = [
            'levels_saved' => [
                'success',
                __('Les niveaux du nageur ont bien été enregistrés.', 'ecole2nat'),
            ],
            'invalid' => [
                'error',
                __('Le groupe ou le nageur sélectionné est invalide.', 'ecole2nat'),
            ],
            'error' => [
                'error',
                __('Une erreur est survenue lors de l’enregistrement.', 'ecole2nat'),
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

    private function renderInvalidContext(): void
    {
        ?>
        <div class="notice notice-error inline">
            <p>
                <?php esc_html_e(
                    'Le groupe ou le nageur demandé est introuvable.',
                    'ecole2nat'
                ); ?>
            </p>
        </div>
        <?php
    }

    private function groupSkillsByDomain(array $skills): array
    {
        $grouped = [];

        foreach ($skills as $skill) {
            $domainName = (string) $skill['domain_name'];
            $grouped[$domainName][] = $skill;
        }

        return $grouped;
    }

    private function defaultGroupId(array $groups): int
    {
        return $groups !== []
            ? (int) $groups[0]['id']
            : 0;
    }

    private function redirect(
        int $groupId,
        int $swimmerId,
        string $notice
    ): void {
        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => 'ecole2nat-evaluations',
                    'group_id' => $groupId,
                    'swimmer_id' => $swimmerId,
                    'e2n_notice' => $notice,
                ],
                admin_url('admin.php')
            )
        );

        exit;
    }
}
