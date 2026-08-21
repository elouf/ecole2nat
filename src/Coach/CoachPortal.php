<?php

namespace Ecole2Nat\Coach;

use Ecole2Nat\Competition\CompetitionService;
use Ecole2Nat\Evaluation\EvaluationService;
use Ecole2Nat\ParentPortal\ParentAccessService;
use Ecole2Nat\ParentPortal\ParentDistributionService;
use Ecole2Nat\Support\Config;
use Ecole2Nat\Support\ContactList;

if (!defined('ABSPATH')) { exit; }

class CoachPortal
{
    private CoachAccessService $access;
    private CoachPortalRepository $repo;
    private EvaluationService $eval;
    private ParentAccessService $parentAccess;
    private ParentDistributionService $parentDistribution;
    private CompetitionService $competitions;
    private string $competitionNotice = '';
    private array $competitionMissing = [];

    public function __construct()
    {
        $this->access = new CoachAccessService();
        $this->repo = new CoachPortalRepository();
        $this->eval = new EvaluationService();
        $this->parentAccess = new ParentAccessService();
        $this->parentDistribution = new ParentDistributionService();
        $this->competitions = new CompetitionService();
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
        add_action('wp_ajax_e2n_coach_send_parent_code', [$this, 'ajaxSendParentCode']);
        add_action('wp_ajax_e2n_coach_get_parent_code', [$this, 'ajaxGetParentCode']);
        add_action('wp_ajax_e2n_coach_save_competition_response', [$this, 'ajaxSaveCompetitionResponse']);
        add_action('wp_ajax_e2n_coach_set_competition_engaged', [$this, 'ajaxSetCompetitionEngaged']);
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
            'confirmParentCode' => __('Renvoyer le code Parents permanent par email ?', 'ecole2nat'),
            'sendingParentCode' => __('Envoi du code…', 'ecole2nat'),
            'loadingParentCode' => __('Récupération du code…', 'ecole2nat'),
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
        $swimmers = $this->repo->allSwimmers();
        $categories = [];
        foreach ($swimmers as $swimmer) {
            $category = (string) $swimmer['category_name'];
            $group = (string) $swimmer['group_name'];
            $categories[$category][$group][] = $swimmer;
        }
        ksort($categories, SORT_NATURAL | SORT_FLAG_CASE); ?>
        <div class="e2n-coach-main"><h1><?php esc_html_e('Nageurs par catégorie', 'ecole2nat'); ?></h1>
        <?php if ($categories === []) : ?><section class="e2n-card"><p><?php esc_html_e('Aucun nageur actif à afficher.', 'ecole2nat'); ?></p></section><?php else : ?>
            <div class="e2n-category-list"><?php foreach ($categories as $category => $groups) : ksort($groups, SORT_NATURAL | SORT_FLAG_CASE); ?><section class="e2n-card"><h2><?php echo esc_html($category); ?></h2><?php foreach ($groups as $group => $rows) : ?><h3><?php echo esc_html($group); ?></h3><div class="e2n-swimmers"><?php foreach ($rows as $swimmer) : $this->swimmerLink($swimmer, 'categories', false); endforeach; ?></div><?php endforeach; ?></section><?php endforeach; ?></div>
        <?php endif; ?></div><?php
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
        $group = $context['group']; ?>
        <a class="e2n-back" href="<?php echo esc_url($this->originUrl($from)); ?>">← <?php echo esc_html($this->originLabel($from)); ?></a><h1><?php echo esc_html($group['name']); ?></h1><p><?php echo esc_html($group['category_name'] . ' · ' . $group['season_name']); ?> <span class="e2n-pill"><?php esc_html_e('Évaluation autorisée', 'ecole2nat'); ?></span></p>
        <section class="e2n-card"><h2><?php esc_html_e('Nageurs', 'ecole2nat'); ?></h2><div class="e2n-swimmers">
        <?php foreach ($context['swimmers'] as $swimmer) : ?><a href="<?php echo esc_url($this->base(['e2n_group' => $groupId, 'e2n_swimmer' => (int) $swimmer['id'], 'e2n_from' => $from])); ?>"><strong><?php echo esc_html($swimmer['first_name'] . ' ' . $swimmer['last_name']); ?></strong><span class="e2n-swimmer-card-meta"><span><?php echo esc_html(sprintf(__('%1$d acquis · %2$d en cours', 'ecole2nat'), (int) $swimmer['acquired_count'], (int) $swimmer['in_progress_count'])); ?></span><?php $this->swimmerFlags($swimmer); ?></span></a><?php endforeach; ?>
        </div></section>
        <?php if ($context['skills'] !== []) : ?><section class="e2n-card"><h2><?php esc_html_e('Évaluation collective rapide', 'ecole2nat'); ?></h2><p><?php esc_html_e('Choisissez une compétence pour mettre à jour tout le groupe.', 'ecole2nat'); ?></p><div class="e2n-skill-picker">
        <?php $domain = ''; foreach ($context['skills'] as $skill) : if ($domain !== $skill['domain_name']) { $domain = $skill['domain_name']; echo '<h3>' . esc_html($domain) . '</h3>'; } ?><a class="e2n-skill-link" href="<?php echo esc_url($this->base(['e2n_group' => $groupId, 'e2n_collective_skill' => (int) $skill['id'], 'e2n_from' => $from])); ?>"><?php echo esc_html($skill['name']); ?></a><?php endforeach; ?>
        </div></section><?php endif;
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
        $emails = ContactList::emails((string) ($data['swimmer']['responsible_email'] ?? ''));
        $previewUrl = $this->parentAccess->coachPreviewUrl($swimmerId); ?>
        <a class="e2n-back" href="<?php echo esc_url($from === 'week' ? $this->base(['e2n_group' => $groupId]) : $this->originUrl($from)); ?>">← <?php echo esc_html($from === 'week' ? __('Groupe', 'ecole2nat') : $this->originLabel($from)); ?></a>
        <article class="e2n-swimmer-profile">
            <header class="e2n-swimmer-heading">
                <div><p class="e2n-eyebrow"><?php esc_html_e('Fiche nageur', 'ecole2nat'); ?></p><h1><?php echo esc_html($data['swimmer']['first_name'] . ' ' . $data['swimmer']['last_name']); ?></h1><p class="e2n-swimmer-meta"><?php echo esc_html($data['group']['name']); ?></p><?php $this->swimmerFlags($data['swimmer'], true); ?></div>
                <details class="e2n-actions-menu"><summary><?php esc_html_e('Actions', 'ecole2nat'); ?> <span aria-hidden="true">•••</span></summary><div class="e2n-actions-panel">
                    <?php if ($phone !== '') : ?><a href="tel:<?php echo esc_attr($phone); ?>"><?php esc_html_e('Appeler le responsable', 'ecole2nat'); ?></a><a href="sms:<?php echo esc_attr($phone); ?>"><?php esc_html_e('Envoyer un message', 'ecole2nat'); ?></a><?php endif; ?>
                    <?php if ($previewUrl !== '') : ?><a href="<?php echo esc_url($previewUrl); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Voir la fiche Parents', 'ecole2nat'); ?></a><?php endif; ?>
                    <button type="button" data-e2n-show-parent-code data-group-id="<?php echo (int) $groupId; ?>" data-swimmer-id="<?php echo (int) $swimmerId; ?>"><?php esc_html_e('Afficher le code Parents', 'ecole2nat'); ?></button>
                    <?php if ($emails !== []) : ?><button class="e2n-action-danger" type="button" data-e2n-send-parent-code data-group-id="<?php echo (int) $groupId; ?>" data-swimmer-id="<?php echo (int) $swimmerId; ?>"><?php esc_html_e('Renvoyer un code Parents', 'ecole2nat'); ?></button><?php else : ?><span class="e2n-contact-missing"><?php esc_html_e('Email responsable non renseigné', 'ecole2nat'); ?></span><?php endif; ?>
                    <span class="e2n-parent-code-status" data-e2n-parent-code-status aria-live="polite"></span>
                </div></details>
            </header>
            <section class="e2n-progress-summary" aria-label="<?php esc_attr_e('Résumé de la progression', 'ecole2nat'); ?>"><div><strong><?php esc_html_e('Progression', 'ecole2nat'); ?></strong><span><?php echo esc_html(sprintf(__('%1$d acquises · %2$d en cours · %3$d au total', 'ecole2nat'), $acquired, $inProgress, $total)); ?></span></div><div class="e2n-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int) $percentage; ?>"><span style="width:<?php echo (int) $percentage; ?>%"></span></div><b><?php echo (int) $percentage; ?> %</b></section>
            <section class="e2n-card e2n-progress-card"><div class="e2n-autosave-status" data-e2n-save-status aria-live="polite"></div><div class="e2n-skills">
                <?php foreach ($domains as $domain => $skills) : ?><section class="e2n-domain"><h2><?php echo esc_html($domain); ?></h2>
                    <?php foreach ($skills as $skill) : ?><article class="e2n-skill"><div class="e2n-skill-name"><strong><?php echo esc_html($skill['name']); ?></strong><?php $this->history($skill['history']); ?></div><div class="e2n-choice-group e2n-choice-group--evaluation" role="radiogroup" aria-label="<?php echo esc_attr($skill['name']); ?>">
                    <?php foreach ($this->eval->statuses() as $value => $label) : ?><label class="e2n-choice e2n-choice--<?php echo esc_attr($value); ?>"><input type="radio" name="status[<?php echo (int) $skill['id']; ?>]" value="<?php echo esc_attr($value); ?>" data-e2n-kind="evaluation" data-group-id="<?php echo (int) $groupId; ?>" data-swimmer-id="<?php echo (int) $swimmerId; ?>" data-skill-id="<?php echo (int) $skill['id']; ?>" <?php checked($skill['status'], $value); ?>><span><?php echo esc_html($label); ?></span></label><?php endforeach; ?></div>
                    <details class="e2n-note-editor" <?php echo $skill['notes'] !== '' ? 'open' : ''; ?>><summary><?php echo esc_html($skill['notes'] !== '' ? __('Note interne renseignée', 'ecole2nat') : __('Ajouter une note', 'ecole2nat')); ?></summary><textarea rows="2" data-e2n-kind="note" data-group-id="<?php echo (int) $groupId; ?>" data-swimmer-id="<?php echo (int) $swimmerId; ?>" data-skill-id="<?php echo (int) $skill['id']; ?>" placeholder="<?php esc_attr_e('Note interne', 'ecole2nat'); ?>"><?php echo esc_textarea($skill['notes']); ?></textarea></details></article><?php endforeach; ?>
                </section><?php endforeach; ?>
            </div></section>
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

    private function phoneUri(string $phone): string
    {
        return (string) preg_replace('/(?!^\+)[^0-9]/', '', trim($phone));
    }

    private function maskEmail(string $email): string
    {
        if (str_contains($email, ',')) {
            return __('les adresses responsables', 'ecole2nat');
        }
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) return __('l’adresse responsable', 'ecole2nat');
        $visible = mb_substr($parts[0], 0, min(2, mb_strlen($parts[0])));
        return $visible . str_repeat('•', max(3, mb_strlen($parts[0]) - mb_strlen($visible))) . '@' . $parts[1];
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
        <?php foreach($rows as $row):$unanswered=max(0,(int)$row['eligible_count']-(int)$row['yes_count']-(int)$row['no_count']);$target=!empty($row['target_all'])?__('Tous','ecole2nat'):($row['competition_category_names']??'');?><a class="e2n-card e2n-competition-link" href="<?php echo esc_url($this->base(['e2n_view'=>'competitions','e2n_competition'=>(int)$row['id']])); ?>"><div><h2><?php echo esc_html($row['name']); ?></h2><p><?php echo esc_html($this->competitionDateLabel($row).' · '.$target); ?></p></div><span><?php echo esc_html(sprintf(__('%1$d oui · %2$d non · %3$d sans réponse · %4$d à engager','ecole2nat'),(int)$row['yes_count'],(int)$row['no_count'],$unanswered,(int)$row['pending_engagement_count'])); ?></span></a><?php endforeach; ?></div></div><?php
    }

