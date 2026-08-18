<?php

namespace Ecole2Nat\Support;

if (!defined('ABSPATH')) {
    exit;
}

class Config
{
    public const DEFAULT_PARENT_EMAIL_SIGNATURE = 'Les coachs';
    public const DEFAULT_PORTAL_TITLE = 'Ecole2Nat’';

    public static function version(): string
    {
        return E2N_VERSION;
    }

    public static function dbVersion(): string
    {
        return E2N_DB_VERSION;
    }

    public static function pluginPath(): string
    {
        return E2N_PLUGIN_PATH;
    }

    public static function pluginUrl(): string
    {
        return E2N_PLUGIN_URL;
    }

    public static function table(string $table): string
    {
        global $wpdb;

        return $wpdb->prefix . 'e2n_' . $table;
    }

    public static function option(string $option): string
    {
        return 'e2n_' . $option;
    }

    public static function parentEmailSignature(): string
    {
        $signature = trim((string) get_option(
            self::option('parent_email_signature'),
            self::DEFAULT_PARENT_EMAIL_SIGNATURE
        ));

        return $signature !== '' ? $signature : self::DEFAULT_PARENT_EMAIL_SIGNATURE;
    }

    public static function portalTitle(): string
    {
        $title = trim((string) get_option(
            self::option('portal_title'),
            get_option(self::option('parent_portal_title'), self::DEFAULT_PORTAL_TITLE)
        ));

        return $title !== '' ? $title : self::DEFAULT_PORTAL_TITLE;
    }

    public static function portalLogoId(): int
    {
        $logoId = max(0, (int) get_option(
            self::option('portal_logo_id'),
            get_option(self::option('parent_portal_logo_id'), 0)
        ));

        return $logoId > 0 && wp_attachment_is_image($logoId) ? $logoId : 0;
    }
}
