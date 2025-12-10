<?php
/**
 * Gestion du cache pour Network Plugin Utilities
 *
 * @package NPU
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NPU_Cache {

    /**
     * Préfixe pour les clés de cache
     */
    const CACHE_PREFIX = 'npu_site_data_';

    /**
     * Clé pour le timestamp du dernier rafraîchissement
     */
    const LAST_REFRESH_KEY = 'npu_last_cache_refresh';

    /**
     * Configuration du plugin
     */
    private static $config;

    /**
     * Initialisation
     */
    public static function init() {
        self::$config = include NPU_PATH . 'config.php';

        // Hook pour nettoyer le cache lors de modifications importantes
        add_action('wpmu_new_blog', [__CLASS__, 'clear_cache']);
        add_action('archive_blog', [__CLASS__, 'clear_cache']);
        add_action('unarchive_blog', [__CLASS__, 'clear_cache']);
        add_action('delete_blog', [__CLASS__, 'clear_cache']);
    }

    /**
     * Récupère les données d'un site depuis le cache ou les génère
     *
     * @param object $site L'objet site WordPress
     * @param array $network_plugins Liste des plugins réseau
     * @return array|false Les données du site ou false en cas d'erreur
     */
    public static function get_site_data($site, $network_plugins = []) {
        $cache_key = self::CACHE_PREFIX . $site->blog_id;
        $cached_data = get_site_transient($cache_key);

        if ($cached_data !== false && is_array($cached_data)) {
            return $cached_data;
        }

        // Générer les données si pas en cache
        $data = self::generate_site_data($site, $network_plugins);

        if ($data !== false) {
            $cache_duration = self::$config['cache_duration'] ?? HOUR_IN_SECONDS;
            set_site_transient($cache_key, $data, $cache_duration);
        }

        return $data;
    }

    /**
     * Génère les données pour un site donné
     *
     * @param object $site L'objet site WordPress
     * @param array $network_plugins Liste des plugins réseau
     * @return array|false Les données du site ou false en cas d'erreur
     */
    private static function generate_site_data($site, $network_plugins = []) {
        try {
            switch_to_blog($site->blog_id);

            $data = [
                'blog_id' => $site->blog_id,
                'site_info' => self::get_site_basic_info($site),
                'technical_info' => self::get_technical_info(),
                'users' => self::get_users_info($site->blog_id),
                'post_types_builtin' => [],
                'post_types_custom' => [],
                'taxonomies_builtin' => [],
                'taxonomies_custom' => [],
                'local_plugins' => self::get_local_plugins($network_plugins),
                'generated_at' => current_time('mysql'),
            ];

            // CPT
            list($data['post_types_builtin'], $data['post_types_custom']) = self::get_post_types_info();

            // Taxonomies
            list($data['taxonomies_builtin'], $data['taxonomies_custom']) = self::get_taxonomies_info();

            restore_current_blog();

            return $data;

        } catch (Exception $e) {
            restore_current_blog();
            error_log('NPU Cache Error for site ' . $site->blog_id . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère les informations de base du site
     *
     * @param object $site L'objet site WordPress
     * @return array
     */
    private static function get_site_basic_info($site) {
        return [
            'name' => get_bloginfo('name'),
            'url' => get_site_url(),
            'admin_url' => get_admin_url(),
        ];
    }

    /**
     * Récupère les informations techniques du site
     *
     * @return array
     */
    private static function get_technical_info() {
        global $wp_version;
        global $wpdb;

        $theme = wp_get_theme();
        $total_attachments = wp_count_posts('attachment');
        $attachments_total = array_sum((array) $total_attachments);

        $valid_media_count = 0;
        foreach (['inherit', 'publish'] as $status) {
            if (isset($total_attachments->$status)) {
                $valid_media_count += $total_attachments->$status;
            }
        }

        $media_files = isset($total_attachments->inherit) ? (int) $total_attachments->inherit : 0;

        // Récupérer le dernier contenu publié selon les post types configurés
        $last_content = self::get_last_content_info();

        return [
            'theme_name' => $theme->get('Name'),
            'theme_version' => $theme->get('Version'),
            'wp_version' => $wp_version,
            'php_version' => PHP_VERSION,
            'mysql_version' => method_exists($wpdb, 'db_version') ? $wpdb->db_version() : '',
            'locale' => get_locale(),
            'attachments_total' => $attachments_total,
            'valid_media_count' => $valid_media_count,
            'media_files' => $media_files,
            // Compat historique : conserver la date seule
            'last_post_date' => $last_content['date'] ?? null,
            // Nouveaux détails
            'last_content' => $last_content,
        ];
    }

    /**
     * Récupère les infos du dernier contenu publié/modifié pour un site
     * Utilise les post types configurés dans les options
     * Priorité : date de modification (post_modified_gmt) > date de publication (post_date_gmt)
     *
     * @return array|null
     */
    private static function get_last_content_info() {
        // Récupérer les post types à analyser depuis les options
        $allowed_post_types = get_site_option('npu_activity_post_types', ['post', 'page']);

        // S'assurer que c'est un tableau
        if (!is_array($allowed_post_types) || empty($allowed_post_types)) {
            $allowed_post_types = ['post', 'page'];
        }

        // Récupérer le dernier contenu modifié parmi les post types sélectionnés
        // On trie par date de modification pour avoir le contenu le plus récemment mis à jour
        $last_post = get_posts([
            'numberposts' => 1,
            'orderby'     => 'modified',  // Trier par date de modification
            'order'       => 'DESC',
            'post_status' => 'publish',
            'post_type'   => $allowed_post_types,
            'fields'      => 'ids', // On ne récupère que l'ID pour optimiser
        ]);

        // Si un post existe, récupérer sa date de modification
        if (!empty($last_post)) {
            $post_id = $last_post[0];

            // Priorité 1 : Date de modification GMT
            $post_modified_gmt = get_post_field('post_modified_gmt', $post_id);

            // Si la date de modification existe et n'est pas '0000-00-00 00:00:00'
            if ($post_modified_gmt && $post_modified_gmt !== '0000-00-00 00:00:00') {
                return [
                    'date'  => $post_modified_gmt,
                    'type'  => get_post_type($post_id),
                    'title' => get_the_title($post_id),
                    'id'    => $post_id,
                ];
            }

            // Fallback : Date de publication GMT
            $post_date_gmt = get_post_field('post_date_gmt', $post_id);
            if ($post_date_gmt && $post_date_gmt !== '0000-00-00 00:00:00') {
                return [
                    'date'  => $post_date_gmt,
                    'type'  => get_post_type($post_id),
                    'title' => get_the_title($post_id),
                    'id'    => $post_id,
                ];
            }

            // Si aucune date GMT n'est disponible, utiliser les dates locales
            $post_modified = get_post_field('post_modified', $post_id);
            if ($post_modified && $post_modified !== '0000-00-00 00:00:00') {
                return [
                    'date'  => $post_modified,
                    'type'  => get_post_type($post_id),
                    'title' => get_the_title($post_id),
                    'id'    => $post_id,
                ];
            }

            // Dernier fallback : date de publication locale
            $post_date = get_post_field('post_date', $post_id);
            return [
                'date'  => $post_date,
                'type'  => get_post_type($post_id),
                'title' => get_the_title($post_id),
                'id'    => $post_id,
            ];
        }

        return null;
    }

    /**
     * Récupère les informations des utilisateurs
     *
     * @param int $blog_id ID du blog
     * @return array
     */
    private static function get_users_info($blog_id) {
        $users = get_users(['blog_id' => $blog_id]);
        $users_data = [];

        foreach ($users as $user) {
            $users_data[] = [
                'ID' => $user->ID,
                'login' => $user->user_login,
                'roles' => implode(', ', $user->roles),
            ];
        }

        return $users_data;
    }

    /**
     * Récupère les informations des Custom Post Types
     *
     * @return array [builtin, custom]
     */
    private static function get_post_types_info() {
        global $npu_cpt_origins;

        $post_types = get_post_types([], 'objects');
        $excluded = self::$config['excluded_post_types'] ?? [];
        $allowed_plugins = self::get_allowed_plugins_filter();
        $builtin = [];
        $custom = [];

        foreach ($post_types as $pt) {
            if (in_array($pt->name, $excluded)) {
                continue;
            }

            $post_counts = wp_count_posts($pt->name);
            $count = isset($post_counts->publish) ? $post_counts->publish : 0;
            $origin = $npu_cpt_origins[$pt->name] ?? ($pt->_builtin ? 'core' : 'inconnu');

            $item = [
                'name' => $pt->name,
                'label' => $pt->labels->name,
                'count' => $count,
                'origin' => $origin,
            ];

            if ($pt->_builtin) {
                $builtin[] = $item;
            } else {
                if (! self::is_allowed_plugin_origin($origin, $allowed_plugins)) {
                    continue;
                }
                $custom[] = $item;
            }
        }

        return [$builtin, $custom];
    }

    /**
     * Récupère les informations des taxonomies
     *
     * @return array [builtin, custom]
     */
    private static function get_taxonomies_info() {
        global $npu_taxo_origins;

        $taxonomies = get_taxonomies([], 'objects');
        $excluded = self::$config['excluded_taxonomies'] ?? [];
        $allowed_plugins = self::get_allowed_plugins_filter();
        $builtin = [];
        $custom = [];

        foreach ($taxonomies as $tax) {
            if (in_array($tax->name, $excluded)) {
                continue;
            }

            $terms = get_terms(['taxonomy' => $tax->name, 'hide_empty' => false]);
            $count = is_array($terms) ? count($terms) : 0;
            $origin = $npu_taxo_origins[$tax->name] ?? ($tax->_builtin ? 'core' : 'inconnu');

            $item = [
                'name' => $tax->name,
                'label' => $tax->labels->name,
                'count' => $count,
                'origin' => $origin,
            ];

            if ($tax->_builtin) {
                $builtin[] = $item;
            } else {
                if (! self::is_allowed_plugin_origin($origin, $allowed_plugins)) {
                    continue;
                }
                $custom[] = $item;
            }
        }

        return [$builtin, $custom];
    }

    /**
     * Récupère les plugins locaux (non réseau)
     *
     * @param array $network_plugins Liste des plugins réseau
     * @return array
     */
    private static function get_local_plugins($network_plugins = []) {
        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $active_plugins = get_option('active_plugins', []);
        $local_plugins = array_diff($active_plugins, $network_plugins);

        $all_plugins_data = get_plugins();
        $plugins = [];

        foreach ($local_plugins as $plugin_file) {
            $plugin_data = $all_plugins_data[$plugin_file] ?? null;
            $plugins[] = [
                'file' => $plugin_file,
                'name' => $plugin_data['Name'] ?? $plugin_file,
                'slug' => dirname($plugin_file),
                'version' => $plugin_data['Version'] ?? '',
                'author' => $plugin_data['Author'] ?? '',
                'author_uri' => $plugin_data['AuthorURI'] ?? '',
                'plugin_uri' => $plugin_data['PluginURI'] ?? '',
            ];
        }

        return array_values($plugins);
    }

    /**
     * Plugins autorisés pour l'analyse (filtre optionnel).
     */
    private static function get_allowed_plugins_filter() {
        $selected = get_site_option('npu_analysis_plugins', []);

        if (! is_array($selected) || empty($selected)) {
            return [];
        }

        $selected = array_map('sanitize_text_field', $selected);
        return array_filter($selected);
    }

    /**
     * Vérifie si l'origine d'un objet correspond au filtre de plugins autorisés.
     */
    private static function is_allowed_plugin_origin($origin, $allowed_plugins) {
        if (empty($allowed_plugins)) {
            return true;
        }

        if (strpos($origin, 'plugin: ') === 0) {
            $slug = trim(substr($origin, strlen('plugin: ')));
            return in_array($slug, $allowed_plugins, true);
        }

        return false;
    }

    /**
     * Vide le cache de tous les sites
     */
    public static function clear_cache() {
        $sites = get_sites(['number' => 0]);

        foreach ($sites as $site) {
            $cache_key = self::CACHE_PREFIX . $site->blog_id;
            delete_site_transient($cache_key);
        }

        // Mettre à jour le timestamp du dernier rafraîchissement
        set_site_transient(self::LAST_REFRESH_KEY, current_time('timestamp'), DAY_IN_SECONDS);
    }

    /**
     * Vide le cache d'un site spécifique
     *
     * @param int $blog_id ID du blog
     */
    public static function clear_site_cache($blog_id) {
        $cache_key = self::CACHE_PREFIX . $blog_id;
        delete_site_transient($cache_key);
    }

    /**
     * Vérifie si on peut rafraîchir le cache (rate limiting)
     *
     * @return bool
     */
    public static function can_refresh_cache() {
        $last_refresh = get_site_transient(self::LAST_REFRESH_KEY);

        if ($last_refresh === false) {
            return true;
        }

        $refresh_limit = self::$config['cache_refresh_limit'] ?? 60;
        $time_since_refresh = current_time('timestamp') - $last_refresh;

        return $time_since_refresh >= $refresh_limit;
    }

    /**
     * Récupère la configuration
     *
     * @return array
     */
    public static function get_config() {
        if (!self::$config) {
            self::$config = include NPU_PATH . 'config.php';
        }
        return self::$config;
    }
}