    private function competition(int $competitionId): void
    {
        $notice=$this->competitionNotice;$missing=$this->competitionMissing;
        $data=$this->competitions->coachDetail($competitionId);if($data===null){echo '<p>'.esc_html__('Compétition introuvable.','ecole2nat').'</p>';return;}$editable=$data['status']==='published'; ?>
        <a class="e2n-back" href="<?php echo esc_url($this->base(['e2n_view'=>'competitions'])); ?>">← <?php esc_html_e('Compétitions','ecole2nat'); ?></a><div class="e2n-competition-title-row"><h1><?php echo esc_html($data['name']); ?></h1><form method="post"><?php wp_nonce_field('e2n_competition_day_'.$competitionId); ?><?php if(empty($data['started_at'])):?><input type="hidden" name="e2n_competition_action" value="start"><button class="e2n-btn e2n-start-competition" type="submit"><span aria-hidden="true">▶</span> <?php esc_html_e('Démarrer la compétition','ecole2nat'); ?></button><?php elseif(empty($data['closed_at'])):?><input type="hidden" name="e2n_competition_action" value="close"><button class="e2n-btn e2n-start-competition e2n-close-competition" type="submit"><span aria-hidden="true">■</span> <?php esc_html_e('Clôturer la compétition','ecole2nat'); ?></button><?php else:?><input type="hidden" name="e2n_competition_action" value="resume"><button class="e2n-btn e2n-start-competition" type="submit"><span aria-hidden="true">▶</span> <?php esc_html_e('Redémarrer la compétition','ecole2nat'); ?></button><?php endif; ?></form></div><p><?php echo esc_html($this->competitionDateLabel($data).(!empty($data['location'])?' · '.$data['location']:'')); ?></p>
        <?php if(!empty($data['information'])):?><details class="e2n-competition-briefing"><summary><?php esc_html_e('Briefing','ecole2nat'); ?></summary><div><?php echo nl2br(esc_html($data['information'])); ?></div></details><?php endif; ?>
        <?php if(!empty($data['technical_document_url'])||!empty($data['program_url'])||!empty($data['carpool_url'])||!empty($data['liveffn_url'])||!empty($data['photo_album_url'])):?><div class="e2n-inline e2n-competition-links">
            <?php if(!empty($data['technical_document_url'])):?><a class="e2n-btn" href="<?php echo esc_url($data['technical_document_url']); ?>" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">↗</span> <?php esc_html_e('Fiche technique','ecole2nat'); ?></a><?php endif; ?>
            <?php if(!empty($data['program_url'])):?><a class="e2n-btn" href="<?php echo esc_url($data['program_url']); ?>" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">↗</span> <?php esc_html_e('Programme','ecole2nat'); ?></a><?php endif; ?>
            <?php if(!empty($data['carpool_url'])):?><a class="e2n-btn" href="<?php echo esc_url($data['carpool_url']); ?>" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">🚗</span> <?php esc_html_e('Covoiturage','ecole2nat'); ?></a><?php endif; ?>
            <?php if(!empty($data['liveffn_url'])):?><a class="e2n-btn" href="<?php echo esc_url($data['liveffn_url']); ?>" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">◉</span> <?php esc_html_e('liveFFN','ecole2nat'); ?></a><?php endif; ?>
            <?php if(!empty($data['photo_album_url'])):?><a class="e2n-btn" href="<?php echo esc_url($data['photo_album_url']); ?>" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">📷</span> <?php esc_html_e('Album photo','ecole2nat'); ?></a><?php endif; ?>
        </div><?php endif; ?>
        <?php if(empty($data['started_at'])):?>
            <?php if($notice==='missing'):?><div class="e2n-alert is-error"><strong><?php echo esc_html(sprintf(__('Impossible de démarrer, vous n’avez pas inscrit %s sur Extranat.','ecole2nat'),implode(', ',$missing))); ?></strong><form method="post" class="e2n-inline-form"><?php wp_nonce_field('e2n_competition_day_'.$competitionId); ?><input type="hidden" name="e2n_competition_action" value="force_start"><button class="e2n-link-button" type="submit"><?php esc_html_e('Si vous voulez tout de même démarrer, en connaissance de cause, cliquez ici.','ecole2nat'); ?></button></form></div><?php endif; ?>
        <section class="e2n-card"><div class="e2n-competition-section-head"><h2><?php esc_html_e('Réponses et engagements','ecole2nat'); ?></h2><div class="e2n-competition-sort" role="group" aria-label="<?php esc_attr_e('Trier les nageurs','ecole2nat'); ?>"><button type="button" class="is-active" data-e2n-competition-sort="alpha" title="<?php esc_attr_e('Ordre alphabétique','ecole2nat'); ?>" aria-label="<?php esc_attr_e('Ordre alphabétique','ecole2nat'); ?>" aria-pressed="true">A–Z</button><button type="button" data-e2n-competition-sort="status" title="<?php esc_attr_e('Trier par avancement','ecole2nat'); ?>" aria-label="<?php esc_attr_e('Trier par avancement','ecole2nat'); ?>" aria-pressed="false">☑</button></div></div><div class="e2n-autosave-status" data-e2n-save-status aria-live="polite"></div><div class="e2n-competition-swimmers">
        <?php foreach($data['swimmers'] as $swimmer):$response=$swimmer['response']??'';$engaged=!empty($swimmer['is_engaged']);$stateClass=$response==='yes'?($engaged?'is-complete':'is-pending'):($response==='no'?'is-declined':'is-unanswered');?><article class="e2n-competition-swimmer <?php echo esc_attr($stateClass); ?>" data-e2n-competition-swimmer data-alpha="<?php echo esc_attr(mb_strtolower($swimmer['last_name'].' '.$swimmer['first_name'])); ?>"><div><strong><?php echo esc_html($swimmer['first_name'].' '.$swimmer['last_name']); ?></strong><small><?php echo esc_html($swimmer['group_name']); ?><?php if(($swimmer['response_source']??'')==='coach'):?> · <?php esc_html_e('saisi par un coach','ecole2nat'); ?><?php endif; ?></small><?php if(!empty($swimmer['comment'])):?><small><?php echo esc_html($swimmer['comment']); ?></small><?php endif; ?><?php if($response!==''):?><small><?php echo esc_html(sprintf(__('Parents officiels : %s','ecole2nat'),!isset($swimmer['parents_official'])?__('Non renseigné','ecole2nat'):((int)$swimmer['parents_official']===1?__('Oui','ecole2nat'):__('Non','ecole2nat')))); ?></small><?php if($response==='yes'&&!empty($data['end_date'])&&$data['end_date']!==$data['start_date']):?><small><?php echo esc_html(sprintf(__('Participation : %s','ecole2nat'),$this->attendanceDaysLabel((string)($swimmer['attendance_days']??''),$data))); ?></small><?php endif; ?><?php endif; ?></div><div class="e2n-choice-group"><label class="e2n-choice"><input type="radio" name="competition[<?php echo (int)$swimmer['id']; ?>]" value="yes" data-e2n-kind="competition-response" data-competition-id="<?php echo (int)$competitionId; ?>" data-swimmer-id="<?php echo (int)$swimmer['id']; ?>" <?php checked($response,'yes'); ?> <?php disabled(!$editable); ?>><span><?php esc_html_e('Oui','ecole2nat'); ?></span></label><label class="e2n-choice"><input type="radio" name="competition[<?php echo (int)$swimmer['id']; ?>]" value="no" data-e2n-kind="competition-response" data-competition-id="<?php echo (int)$competitionId; ?>" data-swimmer-id="<?php echo (int)$swimmer['id']; ?>" <?php checked($response,'no'); ?> <?php disabled(!$editable); ?>><span><?php esc_html_e('Non','ecole2nat'); ?></span></label></div><label class="e2n-engaged"><input type="checkbox" data-e2n-kind="competition-engaged" data-competition-id="<?php echo (int)$competitionId; ?>" data-swimmer-id="<?php echo (int)$swimmer['id']; ?>" <?php checked((int)($swimmer['is_engaged']??0),1); ?> <?php disabled(!$editable||$response!=='yes'); ?>> <?php esc_html_e('Engagement Extranat','ecole2nat'); ?><?php if(!empty($swimmer['engaged_at'])):?><small><?php echo esc_html(wp_date('d/m/Y',strtotime($swimmer['engaged_at'])).' · '.($swimmer['engaged_by_name']?:__('Coach','ecole2nat'))); ?></small><?php endif; ?></label></article><?php endforeach; ?>
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
    }

