<?php

namespace Ecole2Nat\Admin\Pages;

use Ecole2Nat\Session\SessionExerciseService;
use Ecole2Nat\Session\SessionPartService;
use Ecole2Nat\Session\SessionService;

if (!defined('ABSPATH')) {
    exit;
}

class SessionPrintPage
{
    private SessionService $sessionService;
    private SessionPartService $partService;
    private SessionExerciseService $exerciseService;

    public function __construct()
    {
        $this->sessionService = new SessionService();
        $this->partService = new SessionPartService();
        $this->exerciseService = new SessionExerciseService();
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Vous n’avez pas les droits nécessaires.', 'ecole2nat'));
        }

        $sessionId = isset($_GET['session_id']) ? absint($_GET['session_id']) : 0;
        $session = $sessionId > 0 ? $this->sessionService->find($sessionId) : null;

        if ($session === null) {
            wp_die(esc_html__('Séance introuvable.', 'ecole2nat'));
        }

        $parts = $this->partService->allBySession($sessionId);
        $partsWithExercises = [];
        $totalDuration = 0;

        foreach ($parts as $part) {
            $exercises = $this->exerciseService->allByPart((int) $part['id']);
            $partDuration = array_sum(
                array_map(
                    static fn(array $exercise): int =>
                        (int) ($exercise['duration'] ?? 0),
                    $exercises
                )
            );
            $totalDuration += $partDuration;
            $partsWithExercises[] = [
                'part' => $part,
                'exercises' => $exercises,
                'duration' => $partDuration,
            ];
        }
        ?>
        <style>
            .e2n-print-sheet {
                max-width: 900px;
                margin: 24px auto;
                padding: 32px;
                background: #fff;
                color: #1d2327;
                font-size: 14px;
                line-height: 1.45;
            }
            .e2n-print-header {
                border-bottom: 2px solid #1d2327;
                margin-bottom: 24px;
                padding-bottom: 16px;
            }
            .e2n-print-header h1 { margin: 0 0 8px; }
            .e2n-print-meta { display: flex; gap: 24px; flex-wrap: wrap; }
            .e2n-print-part {
                break-inside: avoid;
                border: 1px solid #c3c4c7;
                margin-bottom: 18px;
                padding: 18px;
            }
            .e2n-print-part h2 { margin: 0 0 12px; }
            .e2n-print-exercise { margin-bottom: 14px; }
            .e2n-print-exercise:last-child { margin-bottom: 0; }
            .e2n-print-actions { max-width: 900px; margin: 20px auto; }
            @media print {
                #adminmenumain,
                #wpadminbar,
                #wpfooter,
                .update-nag,
                .notice,
                .e2n-print-actions {
                    display: none !important;
                }
                #wpcontent,
                #wpbody-content {
                    margin: 0 !important;
                    padding: 0 !important;
                }
                .e2n-print-sheet {
                    max-width: none;
                    margin: 0;
                    padding: 12mm;
                }
                @page { size: A4; margin: 10mm; }
            }
        </style>

        <div class="e2n-print-actions">
            <button type="button" class="button button-primary" onclick="window.print();">
                <?php esc_html_e('Imprimer', 'ecole2nat'); ?>
            </button>
            <a
                class="button"
                href="<?php echo esc_url(
                    add_query_arg(
                        ['page' => 'ecole2nat-session', 'session_id' => $sessionId],
                        admin_url('admin.php')
                    )
                ); ?>"
            >
                <?php esc_html_e('Retour à la séance', 'ecole2nat'); ?>
            </a>
        </div>

        <article class="e2n-print-sheet">
            <header class="e2n-print-header">
                <h1><?php echo esc_html($session['name']); ?></h1>
                <div class="e2n-print-meta">
                    <span><strong><?php esc_html_e('Catégorie :', 'ecole2nat'); ?></strong> <?php echo esc_html($session['category_name']); ?></span>
                    <span><strong><?php esc_html_e('Durée totale :', 'ecole2nat'); ?></strong> <?php echo esc_html((string) $totalDuration); ?> min</span>
                </div>
                <?php if (!empty($session['objectives'])) : ?>
                    <p><strong><?php esc_html_e('Objectifs :', 'ecole2nat'); ?></strong> <?php echo nl2br(esc_html($session['objectives'])); ?></p>
                <?php endif; ?>
            </header>

            <?php if ($partsWithExercises === []) : ?>
                <p><?php esc_html_e('Cette séance ne contient aucune partie.', 'ecole2nat'); ?></p>
            <?php else : ?>
                <?php foreach ($partsWithExercises as $item) : ?>
                    <section class="e2n-print-part">
                        <h2>
                            <?php
                            echo esc_html(
                                sprintf(
                                    '%d. %s — %d min',
                                    (int) $item['part']['position'],
                                    $item['part']['title'],
                                    (int) $item['duration']
                                )
                            );
                            ?>
                        </h2>

                        <?php if ($item['exercises'] === []) : ?>
                            <p><?php esc_html_e('Aucun exercice.', 'ecole2nat'); ?></p>
                        <?php else : ?>
                            <?php foreach ($item['exercises'] as $exercise) : ?>
                                <div class="e2n-print-exercise">
                                    <strong>
                                        <?php echo esc_html($exercise['name']); ?>
                                        — <?php echo esc_html((string) ($exercise['duration'] ?? 0)); ?> min
                                    </strong>
                                    <?php if (!empty($exercise['description'])) : ?>
                                        <div><?php echo nl2br(esc_html($exercise['description'])); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($exercise['coach_notes'])) : ?>
                                        <div>
                                            <em><?php esc_html_e('Consignes :', 'ecole2nat'); ?></em>
                                            <?php echo nl2br(esc_html($exercise['coach_notes'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </article>
        <?php
    }
}
