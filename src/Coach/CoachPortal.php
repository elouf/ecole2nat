<?php

namespace Ecole2Nat\Coach;

use Ecole2Nat\Evaluation\EvaluationService;
use Ecole2Nat\Support\ScheduleDurationCalculator;

if (!defined('ABSPATH')) {
    exit;
}

class CoachPortal
{
    private CoachAccessService $access;
    private CoachPortalRepository $repo;
    private FieldSessionRepository $field;
    private CoachSessionEditorRepository $editor;
    private EvaluationService $eval;

    public function __construct()
    {
        $this->access = new CoachAccessService();
        $this->repo = new CoachPortalRepository();
        $this->field = new FieldSessionRepository();
        $this->editor = new CoachSessionEditorRepository();
        $this->eval = new EvaluationService();
    }

    public function register(): void
    {
        add_shortcode('e2n_coach_portal', [$this, 'shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_filter('login_redirect', [$this, 'loginRedirect'], 10, 3);
        add_action('wp_ajax_e2n_coach_save_attendance', [$this, 'ajaxSaveAttendance']);
        add_action('wp_ajax_e2n_coach_save_evaluation', [$this, 'ajaxSaveEvaluation']);
        add_action('wp_ajax_e2n_coach_save_note', [$this, 'ajaxSaveNote']);
        add_action('wp_ajax_e2n_coach_session_action', [$this, 'ajaxSessionAction']);
    }

    public function assets(): void
    {
        $pageId = (int) get_option('e2n_coach_page_id', 0);
        if ($pageId > 0 && is_page($pageId)) {
            wp_enqueue_style('e2n-coach', E2N_PLUGIN_URL . 'assets/css/coach-portal.css', [], E2N_VERSION);
            wp_enqueue_script('e2n-coach', E2N_PLUGIN_URL . 'assets/js/coach-portal.js', [], E2N_VERSION, true);
        }
    }

    public function loginRedirect(string $redirect, string $requested, $user): string
    {
        if ($user instanceof \WP_User && in_array('e2n_coach', (array) $user->roles, true)) {
            $id = (int) get_option('e2n_coach_page_id', 0);
            if ($id > 0) return get_permalink($id);
        }
        return $redirect;
    }

    public function shortcode(): string
    {
        wp_enqueue_style('e2n-coach', E2N_PLUGIN_URL . 'assets/css/coach-portal.css', [], E2N_VERSION);
        wp_enqueue_script('e2n-coach', E2N_PLUGIN_URL . 'assets/js/coach-portal.js', [], E2N_VERSION, true);
        wp_localize_script('e2n-coach', 'e2nCoachAjax', [
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('e2n_coach_ajax'),
            'saving' => __('Enregistrement…', 'ecole2nat'),
            'saved' => __('Enregistré', 'ecole2nat'),
            'error' => __('Non enregistré — réessayer', 'ecole2nat'),
            'preparedDuration' => __('Durée préparée', 'ecole2nat'),
            'slotDuration' => __('créneau', 'ecole2nat'),
            'overDuration' => __('Dépassement', 'ecole2nat'),
            'minutes' => __('min', 'ecole2nat'),
        ]);

        if (!is_user_logged_in()) {
            return '<div class="e2n-coach-login"><p>' . esc_html__('Connectez-vous pour accéder à l’espace coach.', 'ecole2nat') . '</p><a class="e2n-btn" href="' . esc_url(wp_login_url(get_permalink())) . '">' . esc_html__('Se connecter', 'ecole2nat') . '</a></div>';
        }
        if (!$this->access->canView()) {
            return '<p>' . esc_html__('Votre compte ne possède pas l’accès coach.', 'ecole2nat') . '</p>';
        }

        $this->handlePost();

        $groupId = absint($_GET['e2n_group'] ?? 0);
        $swimmerId = absint($_GET['e2n_swimmer'] ?? 0);
        $sessionId = absint($_GET['e2n_session'] ?? 0);
        $editorSessionId = absint($_GET['e2n_edit_session'] ?? 0);
        $collectiveSkillId = absint($_GET['e2n_collective_skill'] ?? 0);
        $date = $this->requestedDate();

        ob_start();
        echo '<div class="e2n-coach">';
        $this->header();

        if ($groupId && $editorSessionId) {
            $this->sessionEditor($groupId, $editorSessionId, $date);
        } elseif ($groupId && $swimmerId) {
            $this->swimmer($groupId, $swimmerId, $date);
        } elseif ($groupId && $sessionId) {
            $this->session($groupId, $sessionId, $date);
        } elseif ($groupId && $collectiveSkillId) {
            $this->collective($groupId, $collectiveSkillId, $date);
        } elseif ($groupId) {
            $this->group($groupId, $date);
        } else {
            $this->dashboard();
        }

        echo '</div>';
        return (string) ob_get_clean();
    }

    private function base(array $args = []): string
    {
        $id = (int) get_option('e2n_coach_page_id', 0);
        return add_query_arg($args, get_permalink($id));
    }

    private function header(): void
    {
        $u = wp_get_current_user();
        ?>
        <header class="e2n-coach-head">
            <div><strong>Ecole2Nat’</strong><span><?php echo esc_html($u->display_name); ?></span></div>
            <nav>
                <a href="<?php echo esc_url($this->base()); ?>"><?php esc_html_e('Planning', 'ecole2nat'); ?></a>
                <a href="<?php echo esc_url(wp_logout_url($this->base())); ?>"><?php esc_html_e('Déconnexion', 'ecole2nat'); ?></a>
            </nav>
        </header>
        <?php
    }

    private function dashboard(): void
    {
        $groups = $this->repo->groups();
        $titular = $this->access->titularGroupIds();
        $days = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];
        $weekStart = $this->requestedWeekStart();
        $previousWeek = $weekStart->modify('-7 days')->format('Y-m-d');
        $nextWeek = $weekStart->modify('+7 days')->format('Y-m-d');
        $currentWeek = $this->currentWeekStart();
        $weekEnd = $weekStart->modify('+6 days');
        ?>
        <div class="e2n-coach-main">
            <h1><?php esc_html_e('Planning hebdomadaire', 'ecole2nat'); ?></h1>
            <nav class="e2n-date-nav e2n-week-nav" aria-label="<?php esc_attr_e('Navigation entre les semaines', 'ecole2nat'); ?>">
                <a href="<?php echo esc_url($this->base(['e2n_week' => $previousWeek])); ?>">← <span><?php esc_html_e('Semaine précédente', 'ecole2nat'); ?></span></a>
                <div>
                    <strong><?php echo esc_html(sprintf(__('%1$s – %2$s', 'ecole2nat'), wp_date('j M', $weekStart->getTimestamp()), wp_date('j M Y', $weekEnd->getTimestamp()))); ?></strong>
                    <?php if ($weekStart->format('Y-m-d') !== $currentWeek->format('Y-m-d')) : ?>
                        <a class="e2n-date-current" href="<?php echo esc_url($this->base()); ?>"><?php esc_html_e('Cette semaine', 'ecole2nat'); ?></a>
                    <?php endif; ?>
                </div>
                <a href="<?php echo esc_url($this->base(['e2n_week' => $nextWeek])); ?>"><span><?php esc_html_e('Semaine suivante', 'ecole2nat'); ?></span> →</a>
            </nav>
            <?php if ($groups === []) : ?>
                <section class="e2n-card"><h2><?php esc_html_e('Aucun groupe à afficher', 'ecole2nat'); ?></h2></section>
            <?php else : ?>
                <div class="e2n-week">
                    <?php foreach ($days as $day => $label) :
                        $rows = array_values(array_filter($groups, static fn(array $g): bool => (int) ($g['weekday'] ?? 0) === $day));
                        if ($rows === []) continue;
                        $dayDate = $weekStart->modify('+' . ($day - 1) . ' days');
                    ?>
                        <section>
                            <h2><?php echo esc_html(sprintf(__('%1$s %2$s', 'ecole2nat'), $label, wp_date('j F', $dayDate->getTimestamp()))); ?></h2>
                            <?php foreach ($rows as $g) :
                                $date = $dayDate->format('Y-m-d');
                                $groupId = (int) $g['id'];
                                $planned = $this->repo->planned($groupId, $date);
                                $isSubstitute = $this->access->isSubstituteForDate($groupId, $date);
                            ?>
                                <a class="e2n-slot" href="<?php echo esc_url($this->base(['e2n_group' => $groupId, 'e2n_date' => $date])); ?>">
                                    <time><?php echo esc_html(!empty($g['start_time']) ? substr((string) $g['start_time'], 0, 5) : '—'); ?></time>
                                    <span>
                                        <strong><?php echo esc_html($g['name']); ?></strong>
                                        <small><?php echo esc_html($g['category_name'] . ' · ' . $g['season_name']); ?><?php if ($planned) echo ' · ' . esc_html($planned['session_name']); ?></small>
                                    </span>
                                    <?php if ($planned && ($planned['status'] ?? 'planned') === 'completed') : ?><em class="e2n-pill done"><?php esc_html_e('Réalisée', 'ecole2nat'); ?></em><?php elseif (in_array($groupId, $titular, true)) : ?><em><?php esc_html_e('Titulaire', 'ecole2nat'); ?></em><?php elseif ($isSubstitute) : ?><em><?php echo esc_html($date === current_time('Y-m-d') ? __('Remplaçant', 'ecole2nat') : ($date > current_time('Y-m-d') ? __('Remplaçant prévu', 'ecole2nat') : __('Remplacement passé', 'ecole2nat'))); ?></em><?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </section>
                    <?php endforeach; ?>
                </div>
                <?php
                $unrecognized = array_values(array_filter(
                    $groups,
                    static fn(array $group): bool => (int) ($group['weekday'] ?? 0) < 1
                        || (int) ($group['weekday'] ?? 0) > 7
                ));
                if ($unrecognized !== []) :
                ?>
                    <section class="e2n-card">
                        <h2><?php esc_html_e('Groupes sans créneau reconnu', 'ecole2nat'); ?></h2>
                        <p><?php esc_html_e('Ces groupes restent accessibles, mais leur jour ou leur heure doit être complété dans l’administration.', 'ecole2nat'); ?></p>
                        <div class="e2n-swimmers">
                            <?php foreach ($unrecognized as $group) : ?>
                                <a href="<?php echo esc_url($this->base(['e2n_group' => (int) $group['id'], 'e2n_date' => current_time('Y-m-d')])); ?>">
                                    <strong><?php echo esc_html($group['name']); ?></strong>
                                    <span><?php echo esc_html($group['category_name'] . ' · ' . $group['season_name']); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function group(int $gid, ?string $requestedDate): void
    {
        $g = $this->repo->group($gid);
        if (!$g) { echo '<p>' . esc_html__('Groupe introuvable.', 'ecole2nat') . '</p>'; return; }

        $date = $requestedDate ?: $this->dateInCurrentWeek((int) $g['weekday']);
        $dateObject = new \DateTimeImmutable($date, wp_timezone());
        $planningWeek = $dateObject->modify('monday this week')->format('Y-m-d');
        $currentGroupDate = $this->dateInCurrentWeek((int) $g['weekday']);
        $canPrepare = $this->access->canPrepareGroup($gid, $date);
        $canOperate = $this->access->canOperateGroup($gid, $date);
        $swimmers = $this->repo->swimmers($gid, (int) $g['season_id']);
        $sessions = $this->repo->sessionsForCategory((int) $g['category_id']);
        $planned = $this->repo->planned($gid, $date);
        $attendance = $this->field->attendance($gid, $date);
        $evalContext = $this->eval->groupContext($gid);
        $present = 0; $absent = 0;
        foreach ($attendance as $row) {
            if (($row['status'] ?? '') === 'present') $present++;
            elseif (($row['status'] ?? '') === 'absent') $absent++;
        }
        ?>
        <a class="e2n-back" href="<?php echo esc_url($this->base(['e2n_week' => $planningWeek])); ?>">← <?php esc_html_e('Planning', 'ecole2nat'); ?></a>
        <h1><?php echo esc_html($g['name']); ?></h1>
        <p>
            <?php echo esc_html($g['category_name'] . ' · ' . $g['season_name']); ?>
            <span class="e2n-pill<?php echo ($canPrepare || $canOperate) ? '' : ' muted'; ?>"><?php echo esc_html($this->access->accessLabel($gid, $date)); ?></span>
        </p>

        <nav class="e2n-date-nav e2n-group-date-nav" aria-label="<?php esc_attr_e('Navigation entre les dates du groupe', 'ecole2nat'); ?>">
            <a href="<?php echo esc_url($this->base(['e2n_group' => $gid, 'e2n_date' => $dateObject->modify('-7 days')->format('Y-m-d')])); ?>">← <span><?php esc_html_e('Séance précédente', 'ecole2nat'); ?></span></a>
            <div>
                <strong><?php echo esc_html(wp_date('j M Y', $dateObject->getTimestamp())); ?></strong>
                <?php if ($date !== $currentGroupDate) : ?><a class="e2n-date-current" href="<?php echo esc_url($this->base(['e2n_group' => $gid, 'e2n_date' => $currentGroupDate])); ?>"><?php esc_html_e('Cette semaine', 'ecole2nat'); ?></a><?php endif; ?>
            </div>
            <a href="<?php echo esc_url($this->base(['e2n_group' => $gid, 'e2n_date' => $dateObject->modify('+7 days')->format('Y-m-d')])); ?>"><span><?php esc_html_e('Séance suivante', 'ecole2nat'); ?></span> →</a>
        </nav>

        <section class="e2n-card">
            <h2><?php echo esc_html(sprintf(__('Séance du %s', 'ecole2nat'), wp_date('d/m/Y', strtotime($date)))); ?></h2>
            <?php if ($planned) : ?>
                <div class="e2n-session-summary">
                    <div>
                        <strong><?php echo esc_html($planned['session_name']); ?></strong>
                        <span class="e2n-pill <?php echo ($planned['status'] ?? 'planned') === 'completed' ? 'done' : 'muted'; ?>">
                            <?php echo esc_html(($planned['status'] ?? 'planned') === 'completed' ? __('Réalisée', 'ecole2nat') : __('Prévue', 'ecole2nat')); ?>
                        </span>
                    </div>
                    <a class="e2n-btn" href="<?php echo esc_url($this->base(['e2n_group' => $gid, 'e2n_session' => (int) $planned['session_id'], 'e2n_date' => $date])); ?>"><?php esc_html_e('Ouvrir la séance', 'ecole2nat'); ?></a>
                    <?php if ($canPrepare && (int) ($planned['coach_editable_copy'] ?? 0) === 1) : ?>
                        <a class="e2n-btn" href="<?php echo esc_url($this->base(['e2n_group' => $gid, 'e2n_edit_session' => (int) $planned['session_id'], 'e2n_date' => $date])); ?>"><?php esc_html_e('Modifier la séance', 'ecole2nat'); ?></a>
                    <?php endif; ?>
                </div>
                <?php if ($canOperate) : ?>
                    <form method="post" class="e2n-inline">
                        <input type="hidden" name="e2n_action" value="complete_session">
                        <input type="hidden" name="group_id" value="<?php echo (int) $gid; ?>">
                        <input type="hidden" name="session_date" value="<?php echo esc_attr($date); ?>">
                        <input type="hidden" name="completed" value="<?php echo ($planned['status'] ?? 'planned') === 'completed' ? '0' : '1'; ?>">
                        <?php wp_nonce_field('e2n_coach_write'); ?>
                        <button class="e2n-btn e2n-btn-secondary" type="submit">
                            <?php echo esc_html(($planned['status'] ?? 'planned') === 'completed' ? __('Repasser en prévue', 'ecole2nat') : __('Marquer comme réalisée', 'ecole2nat')); ?>
                        </button>
                    </form>
                <?php endif; ?>
            <?php else : ?>
                <p><?php esc_html_e('Aucune séance affectée à ce créneau.', 'ecole2nat'); ?></p>
            <?php endif; ?>

            <?php if ($canPrepare && $sessions) : ?>
                <form method="post" class="e2n-inline">
                    <input type="hidden" name="e2n_action" value="schedule">
                    <input type="hidden" name="group_id" value="<?php echo (int) $gid; ?>">
                    <input type="hidden" name="session_date" value="<?php echo esc_attr($date); ?>">
                    <?php wp_nonce_field('e2n_coach_write'); ?>
                    <select name="session_id">
                        <?php foreach ($sessions as $s) : ?><option value="<?php echo (int) $s['id']; ?>" <?php selected((int) ($planned['session_id'] ?? 0), (int) $s['id']); ?>><?php echo esc_html($s['name']); ?></option><?php endforeach; ?>
                    </select>
                    <button class="e2n-btn" type="submit"><?php echo esc_html($planned ? __('Changer la séance', 'ecole2nat') : __('Affecter une séance', 'ecole2nat')); ?></button>
                </form>
            <?php endif; ?>
            <?php if ($canPrepare) : ?>
                <details class="e2n-session-builder">
                    <summary><?php echo esc_html($planned ? __('Créer ou dupliquer pour ce créneau', 'ecole2nat') : __('Créer la séance de ce créneau', 'ecole2nat')); ?></summary>
                    <form class="e2n-editor-action" data-e2n-session-action>
                        <input type="hidden" name="editor_action" value="create_session">
                        <input type="hidden" name="group_id" value="<?php echo (int) $gid; ?>">
                        <input type="hidden" name="session_date" value="<?php echo esc_attr($date); ?>">
                        <?php if ($planned) : ?><input type="hidden" name="source_session_id" value="<?php echo (int) $planned['session_id']; ?>"><?php endif; ?>
                        <label><?php esc_html_e('Nom', 'ecole2nat'); ?><input name="name" required value="<?php echo esc_attr($planned ? sprintf(__('Copie de %s', 'ecole2nat'), $planned['session_name']) : ''); ?>"></label>
                        <label><?php esc_html_e('Objectifs', 'ecole2nat'); ?><textarea name="objectives" rows="3"><?php echo esc_textarea($planned['objectives'] ?? ''); ?></textarea></label>
                        <button class="e2n-btn" type="submit"><?php echo esc_html($planned ? __('Dupliquer et adapter', 'ecole2nat') : __('Créer et préparer', 'ecole2nat')); ?></button>
                        <span class="e2n-autosave-status" data-e2n-save-status aria-live="polite"></span>
                    </form>
                </details>
            <?php endif; ?>
        </section>

        <section class="e2n-card">
            <h2><?php esc_html_e('Présences', 'ecole2nat'); ?> <small data-e2n-attendance-summary data-total="<?php echo (int) count($swimmers); ?>"><?php echo esc_html(sprintf(__('%1$d présents · %2$d absents · %3$d prévus', 'ecole2nat'), $present, $absent, count($swimmers))); ?></small></h2>
            <?php if (!$canOperate) : ?><p class="e2n-info"><?php echo esc_html($canPrepare ? __('Les opérations terrain deviennent modifiables le jour de la séance.', 'ecole2nat') : __('Consultation uniquement : vous n’avez pas de droit terrain pour ce groupe à cette date.', 'ecole2nat')); ?></p><?php endif; ?>
            <div class="e2n-attendance-form">
                <?php if ($canOperate) : ?><button type="button" class="e2n-link-button" data-e2n-all-present data-group-id="<?php echo (int) $gid; ?>" data-session-date="<?php echo esc_attr($date); ?>"><?php esc_html_e('Tous présents', 'ecole2nat'); ?></button><?php endif; ?>
                <div class="e2n-autosave-status" data-e2n-save-status aria-live="polite"></div>
                <div class="e2n-attendance-list">
                    <?php foreach ($swimmers as $x) :
                        $sid = (int) $x['id'];
                        $status = (string) ($attendance[$sid]['status'] ?? 'unknown');
                    ?>
                        <div class="e2n-attendance-row">
                            <a class="e2n-swimmer-name" href="<?php echo esc_url($this->base(['e2n_group' => $gid, 'e2n_swimmer' => $sid, 'e2n_date' => $date])); ?>">
                                <strong><?php echo esc_html($x['first_name'] . ' ' . $x['last_name']); ?></strong>
                                <span class="e2n-swimmer-flags">
                                    <?php if (!empty($x['medical_note'])) : ?><span class="e2n-med" title="<?php esc_attr_e('Information médicale', 'ecole2nat'); ?>">⚠</span><?php endif; ?>
                                    <span class="e2n-image-rights <?php echo $x['image_rights'] === null ? 'is-unknown' : ((int) $x['image_rights'] === 1 ? 'is-yes' : 'is-no'); ?>">📷<?php echo $x['image_rights'] === null ? '?' : ((int) $x['image_rights'] === 1 ? '✓' : '✕'); ?></span>
                                </span>
                            </a>
                            <div class="e2n-choice-group e2n-choice-group--attendance" role="radiogroup" aria-label="<?php echo esc_attr(sprintf(__('Présence de %s', 'ecole2nat'), $x['first_name'] . ' ' . $x['last_name'])); ?>">
                                <?php foreach ([
                                    'unknown' => __('Non pointé', 'ecole2nat'),
                                    'present' => __('Présent', 'ecole2nat'),
                                    'absent' => __('Absent', 'ecole2nat'),
                                ] as $value => $label) : ?>
                                    <label class="e2n-choice e2n-choice--<?php echo esc_attr($value); ?>">
                                        <input type="radio" name="attendance[<?php echo $sid; ?>]" value="<?php echo esc_attr($value); ?>" data-e2n-kind="attendance" data-group-id="<?php echo (int) $gid; ?>" data-swimmer-id="<?php echo (int) $sid; ?>" data-session-date="<?php echo esc_attr($date); ?>" <?php checked($status, $value); ?> <?php disabled(!$canOperate); ?>>
                                        <span><?php echo esc_html($label); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <?php if ($evalContext && $evalContext['skills'] !== []) : ?>
            <section class="e2n-card">
                <h2><?php esc_html_e('Évaluation collective rapide', 'ecole2nat'); ?></h2>
                <p><?php esc_html_e('Choisissez une compétence pour évaluer tout le groupe sur un seul écran.', 'ecole2nat'); ?></p>
                <div class="e2n-skill-picker">
                    <?php $domain = ''; foreach ($evalContext['skills'] as $skill) :
                        if ($domain !== $skill['domain_name']) {
                            $domain = $skill['domain_name'];
                            echo '<h3>' . esc_html($domain) . '</h3>';
                        }
                        $skillUrl = $this->base([
                            'e2n_group' => (int) $gid,
                            'e2n_date' => $date,
                            'e2n_collective_skill' => (int) $skill['id'],
                        ]);
                    ?>
                        <a class="e2n-skill-link" href="<?php echo esc_url($skillUrl); ?>"><?php echo esc_html($skill['name']); ?></a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif;
    }

    private function collective(int $gid, int $skillId, ?string $date): void
    {
        $data = $this->eval->collectiveEvaluation($gid, $skillId);
        if (!$data) { echo '<p>' . esc_html__('Compétence ou groupe introuvable.', 'ecole2nat') . '</p>'; return; }
        $sessionDate = $date ?? '';
        $can = $this->access->canOperateGroup($gid, $sessionDate);
        ?>
        <a class="e2n-back" href="<?php echo esc_url($this->base(['e2n_group' => $gid, 'e2n_date' => $date])); ?>">← <?php esc_html_e('Groupe', 'ecole2nat'); ?></a>
        <h1><?php echo esc_html($data['skill']['name']); ?></h1>
        <p><?php echo esc_html($data['skill']['domain_name'] . ' · ' . $data['group']['name']); ?></p>
        <section class="e2n-card">
            <h2><?php esc_html_e('Évaluation collective', 'ecole2nat'); ?></h2>
            <?php if (!$can) : ?><p class="e2n-info"><?php esc_html_e('Consultation uniquement : vous n’avez pas de droit d’écriture pour ce groupe à cette date.', 'ecole2nat'); ?></p><?php endif; ?>
            <div class="e2n-autosave-scope">
                <div class="e2n-autosave-status" data-e2n-save-status aria-live="polite"></div>
                <div class="e2n-collective-list">
                    <?php foreach ($data['swimmers'] as $sw) : ?>
                        <div class="e2n-collective-row">
                            <strong><?php echo esc_html($sw['first_name'] . ' ' . $sw['last_name']); ?></strong>
                            <div class="e2n-choice-group e2n-choice-group--evaluation" role="radiogroup" aria-label="<?php echo esc_attr(sprintf(__('Évaluation de %s', 'ecole2nat'), $sw['first_name'] . ' ' . $sw['last_name'])); ?>">
                                <?php foreach ($this->eval->statuses() as $value => $label) : ?>
                                    <label class="e2n-choice e2n-choice--<?php echo esc_attr($value); ?>">
                                        <input type="radio" name="status[<?php echo (int) $sw['id']; ?>]" value="<?php echo esc_attr($value); ?>" data-e2n-kind="evaluation" data-group-id="<?php echo (int) $gid; ?>" data-swimmer-id="<?php echo (int) $sw['id']; ?>" data-skill-id="<?php echo (int) $skillId; ?>" data-session-date="<?php echo esc_attr($sessionDate); ?>" <?php checked($sw['status'], $value); ?> <?php disabled(!$can); ?>>
                                        <span><?php echo esc_html($label); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php
    }

    private function swimmer(int $gid, int $sid, ?string $date): void
    {
        $data = $this->eval->swimmerEvaluation($gid, $sid);
        if (!$data) { echo '<p>' . esc_html__('Nageur introuvable dans ce groupe.', 'ecole2nat') . '</p>'; return; }
        $sessionDate = $date ?? '';
        $can = $this->access->canOperateGroup($gid, $sessionDate);
        ?>
        <a class="e2n-back" href="<?php echo esc_url($this->base(['e2n_group' => $gid, 'e2n_date' => $date])); ?>">← <?php esc_html_e('Groupe', 'ecole2nat'); ?></a>
        <h1><?php echo esc_html($data['swimmer']['first_name'] . ' ' . $data['swimmer']['last_name']); ?></h1>
        <p class="e2n-swimmer-meta"><span class="e2n-image-rights <?php echo !array_key_exists('image_rights', $data['swimmer']) || $data['swimmer']['image_rights'] === null ? 'is-unknown' : ((int) $data['swimmer']['image_rights'] === 1 ? 'is-yes' : 'is-no'); ?>">📷 <?php echo esc_html(!array_key_exists('image_rights', $data['swimmer']) || $data['swimmer']['image_rights'] === null ? __('Droit à l’image : non renseigné', 'ecole2nat') : ((int) $data['swimmer']['image_rights'] === 1 ? __('Droit à l’image : oui', 'ecole2nat') : __('Droit à l’image : non', 'ecole2nat'))); ?></span></p>
        <?php if (!empty($data['swimmer']['medical_note'])) : ?><div class="e2n-alert"><strong><?php esc_html_e('Information médicale', 'ecole2nat'); ?></strong><p><?php echo nl2br(esc_html($data['swimmer']['medical_note'])); ?></p></div><?php endif; ?>
        <section class="e2n-card">
            <h2><?php esc_html_e('Compétences', 'ecole2nat'); ?></h2>
            <?php if (!$can) : ?><p class="e2n-info"><?php esc_html_e('Consultation uniquement : vous n’avez pas de droit d’écriture pour ce groupe à cette date.', 'ecole2nat'); ?></p><?php endif; ?>
            <div class="e2n-autosave-scope">
                <div class="e2n-autosave-status" data-e2n-save-status aria-live="polite"></div>
                <div class="e2n-skills">
                    <?php $domain = ''; foreach ($data['skills'] as $skill) :
                        if ($domain !== $skill['domain_name']) { $domain = $skill['domain_name']; echo '<h3>' . esc_html($domain) . '</h3>'; }
                    ?>
                        <div class="e2n-skill">
                            <span><?php echo esc_html($skill['name']); ?></span>
                            <div class="e2n-choice-group e2n-choice-group--evaluation" role="radiogroup" aria-label="<?php echo esc_attr($skill['name']); ?>">
                                <?php foreach ($this->eval->statuses() as $v => $lab) : ?>
                                    <label class="e2n-choice e2n-choice--<?php echo esc_attr($v); ?>">
                                        <input type="radio" name="status[<?php echo (int) $skill['id']; ?>]" value="<?php echo esc_attr($v); ?>" data-e2n-kind="evaluation" data-group-id="<?php echo (int) $gid; ?>" data-swimmer-id="<?php echo (int) $sid; ?>" data-skill-id="<?php echo (int) $skill['id']; ?>" data-session-date="<?php echo esc_attr($sessionDate); ?>" <?php checked($skill['status'], $v); ?> <?php disabled(!$can); ?>>
                                        <span><?php echo esc_html($lab); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <textarea name="notes[<?php echo (int) $skill['id']; ?>]" data-e2n-kind="note" data-group-id="<?php echo (int) $gid; ?>" data-swimmer-id="<?php echo (int) $sid; ?>" data-skill-id="<?php echo (int) $skill['id']; ?>" data-session-date="<?php echo esc_attr($sessionDate); ?>" placeholder="<?php esc_attr_e('Note', 'ecole2nat'); ?>" <?php disabled(!$can); ?>><?php echo esc_textarea($skill['notes']); ?></textarea>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php
    }

    private function session(int $gid, int $sid, ?string $date): void
    {
        $s = $this->repo->sessionDetail($sid);
        $g = $this->repo->group($gid);
        if (!$s || !$g) { echo '<p>' . esc_html__('Séance introuvable.', 'ecole2nat') . '</p>'; return; }
        $date = $date ?: $this->dateInCurrentWeek((int)$g['weekday']);
        $canPrepare = $this->access->canPrepareGroup($gid, $date);
        $canOperate = $this->access->canOperateGroup($gid, $date);
        $swimmers=$this->repo->swimmers($gid,(int)$g['season_id']);
        $attendance=$this->field->attendance($gid,$date);
        ?>
        <a class="e2n-back" href="<?php echo esc_url($this->base(['e2n_group' => $gid, 'e2n_date' => $date])); ?>">← <?php esc_html_e('Groupe', 'ecole2nat'); ?></a>
        <h1><?php echo esc_html($s['name']); ?></h1>
        <p>
            <?php echo esc_html(sprintf(__('Séance du %s', 'ecole2nat'),wp_date('d/m/Y',strtotime($date)))); ?>
            <span class="e2n-pill<?php echo ($canPrepare || $canOperate) ? '' : ' muted'; ?>"><?php echo esc_html($this->access->accessLabel($gid, $date)); ?></span>
        </p>
        <?php if ($s['objectives']) : ?><p><?php echo nl2br(esc_html($s['objectives'])); ?></p><?php endif; ?>
        <?php foreach ($s['parts'] as $p) : ?>
            <section class="e2n-card"><h2><?php echo esc_html($p['title']); ?></h2>
                <?php foreach ($p['exercises'] as $e) : ?><article class="e2n-exercise"><strong><?php echo esc_html($e['name']); ?></strong><?php if ($e['duration']) : ?><span><?php echo (int) $e['duration']; ?> min</span><?php endif; ?><p><?php echo nl2br(esc_html($e['description'])); ?></p><?php if ($e['coach_notes']) : ?><small><?php esc_html_e('Coach :', 'ecole2nat'); ?> <?php echo esc_html($e['coach_notes']); ?></small><?php endif; ?></article><?php endforeach; ?>
            </section>
        <?php endforeach; ?>
        <section class="e2n-card">
            <h2><?php esc_html_e('Nageurs', 'ecole2nat'); ?> <small><?php echo count($swimmers); ?></small></h2>
            <div class="e2n-swimmers">
                <?php foreach($swimmers as $x): $attendanceStatus=(string)($attendance[(int)$x['id']]['status']??'unknown'); ?>
                    <a href="<?php echo esc_url($this->base(['e2n_group'=>$gid,'e2n_swimmer'=>(int)$x['id'],'e2n_date'=>$date])); ?>">
                        <strong><?php echo esc_html($x['first_name'].' '.$x['last_name']); ?></strong>
                        <span class="e2n-swimmer-flags">
                            <?php if($attendanceStatus==='present'):?><span class="e2n-attendance-badge present" title="<?php esc_attr_e('Présent','ecole2nat');?>">✓</span><?php elseif($attendanceStatus==='absent'):?><span class="e2n-attendance-badge absent" title="<?php esc_attr_e('Absent','ecole2nat');?>">✕</span><?php endif;?>
                            <?php if(!empty($x['medical_note'])):?><span class="e2n-med" title="<?php esc_attr_e('Information médicale','ecole2nat');?>">⚠</span><?php endif;?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }

    private function sessionEditor(int $gid, int $sid, ?string $date): void
    {
        $g = $this->repo->group($gid);
        $date = $date ?? '';
        $data = $this->access->canPrepareGroup($gid, $date) ? $this->editor->editor($gid, $date, $sid) : null;
        if (!$g || !$data) {
            echo '<p>' . esc_html__('Cette séance ne peut pas être modifiée dans ce contexte.', 'ecole2nat') . '</p>';
            return;
        }
        $session = $data['session'];
        $targetDuration = ScheduleDurationCalculator::minutes($g['start_time'] ?? null, $g['end_time'] ?? null);
        $durationState = $targetDuration === null
            ? ''
            : ((int) $data['duration'] > $targetDuration ? ' is-over' : ((int) $data['duration'] === $targetDuration ? ' is-match' : ' is-under'));
        ?>
        <a class="e2n-back" href="<?php echo esc_url($this->base(['e2n_group' => $gid, 'e2n_date' => $date])); ?>">← <?php esc_html_e('Groupe', 'ecole2nat'); ?></a>
        <h1><?php esc_html_e('Préparer la séance', 'ecole2nat'); ?></h1>
        <p><?php echo esc_html($g['name'] . ' · ' . wp_date('d/m/Y', strtotime($date))); ?></p>
        <?php if ((int) ($session['is_library'] ?? 1) === 0) : ?>
            <section class="e2n-card e2n-editor-library">
                <div><strong><?php esc_html_e('Adaptation ponctuelle', 'ecole2nat'); ?></strong><p><?php esc_html_e('Cette séance reste liée à ce créneau et n’encombre pas la bibliothèque générale.', 'ecole2nat'); ?></p></div>
                <?php $this->editorButton('promote_session', __('Conserver comme séance type', 'ecole2nat'), $gid, $sid, $date, [], __('Ajouter cette séance à la bibliothèque générale ?', 'ecole2nat')); ?>
            </section>
        <?php else : ?>
            <p class="e2n-info"><?php esc_html_e('Cette séance est conservée dans la bibliothèque générale.', 'ecole2nat'); ?></p>
        <?php endif; ?>
        <section class="e2n-card e2n-editor-general">
            <div class="e2n-editor-heading"><h2><?php esc_html_e('Informations générales', 'ecole2nat'); ?></h2><div class="e2n-duration-summary<?php echo esc_attr($durationState); ?>"><strong data-e2n-session-duration data-target-duration="<?php echo $targetDuration === null ? '' : (int) $targetDuration; ?>"><?php echo esc_html($this->durationLabel((int) $data['duration'], $targetDuration)); ?></strong><small data-e2n-duration-warning><?php echo $targetDuration !== null && (int) $data['duration'] > $targetDuration ? esc_html(sprintf(__('Dépassement : %d min', 'ecole2nat'), (int) $data['duration'] - $targetDuration)) : ''; ?></small></div></div>
            <div class="e2n-autosave-status" data-e2n-save-status aria-live="polite"></div>
            <label><?php esc_html_e('Nom', 'ecole2nat'); ?><input required value="<?php echo esc_attr($session['name']); ?>" data-e2n-editor-field="general" data-field="name" data-group-id="<?php echo (int) $gid; ?>" data-session-id="<?php echo (int) $sid; ?>" data-session-date="<?php echo esc_attr($date); ?>"></label>
            <label><?php esc_html_e('Objectifs', 'ecole2nat'); ?><textarea rows="4" data-e2n-editor-field="general" data-field="objectives" data-group-id="<?php echo (int) $gid; ?>" data-session-id="<?php echo (int) $sid; ?>" data-session-date="<?php echo esc_attr($date); ?>"><?php echo esc_textarea($session['objectives']); ?></textarea></label>
        </section>
        <?php foreach ($data['parts'] as $part) : ?>
            <section class="e2n-card e2n-editor-part">
                <div class="e2n-editor-heading">
                    <input aria-label="<?php esc_attr_e('Titre de la partie', 'ecole2nat'); ?>" value="<?php echo esc_attr($part['title']); ?>" data-e2n-editor-field="part" data-part-id="<?php echo (int) $part['id']; ?>" data-group-id="<?php echo (int) $gid; ?>" data-session-id="<?php echo (int) $sid; ?>" data-session-date="<?php echo esc_attr($date); ?>">
                    <span data-e2n-part-duration><?php echo (int) $part['duration']; ?> min</span>
                </div>
                <div class="e2n-autosave-status" data-e2n-save-status aria-live="polite"></div>
                <div class="e2n-editor-tools">
                    <?php $this->editorButton('move_part', __('Monter', 'ecole2nat'), $gid, $sid, $date, ['part_id' => (int) $part['id'], 'direction' => 'up']); ?>
                    <?php $this->editorButton('move_part', __('Descendre', 'ecole2nat'), $gid, $sid, $date, ['part_id' => (int) $part['id'], 'direction' => 'down']); ?>
                    <?php $this->editorButton('delete_part', __('Supprimer', 'ecole2nat'), $gid, $sid, $date, ['part_id' => (int) $part['id']], __('Supprimer cette partie et ses exercices ?', 'ecole2nat')); ?>
                </div>
                <?php foreach ($part['exercises'] as $item) : ?>
                    <article class="e2n-editor-exercise">
                        <strong><?php echo esc_html($item['name']); ?></strong>
                        <label><?php esc_html_e('Durée (min)', 'ecole2nat'); ?><input type="number" min="1" value="<?php echo (int) $item['duration']; ?>" data-e2n-editor-field="exercise" data-field="duration" data-item-id="<?php echo (int) $item['id']; ?>" data-group-id="<?php echo (int) $gid; ?>" data-session-id="<?php echo (int) $sid; ?>" data-session-date="<?php echo esc_attr($date); ?>"></label>
                        <label><?php esc_html_e('Consigne coach', 'ecole2nat'); ?><textarea rows="2" data-e2n-editor-field="exercise" data-field="notes" data-item-id="<?php echo (int) $item['id']; ?>" data-group-id="<?php echo (int) $gid; ?>" data-session-id="<?php echo (int) $sid; ?>" data-session-date="<?php echo esc_attr($date); ?>"><?php echo esc_textarea($item['coach_notes']); ?></textarea></label>
                        <div class="e2n-editor-tools">
                            <?php $this->editorButton('move_exercise', __('Monter', 'ecole2nat'), $gid, $sid, $date, ['item_id' => (int) $item['id'], 'direction' => 'up']); ?>
                            <?php $this->editorButton('move_exercise', __('Descendre', 'ecole2nat'), $gid, $sid, $date, ['item_id' => (int) $item['id'], 'direction' => 'down']); ?>
                            <?php $this->editorButton('delete_exercise', __('Retirer', 'ecole2nat'), $gid, $sid, $date, ['item_id' => (int) $item['id']], __('Retirer cet exercice ?', 'ecole2nat')); ?>
                        </div>
                    </article>
                <?php endforeach; ?>
                <form class="e2n-editor-action e2n-editor-add" data-e2n-session-action>
                    <input type="hidden" name="editor_action" value="create_exercise"><input type="hidden" name="group_id" value="<?php echo (int) $gid; ?>"><input type="hidden" name="session_id" value="<?php echo (int) $sid; ?>"><input type="hidden" name="session_date" value="<?php echo esc_attr($date); ?>"><input type="hidden" name="part_id" value="<?php echo (int) $part['id']; ?>">
                    <div class="e2n-exercise-picker">
                        <label class="screen-reader-text" for="e2n-exercise-search-<?php echo (int) $part['id']; ?>"><?php esc_html_e('Rechercher un exercice', 'ecole2nat'); ?></label>
                        <input id="e2n-exercise-search-<?php echo (int) $part['id']; ?>" type="search" placeholder="<?php esc_attr_e('Rechercher un exercice…', 'ecole2nat'); ?>" data-e2n-exercise-search autocomplete="off">
                        <?php $this->exerciseSelect($data['library']); ?>
                        <small data-e2n-exercise-results aria-live="polite"><?php echo esc_html(sprintf(_n('%d exercice', '%d exercices', count($data['library']), 'ecole2nat'), count($data['library']))); ?></small>
                    </div>
                    <input type="number" name="duration" min="1" value="5" aria-label="<?php esc_attr_e('Durée en minutes', 'ecole2nat'); ?>"><input name="notes" placeholder="<?php esc_attr_e('Consigne coach', 'ecole2nat'); ?>"><button class="e2n-btn" type="submit"><?php esc_html_e('Ajouter', 'ecole2nat'); ?></button>
                </form>
            </section>
        <?php endforeach; ?>
        <section class="e2n-card"><form class="e2n-editor-action e2n-editor-add" data-e2n-session-action><input type="hidden" name="editor_action" value="create_part"><input type="hidden" name="group_id" value="<?php echo (int) $gid; ?>"><input type="hidden" name="session_id" value="<?php echo (int) $sid; ?>"><input type="hidden" name="session_date" value="<?php echo esc_attr($date); ?>"><input name="title" required placeholder="<?php esc_attr_e('Nouvelle partie', 'ecole2nat'); ?>"><button class="e2n-btn" type="submit"><?php esc_html_e('Ajouter une partie', 'ecole2nat'); ?></button><span class="e2n-autosave-status" data-e2n-save-status aria-live="polite"></span></form></section>
        <?php
    }

    private function editorButton(string $action, string $label, int $gid, int $sid, string $date, array $fields, string $confirm = ''): void
    {
        echo '<form class="e2n-editor-action" data-e2n-session-action' . ($confirm !== '' ? ' data-confirm="' . esc_attr($confirm) . '"' : '') . '>';
        foreach (array_merge(['editor_action' => $action, 'group_id' => $gid, 'session_id' => $sid, 'session_date' => $date], $fields) as $name => $value) {
            echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '">';
        }
        echo '<button type="submit" class="e2n-link-button">' . esc_html($label) . '</button></form>';
    }

    private function durationLabel(int $prepared, ?int $target): string
    {
        if ($target === null) {
            return sprintf(__('Durée préparée : %d min', 'ecole2nat'), $prepared);
        }
        return sprintf(__('Durée préparée : %1$d min / créneau : %2$d min', 'ecole2nat'), $prepared, $target);
    }

    private function exerciseSelect(array $library): void
    {
        echo '<select name="exercise_id" required data-e2n-exercise-select>';
        echo '<option value="">' . esc_html__('Ajouter un exercice…', 'ecole2nat') . '</option>';
        $group = '';
        foreach ($library as $exercise) {
            $nextGroup = (string) $exercise['domain_name'] . ' · ' . (string) $exercise['skill_name'];
            if ($nextGroup !== $group) {
                if ($group !== '') echo '</optgroup>';
                echo '<optgroup label="' . esc_attr($nextGroup) . '">';
                $group = $nextGroup;
            }
            echo '<option value="' . (int) $exercise['id'] . '">' . esc_html($exercise['name']) . '</option>';
        }
        if ($group !== '') echo '</optgroup>';
        echo '</select>';
    }

    public function ajaxSessionAction(): void
    {
        check_ajax_referer('e2n_coach_ajax', 'nonce');
        $gid = absint($_POST['group_id'] ?? 0);
        $sid = absint($_POST['session_id'] ?? 0);
        $date = sanitize_text_field(wp_unslash((string) ($_POST['session_date'] ?? '')));
        $action = sanitize_key(wp_unslash((string) ($_POST['editor_action'] ?? '')));
        if (!$this->access->canPrepareGroup($gid, $date)) {
            wp_send_json_error(['message' => __('Modification non autorisée.', 'ecole2nat')], 403);
        }

        if ($action === 'create_session') {
            $sid = $this->editor->createForSlot($gid, $date, sanitize_text_field(wp_unslash((string) ($_POST['name'] ?? ''))), sanitize_textarea_field(wp_unslash((string) ($_POST['objectives'] ?? ''))), get_current_user_id(), absint($_POST['source_session_id'] ?? 0));
            if ($sid > 0) wp_send_json_success(['redirect' => $this->base(['e2n_group' => $gid, 'e2n_edit_session' => $sid, 'e2n_date' => $date])]);
        } elseif ($action === 'save_general') {
            $ok = $this->editor->updateGeneral($gid, $date, $sid, sanitize_text_field(wp_unslash((string) ($_POST['name'] ?? ''))), sanitize_textarea_field(wp_unslash((string) ($_POST['objectives'] ?? ''))));
        } elseif ($action === 'create_part') {
            $ok = $this->editor->createPart($gid, $date, $sid, sanitize_text_field(wp_unslash((string) ($_POST['title'] ?? ''))));
        } elseif ($action === 'save_part') {
            $ok = $this->editor->updatePart($gid, $date, $sid, absint($_POST['part_id'] ?? 0), sanitize_text_field(wp_unslash((string) ($_POST['title'] ?? ''))));
        } elseif ($action === 'move_part') {
            $ok = $this->editor->movePart($gid, $date, $sid, absint($_POST['part_id'] ?? 0), sanitize_key(wp_unslash((string) ($_POST['direction'] ?? ''))));
        } elseif ($action === 'delete_part') {
            $ok = $this->editor->deletePart($gid, $date, $sid, absint($_POST['part_id'] ?? 0));
        } elseif ($action === 'create_exercise') {
            $ok = $this->editor->createExercise($gid, $date, $sid, absint($_POST['part_id'] ?? 0), absint($_POST['exercise_id'] ?? 0), absint($_POST['duration'] ?? 0), sanitize_textarea_field(wp_unslash((string) ($_POST['notes'] ?? ''))));
        } elseif ($action === 'save_exercise') {
            $ok = $this->editor->updateExercise($gid, $date, $sid, absint($_POST['item_id'] ?? 0), absint($_POST['duration'] ?? 0), sanitize_textarea_field(wp_unslash((string) ($_POST['notes'] ?? ''))));
        } elseif ($action === 'move_exercise') {
            $ok = $this->editor->moveExercise($gid, $date, $sid, absint($_POST['item_id'] ?? 0), sanitize_key(wp_unslash((string) ($_POST['direction'] ?? ''))));
        } elseif ($action === 'delete_exercise') {
            $ok = $this->editor->deleteExercise($gid, $date, $sid, absint($_POST['item_id'] ?? 0));
        } elseif ($action === 'promote_session') {
            $ok = $this->editor->promoteToLibrary($gid, $date, $sid);
        }

        if (!empty($ok)) wp_send_json_success(['message' => __('Séance enregistrée.', 'ecole2nat')]);
        wp_send_json_error(['message' => __('Impossible d’enregistrer la séance.', 'ecole2nat')], 400);
    }

    public function ajaxSaveAttendance(): void
    {
        check_ajax_referer('e2n_coach_ajax', 'nonce');
        $gid = absint($_POST['group_id'] ?? 0);
        $date = sanitize_text_field(wp_unslash((string) ($_POST['session_date'] ?? '')));
        if (!$this->access->canOperateGroup($gid, $date)) {
            wp_send_json_error(['message' => __('Modification non autorisée.', 'ecole2nat')], 403);
        }
        $statuses = isset($_POST['statuses']) && is_array($_POST['statuses']) ? wp_unslash($_POST['statuses']) : [];
        if ($statuses === [] && isset($_POST['swimmer_id'], $_POST['status'])) {
            $statuses = [absint($_POST['swimmer_id']) => sanitize_key(wp_unslash((string) $_POST['status']))];
        }
        if (!$this->field->saveAttendance($gid, $date, $statuses, get_current_user_id())) {
            wp_send_json_error(['message' => __('Impossible d’enregistrer la présence.', 'ecole2nat')], 500);
        }
        wp_send_json_success(['message' => __('Présence enregistrée.', 'ecole2nat')]);
    }

    public function ajaxSaveEvaluation(): void
    {
        check_ajax_referer('e2n_coach_ajax', 'nonce');
        $gid = absint($_POST['group_id'] ?? 0);
        $date = sanitize_text_field(wp_unslash((string) ($_POST['session_date'] ?? '')));
        if (!$this->access->canOperateGroup($gid, $date)) {
            wp_send_json_error(['message' => __('Modification non autorisée.', 'ecole2nat')], 403);
        }
        $result = $this->eval->saveSingleStatus(
            $gid,
            absint($_POST['swimmer_id'] ?? 0),
            absint($_POST['skill_id'] ?? 0),
            sanitize_key(wp_unslash((string) ($_POST['status'] ?? ''))),
            get_current_user_id()
        );
        if (!$result['success']) {
            wp_send_json_error(['message' => __('Impossible d’enregistrer l’évaluation.', 'ecole2nat')], 400);
        }
        wp_send_json_success(['message' => __('Évaluation enregistrée.', 'ecole2nat')]);
    }

    public function ajaxSaveNote(): void
    {
        check_ajax_referer('e2n_coach_ajax', 'nonce');
        $gid = absint($_POST['group_id'] ?? 0);
        $date = sanitize_text_field(wp_unslash((string) ($_POST['session_date'] ?? '')));
        if (!$this->access->canOperateGroup($gid, $date)) {
            wp_send_json_error(['message' => __('Modification non autorisée.', 'ecole2nat')], 403);
        }
        $result = $this->eval->saveSingleNote(
            $gid,
            absint($_POST['swimmer_id'] ?? 0),
            absint($_POST['skill_id'] ?? 0),
            sanitize_textarea_field(wp_unslash((string) ($_POST['note'] ?? ''))),
            get_current_user_id()
        );
        if (!$result['success']) {
            wp_send_json_error(['message' => __('Impossible d’enregistrer la note.', 'ecole2nat')], 400);
        }
        wp_send_json_success(['message' => __('Note enregistrée.', 'ecole2nat')]);
    }

    private function handlePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['e2n_action'])) return;

