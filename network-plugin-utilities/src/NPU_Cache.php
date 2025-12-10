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

        return [
            'theme_name' => $theme->get('Name'),
            'theme_version' => $theme->get('Version'),
            'wp_version' => $wp_version,
            'locale' => get_locale(),
            'attachments_total' => $attachments_total,
            'valid_media_count' => $valid_media_count,
            'media_files' => $media_files,
            'last_post_date' => get_lastpostdate('blog'),
        ];
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
        $active_plugins = get_option('active_plugins', []);
        $local_plugins = array_diff($active_plugins, $network_plugins);

        return array_values($local_plugins);
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
