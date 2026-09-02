<?php

namespace Ecole2Nat\Competition;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) { exit; }

final class CompetitionBillingService
{
    private CompetitionRepository $competitions;
    private CompetitionBillingRepository $billing;

    public function __construct()
    {
        $this->competitions = new CompetitionRepository();
        $this->billing = new CompetitionBillingRepository();
    }

    public function detail(int $competitionId): ?array
    {
        $competition = $this->competitions->find($competitionId);
        if ($competition === null || ($competition['status'] ?? '') === 'draft') return null;
        return [
            'competition' => $competition,
            'rows' => $this->billing->rows($competitionId),
            'global_comment' => $this->billing->globalComment($competitionId),
            'meal_price' => Config::invoiceMealPrice(),
            'night_price' => Config::invoiceNightPrice(),
        ];
    }

    public function save(int $competitionId, array $input, bool $generate, int $userId): array
    {
        $competition = $this->competitions->find($competitionId);
        if ($competition === null || ($competition['status'] ?? '') === 'draft') return ['success' => false, 'message' => 'competition'];

        $engagedIds = $this->billing->engagedSwimmerIds($competitionId);
        sort($engagedIds);
        $submitted = isset($input['billing']) && is_array($input['billing']) ? $input['billing'] : [];
        $submittedIds = array_map('intval', array_keys($submitted));
        sort($submittedIds);
        if ($submittedIds !== $engagedIds) return ['success' => false, 'message' => 'swimmers'];

        $rows = [];
        foreach ($engagedIds as $swimmerId) {
            $row = is_array($submitted[$swimmerId] ?? null) ? $submitted[$swimmerId] : [];
            $rows[$swimmerId] = [
                'meal_quantity' => min(99, absint($row['meal_quantity'] ?? 0)),
                'night_quantity' => min(99, absint($row['night_quantity'] ?? 0)),
                'meal_unit_price' => Config::invoiceMealPrice(),
                'night_unit_price' => Config::invoiceNightPrice(),
                'other_amount' => $this->money($row['other_amount'] ?? 0),
                'individual_comment' => sanitize_textarea_field(wp_unslash((string) ($row['individual_comment'] ?? ''))),
            ];
        }

        return $this->billing->save(
            $competitionId,
            $rows,
            sanitize_textarea_field(wp_unslash((string) ($input['global_comment'] ?? ''))),
            $generate,
            $userId,
            [
                'issuer_name' => Config::invoiceIssuerName(),
                'issuer_address' => Config::invoiceIssuerAddress(),
                'issuer_siret' => Config::invoiceIssuerSiret(),
                'issuer_logo_id' => Config::invoiceLogoId(),
            ]
        );
    }

    public function invoice(int $competitionId, int $invoiceId): ?array
    {
        if ($invoiceId <= 0 || $this->competitions->find($competitionId) === null) return null;
        return $this->billing->currentVersion($competitionId, $invoiceId);
    }

    public function parentInvoice(int $competitionId, int $invoiceId, int $swimmerId): ?array
    {
        if ($competitionId <= 0 || $invoiceId <= 0 || $swimmerId <= 0) return null;
        return $this->billing->currentVersionForSwimmer($competitionId, $invoiceId, $swimmerId);
    }

    public function declarePayment(int $competitionId, int $invoiceId, int $swimmerId, string $comment): array
    {
        $comment = sanitize_textarea_field(wp_unslash($comment));
        $result = $this->billing->declarePayment($competitionId, $invoiceId, $swimmerId, $comment);
        if (!$result['success'] || $result['message'] !== 'ready') return $result;

        $invoice = $result['invoice'];
        $body = sprintf(
            "Un paiement vient d’être déclaré depuis Ecole2Nat’.\n\nFacture : %s\nNageur : %s\nCompétition : %s\nMontant : %s €\n\nCommentaire :\n%s",
            $invoice['invoice_number'], $invoice['swimmer_name'], $invoice['competition_name'],
            number_format((float) $invoice['total_amount'], 2, ',', ' '),
            $comment !== '' ? $comment : 'Aucun commentaire.'
        );
        $subject = sprintf('Paiement déclaré — %s — %s', $invoice['invoice_number'], $invoice['swimmer_name']);
        if (!wp_mail(Config::treasurerEmail(), $subject, $body)) {
            return ['success' => false, 'message' => 'payment_email_failed'];
        }
        if (!$this->billing->confirmPaymentDeclaration($invoiceId, $swimmerId, $comment)) {
            return ['success' => false, 'message' => 'payment_save_failed'];
        }
        return ['success' => true, 'message' => 'payment_declared'];
    }

    private function money(mixed $value): string
    {
        $value = str_replace(',', '.', trim((string) $value));
        $amount = is_numeric($value) ? (float) $value : 0.0;

        return number_format(min(99999999.99, max(0.0, $amount)), 2, '.', '');
    }
}
