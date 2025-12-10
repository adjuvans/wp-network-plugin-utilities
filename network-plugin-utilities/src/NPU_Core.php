<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class NPU_Core {

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
    }

    /**
     * Vérifie si le menu des sites du réseau est activé
     */
    protected static function is_menu_enabled() {
        return (bool) get_site_option('npu_enable_network_menu', true);
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
        $top_menu_slug = 'npu-core';

        // Créer le menu principal (⚠️ pas de callback pour éviter le doublon automatique)
        add_menu_page(
            __( 'NPU core', 'rdc-core-mu-utilities' ),
            __( 'NPU core', 'rdc-core-mu-utilities' ),
            'manage_network_plugins',
            $top_menu_slug,
            [ __CLASS__, 'redirect_to_default_submenu' ],
            'dashicons-admin-generic',
            3
        );

        // 🔹 Supprimer ou renommer le doublon immédiatement
        global $submenu;
        if ( isset($submenu[$top_menu_slug][0]) ) {
            // Variante 1 : renommer 
            // $submenu[$top_menu_slug][0][0] = __( 'Tableau de bord', 'rdc-core-mu-utilities' );

            // Variante 2 : supprimer
            unset($submenu[$top_menu_slug][0]);
        }

        // Sous-menu Analyse du réseau
        $hook = add_submenu_page(
            $top_menu_slug,
            __( 'Analyse du réseau', 'rdc-core-mu-utilities' ),
            __( 'Analyse du réseau', 'rdc-core-mu-utilities' ),
            'manage_network_plugins',
            'npu-network-overview',
            [ 'NPU_Network_Overview', 'render_page' ]
        );

        // Activer les Screen Options pour cette page
        add_action("load-{$hook}", [ __CLASS__, 'add_screen_options' ]);

        // Sous-menu Options NPU
        add_submenu_page(
            $top_menu_slug,
            __( 'Paramètres', 'rdc-core-mu-utilities' ),
            __( 'Paramètres', 'rdc-core-mu-utilities' ),
            'manage_network_options',
            'npu-settings',
            [ __CLASS__, 'render_options_page' ]
        );
    }

    /**
     * Ajoute les Screen Options pour la page d'analyse du réseau
     */
    public static function add_screen_options() {
        $screen = get_current_screen();

        if (!$screen) {
            return;
        }

        // Dans l'admin réseau, WordPress ajoute -network à la fin de l'ID
        if ($screen->id !== 'npu-core_page_npu-network-overview-network') {
            return;
        }

        // Option pour le nombre de sites par page
        add_screen_option('per_page', [
            'label' => __('Sites par page', 'rdc-core-mu-utilities'),
            'default' => 20,
            'option' => 'sites_per_page',
        ]);

        // Instancier le tableau ici pour que WordPress détecte les colonnes
        // Cela permet à WordPress de générer automatiquement les checkboxes de colonnes
        $table = new NPU_Network_Overview();

        // Définir les colonnes sur l'écran
        $columns = $table->get_columns();
        $hidden = $table->get_hidden_columns();

        // Enregistrer les colonnes dans l'écran pour que WordPress génère les options
        $screen->_column_headers = [$columns, $hidden, []];
    }

    /**
     * Redirige le clic sur le menu parent vers le premier sous-menu (Analyse du réseau)
     */
    public static function redirect_to_default_submenu() {
        wp_safe_redirect(
            network_admin_url('admin.php?page=npu-network-overview')
        );
        exit;
    }

    /**
     * Page d'options du plugin (admin réseau)
     */
    public static function render_options_page() {
        if ( isset($_POST['npu_save']) && check_admin_referer('npu_save_options') ) {
            // Sauvegarder l'option du menu réseau
            update_site_option('npu_enable_network_menu', isset($_POST['npu_enable_network_menu']) ? 1 : 0);

            // Sauvegarder les post types sélectionnés pour l'analyse d'activité
            $selected_post_types = [];
            if (isset($_POST['npu_activity_post_types']) && is_array($_POST['npu_activity_post_types'])) {
                // Valider et nettoyer les post types
                foreach ($_POST['npu_activity_post_types'] as $post_type) {
                    $post_type = sanitize_key($post_type);
                    if (post_type_exists($post_type)) {
                        $selected_post_types[] = $post_type;
                    }
                }
            }

            // Si aucun post type sélectionné, utiliser les valeurs par défaut
            if (empty($selected_post_types)) {
                $selected_post_types = ['post', 'page'];
            }

            update_site_option('npu_activity_post_types', $selected_post_types);

            // Invalider le cache pour forcer la mise à jour
            NPU_Cache::clear_cache();

            echo '<div class="updated"><p>' . __("Options sauvegardées. Le cache a été rafraîchi.", 'rdc-core-mu-utilities') . '</p></div>';
        }

        $enabled = self::is_menu_enabled();
        $activity_post_types = get_site_option('npu_activity_post_types', ['post', 'page']);

        // Récupérer tous les post types publics
        $all_post_types = get_post_types(['public' => true, 'show_ui' => true], 'objects');

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
                                <input type="checkbox" name="npu_enable_network_menu" value="1" <?php checked($enabled, 1); ?>>
                                <?php _e('Oui, afficher la liste des sites du réseau', 'rdc-core-mu-utilities'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Post types inclus dans l\'analyse d\'activité', 'rdc-core-mu-utilities'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php _e('Post types à analyser', 'rdc-core-mu-utilities'); ?></span></legend>
                                <p class="description" style="margin-bottom:10px;">
                                    <?php _e('Sélectionnez les types de contenu à prendre en compte pour déterminer si un site est actif ou inactif.', 'rdc-core-mu-utilities'); ?><br>
                                    <?php _e('Par défaut, WordPress analyse uniquement les articles ("Posts") et les pages, mais vous pouvez inclure d\'autres contenus comme les événements, les projets, etc.', 'rdc-core-mu-utilities'); ?><br>
                                    <strong><?php _e('Le plugin utilisera la date de publication du dernier contenu parmi les types sélectionnés.', 'rdc-core-mu-utilities'); ?></strong>
                                </p>
                                <?php foreach ($all_post_types as $post_type_obj): ?>
                                    <label style="display:block;margin-bottom:5px;">
                                        <input
                                            type="checkbox"
                                            name="npu_activity_post_types[]"
                                            value="<?php echo esc_attr($post_type_obj->name); ?>"
                                            <?php checked(in_array($post_type_obj->name, $activity_post_types)); ?>
                                        >
                                        <?php echo esc_html($post_type_obj->labels->name); ?>
                                        <code style="color:#666;font-size:11px;">(<?php echo esc_html($post_type_obj->name); ?>)</code>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
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
        // Le hook correct pour la page "Analyse du réseau" (sous-menu de NPU core)
        if ($hook === 'npu-core_page_npu-network-overview') {
            wp_enqueue_style(
                'npu-admin',
                NPU_URL . 'assets/css/npu-admin.min.css',
                [],
                '1.5'
            );
        }
    }
}