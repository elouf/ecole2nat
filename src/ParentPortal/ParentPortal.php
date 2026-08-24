<?php

namespace Ecole2Nat\ParentPortal;

use Ecole2Nat\Competition\CompetitionService;
use Ecole2Nat\Performance\EventCatalog;
use Ecole2Nat\Performance\PerformanceService;
use Ecole2Nat\Support\Config;
use Ecole2Nat\Support\Extranat;

if (!defined('ABSPATH')) {
    exit;
}

class ParentPortal
{
    private ParentAccessService $service;
    private CompetitionService $competitions;
    private PerformanceService $performances;

    public function __construct()
    {
        $this->service = new ParentAccessService();
        $this->competitions = new CompetitionService();
        $this->performances = new PerformanceService();
    }

    public function register(): void
    {
        add_shortcode('e2n_parent_report', [$this, 'renderShortcode']);
        add_action('wp', [$this, 'registerNoIndex']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_filter('template_include', [$this, 'template'], 99);
    }

    public function assets(): void
    {
        if (!$this->isParentPortalPage()) {
            return;
        }

        wp_enqueue_style(
            'e2n-parent-portal',
            Config::pluginUrl() . 'assets/css/parent-portal.css',
            [],
            Config::version()
        );
        wp_enqueue_script(
            'e2n-parent-portal',
            Config::pluginUrl() . 'assets/js/parent-portal.js',
            [],
            Config::version(),
            true
        );
    }

    public function template(string $template): string
    {
        if (!$this->isParentPortalPage()) {
            return $template;
        }

        $parentTemplate = E2N_PLUGIN_PATH . 'templates/parent-portal.php';

        return is_readable($parentTemplate) ? $parentTemplate : $template;
    }

    public function registerNoIndex(): void
    {
        if ($this->isParentPortalPage()) {
            add_filter('wp_robots', static function (array $robots): array {
                $robots['noindex'] = true;
                $robots['nofollow'] = true;

                return $robots;
            });
        }
    }

    private function isParentPortalPage(): bool
    {
        global $post;

        return $post instanceof \WP_Post
            && is_singular()
            && has_shortcode((string) $post->post_content, 'e2n_parent_report');
    }

    public function renderShortcode(): string
    {
        $message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = isset($_POST['e2n_parent_action'])
                ? sanitize_key(wp_unslash($_POST['e2n_parent_action']))
                : '';

            if ($action === 'logout') {
                check_admin_referer('e2n_parent_logout');
                $this->service->clearAccessCookie();
            }

            if ($action === 'login') {
                check_admin_referer('e2n_parent_login');

                $code = isset($_POST['access_code'])
                    ? sanitize_text_field(wp_unslash($_POST['access_code']))
                    : '';

                $result = $this->service->authenticate($code);

                if (!$result['success']) {
                    $message = $result['message'];
                }
            }
        }

        $previewMode = '';
        $swimmerId = 0;

        if (
            is_user_logged_in()
            && current_user_can('manage_options')
            && isset($_GET['e2n_parent_preview'], $_GET['_wpnonce'])
        ) {
            $previewSwimmerId = absint($_GET['e2n_parent_preview']);
            $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));

