<?php
/*
Plugin Name: Network Plugin Utilities (MU)
Description: Liste les sites du réseau avec plugins locaux, utilisateurs, stats, taxonomies et infos techniques (thème, version WP, langue, médias, dernière mise à jour).
Author: Cyrille de Gourcy <cyrille@gourcy.net>
Version: 1.7.0
Text Domain: npu-core
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define('NPU_PATH', __DIR__ . '/network-plugin-utilities/');
define('NPU_URL',  plugin_dir_url(__FILE__) . 'network-plugin-utilities/');

// Autoload 
spl_autoload_register(function($class) {
    if (strpos($class, 'NPU_') !== 0) {
        return;
    }

    $file = NPU_PATH . 'src/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Init
add_action('plugins_loaded', function() {
    NPU_Cache::init();
    NPU_Alerts::init();
    NPU_Export::init();
    NPU_Core::init();
    NPU_Network_Sites_Menu::init();
});