<?php
/*
Plugin Name: Network Plugin Utilities (MU)
Description: Liste les sites du réseau qui ont des plugins activés localement (hors plugins activés réseau).
Author: Cyrille de Gourcy <cyrille@gourcy.net>
Version: 1.0
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Sécurité

add_action('network_admin_menu', function() {
    add_submenu_page(
        'sites.php',
        'Plugins par site',
        'Plugins par site',
        'manage_network_plugins',
        'network-plugins-overview',
        'npo_render_page'
    );
});

function npo_render_page() {
    if ( ! current_user_can('manage_network_plugins') ) {
        wp_die('Accès interdit');
    }

    $network_plugins = array_keys( get_site_option( 'active_sitewide_plugins', [] ) );
    $sites = get_sites([ 'number' => 0 ]);

    echo '<div class="wrap">';
    echo '<h1>Plugins activés localement par site</h1>';

    foreach ( $sites as $site ) {
        switch_to_blog( $site->blog_id );

        $active_plugins = get_option( 'active_plugins', [] );
        $local_plugins = array_diff( $active_plugins, $network_plugins );

        if ( ! empty( $local_plugins ) ) {
            echo '<h2>' . esc_html( get_bloginfo('name') ) . ' (' . esc_url( get_site_url() ) . ')</h2>';
            echo '<ul>';
            foreach ( $local_plugins as $plugin ) {
                echo '<li>' . esc_html( $plugin ) . '</li>';
            }
            echo '</ul>';
        }

        restore_current_blog();
    }

    echo '</div>';
}
