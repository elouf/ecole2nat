<?php

namespace Ecole2Nat\Support;

if (!defined('ABSPATH')) {
    exit;
}

final class ContactList
{
    public static function emails(string $value): array
    {
        $emails = [];
        foreach (self::parts($value) as $part) {
            $email = sanitize_email($part);
            if ($email !== '' && is_email($email)) {
                $emails[$email] = $email;
            }
        }
        return array_values($emails);
    }

    public static function normalizeEmails(string $value): string
    {
        return implode(' / ', self::emails($value));
    }

    public static function phones(string $value): array
    {
        $phones = [];
        foreach (self::parts($value) as $part) {
            $phone = sanitize_text_field($part);
            if ($phone !== '') {
                $phones[$phone] = $phone;
            }
        }
        return array_values($phones);
    }

    public static function normalizePhones(string $value): string
    {
        return implode(' / ', self::phones($value));
    }

    private static function parts(string $value): array
    {
        return preg_split('/\s*(?:\/|;|\r?\n)\s*/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
