<?php

namespace Ecole2Nat\Application;

use Ecole2Nat\Admin\Menu;
use Ecole2Nat\Admin\Deletion\DeletionController;
use Ecole2Nat\Support\Config;
use Ecole2Nat\ParentPortal\ParentPortal;
use Ecole2Nat\Admin\ParentDistribution\ParentDistributionController;

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
            add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
            (new DeletionController())->register();
            (new ParentDistributionController())->register();
        }
    }

    public function enqueueAdminAssets(): void
    {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (strpos($page, 'ecole2nat') !== 0) {
            return;
        }

        wp_enqueue_style(
            'ecole2nat-admin',
            Config::pluginUrl() . 'assets/css/admin.css',
            [],
            Config::version()
        );
    }

    public function registerAdminMenu(): void
    {
        $menu = new Menu();
        $menu->register();
    }
}