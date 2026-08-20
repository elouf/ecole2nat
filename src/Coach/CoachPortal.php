<?php

namespace Ecole2Nat\Coach;

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

    public function __construct()
    {
        $this->access = new CoachAccessService();
        $this->repo = new CoachPortalRepository();
        $this->eval = new EvaluationService();
        $this->parentAccess = new ParentAccessService();
        $this->parentDistribution = new ParentDistributionService();
    }

    public function register(): void
    {
        add_shortcode('e2n_coach_portal', [$this, 'shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_filter('template_include', [$this, 'template'], 99);
        add_filter('login_redirect', [$this, 'loginRedirect'], 10, 3);
        add_action('wp_ajax_e2n_coach_save_evaluation', [$this, 'ajaxSaveEvaluation']);
        add_action('wp_ajax_e2n_coach_save_note', [$this, 'ajaxSaveNote']);
        add_action('wp_ajax_e2n_coach_send_parent_code', [$this, 'ajaxSendParentCode']);
        add_action('wp_ajax_e2n_coach_get_parent_code', [$this, 'ajaxGetParentCode']);
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
        if (!in_array($view, ['swimmers', 'categories', 'week'], true)) $view = 'week';
        $from = sanitize_key(wp_unslash((string) ($_GET['e2n_from'] ?? $view)));
        if (!in_array($from, ['swimmers', 'categories', 'week'], true)) $from = 'week';
        ob_start();
        echo '<div class="e2n-coach">';
        $this->header($groupId > 0 ? $from : $view);
        if ($groupId && $swimmerId) $this->swimmer($groupId, $swimmerId, $from);
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
        <header class="e2n-coach-head"><a class="e2n-brand" href="<?php echo esc_url($this->base()); ?>"><?php if ($portalLogoId > 0) : ?><?php echo wp_get_attachment_image($portalLogoId, 'thumbnail', false, ['class' => 'e2n-brand-image']); ?><?php else : ?><span class="e2n-brand-mark" aria-hidden="true">E2N</span><?php endif; ?><span><?php echo esc_html($portalTitle); ?></span></a><nav aria-label="<?php esc_attr_e('Navigation Coach', 'ecole2nat'); ?>"><a class="<?php echo $view === 'swimmers' ? 'is-active' : ''; ?>" href="<?php echo esc_url($this->base(['e2n_view' => 'swimmers'])); ?>"><?php esc_html_e('Nageurs', 'ecole2nat'); ?></a><a class="<?php echo $view === 'categories' ? 'is-active' : ''; ?>" href="<?php echo esc_url($this->base(['e2n_view' => 'categories'])); ?>"><?php esc_html_e('Catégories', 'ecole2nat'); ?></a><a class="<?php echo $view === 'week' ? 'is-active' : ''; ?>" href="<?php echo esc_url($this->base()); ?>"><?php esc_html_e('Semaine type', 'ecole2nat'); ?></a></nav><details class="e2n-user-menu"><summary aria-label="<?php esc_attr_e('Menu utilisateur', 'ecole2nat'); ?>"><span aria-hidden="true"><?php echo esc_html(mb_strtoupper(mb_substr((string) $user->display_name, 0, 1))); ?></span></summary><div><strong><?php echo esc_html($user->display_name); ?></strong><a href="<?php echo esc_url(wp_logout_url($this->base())); ?>"><?php esc_html_e('Déconnexion', 'ecole2nat'); ?></a></div></details></header>
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
