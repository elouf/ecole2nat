<?php

namespace Ecole2Nat\Support;

if (!defined('ABSPATH')) {
    exit;
}

final class Extranat
{
    public static function swimmerUrl(?string $licenceNumber): string
    {
        $licenceNumber = trim((string) $licenceNumber);
        if ($licenceNumber === '') return '';

        return 'https://ffn.extranat.fr/webffn/nat_recherche.php?idact=nat&idbas=25&idrch_id=' . rawurlencode($licenceNumber);
    }
}
