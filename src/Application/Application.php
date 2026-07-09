<?php

namespace Ecole2Nat\Application;

use Ecole2Nat\Admin\Menu;

if (!defined('ABSPATH')) {
    exit;
}

class Application
{
    public function boot(): void
    {
        if (is_admin()) {
            add_action('admin_menu', [$this, 'registerAdminMenu']);
        }
    }

    public function registerAdminMenu(): void
    {
        $menu = new Menu();
        $menu->register();
    }
}