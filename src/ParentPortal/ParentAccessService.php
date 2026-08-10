<?php

namespace Ecole2Nat\ParentPortal;

if (!defined('ABSPATH')) {
    exit;
}

class ParentAccessService
{
    private const CODE_LENGTH = 8;
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const MAX_ATTEMPTS = 5;
    private const BLOCK_SECONDS = 900;
    private const COOKIE_TTL = 43200;

    private ParentAccessRepository $repository;

    public function __construct()
    {
        $this->repository = new ParentAccessRepository();
    }

    public function findSwimmer(int $swimmerId): ?array
    {
        return $this->repository->findSwimmer($swimmerId);
    }

    public function generateCode(int $swimmerId): array
    {
        if ($this->repository->findSwimmer($swimmerId) === null) {
            return ['success' => false, 'message' => 'invalid'];
        }

        for ($attempt = 0; $attempt < 25; $attempt++) {
            $code = $this->randomCode();
            $hash = $this->hashCode($code);

            if ($this->repository->codeHashExists($hash)) {
                continue;
            }

            if ($this->repository->saveAccessCode($swimmerId, $hash)) {
                return [
                    'success' => true,
                    'message' => 'access_generated',
                    'code' => $code,
                ];
            }

            return ['success' => false, 'message' => 'error'];
        }

        return ['success' => false, 'message' => 'error'];
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

        if (strlen($code) !== self::CODE_LENGTH) {
            $blocked = $this->recordFailure($rateKey, $rate);
            $this->repository->logAttempt(null, false, $this->ipHash());

            return [
                'success' => false,
                'message' => $blocked ? 'temporarily_blocked' : 'invalid_code',
            ];
        }

        $codeHash = $this->hashCode($code);
        $swimmer = $this->repository->findByCodeHash($codeHash);

        if ($swimmer === null) {
            $blocked = $this->recordFailure($rateKey, $rate);
            $this->repository->logAttempt(null, false, $this->ipHash());

            return [
                'success' => false,
                'message' => $blocked ? 'temporarily_blocked' : 'invalid_code',
            ];
        }

        delete_transient($rateKey);
        $swimmerId = (int) $swimmer['id'];
        $this->repository->markUsed($swimmerId);
        $this->repository->logAttempt($swimmerId, true, $this->ipHash());
        $this->setAccessCookie($swimmerId, $codeHash);

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
            || (int) ($swimmer['parent_access_enabled'] ?? 0) !== 1
            || (int) ($swimmer['is_active'] ?? 0) !== 1
            || empty($swimmer['parent_access_code_hash'])
        ) {
            return 0;
        }

        $expected = $this->signToken(
            (int) $swimmerId,
            (int) $expires,
            (string) $swimmer['parent_access_code_hash']
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

    public function report(int $swimmerId): ?array
    {
        return $this->repository->report($swimmerId);
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

    private function randomCode(): string
    {
        $code = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($index = 0; $index < self::CODE_LENGTH; $index++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }

    private function normalizeCode(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $code));
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
