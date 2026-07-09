<?php
/**
 * Plugin Name: Ecole2Nat'
 * Plugin URI: https://github.com/elouf/ecole2nat
 * Description: Plugin WordPress de suivi pédagogique pour école de natation.
 * Version: 0.1.0
 * Author: Erwannig LOUF
 * Text Domain: ecole2nat
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */

if (!defined('ABSPATH')) {
    exit;
}

define('E2N_VERSION', '0.1.0');
define('E2N_DB_VERSION', '0.1.0');
define('E2N_PLUGIN_FILE', __FILE__);
define('E2N_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('E2N_PLUGIN_URL', plugin_dir_url(__FILE__));
define('E2N_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 */
require_once E2N_PLUGIN_PATH . 'vendor/autoload.php';

/**
 * Démarrage du plugin.
 */
function e2n(): \Ecole2Nat\Core\Plugin
{
    static $plugin = null;

    if ($plugin === null) {
        $plugin = new \Ecole2Nat\Core\Plugin();
    }

    return $plugin;
}

register_activation_hook(E2N_PLUGIN_FILE, ['\Ecole2Nat\Database\Installer', 'activate']);
register_deactivation_hook(E2N_PLUGIN_FILE, ['\Ecole2Nat\Database\Installer', 'deactivate']);

add_action('plugins_loaded', function () {
    e2n()->boot();
});