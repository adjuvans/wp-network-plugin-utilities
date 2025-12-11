<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class NPU_Core {

    public static function init() {
        // Tracker CPT / Taxonomies dès leur enregistrement
        add_filter( 'register_post_type_args', [ __CLASS__, 'track_cpt_origin' ], 10, 2 );
        add_filter( 'register_taxonomy_args', [ __CLASS__, 'track_taxo_origin' ], 10, 2 );

        // Menu réseau (admin network)
        add_action('network_admin_menu', [__CLASS__, 'register_menu']);
        add_action('network_admin_menu', [__CLASS__, 'remove_duplicate_submenu'], 999);

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
     * Supprime le sous-menu auto-généré portant le même slug que le parent.
     */
    public static function remove_duplicate_submenu() {
        remove_submenu_page('npu-core', 'npu-core');
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

            // Sauvegarder la sélection des plugins à analyser
            $selected_plugins = [];
            if (isset($_POST['npu_analysis_plugins']) && is_array($_POST['npu_analysis_plugins'])) {
                foreach ($_POST['npu_analysis_plugins'] as $plugin_slug) {
                    $plugin_slug = sanitize_text_field($plugin_slug);
                    if (! empty($plugin_slug)) {
                        $selected_plugins[] = $plugin_slug;
                    }
                }
            }
            update_site_option('npu_analysis_plugins', array_unique($selected_plugins));

            // Invalider le cache pour forcer la mise à jour
            NPU_Cache::clear_cache();

            echo '<div class="updated"><p>' . __("Options sauvegardées. Le cache a été rafraîchi.", 'rdc-core-mu-utilities') . '</p></div>';
        }

        $enabled = self::is_menu_enabled();
        $activity_post_types = get_site_option('npu_activity_post_types', ['post', 'page']);
        $analysis_plugins = get_site_option('npu_analysis_plugins', []);

        // Récupérer tous les post types publics
        $all_post_types = get_post_types(['public' => true, 'show_ui' => true], 'objects');
        $active_plugins = self::get_active_plugins_for_settings();

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
                                <p class="description npu-option-description">
                                    <?php _e('Sélectionnez les types de contenu à prendre en compte pour déterminer si un site est actif ou inactif.', 'rdc-core-mu-utilities'); ?><br>
                                    <?php _e('Par défaut, WordPress analyse uniquement les articles ("Posts") et les pages, mais vous pouvez inclure d\'autres contenus comme les événements, les projets, etc.', 'rdc-core-mu-utilities'); ?><br>
                                    <strong><?php _e('Le plugin utilisera la date de publication du dernier contenu parmi les types sélectionnés.', 'rdc-core-mu-utilities'); ?></strong>
                                </p>
                                <?php foreach ($all_post_types as $post_type_obj): ?>
                                    <label class="npu-option-checkbox">
                                        <input
                                            type="checkbox"
                                            name="npu_activity_post_types[]"
                                            value="<?php echo esc_attr($post_type_obj->name); ?>"
                                            <?php checked(in_array($post_type_obj->name, $activity_post_types)); ?>
                                        >
                                        <?php echo esc_html($post_type_obj->labels->name); ?>
                                        <code class="npu-code">(<?php echo esc_html($post_type_obj->name); ?>)</code>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Limiter l\'analyse aux plugins', 'rdc-core-mu-utilities'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php _e('Plugins à analyser', 'rdc-core-mu-utilities'); ?></span></legend>
                                <p class="description npu-option-description">
                                    <?php _e('Sélectionnez les plugins actifs (réseau ou site principal) dont les CPT et taxonomies doivent être analysés. Laisser vide pour analyser tous les plugins.', 'rdc-core-mu-utilities'); ?>
                                </p>
                                <?php if (empty($active_plugins)) : ?>
                                    <em><?php _e('Aucun plugin actif détecté.', 'rdc-core-mu-utilities'); ?></em>
                                <?php else : ?>
                                    <?php foreach ($active_plugins as $plugin) : ?>
                                        <label class="npu-option-checkbox">
                                            <input
                                                type="checkbox"
                                                name="npu_analysis_plugins[]"
                                                value="<?php echo esc_attr($plugin['slug']); ?>"
                                                <?php checked(in_array($plugin['slug'], $analysis_plugins, true)); ?>
                                            >
                                            <?php echo esc_html($plugin['name']); ?>
                                            <code class="npu-code">(<?php echo esc_html($plugin['slug']); ?>)</code>
                                            <?php if ($plugin['is_network']) : ?>
                                                <span class="npu-badge npu-badge-network"><?php _e('Réseau', 'rdc-core-mu-utilities'); ?></span>
                                            <?php else : ?>
                                                <span class="npu-badge npu-badge-main"><?php _e('Site principal', 'rdc-core-mu-utilities'); ?></span>
                                            <?php endif; ?>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
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
        $hooks = [
            'npu-core_page_npu-network-overview',
            'npu-core_page_npu-settings',
        ];

        if (in_array($hook, $hooks, true)) {
            wp_enqueue_style(
                'npu-admin',
                NPU_URL . 'assets/css/npu-admin.css',
                [],
                '1.7.2'
            );

            // Enqueue Dashicons (si pas déjà chargé)
            wp_enqueue_style('dashicons');
        }
    }

    /**
     * Helper pour générer une icône d'aide avec tooltip
     *
     * @param string $tooltip_text Texte du tooltip
     * @param string $icon Icon dashicons (sans le préfixe dashicons-)
     * @return string HTML de l'icône
     */
    public static function render_help_icon($tooltip_text, $icon = 'editor-help') {
        return sprintf(
            '<span class="dashicons dashicons-%s npu-help-icon" aria-label="%s" data-tooltip="%s" tabindex="0"></span>',
            esc_attr($icon),
            esc_attr__('Aide', 'rdc-core-mu-utilities'),
            esc_attr($tooltip_text)
        );
    }

    /**
     * Récupère les plugins actifs (réseau + site principal) pour l'affichage des options.
     */
    private static function get_active_plugins_for_settings() {
        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $network_plugins = array_keys(get_site_option('active_sitewide_plugins', []));

        $main_site_id = function_exists('get_main_site_id') ? get_main_site_id() : 1;
        switch_to_blog($main_site_id);
        $main_active = get_option('active_plugins', []);
        restore_current_blog();

        $all = array_unique(array_merge($network_plugins, $main_active));
        $plugins_data = get_plugins();

        $out = [];
        foreach ($all as $plugin_file) {
            $data = $plugins_data[$plugin_file] ?? [];
            $slug = dirname($plugin_file);
            $out[] = [
                'slug'       => $slug,
                'file'       => $plugin_file,
                'name'       => $data['Name'] ?? $plugin_file,
                'is_network' => in_array($plugin_file, $network_plugins, true),
            ];
        }

        usort($out, function ($a, $b) {
            return strcmp(strtolower($a['name']), strtolower($b['name']));
        });

        return $out;
    }
}
