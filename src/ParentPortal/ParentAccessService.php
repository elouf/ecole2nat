<?php

namespace Ecole2Nat\ParentPortal;

if (!defined('ABSPATH')) {
    exit;
}

class ParentAccessService
{
    private const MAX_ATTEMPTS = 5;
    private const BLOCK_SECONDS = 900;
    private const COOKIE_TTL = 43200;

    private ParentAccessRepository $repository;

    public function __construct(?ParentAccessRepository $repository = null)
    {
        $this->repository = $repository ?? new ParentAccessRepository();
    }

    public function findSwimmer(int $swimmerId): ?array
    {
        return $this->repository->findSwimmer($swimmerId);
    }

    public function permanentCode(int $swimmerId, bool $activate = true): array
    {
        $swimmer = $this->repository->findSwimmer($swimmerId);
        if ($swimmer === null || empty($swimmer['birth_date'])) {
            return ['success' => false, 'message' => 'invalid'];
        }

        $code = $this->codeForSwimmer($swimmer);

        return $code !== ''
            ? ['success' => true, 'message' => 'access_retrieved', 'code' => $code]
            : ['success' => false, 'message' => 'invalid'];
    }

    public function resetCode(int $swimmerId): array
    {
        return $this->permanentCode($swimmerId);
    }

    public function disable(int $swimmerId): array
    {
        $disabled = $this->repository->disableAccess($swimmerId);

        return [
            'success' => $disabled,
            'message' => $disabled ? 'access_disabled' : 'error',
        ];
    }

    public function saveParentMessage(int $swimmerId, string $message): array
    {
        $saved = $this->repository->saveParentMessage(
            $swimmerId,
            sanitize_textarea_field($message)
        );

        return [
            'success' => $saved,
            'message' => $saved ? 'parent_message_saved' : 'error',
        ];
    }

    public function authenticate(string $rawCode): array
    {
        $rateKey = $this->rateKey();
        $rate = get_transient($rateKey);

        if (is_array($rate) && !empty($rate['blocked_until']) && time() < (int) $rate['blocked_until']) {
            return ['success' => false, 'message' => 'temporarily_blocked'];
        }

        $code = $this->normalizeCode($rawCode);

        if (!preg_match('/^([A-Z]+)(\d{8})$/', $code, $parts)) {
            $blocked = $this->recordFailure($rateKey, $rate);
            $this->repository->logAttempt(null, false, $this->ipHash());

            return [
                'success' => false,
                'message' => $blocked ? 'temporarily_blocked' : 'invalid_code',
            ];
        }

        $date = \DateTimeImmutable::createFromFormat('!dmY', $parts[2]);
        $birthDate = $date instanceof \DateTimeImmutable && $date->format('dmY') === $parts[2]
            ? $date->format('Y-m-d')
            : '';
        $matches = [];

        foreach ($birthDate !== '' ? $this->repository->activeSwimmersBornOn($birthDate) : [] as $candidate) {
            if (hash_equals($this->codeForSwimmer($candidate), $code)) {
                $matches[] = $candidate;
            }
        }

        $swimmer = count($matches) === 1 ? $matches[0] : null;

        if ($swimmer === null) {
            $blocked = $this->recordFailure($rateKey, $rate);
            $this->repository->logAttempt(null, false, $this->ipHash());

            return [
                'success' => false,
                'message' => $blocked ? 'temporarily_blocked' : (count($matches) > 1 ? 'ambiguous_code' : 'invalid_code'),
            ];
        }

        delete_transient($rateKey);
        $swimmerId = (int) $swimmer['id'];
        $this->repository->markUsed($swimmerId);
        $this->repository->logAttempt($swimmerId, true, $this->ipHash());
        $this->setAccessCookie($swimmerId, $this->hashCode($code));

        return [
            'success' => true,
            'message' => 'authenticated',
            'swimmer_id' => $swimmerId,
        ];
    }

    public function authenticatedSwimmerId(): int
    {
        $cookieName = $this->cookieName();

        if (empty($_COOKIE[$cookieName])) {
            return 0;
        }

        $token = sanitize_text_field(wp_unslash($_COOKIE[$cookieName]));
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return 0;
        }

        [$swimmerId, $expires, $signature] = $parts;

        if (!ctype_digit($swimmerId) || !ctype_digit($expires) || time() > (int) $expires) {
            return 0;
        }

        $swimmer = $this->repository->findSwimmer((int) $swimmerId);

        if (
            $swimmer === null
            || (int) ($swimmer['is_active'] ?? 0) !== 1
            || empty($swimmer['birth_date'])
        ) {
            return 0;
        }