        check_admin_referer('e2n_coach_write');
        $gid = absint($_POST['group_id'] ?? 0);
        $date = sanitize_text_field(wp_unslash((string) ($_POST['session_date'] ?? '')));
        $action = sanitize_key((string) $_POST['e2n_action']);
        if ($action === 'schedule') {
            if (!$this->access->canPrepareGroup($gid, $date)) {
                wp_die(esc_html__('Vous n’êtes pas autorisé à préparer ce groupe à cette date.', 'ecole2nat'));
            }
            $this->repo->schedule($gid, absint($_POST['session_id'] ?? 0), $date, get_current_user_id());
        } else {
            if (!$this->access->canOperateGroup($gid, $date)) {
                wp_die(esc_html__('Vous n’êtes pas autorisé à modifier ce groupe à cette date.', 'ecole2nat'));
            }
        }

        if ($action === 'complete_session') {
            $this->field->markSessionCompleted($gid, $date, get_current_user_id(), absint($_POST['completed'] ?? 0) === 1);
        } elseif ($action === 'attendance') {
            $this->field->saveAttendance($gid, $date, (array) ($_POST['attendance'] ?? []), get_current_user_id());
        } elseif ($action === 'evaluate') {
            $this->eval->save($gid, absint($_POST['swimmer_id'] ?? 0), (array) ($_POST['status'] ?? []), (array) ($_POST['notes'] ?? []), get_current_user_id());
        } elseif ($action === 'collective_evaluate') {
            $this->eval->saveCollective($gid, absint($_POST['skill_id'] ?? 0), (array) ($_POST['status'] ?? []), get_current_user_id());
        }
    }

    private function requestedDate(): ?string
    {
        $date = isset($_GET['e2n_date']) ? sanitize_text_field(wp_unslash($_GET['e2n_date'])) : '';
        if ($date === '' && isset($_POST['session_date'])) $date = sanitize_text_field(wp_unslash($_POST['session_date']));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
    }

    private function requestedWeekStart(): \DateTimeImmutable
    {
        $requested = isset($_GET['e2n_week']) ? sanitize_text_field(wp_unslash($_GET['e2n_week'])) : '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $requested)) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $requested, wp_timezone());
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->modify('monday this week');
            }
        }

        return $this->currentWeekStart();
    }

    private function currentWeekStart(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('today', wp_timezone()))->modify('monday this week');
    }

    private function dateInCurrentWeek(int $weekday): string
    {
        $safeWeekday = max(1, min(7, $weekday));
        return $this->currentWeekStart()->modify('+' . ($safeWeekday - 1) . ' days')->format('Y-m-d');
    }
}