    private function competitionDay(array $competition,string $notice): void
    {
        $competitionId=(int)$competition['id'];$selectedId=$notice==='performance_saved'?0:absint($_GET['e2n_competitor']??0);$participants=$this->competitions->participants($competitionId);$selected=null;foreach($participants as $participant)if((int)$participant['id']===$selectedId)$selected=$participant;
        if($notice==='performance_saved'):?><div class="e2n-alert is-success"><?php esc_html_e('Performance enregistrée.','ecole2nat'); ?></div><?php elseif($notice==='performance_deleted'):?><div class="e2n-alert is-success"><?php esc_html_e('Épreuve supprimée.','ecole2nat'); ?></div><?php elseif($notice==='performance_error'):?><div class="e2n-alert is-error"><?php esc_html_e('Performance non enregistrée. Vérifiez les champs.','ecole2nat'); ?></div><?php endif;
        if($selected!==null){$this->competitionSwimmer($competition,$selected);return;} ?>
        <section class="e2n-card"><div class="e2n-competition-section-head"><h2><?php esc_html_e('Nageurs engagés','ecole2nat'); ?></h2><?php if(current_user_can('manage_options')):?><form method="post" onsubmit="return confirm('<?php echo esc_js(__('Revenir au suivi des inscriptions ? Les performances déjà saisies seront conservées.','ecole2nat')); ?>');"><?php wp_nonce_field('e2n_competition_day_'.$competitionId); ?><input type="hidden" name="e2n_competition_action" value="stop"><button class="e2n-link-button" type="submit"><?php esc_html_e('Revenir aux inscriptions','ecole2nat'); ?></button></form><?php endif; ?></div>
        <div class="e2n-swimmers"><?php foreach($participants as $participant):?><a href="<?php echo esc_url($this->base(['e2n_view'=>'competitions','e2n_competition'=>$competitionId,'e2n_competitor'=>(int)$participant['id']])); ?>"><strong><?php echo esc_html($participant['first_name'].' '.$participant['last_name']); ?></strong><span><?php echo esc_html($participant['group_name']??''); ?></span></a><?php endforeach; ?></div>
        <?php $available=$this->competitions->availableParticipants($competitionId);if($available!==[]):?><details class="e2n-add-competitor"><summary><?php esc_html_e('Ajouter un nageur non inscrit','ecole2nat'); ?></summary><form method="post" class="e2n-inline"><?php wp_nonce_field('e2n_competition_day_'.$competitionId); ?><input type="hidden" name="e2n_competition_action" value="add_participant"><select name="swimmer_id" required><option value=""><?php esc_html_e('Choisir un nageur…','ecole2nat'); ?></option><?php foreach($available as $swimmer):?><option value="<?php echo (int)$swimmer['id']; ?>"><?php echo esc_html($swimmer['first_name'].' '.$swimmer['last_name'].' · '.($swimmer['group_name']??'')); ?></option><?php endforeach; ?></select><button class="e2n-btn" type="submit"><?php esc_html_e('Ajouter','ecole2nat'); ?></button></form></details><?php endif; ?></section><?php
    }

