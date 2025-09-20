<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class NPU_Helpers {

    public static function init() {
        // Tracker CPT / Taxonomies dès leur enregistrement
        add_filter( 'register_post_type_args', [ __CLASS__, 'track_cpt_origin' ], 10, 2 );
        add_filter( 'register_taxonomy_args', [ __CLASS__, 'track_taxo_origin' ], 10, 2 );

        // Menu réseau
        add_action('network_admin_menu', [__CLASS__, 'register_menu']);

        // Screen options
        add_filter('set-screen-option', [__CLASS__, 'set_screen_option'], 10, 3);

        // CSS admin
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /**
     * Enregistrer l’origine d’un CPT
     */
    public static function track_cpt_origin( $args, $post_type ) {
        global $npu_cpt_origins;
        if ( ! isset($npu_cpt_origins) ) {
            $npu_cpt_origins = [];
        }
        $npu_cpt_origins[$post_type] = self::detect_origin();
        return $args;
    }

    /**
     * Enregistrer l’origine d’une Taxonomy
     */
    public static function track_taxo_origin( $args, $taxonomy ) {
        global $npu_taxo_origins;
        if ( ! isset($npu_taxo_origins) ) {
            $npu_taxo_origins = [];
        }
        $npu_taxo_origins[$taxonomy] = self::detect_origin();
        return $args;
    }

    /**
     * Essaie de deviner l’origine (plugin, thème, MU, core)
     */
    protected static function detect_origin() {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30);

        foreach ($trace as $step) {
            if (empty($step['file'])) {
                continue;
            }

            $file = str_replace(ABSPATH, '', $step['file']);

            // ignorer ce plugin
            if (strpos($file, 'wp-content/mu-plugins/network-plugin-utilities') === 0) {
                continue;
            }

            // Plugins
            if (strpos($file, 'wp-content/plugins/') === 0) {
                $parts = explode('/', $file);
                return 'plugin: ' . $parts[2];
            }

            // MU-plugins
            if (strpos($file, 'wp-content/mu-plugins/') === 0) {
                $parts = explode('/', $file);
                return 'mu-plugin: ' . $parts[2];
            }

            // Thèmes
            if (strpos($file, 'wp-content/themes/') === 0) {
                $parts = explode('/', $file);
                return 'theme: ' . $parts[2];
            }
        }

        return 'core';
    }

    /**
     * Menu réseau
     */
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

            add_screen_option('columns', [
                'label'   => __('Colonnes', 'rdc-core-mu-utilities'),
                'default' => 5,
            ]);
        });
    }

    /**
     * Sauvegarde des options d’écran
     */
    public static function set_screen_option($status, $option, $value) {
        return $value;
    }

    /**
     * Charger CSS admin
     */
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

    /**
     * Rendu de la page
     */
    public static function render_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Vue d’ensemble des sites du réseau', 'rdc-core-mu-utilities') . '</h1>';

        $table = new NPU_List_Table();
        $table->prepare_items();
        $table->display();

        echo '</div>';
    }
}
