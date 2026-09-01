<?php

namespace Ecole2Nat\Competition;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) { exit; }

final class CompetitionBillingRepository
{
    public function rows(int $competitionId): array
    {
        global $wpdb;
        $sql = $wpdb->prepare(
            'SELECT s.id swimmer_id,s.first_name,s.last_name,g.name group_name,
                    i.id invoice_id,i.meal_quantity,i.night_quantity,i.meal_unit_price,
                    i.night_unit_price,i.individual_comment,i.status invoice_status,
                    i.invoice_number,i.issued_on,i.current_version
             FROM ' . Config::table('competition_registrations') . ' r
             INNER JOIN ' . Config::table('swimmers') . ' s ON s.id=r.swimmer_id
             LEFT JOIN ' . Config::table('groups') . ' g ON g.id=s.group_id
             LEFT JOIN ' . Config::table('competition_invoices') . ' i ON i.competition_id=r.competition_id AND i.swimmer_id=r.swimmer_id
             WHERE r.competition_id=%d AND r.response=\'yes\' AND r.is_engaged=1
             ORDER BY s.last_name,s.first_name',
            $competitionId
        );
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public function globalComment(int $competitionId): string
    {
        global $wpdb;
        return (string) ($wpdb->get_var($wpdb->prepare(
            'SELECT global_comment FROM ' . Config::table('competition_billing') . ' WHERE competition_id=%d',
            $competitionId
        )) ?? '');
    }

    public function currentVersion(int $competitionId, int $invoiceId): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT v.* FROM ' . Config::table('competition_invoices') . ' i
             INNER JOIN ' . Config::table('competition_invoice_versions') . ' v ON v.invoice_id=i.id AND v.version_number=i.current_version
             WHERE i.id=%d AND i.competition_id=%d AND i.status IN (\'generated\',\'payment_declared\')',
            $invoiceId, $competitionId
        ), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function currentVersionForSwimmer(int $competitionId, int $invoiceId, int $swimmerId): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT v.*,i.status invoice_status,i.payment_declared_at,i.payment_declared_comment
             FROM ' . Config::table('competition_invoices') . ' i
             INNER JOIN ' . Config::table('competition_invoice_versions') . ' v ON v.invoice_id=i.id AND v.version_number=i.current_version
             WHERE i.id=%d AND i.competition_id=%d AND i.swimmer_id=%d AND i.status IN (\'generated\',\'payment_declared\')',
            $invoiceId, $competitionId, $swimmerId
        ), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function declarePayment(int $competitionId, int $invoiceId, int $swimmerId, string $comment): array
    {
        global $wpdb;
        $invoice = $wpdb->get_row($wpdb->prepare(
            'SELECT i.id,i.status,i.invoice_number,v.total_amount,v.swimmer_name,v.competition_name
             FROM ' . Config::table('competition_invoices') . ' i
             INNER JOIN ' . Config::table('competition_invoice_versions') . ' v ON v.invoice_id=i.id AND v.version_number=i.current_version
             WHERE i.id=%d AND i.competition_id=%d AND i.swimmer_id=%d',
            $invoiceId, $competitionId, $swimmerId
        ), ARRAY_A);
        if (!is_array($invoice)) return ['success' => false, 'message' => 'invoice_unavailable'];
        if (($invoice['status'] ?? '') === 'payment_declared') return ['success' => true, 'message' => 'payment_already_declared'];
        if (($invoice['status'] ?? '') !== 'generated') return ['success' => false, 'message' => 'invoice_unavailable'];

        return ['success' => true, 'message' => 'ready', 'invoice' => $invoice, 'comment' => $comment];
    }

    public function confirmPaymentDeclaration(int $invoiceId, int $swimmerId, string $comment): bool
    {
        global $wpdb;
        return $wpdb->query($wpdb->prepare(
            'UPDATE ' . Config::table('competition_invoices') . '
             SET status=\'payment_declared\',payment_declared_at=%s,payment_declared_comment=%s,updated_at=%s
             WHERE id=%d AND swimmer_id=%d AND status=\'generated\'',
            current_time('mysql'), $comment, current_time('mysql'), $invoiceId, $swimmerId
        )) === 1;
    }

    public function engagedSwimmerIds(int $competitionId): array
    {
        global $wpdb;
        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            'SELECT swimmer_id FROM ' . Config::table('competition_registrations') . ' WHERE competition_id=%d AND response=\'yes\' AND is_engaged=1 ORDER BY swimmer_id',
            $competitionId
        )) ?: []);
    }

    public function save(int $competitionId, array $rows, string $globalComment, bool $generate, int $userId, array $snapshot): array
    {
        global $wpdb;
        $now = current_time('mysql');
        $today = current_time('Y-m-d');
        $wpdb->query('START TRANSACTION');

        try {
            $billingSql = $wpdb->prepare(
                'INSERT INTO ' . Config::table('competition_billing') . ' (competition_id,global_comment,updated_by,created_at,updated_at)
                 VALUES (%d,%s,%d,%s,%s)
                 ON DUPLICATE KEY UPDATE global_comment=VALUES(global_comment),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)',
                $competitionId, $globalComment, $userId, $now, $now
            );
            if ($wpdb->query($billingSql) === false) throw new \RuntimeException('billing');

            foreach ($rows as $swimmerId => $row) {
                $invoiceSql = $wpdb->prepare(
                    'INSERT INTO ' . Config::table('competition_invoices') . '
                     (competition_id,swimmer_id,meal_quantity,night_quantity,meal_unit_price,night_unit_price,individual_comment,status,created_at,updated_at)
                     VALUES (%d,%d,%d,%d,%s,%s,%s,\'draft\',%s,%s)
                     ON DUPLICATE KEY UPDATE
                     meal_quantity=IF(status=\'payment_declared\',meal_quantity,VALUES(meal_quantity)),
                     night_quantity=IF(status=\'payment_declared\',night_quantity,VALUES(night_quantity)),
                     meal_unit_price=IF(status=\'payment_declared\',meal_unit_price,VALUES(meal_unit_price)),
                     night_unit_price=IF(status=\'payment_declared\',night_unit_price,VALUES(night_unit_price)),
                     individual_comment=IF(status=\'payment_declared\',individual_comment,VALUES(individual_comment)),
                     updated_at=IF(status=\'payment_declared\',updated_at,VALUES(updated_at))',
                    $competitionId, $swimmerId, $row['meal_quantity'], $row['night_quantity'], $row['meal_unit_price'], $row['night_unit_price'], $row['individual_comment'], $now, $now
                );
                if ($wpdb->query($invoiceSql) === false) throw new \RuntimeException('invoice');
            }

            $generated = 0;
            if ($generate) {
                foreach (array_keys($rows) as $swimmerId) {
                    $invoice = $wpdb->get_row($wpdb->prepare(
                        'SELECT i.*,CONCAT(s.first_name,\' \',s.last_name) swimmer_name,c.name competition_name,c.start_date competition_start_date
                         FROM ' . Config::table('competition_invoices') . ' i
                         INNER JOIN ' . Config::table('swimmers') . ' s ON s.id=i.swimmer_id
                         INNER JOIN ' . Config::table('competitions') . ' c ON c.id=i.competition_id
                         WHERE i.competition_id=%d AND i.swimmer_id=%d FOR UPDATE',
                        $competitionId, $swimmerId
                    ), ARRAY_A);
                    if (!is_array($invoice)) throw new \RuntimeException('invoice_missing');
                    if (($invoice['status'] ?? '') === 'payment_declared') continue;

                    $totalCents = $this->moneyToCents((string) $invoice['meal_unit_price']) * (int) $invoice['meal_quantity']
                        + $this->moneyToCents((string) $invoice['night_unit_price']) * (int) $invoice['night_quantity'];
                    if ($totalCents <= 0) {
                        if (!empty($invoice['invoice_number'])) throw new \RuntimeException('empty_generated_invoice');
                        continue;
                    }

                    if (!empty($invoice['invoice_number']) && (int) $invoice['current_version'] > 0) {
                        $currentVersion = $wpdb->get_row($wpdb->prepare(
                            'SELECT * FROM ' . Config::table('competition_invoice_versions') . ' WHERE invoice_id=%d AND version_number=%d',
                            (int) $invoice['id'], (int) $invoice['current_version']
                        ), ARRAY_A);
                        if (is_array($currentVersion) && $this->sameVersion($currentVersion, $invoice, $globalComment, $snapshot, $totalCents)) continue;
                    }

                    $number = (string) ($invoice['invoice_number'] ?? '');
                    if ($number === '') $number = $this->nextNumber((int) wp_date('Y'), $now);
                    $issuedOn = !empty($invoice['issued_on']) ? (string) $invoice['issued_on'] : $today;
                    $version = (int) $invoice['current_version'] + 1;

                    if ($wpdb->update(Config::table('competition_invoices'), [
                        'status' => 'generated', 'invoice_number' => $number, 'issued_on' => $issuedOn,
                        'current_version' => $version, 'generated_at' => $now, 'generated_by' => $userId, 'updated_at' => $now,
                    ], ['id' => (int) $invoice['id']], ['%s','%s','%s','%d','%s','%d','%s'], ['%d']) === false) {
                        throw new \RuntimeException('invoice_update');
                    }

                    $versionData = [
                        'invoice_id' => (int) $invoice['id'], 'version_number' => $version,
                        'invoice_number' => $number, 'issued_on' => $issuedOn,
                        'swimmer_name' => (string) $invoice['swimmer_name'], 'competition_name' => (string) $invoice['competition_name'],
                        'competition_start_date' => (string) $invoice['competition_start_date'],
                        'meal_quantity' => (int) $invoice['meal_quantity'], 'night_quantity' => (int) $invoice['night_quantity'],
                        'meal_unit_price' => (string) $invoice['meal_unit_price'], 'night_unit_price' => (string) $invoice['night_unit_price'],
                        'total_amount' => $this->centsToMoney($totalCents), 'global_comment' => $globalComment,
                        'individual_comment' => (string) ($invoice['individual_comment'] ?? ''),
                        'issuer_name' => $snapshot['issuer_name'], 'issuer_address' => $snapshot['issuer_address'],
                        'issuer_siret' => $snapshot['issuer_siret'], 'issuer_logo_id' => $snapshot['issuer_logo_id'],
                        'created_at' => $now, 'created_by' => $userId,
                    ];
                    if ($wpdb->insert(Config::table('competition_invoice_versions'), $versionData) === false) {
                        throw new \RuntimeException('invoice_version');
                    }
                    $generated++;
                }
            }

            $wpdb->query('COMMIT');
            return ['success' => true, 'generated' => $generated];
        } catch (\Throwable $exception) {
            $wpdb->query('ROLLBACK');
            return ['success' => false, 'message' => $exception->getMessage()];
        }
    }

    private function nextNumber(int $year, string $now): string
    {
        global $wpdb;
        $table = Config::table('invoice_sequences');
        if ($wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table} (calendar_year,last_number,updated_at) VALUES (%d,999,%s)",
            $year, $now
        )) === false) throw new \RuntimeException('sequence_create');
        $last = $wpdb->get_var($wpdb->prepare("SELECT last_number FROM {$table} WHERE calendar_year=%d FOR UPDATE", $year));
        if ($last === null) throw new \RuntimeException('sequence_read');
        $next = max(999, (int) $last) + 1;
        if ($wpdb->update($table, ['last_number' => $next, 'updated_at' => $now], ['calendar_year' => $year], ['%d','%s'], ['%d']) === false) {
            throw new \RuntimeException('sequence_update');
        }
        return sprintf('F%02d.%d', $year % 100, $next);
    }

    private function moneyToCents(string $value): int
    {
        return (int) round((float) $value * 100);
    }

    private function centsToMoney(int $value): string
    {
        return number_format($value / 100, 2, '.', '');
    }

    private function sameVersion(array $version, array $invoice, string $globalComment, array $snapshot, int $totalCents): bool
    {
        return (int) $version['meal_quantity'] === (int) $invoice['meal_quantity']
            && (int) $version['night_quantity'] === (int) $invoice['night_quantity']
            && $this->moneyToCents((string) $version['meal_unit_price']) === $this->moneyToCents((string) $invoice['meal_unit_price'])
            && $this->moneyToCents((string) $version['night_unit_price']) === $this->moneyToCents((string) $invoice['night_unit_price'])
            && $this->moneyToCents((string) $version['total_amount']) === $totalCents
            && (string) $version['global_comment'] === $globalComment
            && (string) $version['individual_comment'] === (string) ($invoice['individual_comment'] ?? '')
            && (string) $version['swimmer_name'] === (string) $invoice['swimmer_name']
            && (string) $version['competition_name'] === (string) $invoice['competition_name']
            && (string) $version['competition_start_date'] === (string) $invoice['competition_start_date']
            && (string) $version['issuer_name'] === (string) $snapshot['issuer_name']
            && (string) $version['issuer_address'] === (string) $snapshot['issuer_address']
            && (string) $version['issuer_siret'] === (string) $snapshot['issuer_siret']
            && (int) $version['issuer_logo_id'] === (int) $snapshot['issuer_logo_id'];
    }
}