            if (
                $previewSwimmerId > 0
                && wp_verify_nonce($nonce, 'e2n_parent_preview_' . $previewSwimmerId)
            ) {
                $previewMode = 'admin';
                $swimmerId = $previewSwimmerId;
            }
        }

        if (
            $previewMode === ''
            && is_user_logged_in()
            && (current_user_can('manage_options') || current_user_can('e2n_coach_access'))
            && isset($_GET['e2n_coach_preview'], $_GET['_wpnonce'])
        ) {
            $previewSwimmerId = absint($_GET['e2n_coach_preview']);
            $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));
            if (
                $previewSwimmerId > 0
                && wp_verify_nonce($nonce, 'e2n_coach_parent_preview_' . $previewSwimmerId)
            ) {
                $previewMode = 'coach';
                $swimmerId = $previewSwimmerId;
            }
        }

        if ($previewMode === '') {
            $swimmerId = $this->service->authenticatedSwimmerId();
        }

        if ($previewMode === '' && $swimmerId > 0 && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['e2n_parent_action'] ?? '') === 'competition_response') {
            check_admin_referer('e2n_parent_competition_response_' . absint($_POST['competition_id'] ?? 0));
            $officialRaw = sanitize_key(wp_unslash((string) ($_POST['parents_official'] ?? '')));
            $parentsOfficial = $officialRaw === 'yes' ? true : ($officialRaw === 'no' ? false : null);
            $result = $this->competitions->saveParentResponse(absint($_POST['competition_id'] ?? 0), $swimmerId, sanitize_key(wp_unslash((string) ($_POST['response'] ?? ''))), wp_unslash((string) ($_POST['comment'] ?? '')), $parentsOfficial, sanitize_key(wp_unslash((string) ($_POST['attendance_days'] ?? ''))));
            $message = $result['message'];
        }

        ob_start();
        ?>
        <section class="e2n-parent-portal">
            <?php if ($swimmerId <= 0) : ?>
                <?php $this->renderLogin($message); ?>
            <?php else : ?>
                <?php
                $seasonId = isset($_GET['e2n_season']) ? absint($_GET['e2n_season']) : 0;
                $report = $this->service->report($swimmerId, $seasonId);

                if ($report === null) {
                    $this->service->clearAccessCookie();
                    $this->renderLogin('report_unavailable');
                } else {
                    $view = sanitize_key(wp_unslash((string) ($_GET['e2n_parent_view'] ?? 'progress')));
                    $this->renderParentNavigation($view);
                    if ($view === 'competitions') $this->renderCompetitions($report, $previewMode, $message);
                    else $this->renderReport($report, $previewMode);
                }
                ?>
            <?php endif; ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private function renderLogin(string $message): void
    {
        $messages = [
            'invalid_code' => __('Ce code d’accès n’est pas reconnu.', 'ecole2nat'),
            'temporarily_blocked' => __('Trop de tentatives ont été effectuées. Réessayez dans 15 minutes.', 'ecole2nat'),
            'report_unavailable' => __('Le parcours n’est pas disponible pour le moment.', 'ecole2nat'),
        ];
        ?>
        <div class="e2n-parent-login-card">
            <div class="e2n-parent-logo" aria-hidden="true">🏊</div>
            <h1><?php esc_html_e('Mon parcours de natation', 'ecole2nat'); ?></h1>
            <p class="e2n-parent-intro">
                <?php esc_html_e('Saisissez le code remis par votre club pour consulter le parcours de votre enfant.', 'ecole2nat'); ?>
            </p>

            <?php if (isset($messages[$message])) : ?>
                <div class="e2n-parent-alert" role="alert">
                    <?php echo esc_html($messages[$message]); ?>
                </div>
            <?php endif; ?>

            <form method="post" class="e2n-parent-login-form">
                <?php wp_nonce_field('e2n_parent_login'); ?>
                <input type="hidden" name="e2n_parent_action" value="login">

                <label for="e2n-parent-access-code">
                    <?php esc_html_e('Code d’accès', 'ecole2nat'); ?>
                </label>
                <input
                    id="e2n-parent-access-code"
                    type="text"
                    name="access_code"
                    maxlength="9"
                    minlength="8"
                    inputmode="text"
                    autocomplete="one-time-code"
                    autocapitalize="characters"
                    spellcheck="false"
                    placeholder="A7F9K2QM"
                    required
                >

                <button type="submit">
                    <?php esc_html_e('Consulter le parcours', 'ecole2nat'); ?>
                </button>
            </form>
        </div>
        <?php
    }

    private function renderReport(array $report, string $previewMode = ''): void
    {
        $swimmer = $report['swimmer'];
        $counts = $report['counts'];
        $total = (int) $report['total'];
        $acquired = (int) $counts['acquired'];
        $percentage = $total > 0 ? (int) round(($acquired / $total) * 100) : 0;
        $performanceHistory = $this->performances->historyForSwimmer((int) $swimmer['id']);
        ?>
        <?php if ($previewMode !== '') : ?>
            <div class="e2n-parent-alert e2n-parent-preview-banner">
                <?php echo esc_html($previewMode === 'coach'
                    ? __('Prévisualisation Coach — aucun code parent n’a été utilisé.', 'ecole2nat')
                    : __('Prévisualisation administrateur — aucun code parent n’a été utilisé.', 'ecole2nat')); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($report['seasons']) && count($report['seasons']) > 1) : ?>
            <nav class="e2n-parent-season-tabs" aria-label="<?php esc_attr_e('Saisons', 'ecole2nat'); ?>">
                <?php foreach ($report['seasons'] as $season) : ?>
                    <?php
                    $url = add_query_arg('e2n_season', (int) $season['id']);
                    $active = (int) $season['id'] === (int) $report['season']['id'];
                    ?>
                    <a href="<?php echo esc_url($url); ?>" class="<?php echo $active ? 'is-active' : ''; ?>">
                        <?php echo esc_html($season['name']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <header class="e2n-parent-report-header">
            <div>
                <p class="e2n-parent-eyebrow">
                    <?php esc_html_e('Mon parcours de natation', 'ecole2nat'); ?>
                </p>
                <h1>
                    <?php
                    echo esc_html(
                        trim((string) $swimmer['first_name'] . ' ' . strtoupper((string) $swimmer['last_name']))
                    );
                    ?>
                </h1>
                <p class="e2n-parent-group">
                    <?php
                    echo esc_html(
                        sprintf(
                            __('Groupe %s · Catégorie %s · Saison %s', 'ecole2nat'),
                            $swimmer['group_name'] ?: __('Non affecté', 'ecole2nat'),
                            $swimmer['category_name'] ?: __('Non définie', 'ecole2nat'),
                            $swimmer['season_name'] ?: __('Non définie', 'ecole2nat')
                        )
                    );
                    ?>
                </p>
                <p class="e2n-parent-image-rights <?php echo !array_key_exists('image_rights', $swimmer) || $swimmer['image_rights'] === null ? 'is-unknown' : ((int) $swimmer['image_rights'] === 1 ? 'is-yes' : 'is-no'); ?>">
                    <span aria-hidden="true">📷</span>
                    <?php
                    echo esc_html(
                        !array_key_exists('image_rights', $swimmer) || $swimmer['image_rights'] === null
                            ? __('Droit à l’image : non renseigné', 'ecole2nat')
                            : ((int) $swimmer['image_rights'] === 1
                                ? __('Droit à l’image : oui', 'ecole2nat')
                                : __('Droit à l’image : non', 'ecole2nat'))
                    );
                    ?>
                </p>
                <?php $this->extranatLink($swimmer); ?>
            </div>

            <div class="e2n-parent-actions">
                <button type="button" class="e2n-parent-print" onclick="window.print()">
                    <?php esc_html_e('Imprimer', 'ecole2nat'); ?>
                </button>
                <?php if ($previewMode === '') : ?>
                    <form method="post">
                        <?php wp_nonce_field('e2n_parent_logout'); ?>
                        <input type="hidden" name="e2n_parent_action" value="logout">
                        <button type="submit" class="e2n-parent-change-code">
                            <?php esc_html_e('Changer de code', 'ecole2nat'); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($total > 0) : ?>
        <div class="e2n-parent-summary-card">
            <div>
                <span class="e2n-parent-summary-number"><?php echo esc_html((string) $acquired); ?></span>
                <span><?php esc_html_e('compétences acquises', 'ecole2nat'); ?></span>
            </div>
            <div class="e2n-parent-progress" aria-label="<?php echo esc_attr(sprintf(__('%d %% de compétences acquises', 'ecole2nat'), $percentage)); ?>">
                <span style="width: <?php echo esc_attr((string) $percentage); ?>%;"></span>
            </div>
            <p><?php echo esc_html($this->summarySentence($counts, $total)); ?></p>
            <?php if (!empty($report['latest_update'])) : ?>
                <p class="e2n-parent-last-update">
                    <?php
                    echo esc_html(
                        sprintf(
                            __('Dernière mise à jour : %s', 'ecole2nat'),
                            wp_date('d/m/Y', strtotime((string) $report['latest_update']))
                        )
                    );
                    ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="e2n-parent-legend" aria-label="<?php esc_attr_e('Légende des niveaux', 'ecole2nat'); ?>">
            <span class="status-not-observed"><?php esc_html_e('À découvrir', 'ecole2nat'); ?></span>
            <span class="status-in-progress"><?php esc_html_e('En progression', 'ecole2nat'); ?></span>
            <span class="status-acquired"><?php esc_html_e('Acquis', 'ecole2nat'); ?></span>
        </div>

        <div class="e2n-parent-domains">
            <?php foreach ($report['domains'] as $domain) : ?>
                <?php
                $domainTotal = count($domain['skills']);
                $domainAcquired = (int) $domain['acquired_count'];
                $domainPercentage = $domainTotal > 0
                    ? (int) round(($domainAcquired / $domainTotal) * 100)
                    : 0;
                ?>
                <article class="e2n-parent-domain-card">
                    <header>
                        <div>
                            <h2><?php echo esc_html($domain['name']); ?></h2>
                            <?php if (!empty($domain['description'])) : ?>
                                <p><?php echo esc_html($domain['description']); ?></p>
                            <?php endif; ?>
                        </div>
                        <strong><?php echo esc_html($domainAcquired . ' / ' . $domainTotal); ?></strong>
                    </header>

                    <div class="e2n-parent-progress e2n-parent-progress-small">
                        <span style="width: <?php echo esc_attr((string) $domainPercentage); ?>%;"></span>
                    </div>

                    <ul class="e2n-parent-skill-list">
                        <?php foreach ($domain['skills'] as $skill) : ?>
                            <li class="status-<?php echo esc_attr(str_replace('_', '-', $skill['status'])); ?>">
                                <span class="e2n-parent-status-dot" aria-hidden="true"></span>
                                <div class="e2n-parent-skill-content">
                                    <strong><?php echo esc_html($skill['name']); ?></strong>
                                    <span><?php echo esc_html($this->parentStatusLabel($skill['status'])); ?></span>
                                    <?php if (!empty($skill['history'])) : ?>
                                        <details class="e2n-parent-history">
                                            <summary aria-label="<?php esc_attr_e('Afficher l’historique de cette compétence', 'ecole2nat'); ?>"><span aria-hidden="true">◷</span> <?php esc_html_e('Voir l’historique', 'ecole2nat'); ?></summary>
                                            <ol>
                                                <?php foreach ($skill['history'] as $event) : ?>
                                                    <li>
                                                        <time><?php echo esc_html(wp_date('d/m/Y', strtotime((string) $event['changed_at']))); ?></time>
                                                        — <?php echo esc_html($this->parentStatusLabel((string) $event['status'])); ?>
                                                        — <?php echo esc_html((string) ($event['evaluator_name'] ?: __('Coach', 'ecole2nat'))); ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ol>
                                        </details>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php $this->renderPerformanceReport($performanceHistory); ?>

        <?php if (!empty($swimmer['parent_message'])) : ?>
            <aside class="e2n-parent-message-card">
                <h2><?php esc_html_e('Message de l’entraîneur', 'ecole2nat'); ?></h2>
                <p><?php echo nl2br(esc_html((string) $swimmer['parent_message'])); ?></p>
            </aside>
        <?php endif; ?>
        <?php
    }

    private function renderPerformanceReport(array $history): void
    {
        if ($history === []) return;
        $byEvent = [];
        foreach ($history as $performance) {
            $event = strtoupper((string) ($performance['event_code'] ?? ''));
            if (EventCatalog::contains($event)) $byEvent[$event][] = $performance;
        }
        if ($byEvent === []) return; ?>
        <section class="e2n-parent-chrono-report" data-e2n-performance-report><header class="e2n-parent-chrono-head"><div><strong><?php esc_html_e('Rapport des chronos', 'ecole2nat'); ?></strong><small><?php echo esc_html(sprintf(_n('%d chrono', '%d chronos', count($history), 'ecole2nat'), count($history))); ?></small></div><button type="button" data-e2n-toggle-all-charts data-show-label="<?php esc_attr_e('Afficher tous les graphiques', 'ecole2nat'); ?>" data-hide-label="<?php esc_attr_e('Masquer tous les graphiques', 'ecole2nat'); ?>" aria-expanded="false"><?php esc_html_e('Afficher tous les graphiques', 'ecole2nat'); ?></button></header><div class="e2n-parent-performance-report"><?php foreach (EventCatalog::all() as $event) : if (empty($byEvent[$event])) continue; $performances = $byEvent[$event]; usort($performances, static fn(array $left, array $right): int => strcmp((string) $left['performed_at'], (string) $right['performed_at'])); $best = $this->bestPerformance($performances); ?><section class="e2n-parent-performance-event"><header><h2><?php echo esc_html($this->eventLabel($event)); ?></h2><div class="e2n-parent-performance-event-summary"><?php if ($best !== null) : ?><span><?php echo esc_html(sprintf(__('Meilleur : %1$s · %2$s', 'ecole2nat'), (string) $best['elapsed_time'], wp_date('d/m/Y', strtotime((string) $best['performed_at'])))); ?></span><?php endif; ?><button type="button" data-e2n-toggle-chart data-show-label="<?php esc_attr_e('Afficher le graphique', 'ecole2nat'); ?>" data-hide-label="<?php esc_attr_e('Masquer le graphique', 'ecole2nat'); ?>" aria-expanded="false"><?php esc_html_e('Afficher le graphique', 'ecole2nat'); ?></button></div></header><div data-e2n-event-chart hidden><?php $this->renderPerformanceChart($event, $performances); ?></div><details class="e2n-parent-performance-details"><summary><?php esc_html_e('Voir le détail des temps', 'ecole2nat'); ?></summary><div class="e2n-parent-performance-list"><?php foreach (array_reverse($performances) as $performance) : ?><article><div><strong><?php echo esc_html((string) $performance['elapsed_time']); ?></strong><?php if (!empty($performance['is_disqualified'])) : ?><span><?php esc_html_e('Disqualification', 'ecole2nat'); ?></span><?php endif; ?></div><div><time><?php echo esc_html(wp_date('d/m/Y H:i', strtotime((string) $performance['performed_at']))); ?></time><span><?php echo esc_html($performance['source'] === 'competition' ? sprintf(__('Compétition · %s', 'ecole2nat'), (string) $performance['context_name']) : sprintf(__('Entraînement · %s', 'ecole2nat'), (string) $performance['context_name'])); ?></span></div></article><?php endforeach; ?></div></details></section><?php endforeach; ?></div></section><?php
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

    private function renderPerformanceChart(string $event, array $performances): void
    {
        $points = [];
        foreach ($performances as $performance) {
            $centiseconds = $this->elapsedCentiseconds((string) ($performance['elapsed_time'] ?? ''));
            $timestamp = strtotime(substr((string) ($performance['performed_at'] ?? ''), 0, 10) . ' 00:00:00');
            if ($centiseconds !== null && $timestamp !== false) $points[] = ['time' => $centiseconds, 'date' => $timestamp, 'row' => $performance];
        }
        if ($points === []) return;
        $width=720;$height=230;$left=62;$right=18;$top=18;$bottom=42;$plotWidth=$width-$left-$right;$plotHeight=$height-$top-$bottom;
        $dates=array_column($points,'date');$times=array_column($points,'time');$minDate=min($dates);$maxDate=max($dates);$low=min($times);$high=max($times);
        $coordinates=[];foreach($points as $point){$x=$left+($maxDate===$minDate?$plotWidth/2:(($point['date']-$minDate)/($maxDate-$minDate))*$plotWidth);$y=$top+($high===$low?$plotHeight/2:(($high-$point['time'])/($high-$low))*$plotHeight);$coordinates[]=round($x,1).','.round($y,1);} ?>
        <div class="e2n-parent-performance-chart" data-e2n-performance-chart><output class="e2n-parent-chart-tooltip" data-e2n-chart-tooltip aria-live="polite" hidden></output><svg viewBox="0 0 <?php echo $width.' '.$height; ?>" role="img" aria-label="<?php echo esc_attr(sprintf(__('Progression chronométrique en %s', 'ecole2nat'), $this->eventLabel($event))); ?>"><line class="e2n-parent-chart-axis" x1="<?php echo $left; ?>" y1="<?php echo $top; ?>" x2="<?php echo $left; ?>" y2="<?php echo $height-$bottom; ?>"/><line class="e2n-parent-chart-axis" x1="<?php echo $left; ?>" y1="<?php echo $height-$bottom; ?>" x2="<?php echo $width-$right; ?>" y2="<?php echo $height-$bottom; ?>"/><line class="e2n-parent-chart-grid" x1="<?php echo $left; ?>" y1="<?php echo $top+$plotHeight/2; ?>" x2="<?php echo $width-$right; ?>" y2="<?php echo $top+$plotHeight/2; ?>"/><text class="e2n-parent-chart-label" x="<?php echo $left-8; ?>" y="<?php echo $top+4; ?>" text-anchor="end"><?php echo esc_html($this->formatCentiseconds($high)); ?></text><text class="e2n-parent-chart-label" x="<?php echo $left-8; ?>" y="<?php echo $height-$bottom+4; ?>" text-anchor="end"><?php echo esc_html($this->formatCentiseconds($low)); ?></text><text class="e2n-parent-chart-label" x="<?php echo $left; ?>" y="<?php echo $height-12; ?>"><?php echo esc_html(wp_date('d/m/Y',$minDate)); ?></text><text class="e2n-parent-chart-label" x="<?php echo $width-$right; ?>" y="<?php echo $height-12; ?>" text-anchor="end"><?php echo esc_html(wp_date('d/m/Y',$maxDate)); ?></text><?php if(count($coordinates)>1):?><polyline class="e2n-parent-chart-line" points="<?php echo esc_attr(implode(' ',$coordinates)); ?>"/><?php endif; ?><?php foreach($points as $index=>$point):[$x,$y]=array_map('floatval',explode(',',$coordinates[$index]));$pointDate=wp_date('d/m/Y',$point['date']);$pointTime=(string)$point['row']['elapsed_time'];?><circle class="e2n-parent-chart-point" cx="<?php echo $x; ?>" cy="<?php echo $y; ?>" r="5" tabindex="0" role="button" data-e2n-chart-point data-date="<?php echo esc_attr($pointDate); ?>" data-time="<?php echo esc_attr($pointTime); ?>" aria-label="<?php echo esc_attr($pointDate.' · '.$pointTime); ?>"><title><?php echo esc_html($pointDate.' · '.$pointTime); ?></title></circle><?php endforeach; ?></svg></div><?php
    }

    private function elapsedCentiseconds(string $elapsed): ?int
    { return preg_match('/^(\d{1,3}):(\d{2})\.(\d{2})$/',$elapsed,$matches)?((int)$matches[1]*6000+(int)$matches[2]*100+(int)$matches[3]):null; }
    private function formatCentiseconds(int $value): string
    { return sprintf('%d:%02d.%02d',intdiv($value,6000),intdiv($value%6000,100),$value%100); }
    private function eventLabel(string $event): string
    { return preg_replace('/^(100|200|400)4N$/','$1 4N',$event)??$event; }

    private function renderParentNavigation(string $view): void
    { ?>
        <nav class="e2n-parent-main-tabs" aria-label="<?php esc_attr_e('Navigation Parents', 'ecole2nat'); ?>">
            <a class="<?php echo $view !== 'competitions' ? 'is-active' : ''; ?>" href="<?php echo esc_url(remove_query_arg('e2n_parent_view')); ?>"><?php esc_html_e('Progression', 'ecole2nat'); ?></a>
            <a class="<?php echo $view === 'competitions' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('e2n_parent_view','competitions')); ?>"><?php esc_html_e('Compétitions', 'ecole2nat'); ?></a>
        </nav><?php
    }

    private function renderCompetitions(array $report, string $previewMode, string $message): void
    {
        $swimmer=$report['swimmer']; $rows=$this->competitions->forSwimmer((int)$swimmer['id']); ?>
        <header class="e2n-parent-report-header"><div><p class="e2n-parent-eyebrow"><?php esc_html_e('Planning sportif', 'ecole2nat'); ?></p><h1><?php esc_html_e('Compétitions', 'ecole2nat'); ?></h1><div class="e2n-parent-swimmer-name"><p class="e2n-parent-group"><?php echo esc_html($swimmer['first_name'].' '.strtoupper((string)$swimmer['last_name'])); ?></p><?php $this->extranatLink($swimmer); ?></div></div></header>
        <?php if ($message==='saved') : ?><div class="e2n-parent-alert is-success"><?php esc_html_e('Votre réponse a bien été enregistrée.', 'ecole2nat'); ?></div><?php elseif ($message==='closed') : ?><div class="e2n-parent-alert"><?php esc_html_e('La période d’inscription est fermée.', 'ecole2nat'); ?></div><?php elseif ($message==='engaged') : ?><div class="e2n-parent-alert"><?php esc_html_e('Votre engagement Extranat est déjà validé et votre réponse ne peut plus être modifiée.', 'ecole2nat'); ?></div><?php elseif ($message==='invalid') : ?><div class="e2n-parent-alert"><?php esc_html_e('Merci de renseigner toutes les informations demandées.', 'ecole2nat'); ?></div><?php endif; ?>
        <div class="e2n-competition-list"><?php if($rows===[]):?><section class="e2n-parent-domain-card"><p><?php esc_html_e('Aucune compétition ne concerne actuellement cette catégorie.', 'ecole2nat'); ?></p></section><?php endif; ?>
        <?php foreach($rows as $row): $state=$row['registration_state']; $response=$row['response']??'';$isEngaged=$response==='yes'&&(int)($row['is_engaged']??0)===1;$twoDays=!empty($row['end_date'])&&$row['end_date']!==$row['start_date']; ?><article class="e2n-parent-domain-card e2n-competition-card">
            <header><div><h2><?php echo esc_html($row['name']); ?></h2><p><?php echo esc_html(implode(' · ',array_filter([$this->competitionDateLabel($row),$row['location']??'',$row['pool_length']??'']))); ?></p></div><strong><?php echo esc_html($state==='open'?__('Inscriptions ouvertes','ecole2nat'):($state==='upcoming'?__('À venir','ecole2nat'):($state==='cancelled'?__('Annulée','ecole2nat'):__('Inscriptions closes','ecole2nat')))); ?></strong></header>
            <p><?php echo esc_html(sprintf(__('Réponses du %1$s au %2$s','ecole2nat'),wp_date('d/m/Y',strtotime($row['registration_opens_at'])),wp_date('d/m/Y',strtotime($row['registration_closes_at'])))); ?></p>
            <?php if(!empty($row['information'])):?><details class="e2n-competition-briefing"><summary><?php esc_html_e('Briefing','ecole2nat'); ?></summary><div><?php echo nl2br(esc_html($row['information'])); ?></div></details><?php endif; ?>
            <?php if(!empty($row['technical_document_url'])||!empty($row['program_url'])||!empty($row['carpool_url'])||!empty($row['liveffn_url'])||!empty($row['photo_album_url'])):?><div class="e2n-competition-links">
                <?php if(!empty($row['technical_document_url'])):?><a class="e2n-btn e2n-document-btn" href="<?php echo esc_url($row['technical_document_url']); ?>" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">↗</span> <?php esc_html_e('Fiche technique','ecole2nat'); ?></a><?php endif; ?>
                <?php if(!empty($row['program_url'])):?><a class="e2n-btn e2n-secondary-btn" href="<?php echo esc_url($row['program_url']); ?>" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">↗</span> <?php esc_html_e('Programme','ecole2nat'); ?></a><?php endif; ?>
                <?php if(!empty($row['carpool_url'])):?><a class="e2n-btn e2n-secondary-btn" href="<?php echo esc_url($row['carpool_url']); ?>" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">🚗</span> <?php esc_html_e('Covoiturage','ecole2nat'); ?></a><?php endif; ?>
                <?php if(!empty($row['liveffn_url'])):?><a class="e2n-btn e2n-secondary-btn" href="<?php echo esc_url($row['liveffn_url']); ?>" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">◉</span> <?php esc_html_e('liveFFN','ecole2nat'); ?></a><?php endif; ?>
                <?php if(!empty($row['photo_album_url'])):?><a class="e2n-btn e2n-secondary-btn" href="<?php echo esc_url($row['photo_album_url']); ?>" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">📷</span> <?php esc_html_e('Album photo','ecole2nat'); ?></a><?php endif; ?>
            </div><?php endif; ?>
            <?php if($isEngaged):?><div class="e2n-competition-engaged-message"><?php esc_html_e('Vous êtes engagé sur cette compétition. En cas de forfait, vous devez nous prévenir le plus tôt possible (Whatsapp ou email). Hors raison médicale, les frais d’engagement vous seront facturés. Merci pour votre compréhension.','ecole2nat'); ?></div>
            <?php elseif($previewMode==='' && $state==='open'):?><form method="post" class="e2n-competition-response" data-e2n-competition-response><?php wp_nonce_field('e2n_parent_competition_response_'.(int)$row['id']); ?><input type="hidden" name="e2n_parent_action" value="competition_response"><input type="hidden" name="competition_id" value="<?php echo (int)$row['id']; ?>">
                <fieldset><legend><?php esc_html_e('Je participe à cette compétition','ecole2nat'); ?></legend><label><input type="radio" name="response" value="yes" <?php checked($response,'yes'); ?> required> <?php esc_html_e('Oui','ecole2nat'); ?></label><label><input type="radio" name="response" value="no" <?php checked($response,'no'); ?> required> <?php esc_html_e('Non','ecole2nat'); ?></label></fieldset>
                <fieldset><legend><?php esc_html_e('Je participe comme officiel (ou un parent)','ecole2nat'); ?></legend><label><input type="radio" name="parents_official" value="yes" <?php checked((string)($row['parents_official']??''),'1'); ?> required> <?php esc_html_e('Oui','ecole2nat'); ?></label><label><input type="radio" name="parents_official" value="no" <?php checked(isset($row['parents_official'])?(string)$row['parents_official']:'','0'); ?> required> <?php esc_html_e('Non','ecole2nat'); ?></label></fieldset>
                <?php if($twoDays):?><fieldset data-e2n-attendance-days><legend><?php esc_html_e('Je participe les jours suivants','ecole2nat'); ?></legend><label><input type="radio" name="attendance_days" value="both" <?php checked($row['attendance_days']??'','both'); ?>> <?php esc_html_e('Les 2 jours','ecole2nat'); ?></label><label><input type="radio" name="attendance_days" value="first_day" <?php checked($row['attendance_days']??'','first_day'); ?>> <?php echo esc_html(sprintf(__('Seulement le %s','ecole2nat'),wp_date('d/m/Y',strtotime($row['start_date'])))); ?></label><label><input type="radio" name="attendance_days" value="second_day" <?php checked($row['attendance_days']??'','second_day'); ?>> <?php echo esc_html(sprintf(__('Seulement le %s','ecole2nat'),wp_date('d/m/Y',strtotime($row['end_date'])))); ?></label></fieldset><?php endif; ?>
                <textarea name="comment" rows="2" placeholder="<?php esc_attr_e('Commentaire facultatif','ecole2nat'); ?>"><?php echo esc_textarea((string)($row['comment']??'')); ?></textarea><button type="submit" class="e2n-btn e2n-competition-submit"><span aria-hidden="true">✓</span> <?php esc_html_e('Enregistrer ma réponse','ecole2nat'); ?></button></form>
            <?php else:?><div class="e2n-competition-answer"><p><strong><?php esc_html_e('Réponse :','ecole2nat'); ?></strong> <?php echo esc_html($response==='yes'?__('Oui','ecole2nat'):($response==='no'?__('Non','ecole2nat'):__('Non renseigné','ecole2nat'))); ?></p><p><strong><?php esc_html_e('Parents officiels :','ecole2nat'); ?></strong> <?php echo esc_html($row['parents_official']===null?__('Non renseigné','ecole2nat'):((int)$row['parents_official']===1?__('Oui','ecole2nat'):__('Non','ecole2nat'))); ?></p><?php if($twoDays&&$response==='yes'):?><p><strong><?php esc_html_e('Jours :','ecole2nat'); ?></strong> <?php echo esc_html($this->attendanceDaysLabel((string)($row['attendance_days']??''),$row)); ?></p><?php endif; ?></div><?php endif; ?>
            <?php $isExpired=$response===''&&$state==='closed';$responseStatusClass=$response==='no'?'is-declined':($isEngaged?'is-confirmed':($response==='yes'?'is-pending':($isExpired?'is-expired':'is-unanswered')));$responseStatusLabel=$response==='no'?__('Ne participe pas','ecole2nat'):($isEngaged?__('Inscription Extranat validée','ecole2nat'):($response==='yes'?__('En attente de l’inscription Extranat par le coach','ecole2nat'):($isExpired?__('Délai de réponse dépassé','ecole2nat'):__('En attente de réponse','ecole2nat')))); ?><div class="e2n-parent-competition-status <?php echo esc_attr($responseStatusClass); ?>"><?php echo esc_html($responseStatusLabel); ?></div>
        </article><?php endforeach; ?></div><?php
    }

    private function competitionDateLabel(array $competition): string
    {
        $start=wp_date('d/m/Y',strtotime($competition['start_date']));
        if(empty($competition['end_date'])||$competition['end_date']===$competition['start_date'])return $start;
        return sprintf(__('Du %1$s au %2$s','ecole2nat'),$start,wp_date('d/m/Y',strtotime($competition['end_date'])));
    }

    private function extranatLink(array $swimmer): void
    {
        $url = Extranat::swimmerUrl($swimmer['licence_number'] ?? null);
        if ($url === '') return; ?>
        <a class="e2n-parent-extranat-link" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Fiche Extranat', 'ecole2nat'); ?></a><?php
    }

    private function attendanceDaysLabel(string $value,array $competition): string
    {
        return match($value){'both'=>__('Les 2 jours','ecole2nat'),'first_day'=>sprintf(__('Seulement le %s','ecole2nat'),wp_date('d/m/Y',strtotime($competition['start_date']))),'second_day'=>sprintf(__('Seulement le %s','ecole2nat'),wp_date('d/m/Y',strtotime($competition['end_date']))),default=>__('Non renseigné','ecole2nat')};
    }

    private function parentStatusLabel(string $status): string
    {
        return match ($status) {
            'acquired' => __('Acquis', 'ecole2nat'),
            'in_progress' => __('En progression', 'ecole2nat'),
            default => __('À découvrir', 'ecole2nat'),
        };
    }

    private function summarySentence(array $counts, int $total): string
    {
        if ($total === 0) {
            return __('Le référentiel pédagogique de cette catégorie est en cours de préparation.', 'ecole2nat');
        }

        $acquired = (int) $counts['acquired'];
        $inProgress = (int) $counts['in_progress'];
        $ratio = $acquired / $total;

        if ($ratio >= 0.8) {
            return __('Une très grande partie du parcours est déjà acquise. Bravo pour ces progrès !', 'ecole2nat');
        }

        if ($ratio >= 0.5) {
            return __('Le parcours avance régulièrement, avec de nombreuses compétences déjà acquises.', 'ecole2nat');
        }

        if ($acquired > 0 || $inProgress > 0) {
            return __('Les apprentissages progressent : plusieurs compétences sont déjà acquises ou en cours.', 'ecole2nat');
        }

        return __('Le parcours commence : de nombreuses découvertes sont à venir.', 'ecole2nat');
    }
}