        $expected = $this->signToken(
            (int) $swimmerId,
            (int) $expires,
            $this->hashCode($this->codeForSwimmer($swimmer))
        );

        if (!hash_equals($expected, $signature)) {
            return 0;
        }

        return (int) $swimmerId;
    }

    public function clearAccessCookie(): void
    {
        unset($_COOKIE[$this->cookieName()]);

        setcookie(
            $this->cookieName(),
            '',
            [
                'expires' => time() - HOUR_IN_SECONDS,
                'path' => COOKIEPATH ?: '/',
                'domain' => (string) COOKIE_DOMAIN,
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    public function report(int $swimmerId, int $seasonId = 0): ?array
    {
        return $this->repository->report($swimmerId, $seasonId);
    }


    public function previewUrl(int $swimmerId): string
    {
        if ($swimmerId <= 0 || !current_user_can('manage_options')) {
            return '';
        }

        $portalUrl = $this->portalUrl();

        if ($portalUrl === '') {
            return '';
        }

        return wp_nonce_url(
            add_query_arg(
                ['e2n_parent_preview' => $swimmerId],
                $portalUrl
            ),
            'e2n_parent_preview_' . $swimmerId
        );
    }

    public function coachPreviewUrl(int $swimmerId): string
    {
        if ($swimmerId <= 0 || (!current_user_can('manage_options') && !current_user_can('e2n_coach_access'))) {
            return '';
        }

        $portalUrl = $this->portalUrl();
        if ($portalUrl === '') {
            return '';
        }

        return wp_nonce_url(
            add_query_arg(['e2n_coach_preview' => $swimmerId], $portalUrl),
            'e2n_coach_parent_preview_' . $swimmerId
        );
    }

    public function portalUrl(): string
    {
        global $wpdb;

        $like = '%' . $wpdb->esc_like('[e2n_parent_report') . '%';
        $pageId = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                WHERE post_type = 'page'
                AND post_status = 'publish'
                AND post_content LIKE %s
                ORDER BY ID ASC
                LIMIT 1",
                $like
            )
        );

        return $pageId !== null ? get_permalink((int) $pageId) : '';
    }

    public function codeTransientKey(int $userId, int $swimmerId): string
    {
        return 'e2n_parent_code_' . $userId . '_' . $swimmerId;
    }

    private function codeForSwimmer(array $swimmer): string
    {
        $firstName = strtoupper(remove_accents((string) ($swimmer['first_name'] ?? '')));
        $firstName = (string) preg_replace('/[^A-Z]/', '', $firstName);
        $birthDate = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($swimmer['birth_date'] ?? ''));

        if ($firstName === '' || !$birthDate instanceof \DateTimeImmutable) {
            return '';
        }

        return $firstName . $birthDate->format('dmY');
    }

    private function normalizeCode(string $code): string
    {
        $code = strtoupper(remove_accents($code));

        return (string) preg_replace('/[^A-Z0-9]/', '', $code);
    }

    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', $this->normalizeCode($code), wp_salt('auth'));
    }

    private function rateKey(): string
    {
        return 'e2n_parent_rate_' . substr($this->ipHash(), 0, 32);
    }

    private function recordFailure(string $rateKey, mixed $rate): bool
    {
        $attempts = is_array($rate) ? (int) ($rate['attempts'] ?? 0) : 0;
        $attempts++;
        $blockedUntil = $attempts >= self::MAX_ATTEMPTS
            ? time() + self::BLOCK_SECONDS
            : 0;

        set_transient(
            $rateKey,
            [
                'attempts' => $attempts,
                'blocked_until' => $blockedUntil,
            ],
            self::BLOCK_SECONDS
        );

        return $blockedUntil > 0;
    }

    private function ipHash(): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
            : 'unknown';

        return hash_hmac('sha256', $ip, wp_salt('nonce'));
    }

    private function cookieName(): string
    {
        return 'e2n_parent_access';
    }

    private function setAccessCookie(int $swimmerId, string $codeHash): void
    {
        $expires = time() + self::COOKIE_TTL;
        $token = $swimmerId . '.' . $expires . '.' . $this->signToken(
            $swimmerId,
            $expires,
            $codeHash
        );

        setcookie(
            $this->cookieName(),
            $token,
            [
                'expires' => $expires,
                'path' => COOKIEPATH ?: '/',
                'domain' => (string) COOKIE_DOMAIN,
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );

        $_COOKIE[$this->cookieName()] = $token;
    }

    private function signToken(
        int $swimmerId,
        int $expires,
        string $codeHash
    ): string {
        return hash_hmac(
            'sha256',
            $swimmerId . '|' . $expires . '|' . $codeHash,
            wp_salt('secure_auth')
        );
    }
}
