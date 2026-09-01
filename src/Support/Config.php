<?php

namespace Ecole2Nat\Support;

if (!defined('ABSPATH')) {
    exit;
}

class Config
{
    public const DEFAULT_PARENT_EMAIL_SIGNATURE = 'Les coachs';
    public const DEFAULT_PORTAL_TITLE = 'Ecole2Nat’';
    public const DEFAULT_INVOICE_MEAL_PRICE = '6.00';
    public const DEFAULT_INVOICE_NIGHT_PRICE = '20.00';
    public const DEFAULT_INVOICE_ISSUER_NAME = 'Dauphins de Mayenne';
    public const DEFAULT_INVOICE_ISSUER_ADDRESS = "Centre aquatique « La Vague »\nRue du Chemin Montois\n53100 Mayenne";
    public const DEFAULT_INVOICE_ISSUER_SIRET = '439130741000010';
    public const DEFAULT_TREASURER_EMAIL = 'tresorierdauphinsmayennais@gmail.com';

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

    public static function invoiceMealPrice(): string
    {
        return self::decimalOption('invoice_meal_price', self::DEFAULT_INVOICE_MEAL_PRICE);
    }

    public static function invoiceNightPrice(): string
    {
        return self::decimalOption('invoice_night_price', self::DEFAULT_INVOICE_NIGHT_PRICE);
    }

    public static function invoiceIssuerName(): string
    {
        return self::textOption('invoice_issuer_name', self::DEFAULT_INVOICE_ISSUER_NAME);
    }

    public static function invoiceIssuerAddress(): string
    {
        return self::textOption('invoice_issuer_address', self::DEFAULT_INVOICE_ISSUER_ADDRESS);
    }

    public static function invoiceIssuerSiret(): string
    {
        return self::textOption('invoice_issuer_siret', self::DEFAULT_INVOICE_ISSUER_SIRET);
    }

    public static function treasurerEmail(): string
    {
        $email = sanitize_email((string) get_option(self::option('treasurer_email'), self::DEFAULT_TREASURER_EMAIL));
        return is_email($email) ? $email : self::DEFAULT_TREASURER_EMAIL;
    }

    public static function invoiceLogoId(): int
    {
        $stored = get_option(self::option('invoice_logo_id'), null);
        if ($stored === null) return self::portalLogoId();
        $logoId = max(0, (int) $stored);
        return $logoId > 0 && wp_attachment_is_image($logoId) ? $logoId : 0;
    }

    public static function invoiceRibId(): int
    {
        $attachmentId = max(0, (int) get_option(self::option('invoice_rib_id'), 0));
        return get_post_type($attachmentId) === 'attachment' ? $attachmentId : 0;
    }

    private static function textOption(string $name, string $default): string
    {
        $value = trim((string) get_option(self::option($name), $default));
        return $value !== '' ? $value : $default;
    }

    private static function decimalOption(string $name, string $default): string
    {
        $value = str_replace(',', '.', trim((string) get_option(self::option($name), $default)));
        return is_numeric($value) && (float) $value >= 0 ? number_format((float) $value, 2, '.', '') : $default;
    }
}