    private function competitionSwimmer(array $competition,array $swimmer): void
    {
        $competitionId=(int)$competition['id'];$swimmerId=(int)$swimmer['id'];$performanceId=absint($_GET['e2n_performance']??0);$performances=$this->competitions->performances($competitionId,$swimmerId);$editing=null;foreach($performances as $performance)if((int)$performance['id']===$performanceId)$editing=$performance; ?>
        <a class="e2n-back" href="<?php echo esc_url($this->base(['e2n_view'=>'competitions','e2n_competition'=>$competitionId])); ?>">← <?php esc_html_e('Participants','ecole2nat'); ?></a><section class="e2n-card"><h2><?php echo esc_html($swimmer['first_name'].' '.$swimmer['last_name']); ?></h2>
        <?php if($performances!==[]):?><div class="e2n-performance-list"><?php foreach($performances as $performance):$rating=(int)($performance['time_rating']??0);?><a class="<?php echo $rating>=1&&$rating<=5?'is-rating-'.$rating:'is-unrated'; ?>" href="<?php echo esc_url($this->base(['e2n_view'=>'competitions','e2n_competition'=>$competitionId,'e2n_competitor'=>$swimmerId,'e2n_performance'=>(int)$performance['id']])); ?>"><strong><?php echo esc_html($performance['event_code']); ?></strong><span><?php echo esc_html($performance['elapsed_time']?:__('Chrono non renseigné','ecole2nat')); ?><?php if($rating>0):?> · <?php echo esc_html(str_repeat('★',$rating)); ?><?php endif; ?><?php if(!empty($performance['is_disqualified'])):?> · <?php esc_html_e('Disqualification','ecole2nat'); ?><?php endif; ?></span></a><?php endforeach; ?></div><?php endif; ?>
        <form method="post" class="e2n-performance-form" data-e2n-performance-form data-performance-id="<?php echo (int)($editing['id']??0); ?>"><?php wp_nonce_field('e2n_competition_day_'.$competitionId); ?><input type="hidden" name="e2n_competition_action" value="save_performance"><input type="hidden" name="swimmer_id" value="<?php echo $swimmerId; ?>"><input type="hidden" name="performance_id" value="<?php echo (int)($editing['id']??0); ?>"><input type="hidden" name="event_code" value="<?php echo esc_attr($editing['event_code']??''); ?>" data-e2n-event-value>
        <?php $eventGroups=['PAP'=>['50PAP','100PAP','200PAP'],'DOS'=>['50DOS','100DOS','200DOS'],'BRASSE'=>['50BRASSE','100BRASSE','200BRASSE'],'NL'=>['50NL','100NL','200NL','400NL','800NL','1500NL'],'4N'=>['1004N','2004N','4004N']]; ?><div class="e2n-event-grid" data-e2n-event-grid><?php foreach($eventGroups as $stroke=>$events):?><div class="e2n-event-row"><strong><?php echo esc_html($stroke); ?></strong><div><?php foreach($events as $event):?><button type="button" data-e2n-event="<?php echo esc_attr($event); ?>" class="<?php echo ($editing['event_code']??'')===$event?'is-selected':''; ?>"><?php echo esc_html($event); ?></button><?php endforeach; ?></div></div><?php endforeach; ?></div>
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
        <?php foreach ($data['swimmers'] as $swimmer) : ?><div class="e2n-collective-row"><strong><?php echo esc_html($swimmer['first_name'] . ' ' . $swimmer['last_name']); ?></strong><div class="e2n-choice-group" role="radiogroup"><?php foreach ($this->eval->statuses() as $value => $label) : ?><label class="e2n-choice e2n-choice--<?php echo esc_attr($value); ?>"><input type="radio" value="<?php echo esc_attr($value); ?>" data-e2n-kind="evaluation" data-group-id="<?php echo (int) $groupId; ?>" data-swimmer-id="<?php echo (int) $swimmer['id']; ?>" data-skill-id="<?php echo (int) $skillId; ?>" <?php checked($swimmer['status'], $value); ?>><span><?php echo esc_html($label); ?></span></label><?php endforeach; ?></div></div><?php endforeach; ?>
        </div></section><?php
    }

