<?php

namespace Ecole2Nat\Coach;

use Ecole2Nat\Competition\CompetitionService;
use Ecole2Nat\Competition\CompetitionBillingService;
use Ecole2Nat\Evaluation\EvaluationService;
use Ecole2Nat\ParentPortal\ParentAccessService;
use Ecole2Nat\Performance\EventCatalog;
use Ecole2Nat\Performance\PerformanceService;
use Ecole2Nat\Support\Config;
use Ecole2Nat\Support\ContactList;
use Ecole2Nat\Support\Extranat;

if (!defined('ABSPATH')) { exit; }

class CoachPortal
{
    private CoachAccessService $access;
    private CoachPortalRepository $repo;
    private EvaluationService $eval;
    private ParentAccessService $parentAccess;
    private CompetitionService $competitions;
    private CompetitionBillingService $billing;
    private PerformanceService $performances;
    private string $competitionNotice = '';
    private array $competitionMissing = [];

    public function __construct()
    {
        $this->access = new CoachAccessService();
        $this->repo = new CoachPortalRepository();
        $this->eval = new EvaluationService();
        $this->parentAccess = new ParentAccessService();
        $this->competitions = new CompetitionService();
        $this->billing = new CompetitionBillingService();
        $this->performances = new PerformanceService();
    }

