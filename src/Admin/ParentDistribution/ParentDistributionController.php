<?php

namespace Ecole2Nat\Admin\ParentDistribution;

use Ecole2Nat\ParentPortal\ParentDistributionService;

if (!defined('ABSPATH')) {
    exit;
}

class ParentDistributionController
{
    private ParentDistributionService $service;

    public function __construct()
    {
        $this->service = new ParentDistributionService();
    }

    public function register(): void
    {
        add_action(
            'admin_post_e2n_parent_distribution_csv',
            [$this, 'exportCsv']
        );
    }

    public function exportCsv(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Accès refusé.', 'ecole2nat'));
        }

        check_admin_referer('e2n_parent_distribution_csv');

        $rows = $this->service->batch(get_current_user_id());

        if ($rows === []) {
            wp_die(esc_html__('Aucun lot de codes n’est disponible.', 'ecole2nat'));
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="ecole2nat-acces-parents-' . wp_date('Y-m-d-His') . '.csv"');

        $output = fopen('php://output', 'wb');

        if ($output === false) {
            wp_die(esc_html__('Impossible de générer le fichier CSV.', 'ecole2nat'));
        }

        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Nom', 'Prénom', 'Groupe', 'Email responsable', 'Code', 'URL'], ';');

        foreach ($rows as $row) {
            fputcsv(
                $output,
                [
                    $row['last_name'],
                    $row['first_name'],
                    $row['group_name'],
                    $row['email'],
                    $row['code'],
                    $row['portal_url'],
                ],
                ';'
            );
        }

        fclose($output);
        exit;
    }
}
