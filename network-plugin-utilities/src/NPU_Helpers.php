<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class NPU_Helpers {

    public static function init() {
        // Tracker CPT / Taxonomies dès leur enregistrement
        add_filter( 'register_post_type_args', [ __CLASS__, 'track_cpt_origin' ], 10, 2 );
        add_filter( 'register_taxonomy_args', [ __CLASS__, 'track_taxo_origin' ], 10, 2 );

        // Menu réseau (admin network)
        add_action('network_admin_menu', [__CLASS__, 'register_menu']);

        // Screen options (colonnes, pagination)
        add_filter('set-screen-option', [__CLASS__, 'set_screen_option'], 10, 3);

        // CSS admin
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        // Shortcode pour affichage dynamique d'un menu des sites
        add_shortcode('network_sites_menu', [__CLASS__, 'shortcode_network_sites_menu']);
    }

    /**
     * Vérifie si le menu des sites du réseau est activé
     */
    protected static function is_menu_enabled() {
        // Option stockée en base réseau, activée par défaut
        return (bool) get_site_option('npu_enable_network_menu', true);
    }

    /**
     * Récupère les sites du réseau
     */
    protected static function get_sites_list() {
        // Si désactivé via l’option → ne rien renvoyer
        if ( ! self::is_menu_enabled() ) {
            return [];
        }

        $sites = get_sites([
            'public'   => 1,
            'archived' => 0,
            'deleted'  => 0
        ]);

        $out = [];
        foreach ( $sites as $site ) {
            $out[] = [
                'id'   => $site->blog_id,
                'url'  => get_site_url( $site->blog_id ),
                'name' => get_blog_option( $site->blog_id, 'blogname' )
            ];
        }
        return $out;
    }

    /**
     * Affiche ou retourne la liste HTML des sites
     */
    public static function render_sites_list( $wrapper = 'ul', $class = 'network-sites-menu', $echo = true ) {
        $sites = self::get_sites_list();
        if ( empty( $sites ) ) {
            return '';
        }

        ob_start();
        echo '<' . tag_escape( $wrapper ) . ' class="' . esc_attr( $class ) . '">';
        foreach ( $sites as $site ) {
            echo '<li><a href="' . esc_url( $site['url'] ) . '">' . esc_html( $site['name'] ) . '</a></li>';
        }
        echo '</' . tag_escape( $wrapper ) . '>';

        $html = ob_get_clean();

        if ( $echo ) {
            echo $html;
        }
        return $html;
    }

    /**
     * Fonction d’appel direct en PHP
     * Usage : <?php NPU_Helpers::network_sites_menu(); ?>
     */
    public static function network_sites_menu( $wrapper = 'ul', $class = 'network-sites-menu' ) {
        return self::render_sites_list( $wrapper, $class, true );
    }

    /**
     * Shortcode [network_sites_menu]
     */
    public static function shortcode_network_sites_menu( $atts ) {
        $atts = shortcode_atts([
            'class'   => 'network-sites-menu',
            'wrapper' => 'ul'
        ], $atts );

        return self::render_sites_list( $atts['wrapper'], $atts['class'], false );
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
     * Menu réseau (pages dans l’admin réseau)
     */
    public static function register_menu() {
        // Page principale d’analyse
        $hook = add_submenu_page(
            'sites.php',
            __('Analyse du réseau (MU)', 'rdc-core-mu-utilities'),
            __('Analyse du réseau (MU)', 'rdc-core-mu-utilities'),
            'manage_network_plugins',
            'network-plugins-overview',
            [__CLASS__, 'render_page']
        );

        // Options d’écran
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

        // Page d’options NPU
        add_submenu_page(
            'settings.php',
            __('Options NPU', 'rdc-core-mu-utilities'),
            __('Options NPU', 'rdc-core-mu-utilities'),
            'manage_network_options',
            'npu-options',
            [__CLASS__, 'render_options_page']
        );
    }

    /**
     * Page d’options du plugin (admin réseau)
     */
    public static function render_options_page() {
        // Sauvegarde si POST
        if ( isset($_POST['npu_save']) && check_admin_referer('npu_save_options') ) {
            update_site_option('npu_enable_network_menu', ! empty($_POST['npu_enable_network_menu']));
            echo '<div class="updated"><p>' . __('Options sauvegardées', 'rdc-core-mu-utilities') . '</p></div>';
        }

        $enabled = self::is_menu_enabled();
        ?>
        <div class="wrap">
            <h1><?php _e('Options NPU', 'rdc-core-mu-utilities'); ?></h1>
            <form method="post">
                <?php wp_nonce_field('npu_save_options'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Activer le menu réseau', 'rdc-core-mu-utilities'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="npu_enable_network_menu" value="1" <?php checked($enabled); ?>>
                                <?php _e('Oui, afficher la liste des sites du réseau', 'rdc-core-mu-utilities'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Enregistrer', 'rdc-core-mu-utilities'), 'primary', 'npu_save'); ?>
            </form>
        </div>
        <?php
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
     * Rendu de la page principale
     */
    public static function render_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Vue d’ensemble des sites du réseau', 'rdc-core-mu-utilities') . '</h1>';

        // ⚠️ S’assurer que la classe NPU_List_Table existe
        if ( class_exists('NPU_List_Table') ) {
            $table = new NPU_List_Table();
            $table->prepare_items();
            $table->display();
        } else {
            echo '<p>' . __('Erreur : classe NPU_List_Table manquante.', 'rdc-core-mu-utilities') . '</p>';
        }

        echo '</div>';
    }

}