    public function register(): void
    {
        add_shortcode('e2n_coach_portal', [$this, 'shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_filter('template_include', [$this, 'template'], 99);
        add_filter('login_redirect', [$this, 'loginRedirect'], 10, 3);
        add_filter('show_admin_bar', [$this, 'showAdminBar']);
        add_action('wp_ajax_e2n_coach_save_evaluation', [$this, 'ajaxSaveEvaluation']);
        add_action('wp_ajax_e2n_coach_save_note', [$this, 'ajaxSaveNote']);
        add_action('wp_ajax_e2n_coach_save_competition_response', [$this, 'ajaxSaveCompetitionResponse']);
        add_action('wp_ajax_e2n_coach_set_competition_engaged', [$this, 'ajaxSetCompetitionEngaged']);
        add_action('wp_ajax_e2n_coach_save_timed_performance', [$this, 'ajaxSaveTimedPerformance']);
        add_action('wp_ajax_e2n_coach_save_training_performance', [$this, 'ajaxSaveTrainingPerformance']);
        add_action('wp_ajax_e2n_coach_delete_timed_performance', [$this, 'ajaxDeleteTimedPerformance']);
        add_action('wp_ajax_e2n_coach_delete_timed_series', [$this, 'ajaxDeleteTimedSeries']);
        add_action('wp_ajax_e2n_coach_delete_swimmer_performance', [$this, 'ajaxDeleteSwimmerPerformance']);
        add_action('wp_ajax_e2n_coach_purge_swimmer_performances', [$this, 'ajaxPurgeSwimmerPerformances']);
        add_action('wp_ajax_e2n_coach_save_category_visibility', [$this, 'ajaxSaveCategoryVisibility']);
    }

    public function template(string $template): string
    {
        $pageId = (int) get_option('e2n_coach_page_id', 0);
        if ($pageId > 0 && is_page($pageId)) {
            $coachTemplate = E2N_PLUGIN_PATH . 'templates/coach-portal.php';
            if (is_readable($coachTemplate)) return $coachTemplate;
        }
        return $template;
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

    public function showAdminBar(bool $show): bool
    {
        if (is_admin()) return $show;
        $user = wp_get_current_user();
        return in_array('e2n_coach', (array) $user->roles, true) && !current_user_can('manage_options') ? false : $show;
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
            'selectRace' => __('Choisissez une épreuve et au moins un nageur.', 'ecole2nat'),
            'confirmRaceReset' => __('Abandonner le chronométrage en cours ?', 'ecole2nat'),
            'confirmDeleteRaceTime' => __('Supprimer définitivement ce chrono ?', 'ecole2nat'),
            'confirmDeleteRaceSeries' => __('Supprimer définitivement toute cette série et tous ses chronos ?', 'ecole2nat'),
            'raceTimeDeleted' => __('Chrono supprimé.', 'ecole2nat'),
            'raceSeriesDeleted' => __('Série supprimée.', 'ecole2nat'),
            'confirmDeleteSwimmerTime' => __('Supprimer définitivement ce chrono ?', 'ecole2nat'),
            'confirmPurgeSwimmerTimes' => __('Supprimer définitivement tous les chronos de ce nageur, en entraînement comme en compétition ?', 'ecole2nat'),
        ]);
        if (!is_user_logged_in()) return '<div class="e2n-coach-login"><p>' . esc_html__('Connectez-vous pour accéder à l’espace coach.', 'ecole2nat') . '</p><a class="e2n-btn" href="' . esc_url(wp_login_url(get_permalink())) . '">' . esc_html__('Se connecter', 'ecole2nat') . '</a></div>';
        if (!$this->access->canView()) return '<p>' . esc_html__('Votre compte ne possède pas l’accès coach.', 'ecole2nat') . '</p>';

        $groupId = absint($_GET['e2n_group'] ?? 0);
        $swimmerId = absint($_GET['e2n_swimmer'] ?? 0);
        $collectiveSkillId = absint($_GET['e2n_collective_skill'] ?? 0);
        $view = sanitize_key(wp_unslash((string) ($_GET['e2n_view'] ?? 'week')));
        if (!in_array($view, ['swimmers', 'categories', 'week', 'competitions'], true)) $view = 'week';
        $from = sanitize_key(wp_unslash((string) ($_GET['e2n_from'] ?? $view)));
        if (!in_array($from, ['swimmers', 'categories', 'week', 'competitions'], true)) $from = 'week';
        $competitionId = absint($_GET['e2n_competition'] ?? 0);
        if ($view === 'competitions' && $competitionId > 0) $this->handleCompetitionAction($competitionId);
        ob_start();
        echo '<div class="e2n-coach">';
        $this->header($groupId > 0 ? $from : $view);
        if ($view === 'competitions' && $competitionId > 0) $this->competition($competitionId);
        elseif ($view === 'competitions') $this->competitions();
        elseif ($groupId && $swimmerId) $this->swimmer($groupId, $swimmerId, $from);
        elseif ($groupId && $collectiveSkillId) $this->collective($groupId, $collectiveSkillId, $from);
        elseif ($groupId) $this->group($groupId, $from);
        elseif ($view === 'swimmers') $this->swimmersIndex();
        elseif ($view === 'categories') $this->categoriesIndex();
        else $this->dashboard();
        echo '</div>';
        return (string) ob_get_clean();
    }

    private function base(array $args = []): string
    {
        return add_query_arg($args, get_permalink((int) get_option('e2n_coach_page_id', 0)));
    }

    private function header(string $view): void
    {
        $user = wp_get_current_user();
        $portalTitle = Config::portalTitle();
        $portalLogoId = Config::portalLogoId(); ?>
        <header class="e2n-coach-head"><a class="e2n-brand" href="<?php echo esc_url($this->base()); ?>"><?php if ($portalLogoId > 0) : ?><?php echo wp_get_attachment_image($portalLogoId, 'thumbnail', false, ['class' => 'e2n-brand-image']); ?><?php else : ?><span class="e2n-brand-mark" aria-hidden="true">E2N</span><?php endif; ?><span><?php echo esc_html($portalTitle); ?></span></a><nav aria-label="<?php esc_attr_e('Navigation Coach', 'ecole2nat'); ?>"><a class="<?php echo $view === 'swimmers' ? 'is-active' : ''; ?>" href="<?php echo esc_url($this->base(['e2n_view' => 'swimmers'])); ?>"><?php esc_html_e('Nageurs', 'ecole2nat'); ?></a><a class="<?php echo $view === 'categories' ? 'is-active' : ''; ?>" href="<?php echo esc_url($this->base(['e2n_view' => 'categories'])); ?>"><?php esc_html_e('Catégories', 'ecole2nat'); ?></a><a class="<?php echo $view === 'week' ? 'is-active' : ''; ?>" href="<?php echo esc_url($this->base()); ?>"><?php esc_html_e('Semaine type', 'ecole2nat'); ?></a><a class="<?php echo $view === 'competitions' ? 'is-active' : ''; ?>" href="<?php echo esc_url($this->base(['e2n_view'=>'competitions'])); ?>"><?php esc_html_e('Compétitions','ecole2nat'); ?></a></nav><details class="e2n-user-menu"><summary aria-label="<?php esc_attr_e('Menu utilisateur', 'ecole2nat'); ?>"><span aria-hidden="true"><?php echo esc_html(mb_strtoupper(mb_substr((string) $user->display_name, 0, 1))); ?></span></summary><div><strong><?php echo esc_html($user->display_name); ?></strong><a href="<?php echo esc_url(admin_url()); ?>"><?php esc_html_e('Tableau de bord', 'ecole2nat'); ?></a><a href="<?php echo esc_url(wp_logout_url($this->base())); ?>"><?php esc_html_e('Déconnexion', 'ecole2nat'); ?></a></div></details></header>
        <?php $activeCompetitions=$this->competitions->activeCompetitions();if($activeCompetitions!==[]):?><nav class="e2n-live-competitions" aria-label="<?php esc_attr_e('Compétitions en cours','ecole2nat'); ?>"><strong><span aria-hidden="true">▶</span> <?php esc_html_e('En cours','ecole2nat'); ?></strong><?php foreach($activeCompetitions as $activeCompetition):?><a href="<?php echo esc_url($this->base(['e2n_view'=>'competitions','e2n_competition'=>(int)$activeCompetition['id']])); ?>"><?php echo esc_html($activeCompetition['name']); ?></a><?php endforeach; ?></nav><?php endif; ?>
        <?php
    }

    private function dashboard(): void
    {
        $groups = $this->repo->groups();
        $days = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche']; ?>
        <div class="e2n-coach-main"><h1><?php esc_html_e('Semaine type', 'ecole2nat'); ?></h1><p class="e2n-info"><?php esc_html_e('Choisissez un créneau pour consulter le groupe et mettre à jour les progressions.', 'ecole2nat'); ?></p>
        <?php if ($groups === []) : ?><section class="e2n-card"><h2><?php esc_html_e('Aucun groupe à afficher', 'ecole2nat'); ?></h2></section><?php else : ?>
            <div class="e2n-week">
            <?php foreach ($days as $day => $label) : $rows = array_values(array_filter($groups, static fn(array $group): bool => (int) ($group['weekday'] ?? 0) === $day)); if ($rows === []) continue; ?>
                <section><h2><?php echo esc_html($label); ?></h2>
                <?php foreach ($rows as $group) : $groupId = (int) $group['id']; $titulars = $this->repo->titularNames($groupId); ?>
                    <a class="e2n-slot" href="<?php echo esc_url($this->base(['e2n_group' => $groupId])); ?>"><time><?php echo esc_html($this->timeRange($group)); ?></time><span><strong><?php echo esc_html($group['name']); ?></strong><small><?php echo esc_html($group['category_name'] . ' · ' . $group['season_name']); ?></small></span><?php if ($titulars !== []) : ?><em><?php echo esc_html(implode(' · ', $titulars)); ?></em><?php endif; ?></a>
                <?php endforeach; ?></section>
            <?php endforeach; ?>
            </div>
            <?php $unrecognized = array_values(array_filter($groups, static fn(array $group): bool => (int) ($group['weekday'] ?? 0) < 1 || (int) ($group['weekday'] ?? 0) > 7)); if ($unrecognized !== []) : ?>
                <section class="e2n-card"><h2><?php esc_html_e('Groupes sans créneau reconnu', 'ecole2nat'); ?></h2><div class="e2n-swimmers"><?php foreach ($unrecognized as $group) : ?><a href="<?php echo esc_url($this->base(['e2n_group' => (int) $group['id']])); ?>"><strong><?php echo esc_html($group['name']); ?></strong><span><?php echo esc_html($group['category_name']); ?></span></a><?php endforeach; ?></div></section>
            <?php endif; ?>
        <?php endif; ?></div><?php
    }

    private function swimmersIndex(): void
    {
        $swimmers = $this->repo->allSwimmers(); ?>
        <div class="e2n-coach-main"><h1><?php esc_html_e('Tous les nageurs', 'ecole2nat'); ?></h1>
        <label class="e2n-search"><span><?php esc_html_e('Rechercher un nageur', 'ecole2nat'); ?></span><input type="search" data-e2n-swimmer-search placeholder="<?php esc_attr_e('Nom, prénom, groupe…', 'ecole2nat'); ?>"></label>
        <?php if ($swimmers === []) : ?><section class="e2n-card"><p><?php esc_html_e('Aucun nageur actif à afficher.', 'ecole2nat'); ?></p></section><?php else : ?>
            <section class="e2n-card"><div class="e2n-swimmers" data-e2n-swimmer-list><?php foreach ($swimmers as $swimmer) : $this->swimmerLink($swimmer, 'swimmers'); endforeach; ?></div><p class="e2n-empty-filter" data-e2n-empty-filter hidden><?php esc_html_e('Aucun nageur ne correspond à cette recherche.', 'ecole2nat'); ?></p></section>
        <?php endif; ?></div><?php
    }

    private function categoriesIndex(): void
    {
        $swimmers = $this->withPerformanceCounts($this->repo->allSwimmers());
        $categories = [];
        foreach ($swimmers as $swimmer) {
            $categoryId = (int) $swimmer['category_id'];
            $group = (string) $swimmer['group_name'];
            $categories[$categoryId]['name'] = (string) $swimmer['category_name'];
            $categories[$categoryId]['groups'][$group][] = $swimmer;
        }
        uasort($categories, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        $hiddenCategories = array_map('absint', (array) get_user_meta(get_current_user_id(), 'e2n_hidden_coach_categories', true)); ?>
        <div class="e2n-coach-main"><h1><?php esc_html_e('Nageurs par catégorie', 'ecole2nat'); ?></h1>
        <?php if ($categories === []) : ?><section class="e2n-card"><p><?php esc_html_e('Aucun nageur actif à afficher.', 'ecole2nat'); ?></p></section><?php else : ?>
            <fieldset class="e2n-category-filters"><legend><?php esc_html_e('Catégories affichées', 'ecole2nat'); ?></legend><?php foreach ($categories as $categoryId => $categoryData) : ?><label><input type="checkbox" value="<?php echo (int) $categoryId; ?>" data-e2n-kind="category-visibility" <?php checked(!in_array((int) $categoryId, $hiddenCategories, true)); ?>> <span><?php echo esc_html($categoryData['name']); ?></span></label><?php endforeach; ?><span class="e2n-category-filter-status" data-e2n-category-filter-status aria-live="polite"></span></fieldset>
            <div class="e2n-category-list"><?php foreach ($categories as $categoryId => $categoryData) : $groups = $categoryData['groups']; ksort($groups, SORT_NATURAL | SORT_FLAG_CASE); ?><section class="e2n-card" data-e2n-category-section="<?php echo (int) $categoryId; ?>" <?php echo in_array((int) $categoryId, $hiddenCategories, true) ? 'hidden' : ''; ?>><h2><?php echo esc_html($categoryData['name']); ?></h2><?php foreach ($groups as $group => $rows) : ?><h3><?php echo esc_html($group); ?></h3><div class="e2n-swimmers"><?php foreach ($rows as $swimmer) : $this->categorySwimmerLink($swimmer); endforeach; ?></div><?php endforeach; ?></section><?php endforeach; ?></div>
            <?php $uniqueSwimmers = []; foreach ($swimmers as $swimmer) $uniqueSwimmers[(int) $swimmer['id']] = $swimmer; $this->raceTimer('training-categories', 0, array_values($uniqueSwimmers), true, true); ?>
        <?php endif; ?></div><?php
    }

    private function categorySwimmerLink(array $swimmer): void
    { ?>
        <div class="e2n-category-swimmer"><a href="<?php echo esc_url($this->base(['e2n_group' => (int) $swimmer['group_id'], 'e2n_swimmer' => (int) $swimmer['id'], 'e2n_from' => 'categories'])); ?>"><strong><?php echo esc_html($swimmer['first_name'] . ' ' . $swimmer['last_name']); ?></strong><span class="e2n-swimmer-card-meta"><?php $this->performanceCountBadge($swimmer); ?><?php $this->swimmerFlags($swimmer); ?></span></a><?php $this->extranatLink($swimmer); ?></div><?php
    }

    private function swimmerLink(array $swimmer, string $from, bool $showGroup = true): void
    {
        $search = implode(' ', [(string) $swimmer['first_name'], (string) $swimmer['last_name'], (string) $swimmer['group_name'], (string) $swimmer['category_name']]); ?>
        <a data-e2n-swimmer-card data-search="<?php echo esc_attr($search); ?>" href="<?php echo esc_url($this->base(['e2n_group' => (int) $swimmer['group_id'], 'e2n_swimmer' => (int) $swimmer['id'], 'e2n_from' => $from])); ?>"><strong><?php echo esc_html($swimmer['first_name'] . ' ' . $swimmer['last_name']); ?></strong><span class="e2n-swimmer-card-meta"><?php if ($showGroup) : ?><span><?php echo esc_html($swimmer['group_name']); ?></span><?php endif; ?><?php $this->swimmerFlags($swimmer); ?></span></a><?php
    }

    private function group(int $groupId, string $from = 'week'): void
    {
        $context = $this->eval->groupContext($groupId);
        if ($context === null) { echo '<p>' . esc_html__('Groupe introuvable.', 'ecole2nat') . '</p>'; return; }
        $context['swimmers']=$this->withPerformanceCounts($context['swimmers']);$group = $context['group'];$hasSkills=$context['skills']!==[]; ?>
        <a class="e2n-back" href="<?php echo esc_url($this->originUrl($from)); ?>">← <?php echo esc_html($this->originLabel($from)); ?></a><h1><?php echo esc_html($group['name']); ?></h1><p><?php echo esc_html($group['category_name'] . ' · ' . $group['season_name']); ?> <span class="e2n-pill"><?php esc_html_e('Évaluation autorisée', 'ecole2nat'); ?></span></p>
        <section class="e2n-card"><h2><?php esc_html_e('Nageurs', 'ecole2nat'); ?></h2><div class="e2n-swimmers">
        <?php foreach ($context['swimmers'] as $swimmer) : ?><a href="<?php echo esc_url($this->base(['e2n_group' => $groupId, 'e2n_swimmer' => (int) $swimmer['id'], 'e2n_from' => $from])); ?>"><strong><?php echo esc_html($swimmer['first_name'] . ' ' . $swimmer['last_name']); ?></strong><span class="e2n-swimmer-card-meta"><?php if($hasSkills):?><span><?php echo esc_html(sprintf(__('%1$d acquis · %2$d en cours', 'ecole2nat'), (int) $swimmer['acquired_count'], (int) $swimmer['in_progress_count'])); ?></span><?php endif; ?><?php $this->performanceCountBadge($swimmer); ?><?php $this->swimmerFlags($swimmer); ?></span></a><?php endforeach; ?>
        </div></section>
        <?php if ($context['skills'] !== []) : ?><section class="e2n-card"><h2><?php esc_html_e('Évaluation collective rapide', 'ecole2nat'); ?></h2><p><?php esc_html_e('Choisissez une compétence pour mettre à jour tout le groupe.', 'ecole2nat'); ?></p><div class="e2n-skill-picker">
        <?php $domain = ''; foreach ($context['skills'] as $skill) : if ($domain !== $skill['domain_name']) { $domain = $skill['domain_name']; echo '<h3>' . esc_html($domain) . '</h3>'; } ?><a class="e2n-skill-link" href="<?php echo esc_url($this->base(['e2n_group' => $groupId, 'e2n_collective_skill' => (int) $skill['id'], 'e2n_from' => $from])); ?>"><?php echo esc_html($skill['name']); ?></a><?php endforeach; ?>
        </div></section><?php endif;
        $this->raceTimer('training', $groupId, $context['swimmers'], true);
    }

    private function swimmer(int $groupId, int $swimmerId, string $from): void
    {
        $data = $this->eval->swimmerEvaluation($groupId, $swimmerId);
        if ($data === null) { echo '<p>' . esc_html__('Nageur introuvable dans ce groupe.', 'ecole2nat') . '</p>'; return; }
        $total = count($data['skills']);
        $acquired = count(array_filter($data['skills'], static fn(array $skill): bool => $skill['status'] === EvaluationService::STATUS_ACQUIRED));
        $inProgress = count(array_filter($data['skills'], static fn(array $skill): bool => $skill['status'] === EvaluationService::STATUS_IN_PROGRESS));
        $percentage = $total > 0 ? (int) round(($acquired / $total) * 100) : 0;
        $domains = [];
        foreach ($data['skills'] as $skill) $domains[(string) $skill['domain_name']][] = $skill;
        $phones = ContactList::phones((string) ($data['swimmer']['responsible_phone'] ?? ''));
        $phone = $phones !== [] ? $this->phoneUri($phones[0]) : '';
        $previewUrl = $this->parentAccess->coachPreviewUrl($swimmerId);
        $performanceHistory = $this->performances->historyForSwimmer($swimmerId); ?>
        <a class="e2n-back" href="<?php echo esc_url($from === 'week' ? $this->base(['e2n_group' => $groupId]) : $this->originUrl($from)); ?>">← <?php echo esc_html($from === 'week' ? __('Groupe', 'ecole2nat') : $this->originLabel($from)); ?></a>
        <article class="e2n-swimmer-profile">
            <header class="e2n-swimmer-heading">
                <div><p class="e2n-eyebrow"><?php esc_html_e('Fiche nageur', 'ecole2nat'); ?></p><h1><?php echo esc_html($data['swimmer']['first_name'] . ' ' . $data['swimmer']['last_name']); ?></h1><p class="e2n-swimmer-meta"><?php echo esc_html($data['group']['name']); ?></p><?php $this->swimmerFlags($data['swimmer'], true); ?><?php $this->extranatLink($data['swimmer']); ?></div>
                <details class="e2n-actions-menu"><summary><?php esc_html_e('Actions', 'ecole2nat'); ?> <span aria-hidden="true">•••</span></summary><div class="e2n-actions-panel">
                    <?php if ($phone !== '') : ?><a href="tel:<?php echo esc_attr($phone); ?>"><?php esc_html_e('Appeler le responsable', 'ecole2nat'); ?></a><a href="sms:<?php echo esc_attr($phone); ?>"><?php esc_html_e('Envoyer un message', 'ecole2nat'); ?></a><?php endif; ?>
                    <?php if ($previewUrl !== '') : ?><a href="<?php echo esc_url($previewUrl); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Voir la fiche Parents', 'ecole2nat'); ?></a><?php endif; ?>
                    <?php if ($performanceHistory !== []) : ?><button class="e2n-action-danger" type="button" data-e2n-purge-swimmer-times data-group-id="<?php echo (int) $groupId; ?>" data-swimmer-id="<?php echo (int) $swimmerId; ?>"><?php esc_html_e('Purger les chronos', 'ecole2nat'); ?></button><?php endif; ?>
                </div></details>
            </header>
            <?php if ($total > 0) : ?><section class="e2n-progress-summary" aria-label="<?php esc_attr_e('Résumé de la progression', 'ecole2nat'); ?>"><div><strong><?php esc_html_e('Progression', 'ecole2nat'); ?></strong><span><?php echo esc_html(sprintf(__('%1$d acquises · %2$d en cours · %3$d au total', 'ecole2nat'), $acquired, $inProgress, $total)); ?></span></div><div class="e2n-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int) $percentage; ?>"><span style="width:<?php echo (int) $percentage; ?>%"></span></div><b><?php echo (int) $percentage; ?> %</b></section><?php endif; ?>
            <?php $this->performanceHistory($performanceHistory, $groupId, $swimmerId); ?>
            <?php if ($total > 0) : ?><section class="e2n-card e2n-progress-card"><div class="e2n-autosave-status" data-e2n-save-status aria-live="polite"></div><div class="e2n-skills">
                <?php foreach ($domains as $domain => $skills) : ?><section class="e2n-domain"><h2><?php echo esc_html($domain); ?></h2>
                    <?php foreach ($skills as $skill) : ?><article class="e2n-skill"><div class="e2n-skill-name"><strong><?php echo esc_html($skill['name']); ?></strong><?php $this->history($skill['history']); ?></div><div class="e2n-choice-group e2n-choice-group--evaluation" role="radiogroup" aria-label="<?php echo esc_attr($skill['name']); ?>">
                    <?php foreach ($this->eval->statuses() as $value => $label) : ?><label class="e2n-choice e2n-choice--<?php echo esc_attr($value); ?>"><input type="radio" name="status[<?php echo (int) $skill['id']; ?>]" value="<?php echo esc_attr($value); ?>" data-e2n-kind="evaluation" data-group-id="<?php echo (int) $groupId; ?>" data-swimmer-id="<?php echo (int) $swimmerId; ?>" data-skill-id="<?php echo (int) $skill['id']; ?>" <?php checked($skill['status'], $value); ?>><span><?php echo esc_html($label); ?></span></label><?php endforeach; ?></div>
                    <details class="e2n-note-editor" <?php echo $skill['notes'] !== '' ? 'open' : ''; ?>><summary><?php echo esc_html($skill['notes'] !== '' ? __('Note interne renseignée', 'ecole2nat') : __('Ajouter une note', 'ecole2nat')); ?></summary><textarea rows="2" data-e2n-kind="note" data-group-id="<?php echo (int) $groupId; ?>" data-swimmer-id="<?php echo (int) $swimmerId; ?>" data-skill-id="<?php echo (int) $skill['id']; ?>" placeholder="<?php esc_attr_e('Note interne', 'ecole2nat'); ?>"><?php echo esc_textarea($skill['notes']); ?></textarea></details></article><?php endforeach; ?>
                </section><?php endforeach; ?>
            </div></section><?php endif; ?>
        </article><?php
    }

    private function originUrl(string $from): string
    {
        return $from === 'week' ? $this->base() : $this->base(['e2n_view' => $from]);
    }

    private function swimmerFlags(array $swimmer, bool $detailed = false): void
    { ?>
        <span class="e2n-swimmer-flags <?php echo $detailed ? 'is-detailed' : ''; ?>"><?php if (!empty($swimmer['health_alert'])) : ?><span class="e2n-health-alert" title="<?php esc_attr_e('Information de santé à consulter', 'ecole2nat'); ?>" aria-label="<?php esc_attr_e('Information de santé à consulter', 'ecole2nat'); ?>">⚠<?php if ($detailed) : ?> <?php esc_html_e('Santé à consulter', 'ecole2nat'); ?><?php endif; ?></span><?php endif; ?><span class="e2n-image-rights <?php echo $swimmer['image_rights'] === null ? 'is-unknown' : ((int) $swimmer['image_rights'] === 1 ? 'is-yes' : 'is-no'); ?>" title="<?php esc_attr_e('Droit à l’image', 'ecole2nat'); ?>" aria-label="<?php esc_attr_e('Droit à l’image', 'ecole2nat'); ?>">📷<?php echo $swimmer['image_rights'] === null ? '?' : ((int) $swimmer['image_rights'] === 1 ? '✓' : '✕'); ?><?php if ($detailed) : ?> <?php echo esc_html($swimmer['image_rights'] === null ? __('Image non renseignée', 'ecole2nat') : ((int) $swimmer['image_rights'] === 1 ? __('Image autorisée', 'ecole2nat') : __('Image refusée', 'ecole2nat'))); ?><?php endif; ?></span></span><?php
    }

    private function withPerformanceCounts(array $swimmers): array
    {
        $counts=$this->performances->countsForSwimmers(array_column($swimmers,'id'));
        foreach($swimmers as &$swimmer)$swimmer['performance_count']=$counts[(int)$swimmer['id']]??0;
        unset($swimmer);return $swimmers;
    }

    private function withCompetitionPerformanceCounts(array $swimmers,int $competitionId): array
    {
        $counts=$this->performances->competitionCountsForSwimmers($competitionId,array_column($swimmers,'id'));
        foreach($swimmers as &$swimmer){$swimmer['performance_count']=$counts[(int)$swimmer['id']]??0;$swimmer['performance_count_variant']='competition';}
        unset($swimmer);return $swimmers;
    }

    private function performanceCountBadge(array $swimmer): void
    {
        $count=(int)($swimmer['performance_count']??0);if($count<1)return;$competition=($swimmer['performance_count_variant']??'')==='competition';
        $label=$competition?sprintf(_n('%d épreuve réalisée','%d épreuves réalisées',$count,'ecole2nat'),$count):sprintf(_n('%d chrono enregistré','%d chronos enregistrés',$count,'ecole2nat'),$count); ?>
        <span class="e2n-performance-count <?php echo $competition?'is-competition':''; ?>" title="<?php echo esc_attr($label); ?>" aria-label="<?php echo esc_attr($label); ?>"><?php echo $count; ?></span><?php
    }

    private function phoneUri(string $phone): string
    {
        return (string) preg_replace('/(?!^\+)[^0-9]/', '', trim($phone));
    }

    private function extranatLink(array $swimmer): void
    {
        $url = Extranat::swimmerUrl($swimmer['licence_number'] ?? null);
        if ($url === '') return; ?>
        <a class="e2n-extranat-link" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Fiche Extranat', 'ecole2nat'); ?></a><?php
    }

    private function originLabel(string $from): string
    {
        return ['swimmers' => __('Nageurs', 'ecole2nat'), 'categories' => __('Catégories', 'ecole2nat')][$from] ?? __('Semaine type', 'ecole2nat');
    }

    private function competitions(): void
    {
        $rows=$this->competitions->coachList(); ?>
        <div class="e2n-coach-main"><h1><?php esc_html_e('Compétitions','ecole2nat'); ?></h1><div class="e2n-competition-list">
        <?php if($rows===[]):?><section class="e2n-card"><p><?php esc_html_e('Aucune compétition publiée.','ecole2nat'); ?></p></section><?php endif; ?>
        <?php foreach($rows as $row):$unanswered=max(0,(int)$row['eligible_count']-(int)$row['yes_count']-(int)$row['no_count']);$target=!empty($row['target_all'])?__('Tous','ecole2nat'):($row['competition_category_names']??'');?><a class="e2n-card e2n-competition-link" href="<?php echo esc_url($this->base(['e2n_view'=>'competitions','e2n_competition'=>(int)$row['id']])); ?>"><div><h2><?php echo esc_html($row['name']); ?></h2><p><?php echo esc_html(implode(' · ',array_filter([$this->competitionDateLabel($row),$target,$row['location']??'',$row['pool_length']??'']))); ?></p></div><span><?php echo esc_html(sprintf(__('%1$d oui · %2$d non · %3$d sans réponse · %4$d à engager','ecole2nat'),(int)$row['yes_count'],(int)$row['no_count'],$unanswered,(int)$row['pending_engagement_count'])); ?></span></a><?php endforeach; ?></div></div><?php
    }

    private function competition(int $competitionId): void
    {
        $notice=$this->competitionNotice;$missing=$this->competitionMissing;
        $data=$this->competitions->coachDetail($competitionId);if($data===null){echo '<p>'.esc_html__('Compétition introuvable.','ecole2nat').'</p>';return;}$data['swimmers']=$this->withCompetitionPerformanceCounts($data['swimmers'],$competitionId);$editable=$data['status']==='published'; ?>
        <a class="e2n-back" href="<?php echo esc_url($this->base(['e2n_view'=>'competitions'])); ?>">← <?php esc_html_e('Compétitions','ecole2nat'); ?></a><div class="e2n-competition-title-row"><h1><?php echo esc_html($data['name']); ?></h1><form method="post"><?php wp_nonce_field('e2n_competition_day_'.$competitionId); ?><?php if(empty($data['started_at'])):?><input type="hidden" name="e2n_competition_action" value="start"><button class="e2n-btn e2n-start-competition" type="submit"><span aria-hidden="true">▶</span> <?php esc_html_e('Démarrer la compétition','ecole2nat'); ?></button><?php elseif(empty($data['closed_at'])):?><input type="hidden" name="e2n_competition_action" value="close"><button class="e2n-btn e2n-start-competition e2n-close-competition" type="submit"><span aria-hidden="true">■</span> <?php esc_html_e('Clôturer la compétition','ecole2nat'); ?></button><?php else:?><input type="hidden" name="e2n_competition_action" value="resume"><button class="e2n-btn e2n-start-competition" type="submit"><span aria-hidden="true">▶</span> <?php esc_html_e('Redémarrer la compétition','ecole2nat'); ?></button><?php endif; ?></form></div><p><?php echo esc_html(implode(' · ',array_filter([$this->competitionDateLabel($data),$data['location']??'',$data['pool_length']??'']))); ?></p>
        <?php if(!empty($data['information'])):?><details class="e2n-competition-briefing"><summary><?php esc_html_e('Briefing','ecole2nat'); ?></summary><div><?php echo nl2br(esc_html($data['information'])); ?></div></details><?php endif; ?>
        <div class="e2n-inline e2n-competition-links">
            <?php if(!empty($data['technical_document_url'])):?><a class="e2n-btn" href="<?php echo esc_url($data['technical_document_url']); ?>" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">↗</span> <?php esc_html_e('Fiche technique','ecole2nat'); ?></a><?php endif; ?>
            <?php if(!empty($data['program_url'])):?><a class="e2n-btn" href="<?php echo esc_url($data['program_url']); ?>" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">↗</span> <?php esc_html_e('Programme','ecole2nat'); ?></a><?php endif; ?>
            <?php if(!empty($data['carpool_url'])):?><a class="e2n-btn" href="<?php echo esc_url($data['carpool_url']); ?>" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">🚗</span> <?php esc_html_e('Covoiturage','ecole2nat'); ?></a><?php endif; ?>
            <?php if(!empty($data['liveffn_url'])):?><a class="e2n-btn" href="<?php echo esc_url($data['liveffn_url']); ?>" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">◉</span> <?php esc_html_e('liveFFN','ecole2nat'); ?></a><?php endif; ?>
            <?php if(!empty($data['photo_album_url'])):?><a class="e2n-btn" href="<?php echo esc_url($data['photo_album_url']); ?>" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">📷</span> <?php esc_html_e('Album photo','ecole2nat'); ?></a><?php endif; ?>
            <a class="e2n-btn <?php echo isset($_GET['e2n_billing']) ? 'is-active' : ''; ?>" href="<?php echo esc_url($this->base(['e2n_view'=>'competitions','e2n_competition'=>$competitionId,'e2n_billing'=>1])); ?>"><span aria-hidden="true">🧾</span> <?php esc_html_e('Facturation','ecole2nat'); ?></a>
        </div>
        <?php if(isset($_GET['e2n_billing'])){$this->competitionBilling($competitionId);return;} ?>
        <?php if(empty($data['started_at'])):?>
            <?php if($notice==='missing'):?><div class="e2n-alert is-error"><strong><?php echo esc_html(sprintf(__('Impossible de démarrer, vous n’avez pas inscrit %s sur Extranat.','ecole2nat'),implode(', ',$missing))); ?></strong><form method="post" class="e2n-inline-form"><?php wp_nonce_field('e2n_competition_day_'.$competitionId); ?><input type="hidden" name="e2n_competition_action" value="force_start"><button class="e2n-link-button" type="submit"><?php esc_html_e('Si vous voulez tout de même démarrer, en connaissance de cause, cliquez ici.','ecole2nat'); ?></button></form></div><?php endif; ?>
        <section class="e2n-card"><div class="e2n-competition-section-head"><h2><?php esc_html_e('Réponses et engagements','ecole2nat'); ?></h2><div class="e2n-competition-sort" role="group" aria-label="<?php esc_attr_e('Trier les nageurs','ecole2nat'); ?>"><button type="button" class="is-active" data-e2n-competition-sort="alpha" title="<?php esc_attr_e('Ordre alphabétique','ecole2nat'); ?>" aria-label="<?php esc_attr_e('Ordre alphabétique','ecole2nat'); ?>" aria-pressed="true">A–Z</button><button type="button" data-e2n-competition-sort="status" title="<?php esc_attr_e('Trier par avancement','ecole2nat'); ?>" aria-label="<?php esc_attr_e('Trier par avancement','ecole2nat'); ?>" aria-pressed="false">☑</button></div></div><div class="e2n-autosave-status" data-e2n-save-status aria-live="polite"></div><div class="e2n-competition-swimmers">
        <?php foreach($data['swimmers'] as $swimmer):$response=$swimmer['response']??'';$engaged=!empty($swimmer['is_engaged']);$stateClass=$response==='yes'?($engaged?'is-complete':'is-pending'):($response==='no'?'is-declined':'is-unanswered');?><article class="e2n-competition-swimmer <?php echo esc_attr($stateClass); ?>" data-e2n-competition-swimmer data-alpha="<?php echo esc_attr(mb_strtolower($swimmer['last_name'].' '.$swimmer['first_name'])); ?>"><div><div class="e2n-competition-swimmer-name"><strong><?php echo esc_html($swimmer['first_name'].' '.$swimmer['last_name']); ?></strong><span class="e2n-competition-swimmer-tools"><?php $this->performanceCountBadge($swimmer); ?><?php $this->extranatLink($swimmer); ?></span></div><small><?php echo esc_html($swimmer['group_name']); ?><?php if(($swimmer['response_source']??'')==='coach'):?> · <?php esc_html_e('saisi par un coach','ecole2nat'); ?><?php endif; ?></small><?php if(!empty($swimmer['comment'])):?><small><?php echo esc_html($swimmer['comment']); ?></small><?php endif; ?><?php if($response!==''):?><small><?php echo esc_html(sprintf(__('Parents officiels : %s','ecole2nat'),!isset($swimmer['parents_official'])?__('Non renseigné','ecole2nat'):((int)$swimmer['parents_official']===1?__('Oui','ecole2nat'):__('Non','ecole2nat')))); ?></small><?php if($response==='yes'&&!empty($data['end_date'])&&$data['end_date']!==$data['start_date']):?><small><?php echo esc_html(sprintf(__('Participation : %s','ecole2nat'),$this->attendanceDaysLabel((string)($swimmer['attendance_days']??''),$data))); ?></small><?php endif; ?><?php endif; ?></div><div class="e2n-choice-group"><label class="e2n-choice"><input type="radio" name="competition[<?php echo (int)$swimmer['id']; ?>]" value="yes" data-e2n-kind="competition-response" data-competition-id="<?php echo (int)$competitionId; ?>" data-swimmer-id="<?php echo (int)$swimmer['id']; ?>" <?php checked($response,'yes'); ?> <?php disabled(!$editable); ?>><span><?php esc_html_e('Oui','ecole2nat'); ?></span></label><label class="e2n-choice"><input type="radio" name="competition[<?php echo (int)$swimmer['id']; ?>]" value="no" data-e2n-kind="competition-response" data-competition-id="<?php echo (int)$competitionId; ?>" data-swimmer-id="<?php echo (int)$swimmer['id']; ?>" <?php checked($response,'no'); ?> <?php disabled(!$editable); ?>><span><?php esc_html_e('Non','ecole2nat'); ?></span></label></div><label class="e2n-engaged"><input type="checkbox" data-e2n-kind="competition-engaged" data-competition-id="<?php echo (int)$competitionId; ?>" data-swimmer-id="<?php echo (int)$swimmer['id']; ?>" <?php checked((int)($swimmer['is_engaged']??0),1); ?> <?php disabled(!$editable||$response!=='yes'); ?>> <?php esc_html_e('Engagement Extranat','ecole2nat'); ?><?php if(!empty($swimmer['engaged_at'])):?><small><?php echo esc_html(wp_date('d/m/Y',strtotime($swimmer['engaged_at'])).' · '.($swimmer['engaged_by_name']?:__('Coach','ecole2nat'))); ?></small><?php endif; ?></label></article><?php endforeach; ?>
        </div></section>
        <?php else:$this->competitionDay($data,$notice);endif;
    }

    private function handleCompetitionAction(int $competitionId): void
    {
        if($_SERVER['REQUEST_METHOD']!=='POST'||!isset($_POST['e2n_competition_action']))return;
        check_admin_referer('e2n_competition_day_'.$competitionId);$action=sanitize_key(wp_unslash((string)$_POST['e2n_competition_action']));$userId=get_current_user_id();
        if($action==='start'||$action==='force_start'){$result=$this->competitions->start($competitionId,$userId,$action==='force_start');if(empty($result['success'])&&($result['message']??'')==='missing'){$this->competitionMissing=$result['names'];$this->competitionNotice='missing';}}
        elseif($action==='stop'&&current_user_can('manage_options'))$this->competitions->stop($competitionId);
        elseif($action==='close')$this->competitions->close($competitionId,$userId);
        elseif($action==='resume')$this->competitions->resume($competitionId);
        elseif($action==='add_participant')$this->competitions->addParticipant($competitionId,absint($_POST['swimmer_id']??0),$userId);
        elseif($action==='save_performance')$this->competitionNotice=$this->competitions->savePerformance($competitionId,absint($_POST['swimmer_id']??0),absint($_POST['performance_id']??0),wp_unslash($_POST),$userId)?'performance_saved':'performance_error';
        elseif($action==='delete_performance')$this->competitionNotice=$this->competitions->deletePerformance($competitionId,absint($_POST['swimmer_id']??0),absint($_POST['performance_id']??0))?'performance_deleted':'performance_error';
        elseif($action==='billing_save'||$action==='billing_generate'){
            $result=$this->billing->save($competitionId,wp_unslash($_POST),$action==='billing_generate',$userId);
            $this->competitionNotice=empty($result['success'])?(($result['message']??'')==='empty_generated_invoice'?'billing_zero_error':'billing_error'):($action==='billing_generate'?((int)($result['generated']??0)>0?'billing_generated':'billing_empty'):'billing_saved');
        }
    }

    private function competitionBilling(int $competitionId): void
    {
        $data=$this->billing->detail($competitionId);if($data===null){?><div class="e2n-alert is-error"><?php esc_html_e('La facturation de cette compétition est indisponible.','ecole2nat'); ?></div><?php return;}
        $invoiceId=absint($_GET['e2n_invoice']??0);if($invoiceId>0){$invoice=$this->billing->invoice($competitionId,$invoiceId);if($invoice===null){?><div class="e2n-alert is-error"><?php esc_html_e('Facture introuvable.','ecole2nat'); ?></div><?php return;}$this->renderCoachInvoice($competitionId,$invoice);return;}
        $rows=$data['rows'];$mealPrice=(float)$data['meal_price'];$nightPrice=(float)$data['night_price']; ?>
        <a class="e2n-back" href="<?php echo esc_url($this->base(['e2n_view'=>'competitions','e2n_competition'=>$competitionId])); ?>">← <?php esc_html_e('Retour à la compétition','ecole2nat'); ?></a>
        <?php if($this->competitionNotice==='billing_saved'):?><div class="e2n-alert is-success"><?php esc_html_e('Les frais ont été enregistrés.','ecole2nat'); ?></div><?php elseif($this->competitionNotice==='billing_generated'):?><div class="e2n-alert is-success"><?php esc_html_e('Les factures nouvelles ou modifiées ont été générées.','ecole2nat'); ?></div><?php elseif($this->competitionNotice==='billing_empty'):?><div class="e2n-alert"><?php esc_html_e('Les frais ont été enregistrés, mais aucune facture nouvelle ou modifiée n’était à générer.','ecole2nat'); ?></div><?php elseif($this->competitionNotice==='billing_zero_error'):?><div class="e2n-alert is-error"><?php esc_html_e('Une facture déjà émise ne peut pas être régénérée avec un montant nul. Rétablissez des frais avant de régénérer.','ecole2nat'); ?></div><?php elseif($this->competitionNotice==='billing_error'):?><div class="e2n-alert is-error"><?php esc_html_e('La facturation n’a pas été enregistrée. Rechargez la page et vérifiez les quantités.','ecole2nat'); ?></div><?php endif; ?>
        <section class="e2n-card e2n-billing-card"><div class="e2n-competition-section-head"><div><h2><?php esc_html_e('Facturation','ecole2nat'); ?></h2><p><?php echo esc_html(sprintf(__('Repas : %1$s € · Nuitée : %2$s €','ecole2nat'),number_format_i18n($mealPrice,2),number_format_i18n($nightPrice,2))); ?></p></div></div>
        <?php if($rows===[]):?><p class="e2n-info"><?php esc_html_e('Aucun nageur engagé sur Extranat ne peut être facturé pour le moment.','ecole2nat'); ?></p><?php else:?><form method="post" data-e2n-billing-form><?php wp_nonce_field('e2n_competition_day_'.$competitionId); ?>
            <label class="e2n-billing-global"><span><?php esc_html_e('Commentaire global','ecole2nat'); ?></span><textarea name="global_comment" rows="3" placeholder="<?php esc_attr_e('Ce commentaire apparaîtra sur toutes les factures de la compétition.','ecole2nat'); ?>"><?php echo esc_textarea((string)$data['global_comment']); ?></textarea></label>
            <div class="e2n-billing-list"><?php foreach($rows as $row):$swimmerId=(int)$row['swimmer_id'];$mealQty=(int)($row['meal_quantity']??0);$nightQty=(int)($row['night_quantity']??0);$otherAmount=(float)($row['other_amount']??0);$total=$mealQty*$mealPrice+$nightQty*$nightPrice+$otherAmount;$locked=($row['invoice_status']??'')==='payment_declared';?><article class="e2n-billing-row" data-e2n-billing-row data-meal-price="<?php echo esc_attr((string)$mealPrice); ?>" data-night-price="<?php echo esc_attr((string)$nightPrice); ?>">
                <?php if($locked):?><input type="hidden" name="billing[<?php echo $swimmerId; ?>][meal_quantity]" value="<?php echo $mealQty; ?>"><input type="hidden" name="billing[<?php echo $swimmerId; ?>][night_quantity]" value="<?php echo $nightQty; ?>"><input type="hidden" name="billing[<?php echo $swimmerId; ?>][other_amount]" value="<?php echo esc_attr(number_format($otherAmount,2,'.','')); ?>"><input type="hidden" name="billing[<?php echo $swimmerId; ?>][individual_comment]" value="<?php echo esc_attr((string)($row['individual_comment']??'')); ?>"><?php endif; ?>
                <div class="e2n-billing-person"><strong><?php echo esc_html($row['first_name'].' '.$row['last_name']); ?></strong><small><?php echo esc_html((string)($row['group_name']??'')); ?></small><?php if(!empty($row['invoice_number'])):?><a href="<?php echo esc_url($this->base(['e2n_view'=>'competitions','e2n_competition'=>$competitionId,'e2n_billing'=>1,'e2n_invoice'=>(int)$row['invoice_id']])); ?>"><?php echo esc_html(sprintf(__('Voir la facture %1$s · version %2$d','ecole2nat'),$row['invoice_number'],(int)$row['current_version'])); ?></a><?php endif; ?></div>
                <div class="e2n-billing-quantities"><div><span><?php esc_html_e('Repas','ecole2nat'); ?></span><div class="e2n-quantity"><button type="button" data-e2n-quantity-change="-1" <?php disabled($locked); ?> aria-label="<?php esc_attr_e('Retirer un repas','ecole2nat'); ?>">−</button><input type="number" min="0" max="99" name="billing[<?php echo $swimmerId; ?>][meal_quantity]" value="<?php echo $mealQty; ?>" data-e2n-meal-quantity <?php disabled($locked); ?>><button type="button" data-e2n-quantity-change="1" <?php disabled($locked); ?> aria-label="<?php esc_attr_e('Ajouter un repas','ecole2nat'); ?>">+</button></div></div><div><span><?php esc_html_e('Nuitées','ecole2nat'); ?></span><div class="e2n-quantity"><button type="button" data-e2n-quantity-change="-1" <?php disabled($locked); ?> aria-label="<?php esc_attr_e('Retirer une nuitée','ecole2nat'); ?>">−</button><input type="number" min="0" max="99" name="billing[<?php echo $swimmerId; ?>][night_quantity]" value="<?php echo $nightQty; ?>" data-e2n-night-quantity <?php disabled($locked); ?>><button type="button" data-e2n-quantity-change="1" <?php disabled($locked); ?> aria-label="<?php esc_attr_e('Ajouter une nuitée','ecole2nat'); ?>">+</button></div></div><div class="e2n-billing-other"><span><?php esc_html_e('Montant libre','ecole2nat'); ?></span><label><input type="number" min="0" max="99999999.99" step="0.01" name="billing[<?php echo $swimmerId; ?>][other_amount]" value="<?php echo esc_attr(number_format($otherAmount,2,'.','')); ?>" data-e2n-other-amount <?php disabled($locked); ?>><span>€</span></label></div></div>
                <label class="e2n-billing-comment"><span><?php esc_html_e('Commentaire individuel','ecole2nat'); ?></span><textarea name="billing[<?php echo $swimmerId; ?>][individual_comment]" rows="2" <?php disabled($locked); ?>><?php echo esc_textarea((string)($row['individual_comment']??'')); ?></textarea></label>
                <strong class="e2n-billing-total" data-e2n-billing-total><?php echo esc_html(number_format_i18n($total,2).' €'); ?></strong>
                <?php if($locked):?><span class="e2n-billing-locked"><?php esc_html_e('Paiement déclaré · facture verrouillée','ecole2nat'); ?></span><?php endif; ?>
            </article><?php endforeach; ?></div>
            <div class="e2n-billing-actions"><button class="e2n-btn e2n-billing-save" type="submit" name="e2n_competition_action" value="billing_save"><?php esc_html_e('Enregistrer les frais','ecole2nat'); ?></button><button class="e2n-btn" type="submit" name="e2n_competition_action" value="billing_generate" onclick="return confirm('<?php echo esc_js(__('Générer ou régénérer toutes les factures ayant un montant positif ?','ecole2nat')); ?>');"><span aria-hidden="true">🧾</span> <?php esc_html_e('Générer les factures','ecole2nat'); ?></button></div>
        </form><?php endif; ?></section><?php
    }

    private function renderCoachInvoice(int $competitionId,array $invoice): void
    {
        $mealTotal=(int)$invoice['meal_quantity']*(float)$invoice['meal_unit_price'];$nightTotal=(int)$invoice['night_quantity']*(float)$invoice['night_unit_price'];$otherAmount=(float)($invoice['other_amount']??0); ?>
        <div class="e2n-invoice-toolbar"><a class="e2n-back" href="<?php echo esc_url($this->base(['e2n_view'=>'competitions','e2n_competition'=>$competitionId,'e2n_billing'=>1])); ?>">← <?php esc_html_e('Retour à la facturation','ecole2nat'); ?></a><button type="button" class="e2n-btn" onclick="window.print()"><span aria-hidden="true">🖨</span> <?php esc_html_e('Imprimer ou enregistrer en PDF','ecole2nat'); ?></button></div>
        <article class="e2n-web-invoice">
            <header><div><?php if((int)$invoice['issuer_logo_id']>0):?><?php echo wp_get_attachment_image((int)$invoice['issuer_logo_id'],'medium',false,['class'=>'e2n-invoice-logo']); ?><?php endif; ?><strong><?php echo esc_html($invoice['issuer_name']); ?></strong><p><?php echo nl2br(esc_html($invoice['issuer_address'])); ?></p><?php if(!empty($invoice['issuer_siret'])):?><p><?php echo esc_html(sprintf(__('SIRET : %s','ecole2nat'),$invoice['issuer_siret'])); ?></p><?php endif; ?></div><div class="e2n-invoice-recipient"><span><?php esc_html_e('Facturé à','ecole2nat'); ?></span><strong><?php echo esc_html($invoice['swimmer_name']); ?></strong></div></header>
            <div class="e2n-invoice-meta"><h2><?php esc_html_e('FACTURE','ecole2nat'); ?></h2><dl><div><dt><?php esc_html_e('N° facture','ecole2nat'); ?></dt><dd><?php echo esc_html($invoice['invoice_number']); ?></dd></div><div><dt><?php esc_html_e('Date','ecole2nat'); ?></dt><dd><?php echo esc_html(wp_date('d/m/Y',strtotime($invoice['issued_on']))); ?></dd></div></dl></div>
            <table><thead><tr><th><?php esc_html_e('Désignation','ecole2nat'); ?></th><th><?php esc_html_e('Quantité','ecole2nat'); ?></th><th><?php esc_html_e('Prix unitaire net','ecole2nat'); ?></th><th><?php esc_html_e('Montant total net','ecole2nat'); ?></th></tr></thead><tbody><?php $serviceLabel=$invoice['competition_name'].' · '.wp_date('d/m/Y',strtotime($invoice['competition_start_date']));if((int)$invoice['meal_quantity']>0):?><tr><td><?php echo esc_html(sprintf(__('Repas · %s','ecole2nat'),$serviceLabel)); ?></td><td><?php echo (int)$invoice['meal_quantity']; ?></td><td><?php echo esc_html(number_format_i18n((float)$invoice['meal_unit_price'],2).' €'); ?></td><td><?php echo esc_html(number_format_i18n($mealTotal,2).' €'); ?></td></tr><?php endif; ?><?php if((int)$invoice['night_quantity']>0):?><tr><td><?php echo esc_html(sprintf(__('Nuitée · %s','ecole2nat'),$serviceLabel)); ?></td><td><?php echo (int)$invoice['night_quantity']; ?></td><td><?php echo esc_html(number_format_i18n((float)$invoice['night_unit_price'],2).' €'); ?></td><td><?php echo esc_html(number_format_i18n($nightTotal,2).' €'); ?></td></tr><?php endif; ?><?php if($otherAmount>0):?><tr><td><?php esc_html_e('Autre','ecole2nat'); ?></td><td>1</td><td><?php echo esc_html(number_format_i18n($otherAmount,2).' €'); ?></td><td><?php echo esc_html(number_format_i18n($otherAmount,2).' €'); ?></td></tr><?php endif; ?></tbody><tfoot><tr><th colspan="3"><?php esc_html_e('NET À PAYER','ecole2nat'); ?></th><td><?php echo esc_html(number_format_i18n((float)$invoice['total_amount'],2).' €'); ?></td></tr></tfoot></table>
            <?php if(!empty($invoice['global_comment'])||!empty($invoice['individual_comment'])):?><section class="e2n-invoice-comments"><h3><?php esc_html_e('Informations','ecole2nat'); ?></h3><?php if(!empty($invoice['global_comment'])):?><p><?php echo nl2br(esc_html($invoice['global_comment'])); ?></p><?php endif; ?><?php if(!empty($invoice['individual_comment'])):?><p><?php echo nl2br(esc_html($invoice['individual_comment'])); ?></p><?php endif; ?></section><?php endif; ?>
            <footer><p><?php esc_html_e('À régler dès réception. Paiement par virement privilégié. Le RIB du club sera disponible depuis l’espace du nageur.','ecole2nat'); ?></p></footer>
        </article><?php
    }

    private function competitionDay(array $competition,string $notice): void
    {
        $competitionId=(int)$competition['id'];$selectedId=$notice==='performance_saved'?0:absint($_GET['e2n_competitor']??0);$participants=$this->withCompetitionPerformanceCounts($this->competitions->participants($competitionId),$competitionId);$selected=null;foreach($participants as $participant)if((int)$participant['id']===$selectedId)$selected=$participant;
        if($notice==='performance_saved'):?><div class="e2n-alert is-success"><?php esc_html_e('Performance enregistrée.','ecole2nat'); ?></div><?php elseif($notice==='performance_deleted'):?><div class="e2n-alert is-success"><?php esc_html_e('Épreuve supprimée.','ecole2nat'); ?></div><?php elseif($notice==='performance_error'):?><div class="e2n-alert is-error"><?php esc_html_e('Performance non enregistrée. Vérifiez les champs.','ecole2nat'); ?></div><?php endif;
        if($selected!==null){$this->competitionSwimmer($competition,$selected);return;} ?>
        <section class="e2n-card"><div class="e2n-competition-section-head"><h2><?php esc_html_e('Nageurs engagés','ecole2nat'); ?></h2><?php if(current_user_can('manage_options')):?><form method="post" onsubmit="return confirm('<?php echo esc_js(__('Revenir au suivi des inscriptions ? Les performances déjà saisies seront conservées.','ecole2nat')); ?>');"><?php wp_nonce_field('e2n_competition_day_'.$competitionId); ?><input type="hidden" name="e2n_competition_action" value="stop"><button class="e2n-link-button" type="submit"><?php esc_html_e('Revenir aux inscriptions','ecole2nat'); ?></button></form><?php endif; ?></div>
        <div class="e2n-swimmers"><?php foreach($participants as $participant):?><a href="<?php echo esc_url($this->base(['e2n_view'=>'competitions','e2n_competition'=>$competitionId,'e2n_competitor'=>(int)$participant['id']])); ?>"><strong><?php echo esc_html($participant['first_name'].' '.$participant['last_name']); ?></strong><span class="e2n-swimmer-card-meta"><span><?php echo esc_html($participant['group_name']??''); ?></span><?php $this->performanceCountBadge($participant); ?></span></a><?php endforeach; ?></div>
        <?php $available=$this->competitions->availableParticipants($competitionId);if($available!==[]):?><details class="e2n-add-competitor"><summary><?php esc_html_e('Ajouter un nageur non inscrit','ecole2nat'); ?></summary><form method="post" class="e2n-inline"><?php wp_nonce_field('e2n_competition_day_'.$competitionId); ?><input type="hidden" name="e2n_competition_action" value="add_participant"><select name="swimmer_id" required><option value=""><?php esc_html_e('Choisir un nageur…','ecole2nat'); ?></option><?php foreach($available as $swimmer):?><option value="<?php echo (int)$swimmer['id']; ?>"><?php echo esc_html($swimmer['first_name'].' '.$swimmer['last_name'].' · '.($swimmer['group_name']??'')); ?></option><?php endforeach; ?></select><button class="e2n-btn" type="submit"><?php esc_html_e('Ajouter','ecole2nat'); ?></button></form></details><?php endif; ?></section>
        <?php $this->raceTimer('competition',$competitionId,$participants); ?><?php
    }

    private function raceTimer(string $contextType,int $contextId,array $participants,bool $collapsed=false,bool $filterCategories=false): void
    {
        if($participants===[])return;$groups=EventCatalog::groups();if($collapsed):?><details class="e2n-race-launcher" data-e2n-race-launcher><summary><span aria-hidden="true">⏱</span> <?php esc_html_e('Chronométrer une série','ecole2nat'); ?></summary><?php endif; ?>
        <section class="e2n-card e2n-race-timer" data-e2n-race-timer data-context-type="<?php echo esc_attr($contextType); ?>" data-context-id="<?php echo $contextId; ?>">
            <div class="e2n-race-heading"><div><h2><?php echo esc_html($collapsed ? __('Préparer la série','ecole2nat') : __('Chronométrer une série','ecole2nat')); ?></h2><p><?php esc_html_e('Une épreuve, un départ commun, un arrêt individuel par nageur.','ecole2nat'); ?></p></div><button type="button" class="e2n-race-view-toggle" data-e2n-race-view-toggle data-compact-label="<?php esc_attr_e('Mode réduit','ecole2nat'); ?>" data-detailed-label="<?php esc_attr_e('Mode complet','ecole2nat'); ?>" aria-pressed="false"><span data-e2n-race-view-icon aria-hidden="true">▤</span> <span data-e2n-race-view-label><?php esc_html_e('Mode réduit','ecole2nat'); ?></span></button></div>
            <label class="e2n-race-event"><span><?php esc_html_e('Épreuve','ecole2nat'); ?></span><select data-e2n-race-event><option value=""><?php esc_html_e('Choisir une épreuve…','ecole2nat'); ?></option><?php foreach($groups as $label=>$events):?><optgroup label="<?php echo esc_attr($label); ?>"><?php foreach($events as $event):?><option value="<?php echo esc_attr($event); ?>"><?php echo esc_html($event); ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label>
            <fieldset class="e2n-race-participants"><legend><?php esc_html_e('Participants','ecole2nat'); ?></legend><?php foreach($participants as $participant):$id=(int)$participant['id'];$categoryId=$filterCategories?(int)($participant['category_id']??0):0;?><label data-e2n-race-participant data-category-id="<?php echo $categoryId; ?>"><input type="checkbox" value="<?php echo $id; ?>" data-e2n-race-select><span><?php echo esc_html($participant['first_name'].' '.$participant['last_name']); ?><small><?php echo esc_html($participant['group_name']??''); ?></small></span></label><?php endforeach; ?></fieldset>
            <div class="e2n-race-actions"><button type="button" class="e2n-btn e2n-race-play" data-e2n-race-play disabled>▶ <?php esc_html_e('Départ','ecole2nat'); ?></button><button type="button" class="e2n-link-button" data-e2n-race-reset hidden><?php esc_html_e('Nouvelle série','ecole2nat'); ?></button><button type="button" class="e2n-race-delete-series" data-e2n-race-delete-series hidden><?php esc_html_e('Supprimer la série','ecole2nat'); ?></button><span data-e2n-race-message role="status"></span></div>
            <div class="e2n-race-cards"><?php foreach($participants as $participant):$id=(int)$participant['id'];$participantGroupId=$filterCategories?(int)($participant['group_id']??0):$contextId;$categoryId=$filterCategories?(int)($participant['category_id']??0):0;?><article class="e2n-race-card" data-e2n-race-card data-swimmer-id="<?php echo $id; ?>" data-group-id="<?php echo $participantGroupId; ?>" data-category-id="<?php echo $categoryId; ?>" data-performance-id="0" hidden><div class="e2n-race-card-head"><div><h3><?php echo esc_html($participant['first_name'].' '.$participant['last_name']); ?></h3></div><button type="button" data-e2n-race-stop disabled>■ <?php esc_html_e('Stop','ecole2nat'); ?></button></div><label class="e2n-race-time-field"><?php esc_html_e('Chrono','ecole2nat'); ?><input type="text" data-e2n-race-time placeholder="1:23.45"></label><fieldset><legend><?php esc_html_e('Évaluation du temps','ecole2nat'); ?></legend><div class="e2n-stars"><?php for($star=1;$star<=5;$star++):?><label><input type="radio" name="race_rating_<?php echo esc_attr($contextType.'_'.$contextId.'_'.$id); ?>" value="<?php echo $star; ?>"><span><?php echo str_repeat('★',$star); ?></span></label><?php endfor; ?></div></fieldset><label class="e2n-race-comment-field"><?php esc_html_e('Commentaire','ecole2nat'); ?><textarea rows="2" data-e2n-race-comment></textarea></label><label class="e2n-check"><input type="checkbox" data-e2n-race-dq> <?php echo esc_html(($participant['gender']??'')==='F'?__('Disqualifiée','ecole2nat'):__('Disqualifié','ecole2nat')); ?></label><div class="e2n-race-card-foot"><span class="e2n-race-card-status" data-e2n-race-card-status role="status"></span><button type="button" class="e2n-race-delete-time" data-e2n-race-delete-time hidden><?php esc_html_e('Supprimer ce chrono','ecole2nat'); ?></button></div></article><?php endforeach; ?></div>
        </section><?php if($collapsed):?></details><?php endif;
    }

    private function competitionSwimmer(array $competition,array $swimmer): void
    {
        $competitionId=(int)$competition['id'];$swimmerId=(int)$swimmer['id'];$performanceId=absint($_GET['e2n_performance']??0);$performances=$this->competitions->performances($competitionId,$swimmerId);$editing=null;foreach($performances as $performance)if((int)$performance['id']===$performanceId)$editing=$performance; ?>
        <a class="e2n-back" href="<?php echo esc_url($this->base(['e2n_view'=>'competitions','e2n_competition'=>$competitionId])); ?>">← <?php esc_html_e('Participants','ecole2nat'); ?></a><section class="e2n-card"><div class="e2n-competition-swimmer-heading"><h2><?php echo esc_html($swimmer['first_name'].' '.$swimmer['last_name']); ?></h2><?php $this->extranatLink($swimmer); ?></div>
        <?php if($performances!==[]):?><div class="e2n-performance-list"><?php foreach($performances as $performance):$rating=(int)($performance['time_rating']??0);?><a class="<?php echo $rating>=1&&$rating<=5?'is-rating-'.$rating:'is-unrated'; ?>" href="<?php echo esc_url($this->base(['e2n_view'=>'competitions','e2n_competition'=>$competitionId,'e2n_competitor'=>$swimmerId,'e2n_performance'=>(int)$performance['id']])); ?>"><strong><?php echo esc_html($performance['event_code']); ?></strong><span><?php echo esc_html($performance['elapsed_time']?:__('Chrono non renseigné','ecole2nat')); ?><?php if($rating>0):?> · <?php echo esc_html(str_repeat('★',$rating)); ?><?php endif; ?><?php if(!empty($performance['is_disqualified'])):?> · <?php esc_html_e('Disqualification','ecole2nat'); ?><?php endif; ?></span></a><?php endforeach; ?></div><?php endif; ?>
        <form method="post" class="e2n-performance-form" data-e2n-performance-form data-performance-id="<?php echo (int)($editing['id']??0); ?>"><?php wp_nonce_field('e2n_competition_day_'.$competitionId); ?><input type="hidden" name="e2n_competition_action" value="save_performance"><input type="hidden" name="swimmer_id" value="<?php echo $swimmerId; ?>"><input type="hidden" name="performance_id" value="<?php echo (int)($editing['id']??0); ?>"><input type="hidden" name="event_code" value="<?php echo esc_attr($editing['event_code']??''); ?>" data-e2n-event-value>
        <?php $eventGroups=EventCatalog::groups(); ?><div class="e2n-event-grid" data-e2n-event-grid><?php foreach($eventGroups as $stroke=>$events):?><div class="e2n-event-row"><strong><?php echo esc_html($stroke); ?></strong><div><?php foreach($events as $event):?><button type="button" data-e2n-event="<?php echo esc_attr($event); ?>" class="<?php echo ($editing['event_code']??'')===$event?'is-selected':''; ?>"><?php echo esc_html($event); ?></button><?php endforeach; ?></div></div><?php endforeach; ?></div>
        <div class="e2n-performance-fields" data-e2n-performance-fields <?php echo $editing===null?'hidden':''; ?>><label><?php esc_html_e('Chrono','ecole2nat'); ?><input name="elapsed_time" value="<?php echo esc_attr($editing['elapsed_time']??''); ?>" placeholder="1:23.45"></label><fieldset><legend><?php esc_html_e('Évaluation du temps','ecole2nat'); ?></legend><div class="e2n-stars"><?php for($star=1;$star<=5;$star++):?><label><input type="radio" name="time_rating" value="<?php echo $star; ?>" <?php checked((int)($editing['time_rating']??0),$star); ?>><span><?php echo str_repeat('★',$star); ?></span></label><?php endfor; ?></div></fieldset><label><?php esc_html_e('Commentaire','ecole2nat'); ?><textarea name="comment" rows="3"><?php echo esc_textarea($editing['comment']??''); ?></textarea></label><label class="e2n-check"><input type="checkbox" name="is_disqualified" value="1" <?php checked((int)($editing['is_disqualified']??0),1); ?>> <?php echo esc_html(($swimmer['gender']??'')==='F'?__('Disqualifiée','ecole2nat'):__('Disqualifié','ecole2nat')); ?></label><div class="e2n-inline e2n-performance-actions"><button class="e2n-btn" type="submit"><?php esc_html_e('Enregistrer','ecole2nat'); ?></button><button class="e2n-link-button" type="button" data-e2n-event-cancel><?php esc_html_e('Annuler','ecole2nat'); ?></button><?php if($editing!==null):?><button class="e2n-delete-performance" type="submit" name="e2n_competition_action" value="delete_performance" onclick="return confirm('<?php echo esc_js(__('Supprimer définitivement cette épreuve ?','ecole2nat')); ?>');"><?php esc_html_e('Supprimer','ecole2nat'); ?></button><?php endif; ?></div></div></form></section><?php
    }

    private function competitionDateLabel(array $competition): string
    { $start=wp_date('d/m/Y',strtotime($competition['start_date']));return empty($competition['end_date'])||$competition['end_date']===$competition['start_date']?$start:sprintf(__('Du %1$s au %2$s','ecole2nat'),$start,wp_date('d/m/Y',strtotime($competition['end_date']))); }

    private function attendanceDaysLabel(string $value,array $competition): string
    { return match($value){'both'=>__('Les 2 jours','ecole2nat'),'first_day'=>sprintf(__('Seulement le %s','ecole2nat'),wp_date('d/m/Y',strtotime($competition['start_date']))),'second_day'=>sprintf(__('Seulement le %s','ecole2nat'),wp_date('d/m/Y',strtotime($competition['end_date']))),default=>__('Non renseigné','ecole2nat')}; }

    private function collective(int $groupId, int $skillId, string $from): void
    {
        $data = $this->eval->collectiveEvaluation($groupId, $skillId);
        if ($data === null) { echo '<p>' . esc_html__('Compétence introuvable pour ce groupe.', 'ecole2nat') . '</p>'; return; } ?>
        <a class="e2n-back" href="<?php echo esc_url($this->base(['e2n_group' => $groupId, 'e2n_from' => $from])); ?>">← <?php esc_html_e('Groupe', 'ecole2nat'); ?></a><h1><?php echo esc_html($data['skill']['name']); ?></h1><p><?php echo esc_html($data['group']['name']); ?></p><section class="e2n-card"><h2><?php esc_html_e('Évaluation collective', 'ecole2nat'); ?></h2><div class="e2n-autosave-status" data-e2n-save-status aria-live="polite"></div><div class="e2n-collective-list">
        <?php foreach ($data['swimmers'] as $swimmer) : ?><div class="e2n-collective-row"><strong><?php echo esc_html($swimmer['first_name'] . ' ' . $swimmer['last_name']); ?></strong><div class="e2n-choice-group" role="radiogroup"><?php foreach ($this->eval->statuses() as $value => $label) : ?><label class="e2n-choice e2n-choice--<?php echo esc_attr($value); ?>"><input type="radio" value="<?php echo esc_attr($value); ?>" data-e2n-kind="evaluation" data-group-id="<?php echo (int) $groupId; ?>" data-swimmer-id="<?php echo (int) $swimmer['id']; ?>" data-skill-id="<?php echo (int) $skillId; ?>" <?php checked($swimmer['status'], $value); ?>><span><?php echo esc_html($label); ?></span></label><?php endforeach; ?></div><details class="e2n-note-editor e2n-collective-note" <?php echo $swimmer['notes'] !== '' ? 'open' : ''; ?>><summary><?php echo esc_html($swimmer['notes'] !== '' ? __('Commentaire renseigné', 'ecole2nat') : __('Ajouter un commentaire', 'ecole2nat')); ?></summary><textarea rows="2" data-e2n-kind="note" data-group-id="<?php echo (int) $groupId; ?>" data-swimmer-id="<?php echo (int) $swimmer['id']; ?>" data-skill-id="<?php echo (int) $skillId; ?>" placeholder="<?php esc_attr_e('Commentaire interne', 'ecole2nat'); ?>"><?php echo esc_textarea($swimmer['notes']); ?></textarea></details></div><?php endforeach; ?>
        </div></section><?php
    }

    private function history(array $history): void
    {
        if ($history === []) return; ?>
        <details class="e2n-skill-history"><summary aria-label="<?php esc_attr_e('Afficher l’historique', 'ecole2nat'); ?>">◷ <?php esc_html_e('Historique', 'ecole2nat'); ?></summary><ul><?php foreach ($history as $event) : ?><li><time><?php echo esc_html(wp_date('d/m/Y', strtotime((string) $event['changed_at']))); ?></time> · <?php echo esc_html($this->statusLabel((string) $event['status'])); ?> · <?php echo esc_html((string) ($event['evaluator_name'] ?: __('Coach', 'ecole2nat'))); ?></li><?php endforeach; ?></ul></details><?php
    }

    private function performanceHistory(array $history, int $groupId, int $swimmerId): void
    {
        if ($history === []) return;
        $byEvent = [];
        foreach ($history as $performance) {
            $event = strtoupper((string) ($performance['event_code'] ?? ''));
            if (EventCatalog::contains($event)) $byEvent[$event][] = $performance;
        }
        if ($byEvent === []) return; ?>
        <section class="e2n-card e2n-performance-history" data-e2n-performance-report><header class="e2n-performance-history-head"><div><strong><?php esc_html_e('Rapport des chronos', 'ecole2nat'); ?></strong><small><?php echo esc_html(sprintf(_n('%d chrono', '%d chronos', count($history), 'ecole2nat'), count($history))); ?></small></div><button type="button" data-e2n-toggle-all-charts data-show-label="<?php esc_attr_e('Afficher tous les graphiques', 'ecole2nat'); ?>" data-hide-label="<?php esc_attr_e('Masquer tous les graphiques', 'ecole2nat'); ?>" aria-expanded="false"><?php esc_html_e('Afficher tous les graphiques', 'ecole2nat'); ?></button></header><div class="e2n-performance-report"><?php foreach (EventCatalog::all() as $event) : if (empty($byEvent[$event])) continue; $performances = $byEvent[$event]; usort($performances, static fn(array $left, array $right): int => strcmp((string) $left['performed_at'], (string) $right['performed_at'])); $best = $this->bestPerformance($performances); ?><section class="e2n-performance-event"><header><h3><?php echo esc_html($this->eventLabel($event)); ?></h3><div class="e2n-performance-event-summary"><?php if ($best !== null) : ?><span><?php echo esc_html(sprintf(__('Meilleur : %1$s · %2$s', 'ecole2nat'), (string) $best['elapsed_time'], wp_date('d/m/Y', strtotime((string) $best['performed_at'])))); ?></span><?php endif; ?><button type="button" data-e2n-toggle-chart data-show-label="<?php esc_attr_e('Afficher le graphique', 'ecole2nat'); ?>" data-hide-label="<?php esc_attr_e('Masquer le graphique', 'ecole2nat'); ?>" aria-expanded="false"><?php esc_html_e('Afficher le graphique', 'ecole2nat'); ?></button></div></header><div data-e2n-event-chart hidden><?php $this->performanceChart($event, $performances); ?></div><details class="e2n-performance-details"><summary><?php esc_html_e('Voir le détail des temps', 'ecole2nat'); ?></summary><div class="e2n-performance-history-list"><?php foreach (array_reverse($performances) as $performance) : $this->performanceHistoryRow($performance, $groupId, $swimmerId); endforeach; ?></div></details></section><?php endforeach; ?></div></section><?php
    }

    private function bestPerformance(array $performances): ?array
    {
        $best = null;
        $bestTime = null;
        foreach ($performances as $performance) {
            if (!empty($performance['is_disqualified'])) continue;
            $time = $this->elapsedCentiseconds((string) ($performance['elapsed_time'] ?? ''));
            if ($time !== null && ($bestTime === null || $time < $bestTime)) { $best = $performance; $bestTime = $time; }
        }
        return $best;
    }

    private function performanceChart(string $event, array $performances): void
    {
        $points = [];
        foreach ($performances as $performance) {
            $centiseconds = $this->elapsedCentiseconds((string) ($performance['elapsed_time'] ?? ''));
            $timestamp = strtotime(substr((string) ($performance['performed_at'] ?? ''), 0, 10) . ' 00:00:00');
            if ($centiseconds !== null && $timestamp !== false) $points[] = ['time' => $centiseconds, 'date' => $timestamp, 'row' => $performance];
        }
        if ($points === []) return;
        $width=720;$height=230;$left=62;$right=18;$top=18;$bottom=42;$plotWidth=$width-$left-$right;$plotHeight=$height-$top-$bottom;
        $dates=array_column($points,'date');$times=array_column($points,'time');$minDate=min($dates);$maxDate=max($dates);$minTime=min($times);$maxTime=max($times);$low=$minTime;$high=$maxTime;
        $coordinates=[];foreach($points as $point){$x=$left+($maxDate===$minDate?$plotWidth/2:(($point['date']-$minDate)/($maxDate-$minDate))*$plotWidth);$y=$top+($high===$low?$plotHeight/2:(($high-$point['time'])/($high-$low))*$plotHeight);$coordinates[]=round($x,1).','.round($y,1);} ?>
        <div class="e2n-performance-chart" data-e2n-performance-chart><output class="e2n-chart-tooltip" data-e2n-chart-tooltip aria-live="polite" hidden></output><svg viewBox="0 0 <?php echo $width.' '.$height; ?>" role="img" aria-label="<?php echo esc_attr(sprintf(__('Progression chronométrique en %s', 'ecole2nat'), $this->eventLabel($event))); ?>"><line class="e2n-chart-axis" x1="<?php echo $left; ?>" y1="<?php echo $top; ?>" x2="<?php echo $left; ?>" y2="<?php echo $height-$bottom; ?>"/><line class="e2n-chart-axis" x1="<?php echo $left; ?>" y1="<?php echo $height-$bottom; ?>" x2="<?php echo $width-$right; ?>" y2="<?php echo $height-$bottom; ?>"/><line class="e2n-chart-grid" x1="<?php echo $left; ?>" y1="<?php echo $top+$plotHeight/2; ?>" x2="<?php echo $width-$right; ?>" y2="<?php echo $top+$plotHeight/2; ?>"/><text class="e2n-chart-label" x="<?php echo $left-8; ?>" y="<?php echo $top+4; ?>" text-anchor="end"><?php echo esc_html($this->formatCentiseconds($high)); ?></text><text class="e2n-chart-label" x="<?php echo $left-8; ?>" y="<?php echo $height-$bottom+4; ?>" text-anchor="end"><?php echo esc_html($this->formatCentiseconds($low)); ?></text><text class="e2n-chart-label" x="<?php echo $left; ?>" y="<?php echo $height-12; ?>"><?php echo esc_html(wp_date('d/m/Y',$minDate)); ?></text><text class="e2n-chart-label" x="<?php echo $width-$right; ?>" y="<?php echo $height-12; ?>" text-anchor="end"><?php echo esc_html(wp_date('d/m/Y',$maxDate)); ?></text><?php if(count($coordinates)>1):?><polyline class="e2n-chart-line" points="<?php echo esc_attr(implode(' ',$coordinates)); ?>"/><?php endif; ?><?php foreach($points as $index=>$point):[$x,$y]=array_map('floatval',explode(',',$coordinates[$index]));$pointDate=wp_date('d/m/Y',$point['date']);$pointTime=(string)$point['row']['elapsed_time'];?><circle class="e2n-chart-point" cx="<?php echo $x; ?>" cy="<?php echo $y; ?>" r="5" tabindex="0" role="button" data-e2n-chart-point data-date="<?php echo esc_attr($pointDate); ?>" data-time="<?php echo esc_attr($pointTime); ?>" aria-label="<?php echo esc_attr($pointDate.' · '.$pointTime); ?>"><title><?php echo esc_html($pointDate.' · '.$pointTime); ?></title></circle><?php endforeach; ?></svg></div><?php
    }

    private function performanceHistoryRow(array $performance, int $groupId, int $swimmerId): void
    {
        $rating=(int)($performance['time_rating']??0);?><article><div><strong><?php echo esc_html((string)$performance['elapsed_time']); ?></strong><?php if($rating>0):?><span><?php echo esc_html(str_repeat('★',$rating)); ?></span><?php endif; ?><?php if(!empty($performance['is_disqualified'])):?><span><?php esc_html_e('Disqualification','ecole2nat'); ?></span><?php endif; ?></div><div><time><?php echo esc_html(wp_date('d/m/Y H:i',strtotime((string)$performance['performed_at']))); ?></time><span><?php echo esc_html($performance['source']==='competition'?sprintf(__('Compétition · %s','ecole2nat'),(string)$performance['context_name']):sprintf(__('Entraînement · %s','ecole2nat'),(string)$performance['context_name'])); ?></span><?php if(!empty($performance['coach_name'])):?><span><?php echo esc_html(sprintf(__('Saisi par %s','ecole2nat'),(string)$performance['coach_name'])); ?></span><?php endif; ?></div><?php if(!empty($performance['comment'])):?><p><?php echo nl2br(esc_html((string)$performance['comment'])); ?></p><?php endif; ?><button class="e2n-performance-delete" type="button" data-e2n-delete-swimmer-time data-source="<?php echo esc_attr((string)$performance['source']); ?>" data-performance-id="<?php echo (int)$performance['id']; ?>" data-group-id="<?php echo (int)$groupId; ?>" data-swimmer-id="<?php echo (int)$swimmerId; ?>"><?php esc_html_e('Supprimer ce temps','ecole2nat'); ?></button></article><?php
    }

    private function elapsedCentiseconds(string $elapsed): ?int
    { return preg_match('/^(\d{1,3}):(\d{2})\.(\d{2})$/',$elapsed,$matches)?((int)$matches[1]*6000+(int)$matches[2]*100+(int)$matches[3]):null; }
    private function formatCentiseconds(int $value): string
    { return sprintf('%d:%02d.%02d',intdiv($value,6000),intdiv($value%6000,100),$value%100); }
    private function eventLabel(string $event): string
    { return preg_replace('/^(100|200|400)4N$/','$1 4N',$event)??$event; }

    public function ajaxSaveEvaluation(): void
    {
        check_ajax_referer('e2n_coach_ajax', 'nonce');
        if (!$this->access->canEvaluateGroup(absint($_POST['group_id'] ?? 0))) wp_send_json_error(['message' => __('Modification non autorisée.', 'ecole2nat')], 403);
        $result = $this->eval->saveSingleStatus(absint($_POST['group_id'] ?? 0), absint($_POST['swimmer_id'] ?? 0), absint($_POST['skill_id'] ?? 0), sanitize_key(wp_unslash((string) ($_POST['status'] ?? ''))), get_current_user_id());
        if (!$result['success']) wp_send_json_error(['message' => __('Impossible d’enregistrer l’évaluation.', 'ecole2nat')], 400);
        wp_send_json_success(['message' => __('Évaluation enregistrée.', 'ecole2nat')]);
    }

    public function ajaxSaveNote(): void
    {
        check_ajax_referer('e2n_coach_ajax', 'nonce');
        if (!$this->access->canEvaluateGroup(absint($_POST['group_id'] ?? 0))) wp_send_json_error(['message' => __('Modification non autorisée.', 'ecole2nat')], 403);
        $result = $this->eval->saveSingleNote(absint($_POST['group_id'] ?? 0), absint($_POST['swimmer_id'] ?? 0), absint($_POST['skill_id'] ?? 0), sanitize_textarea_field(wp_unslash((string) ($_POST['note'] ?? ''))), get_current_user_id());
        if (!$result['success']) wp_send_json_error(['message' => __('Impossible d’enregistrer la note.', 'ecole2nat')], 400);
        wp_send_json_success(['message' => __('Note enregistrée.', 'ecole2nat')]);
    }

    public function ajaxSaveCompetitionResponse(): void
    {
        check_ajax_referer('e2n_coach_ajax','nonce');
        if(!$this->access->canView())wp_send_json_error(['message'=>__('Modification non autorisée.','ecole2nat')],403);
        $result=$this->competitions->saveCoachResponse(absint($_POST['competition_id']??0),absint($_POST['swimmer_id']??0),sanitize_key(wp_unslash((string)($_POST['response']??''))),'',get_current_user_id());
        if(empty($result['success']))wp_send_json_error(['message'=>__('Réponse non enregistrée.','ecole2nat')],400);
        wp_send_json_success(['message'=>__('Réponse enregistrée.','ecole2nat')]);
    }

    public function ajaxSetCompetitionEngaged(): void
    {
        check_ajax_referer('e2n_coach_ajax','nonce');
        if(!$this->access->canView())wp_send_json_error(['message'=>__('Modification non autorisée.','ecole2nat')],403);
        $success=$this->competitions->setEngaged(absint($_POST['competition_id']??0),absint($_POST['swimmer_id']??0),!empty($_POST['engaged']),get_current_user_id());
        if(!$success)wp_send_json_error(['message'=>__('Engagement non enregistré.','ecole2nat')],400);
        wp_send_json_success(['message'=>__('Engagement enregistré.','ecole2nat')]);
    }

    public function ajaxSaveTimedPerformance(): void
    {
        check_ajax_referer('e2n_coach_ajax','nonce');
        if(!$this->access->canView())wp_send_json_error(['message'=>__('Enregistrement non autorisé.','ecole2nat')],403);
        $result=$this->competitions->saveTimedPerformance(absint($_POST['competition_id']??0),absint($_POST['swimmer_id']??0),absint($_POST['performance_id']??0),wp_unslash($_POST),get_current_user_id());
        if(empty($result['success']))wp_send_json_error(['message'=>__('Performance non enregistrée. Vérifiez les champs.','ecole2nat')],400);
        wp_send_json_success(['message'=>__('Performance enregistrée.','ecole2nat'),'performance_id'=>(int)$result['performance_id']]);
    }

    public function ajaxSaveTrainingPerformance(): void
    {
        check_ajax_referer('e2n_coach_ajax', 'nonce');
        $groupId = absint($_POST['group_id'] ?? 0);
        $swimmerId = absint($_POST['swimmer_id'] ?? 0);
        if (!$this->access->canEvaluateGroup($groupId)) {
            wp_send_json_error(['message' => __('Enregistrement non autorisé.', 'ecole2nat')], 403);
        }
        $evaluation = $this->eval->swimmerEvaluation($groupId, $swimmerId);
        if ($evaluation === null) {
            wp_send_json_error(['message' => __('Ce nageur n’appartient pas à ce groupe actif.', 'ecole2nat')], 403);
        }
        $result = $this->performances->saveTrainingTimed(
            $groupId,
            (int) $evaluation['group']['season_id'],
            $swimmerId,
            absint($_POST['performance_id'] ?? 0),
            wp_unslash($_POST),
            get_current_user_id()
        );
        if (empty($result['success'])) {
            wp_send_json_error(['message' => __('Chrono non enregistré. Vérifiez les champs.', 'ecole2nat')], 400);
        }
        wp_send_json_success(['message' => __('Chrono d’entraînement enregistré.', 'ecole2nat'), 'performance_id' => (int) $result['performance_id']]);
    }

    public function ajaxDeleteTimedPerformance(): void
    {
        check_ajax_referer('e2n_coach_ajax', 'nonce');
        $type=sanitize_key(wp_unslash((string)($_POST['context_type']??'')));$swimmerId=absint($_POST['swimmer_id']??0);$performanceId=absint($_POST['performance_id']??0);
        if($type==='competition'){
            if(!$this->access->canView()||!$this->competitions->deletePerformance(absint($_POST['competition_id']??0),$swimmerId,$performanceId))wp_send_json_error(['message'=>__('Chrono non supprimé.','ecole2nat')],400);
        }else{
            $groupId=absint($_POST['group_id']??0);if(!$this->access->canEvaluateGroup($groupId))wp_send_json_error(['message'=>__('Suppression non autorisée.','ecole2nat')],403);
            $evaluation=$this->eval->swimmerEvaluation($groupId,$swimmerId);if($evaluation===null||!$this->performances->deleteTrainingPerformance($groupId,(int)$evaluation['group']['season_id'],$swimmerId,$performanceId))wp_send_json_error(['message'=>__('Chrono non supprimé.','ecole2nat')],400);
        }
        wp_send_json_success(['message'=>__('Chrono supprimé.','ecole2nat')]);
    }

    public function ajaxDeleteTimedSeries(): void
    {
        check_ajax_referer('e2n_coach_ajax', 'nonce');
        $type=sanitize_key(wp_unslash((string)($_POST['context_type']??'')));$seriesKey=sanitize_text_field(wp_unslash((string)($_POST['series_key']??'')));
        if($type==='competition'){
            if(!$this->access->canView()||!$this->competitions->deleteSeries(absint($_POST['competition_id']??0),$seriesKey))wp_send_json_error(['message'=>__('Série non supprimée.','ecole2nat')],400);
        }else{
            $groups=$this->performances->trainingSeriesGroups($seriesKey);if($groups===[])wp_send_json_error(['message'=>__('Série introuvable.','ecole2nat')],404);
            foreach($groups as $groupId)if(!$this->access->canEvaluateGroup($groupId))wp_send_json_error(['message'=>__('Suppression non autorisée.','ecole2nat')],403);
            if(!$this->performances->deleteTrainingSeries($seriesKey))wp_send_json_error(['message'=>__('Série non supprimée.','ecole2nat')],400);
        }
        wp_send_json_success(['message'=>__('Série supprimée.','ecole2nat')]);
    }

    public function ajaxDeleteSwimmerPerformance(): void
    {
        check_ajax_referer('e2n_coach_ajax', 'nonce');
        $groupId=absint($_POST['group_id']??0);$swimmerId=absint($_POST['swimmer_id']??0);
        if(!$this->access->canEvaluateGroup($groupId)||$this->eval->swimmerEvaluation($groupId,$swimmerId)===null)wp_send_json_error(['message'=>__('Suppression non autorisée.','ecole2nat')],403);
        $success=$this->performances->deleteForSwimmer(sanitize_key(wp_unslash((string)($_POST['source']??''))),$swimmerId,absint($_POST['performance_id']??0));
        if(!$success)wp_send_json_error(['message'=>__('Chrono non supprimé.','ecole2nat')],400);
        wp_send_json_success(['message'=>__('Chrono supprimé.','ecole2nat')]);
    }

    public function ajaxPurgeSwimmerPerformances(): void
    {
        check_ajax_referer('e2n_coach_ajax', 'nonce');
        $groupId=absint($_POST['group_id']??0);$swimmerId=absint($_POST['swimmer_id']??0);
        if(!$this->access->canEvaluateGroup($groupId)||$this->eval->swimmerEvaluation($groupId,$swimmerId)===null)wp_send_json_error(['message'=>__('Purge non autorisée.','ecole2nat')],403);
        if(!$this->performances->purgeForSwimmer($swimmerId))wp_send_json_error(['message'=>__('Les chronos n’ont pas pu être purgés.','ecole2nat')],400);
        wp_send_json_success(['message'=>__('Tous les chronos ont été supprimés.','ecole2nat')]);
    }

    public function ajaxSaveCategoryVisibility(): void
    {
        check_ajax_referer('e2n_coach_ajax', 'nonce');
        if (!$this->access->canView()) wp_send_json_error(['message' => __('Modification non autorisée.', 'ecole2nat')], 403);
        $hidden = array_values(array_unique(array_filter(array_map('absint', wp_unslash((array) ($_POST['hidden_categories'] ?? []))))));
        update_user_meta(get_current_user_id(), 'e2n_hidden_coach_categories', $hidden);
        wp_send_json_success(['message' => __('Préférences enregistrées.', 'ecole2nat')]);
    }

    private function timeRange(array $group): string
    {
        $start = !empty($group['start_time']) ? substr((string) $group['start_time'], 0, 5) : '—';
        $end = !empty($group['end_time']) ? substr((string) $group['end_time'], 0, 5) : '';
        return $end !== '' ? $start . '–' . $end : $start;
    }

    private function statusLabel(string $status): string
    {
        return $this->eval->statuses()[$status] ?? __('Non observé', 'ecole2nat');
    }
}