    private function history(array $history): void
    {
        if ($history === []) return; ?>
        <details class="e2n-skill-history"><summary aria-label="<?php esc_attr_e('Afficher l’historique', 'ecole2nat'); ?>">◷ <?php esc_html_e('Historique', 'ecole2nat'); ?></summary><ul><?php foreach ($history as $event) : ?><li><time><?php echo esc_html(wp_date('d/m/Y', strtotime((string) $event['changed_at']))); ?></time> · <?php echo esc_html($this->statusLabel((string) $event['status'])); ?> · <?php echo esc_html((string) ($event['evaluator_name'] ?: __('Coach', 'ecole2nat'))); ?></li><?php endforeach; ?></ul></details><?php
    }

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

    public function ajaxSendParentCode(): void
    {
        check_ajax_referer('e2n_coach_ajax', 'nonce');
        $groupId = absint($_POST['group_id'] ?? 0);
        $swimmerId = absint($_POST['swimmer_id'] ?? 0);
        if (!$this->access->canEvaluateGroup($groupId) || $this->eval->swimmerEvaluation($groupId, $swimmerId) === null) {
            wp_send_json_error(['message' => __('Envoi non autorisé.', 'ecole2nat')], 403);
        }

        $result = $this->parentDistribution->sendForSwimmer($swimmerId);
        if (empty($result['success'])) {
            $message = ($result['message'] ?? '') === 'missing_email'
                ? __('Aucun email responsable valide n’est enregistré.', 'ecole2nat')
                : (($result['message'] ?? '') === 'missing_portal'
                    ? __('La page du portail Parents est introuvable.', 'ecole2nat')
                    : __('Le code n’a pas pu être envoyé.', 'ecole2nat'));
            wp_send_json_error(['message' => $message], 400);
        }

        wp_send_json_success([
            'message' => sprintf(
                __('Code envoyé à %s.', 'ecole2nat'),
                $this->maskEmail((string) ($result['email'] ?? ''))
            ),
        ]);
    }

    public function ajaxGetParentCode(): void
    {
        check_ajax_referer('e2n_coach_ajax', 'nonce');
        $groupId = absint($_POST['group_id'] ?? 0);
        $swimmerId = absint($_POST['swimmer_id'] ?? 0);
        if (!$this->access->canEvaluateGroup($groupId) || $this->eval->swimmerEvaluation($groupId, $swimmerId) === null) {
            wp_send_json_error(['message' => __('Consultation non autorisée.', 'ecole2nat')], 403);
        }
        $result = $this->parentAccess->permanentCode($swimmerId, false);
        if (empty($result['success'])) {
            wp_send_json_error(['message' => __('Le code Parents n’a pas pu être récupéré.', 'ecole2nat')], 400);
        }
        wp_send_json_success([
            'message' => sprintf(__('Code Parents : %s', 'ecole2nat'), (string) $result['code']),
            'code' => (string) $result['code'],
        ]);
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
