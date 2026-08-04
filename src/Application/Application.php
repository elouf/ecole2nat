<?php

namespace Ecole2Nat\Application;

use Ecole2Nat\Admin\Menu;
use Ecole2Nat\ParentPortal\ParentPortal;

if (!defined('ABSPATH')) {
    exit;
}

class Application
{
    public function boot(): void
    {
        $parentPortal = new ParentPortal();
        $parentPortal->register();

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