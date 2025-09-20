<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class NPU_Helpers {

    public static function init() {
        // Menu
        add_action('network_admin_menu', [__CLASS__, 'register_menu']);
        // Screen options
        add_filter('set-screen-option', [__CLASS__, 'set_screen_option'], 10, 3);
        // CSS
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function register_menu() {
        $hook = add_submenu_page(
            'sites.php',
            __('Analyse du réseau (MU)', 'rdc-core-mu-utilities'),
            __('Analyse du réseau (MU)', 'rdc-core-mu-utilities'),
            'manage_network_plugins',
            'network-plugins-overview',
            [__CLASS__, 'render_page']
        );

        add_action("load-$hook", function() {
            add_screen_option('per_page', [
                'label'   => __('Sites par page', 'rdc-core-mu-utilities'),
                'default' => 20,
                'option'  => 'sites_per_page',
            ]);
        });
    }

    public static function set_screen_option($status, $option, $value) {
        return $value;
    }

    public static function enqueue_assets($hook) {
        if ($hook === 'sites_page_network-plugins-overview') {
            wp_enqueue_style(
                'npu-admin',
                NPU_URL . 'assets/css/npu-admin.min.css',
                [],
                '1.0'
            );
        }
    }

    public static function render_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Vue d’ensemble des sites du réseau', 'rdc-core-mu-utilities') . '</h1>';

        $table = new NPU_List_Table();
        $table->prepare_items();
        $table->display();

        echo '</div>';
    }

} 