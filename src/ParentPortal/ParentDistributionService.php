<?php

namespace Ecole2Nat\ParentPortal;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) {
    exit;
}

class ParentDistributionService
{
    private ParentAccessService $accessService;
    private ParentAccessRepository $repository;

    public function __construct()
    {
        $this->accessService = new ParentAccessService();
        $this->repository = new ParentAccessRepository();
    }

    public function categories(): array
    {
        return $this->repository->distributionCategories();
    }

    public function groups(array $categoryIds = []): array
    {
        return $this->repository->distributionGroups($categoryIds);
    }

    public function swimmers(array $filters = []): array
    {
        return $this->repository->swimmersForDistribution($filters);
    }

    public function swimmersByGroup(int $groupId): array
    {
        return $this->repository->swimmersByGroupForDistribution($groupId);
    }

    public function sendForSwimmer(int $swimmerId): array
    {
        $swimmer = $this->repository->findSwimmer($swimmerId);
        if ($swimmer === null) {
            return ['success' => false, 'message' => 'invalid'];
        }

        $email = sanitize_email((string) ($swimmer['responsible_email'] ?? ''));
        if ($email === '' || !is_email($email)) {
            return ['success' => false, 'message' => 'missing_email', 'swimmer_id' => $swimmerId];
        }

        $portalUrl = $this->accessService->portalUrl();
        if ($portalUrl === '') {
            return ['success' => false, 'message' => 'missing_portal'];
        }

        $generated = $this->accessService->generateCode($swimmerId);
        if (!$generated['success']) {
            return $generated;
        }

        $code = (string) $generated['code'];
        $subject = sprintf(
            __('Accès au parcours de natation de %s', 'ecole2nat'),
            (string) $swimmer['first_name']
        );
        $sent = wp_mail($email, $subject, $this->emailBody($swimmer, $portalUrl, $code));
        if (!$sent) {
            return ['success' => false, 'message' => 'mail_error', 'swimmer_id' => $swimmerId, 'email' => $email];
        }

        $this->repository->markDistributed($swimmerId, 'email', $email);

        return [
            'success' => true,
            'message' => 'mail_sent',
            'swimmer_id' => $swimmerId,
            'last_name' => (string) $swimmer['last_name'],
            'first_name' => (string) $swimmer['first_name'],
            'group_name' => (string) ($swimmer['group_name'] ?? ''),
            'email' => $email,
            'code' => $code,
            'portal_url' => $portalUrl,
        ];
    }

    public function sendSelected(array $swimmerIds, ?int $batchUserId = null): array
    {
        $results = ['sent' => 0, 'failed' => 0, 'missing_email' => 0, 'items' => [], 'coupons' => []];
        foreach (array_values(array_unique(array_map('absint', $swimmerIds))) as $swimmerId) {
            if ($swimmerId <= 0) {
                continue;
            }
            $result = $this->sendForSwimmer($swimmerId);
            $results['items'][] = $result;
            if ($result['success']) {
                $results['sent']++;
                $results['coupons'][] = $this->couponRowFromResult($result);
            } elseif (($result['message'] ?? '') === 'missing_email') {
                $results['missing_email']++;
                $results['failed']++;
            } else {
                $results['failed']++;
            }
        }
        if ($batchUserId !== null && $results['coupons'] !== []) {
            $this->saveBatch($batchUserId, $results['coupons']);
        }
        return $results;
    }

    public function sendMissingByFilters(array $filters, int $batchUserId): array
    {
        $filters['access_status'] = 'not_sent';
        $ids = array_map(
            static fn(array $swimmer): int => (int) $swimmer['id'],
            $this->swimmers($filters)
        );
        return $this->sendSelected($ids, $batchUserId);
    }

    public function sendMissingByGroup(int $groupId): array
    {
        return $this->sendMissingByFilters(['group_id' => $groupId], get_current_user_id());
    }

    public function prepareCoupons(array $swimmerIds, int $userId): array
    {
        $rows = [];
        $portalUrl = $this->accessService->portalUrl();
        if ($portalUrl === '') {
            return ['success' => false, 'message' => 'missing_portal'];
        }

        foreach (array_values(array_unique(array_map('absint', $swimmerIds))) as $swimmerId) {
            $swimmer = $this->repository->findSwimmer($swimmerId);
            if ($swimmer === null) {
                continue;
            }
            $generated = $this->accessService->generateCode($swimmerId);
            if (!$generated['success']) {
                continue;
            }
            $rows[] = [
                'swimmer_id' => $swimmerId,
                'last_name' => (string) $swimmer['last_name'],
                'first_name' => (string) $swimmer['first_name'],
                'group_name' => (string) ($swimmer['group_name'] ?? ''),
                'email' => (string) ($swimmer['responsible_email'] ?? ''),
                'code' => (string) $generated['code'],
                'portal_url' => $portalUrl,
            ];
        }
        if ($rows === []) {
            return ['success' => false, 'message' => 'error'];
        }
        $this->saveBatch($userId, $rows);
        return ['success' => true, 'message' => 'coupons_ready', 'count' => count($rows)];
    }

    public function batch(int $userId): array
    {
        $rows = get_transient($this->batchTransientKey($userId));
        return is_array($rows) ? $rows : [];
    }

    public function clearBatch(int $userId): void
    {
        delete_transient($this->batchTransientKey($userId));
    }

    public function batchTransientKey(int $userId): string
    {
        return 'e2n_parent_distribution_batch_' . $userId;
    }

    private function saveBatch(int $userId, array $rows): void
    {
        set_transient($this->batchTransientKey($userId), $rows, 30 * MINUTE_IN_SECONDS);
    }

    private function couponRowFromResult(array $result): array
    {
        return [
            'swimmer_id' => (int) $result['swimmer_id'],
            'last_name' => (string) $result['last_name'],
            'first_name' => (string) $result['first_name'],
            'group_name' => (string) $result['group_name'],
            'email' => (string) $result['email'],
            'code' => (string) $result['code'],
            'portal_url' => (string) $result['portal_url'],
        ];
    }

    private function emailBody(array $swimmer, string $portalUrl, string $code): string
    {
        return sprintf(
            __("Bonjour,\n\nVous pouvez consulter le parcours de natation de %1\$s à l'adresse suivante :\n%2\$s\n\nCode d'accès : %3\$s\n\nCe code est personnel et permet d'accéder au parcours de votre enfant.\n\n%4\$s", 'ecole2nat'),
            (string) $swimmer['first_name'],
            $portalUrl,
            $code,
            Config::parentEmailSignature()
        );
    }
}
