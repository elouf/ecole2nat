<?php

namespace Ecole2Nat\ParentPortal;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) {
    exit;
}

class ParentPortal
{
    private ParentAccessService $service;

    public function __construct()
    {
        $this->service = new ParentAccessService();
    }

    public function register(): void
    {
        add_shortcode('e2n_parent_report', [$this, 'renderShortcode']);
        add_action('wp', [$this, 'registerNoIndex']);
    }

    public function registerNoIndex(): void
    {
        global $post;

        if ($post instanceof \WP_Post && has_shortcode((string) $post->post_content, 'e2n_parent_report')) {
            add_filter('wp_robots', static function (array $robots): array {
                $robots['noindex'] = true;
                $robots['nofollow'] = true;

                return $robots;
            });
        }
    }

    public function renderShortcode(): string
    {
        wp_enqueue_style(
            'e2n-parent-portal',
            Config::pluginUrl() . 'assets/css/parent-portal.css',
            [],
            Config::version()
        );

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

        $swimmerId = $this->service->authenticatedSwimmerId();

        ob_start();
        ?>
        <section class="e2n-parent-portal">
            <?php if ($swimmerId <= 0) : ?>
                <?php $this->renderLogin($message); ?>
            <?php else : ?>
                <?php
                $report = $this->service->report($swimmerId);

                if ($report === null) {
                    $this->service->clearAccessCookie();
                    $this->renderLogin('report_unavailable');
                } else {
                    $this->renderReport($report);
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
                    maxlength="8"
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

    private function renderReport(array $report): void
    {
        $swimmer = $report['swimmer'];
        $counts = $report['counts'];
        $total = (int) $report['total'];
        $acquired = (int) $counts['acquired'];
        $percentage = $total > 0 ? (int) round(($acquired / $total) * 100) : 0;
        ?>
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
                            __('Groupe %s · Catégorie %s', 'ecole2nat'),
                            $swimmer['group_name'] ?: __('Non affecté', 'ecole2nat'),
                            $swimmer['category_name'] ?: __('Non définie', 'ecole2nat')
                        )
                    );
                    ?>
                </p>
            </div>

            <div class="e2n-parent-actions">
                <button type="button" class="e2n-parent-print" onclick="window.print()">
                    <?php esc_html_e('Imprimer', 'ecole2nat'); ?>
                </button>
                <form method="post">
                    <?php wp_nonce_field('e2n_parent_logout'); ?>
                    <input type="hidden" name="e2n_parent_action" value="logout">
                    <button type="submit" class="e2n-parent-change-code">
                        <?php esc_html_e('Changer de code', 'ecole2nat'); ?>
                    </button>
                </form>
            </div>
        </header>

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
                                <div>
                                    <strong><?php echo esc_html($skill['name']); ?></strong>
                                    <span><?php echo esc_html($this->parentStatusLabel($skill['status'])); ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($swimmer['parent_message'])) : ?>
            <aside class="e2n-parent-message-card">
                <h2><?php esc_html_e('Message de l’entraîneur', 'ecole2nat'); ?></h2>
                <p><?php echo nl2br(esc_html((string) $swimmer['parent_message'])); ?></p>
            </aside>
        <?php endif; ?>
        <?php
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
