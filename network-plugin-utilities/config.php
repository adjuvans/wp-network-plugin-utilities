<?php
/**
 * Configuration centralisée pour Network Plugin Utilities
 *
 * @package NPU
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

return [
    /**
     * Durée du cache des données de sites (en secondes)
     * Par défaut : 1 heure
     */
    'cache_duration' => HOUR_IN_SECONDS,

    /**
     * Nombre de sites par page par défaut
     */
    'default_per_page' => 20,

    /**
     * Custom Post Types à exclure de l'affichage
     */
    'excluded_post_types' => [
        'revision',
        'nav_menu_item',
        'custom_css',
        'customize_changeset',
        'oembed_cache',
        'user_request',
        'wp_block',
        'wp_template',
        'wp_template_part',
        'wp_global_styles',
        'wp_navigation',
    ],

    /**
     * Taxonomies à exclure de l'affichage
     */
    'excluded_taxonomies' => [
        'nav_menu',
        'link_category',
        'post_format',
        'wp_theme',
        'wp_template_part_area',
    ],

    /**
     * Seuils pour les alertes
     */
    'alert_thresholds' => [
        /**
         * Nombre de mois d'inactivité avant alerte
         */
        'inactive_months' => 6,

        /**
         * Nombre de médias avant alerte (quota)
         */
        'high_media_count' => 1000,

        /**
         * Alerter si le site n'a pas d'utilisateurs
         */
        'no_users' => true,
    ],

    /**
     * Capacités requises
     */
    'capabilities' => [
        'view_overview' => 'manage_network_plugins',
        'manage_settings' => 'manage_network_options',
    ],

    /**
     * Limite de rafraîchissement du cache (en secondes)
     * Empêche les rafraîchissements trop fréquents
     */
    'cache_refresh_limit' => 60, // 1 minute minimum entre deux rafraîchissements
];
