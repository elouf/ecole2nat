<?php

namespace Ecole2Nat\Admin\UI;

if (!defined('ABSPATH')) {
    exit;
}

final class Badge
{
    public static function status(bool $active): string
    {
        $class = $active ? 'e2n-status--active' : 'e2n-status--inactive';
        $label = $active ? __('Actif', 'ecole2nat') : __('Inactif', 'ecole2nat');

        return sprintf(
            '<span class="e2n-status %s">%s</span>',
            esc_attr($class),
            esc_html($label)
        );
    }
}
