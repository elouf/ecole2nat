<?php

namespace Ecole2Nat\Application;

use Ecole2Nat\Admin\Menu;
use Ecole2Nat\Admin\Deletion\DeletionController;
use Ecole2Nat\Support\Config;
use Ecole2Nat\ParentPortal\ParentPortal;
use Ecole2Nat\Coach\CoachPortal;

if (!defined('ABSPATH')) {
    exit;
}

class Application
{
    public function boot(): void
    {
        $parentPortal = new ParentPortal();
        $parentPortal->register();

        (new CoachPortal())->register();

        if (is_admin()) {
            add_action('admin_menu', [$this, 'registerAdminMenu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
            (new DeletionController())->register();
        }
    }

    public function enqueueAdminAssets(): void
    {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (strpos($page, 'ecole2nat') !== 0) {
            return;
        }

        $adminCssPath = Config::pluginPath() . 'assets/css/admin.css';
        $adminJsPath = Config::pluginPath() . 'assets/js/admin.js';
        $scriptDependencies = [];

        if ($page === 'ecole2nat-settings') {
            wp_enqueue_media();
            $scriptDependencies[] = 'media-editor';
        }

        wp_enqueue_style(
            'ecole2nat-admin',
            Config::pluginUrl() . 'assets/css/admin.css',
            [],
            Config::version() . '.' . (string) filemtime($adminCssPath)
        );

        wp_enqueue_script(
            'ecole2nat-admin',
            Config::pluginUrl() . 'assets/js/admin.js',
            $scriptDependencies,
            Config::version() . '.' . (string) filemtime($adminJsPath),
            true
        );
    }

    public function registerAdminMenu(): void
    {
        $menu = new Menu();
        $menu->register();
    }
}
