<?php
/*
Plugin Name: Network Plugin Utilities (MU)
Description: Liste les sites du réseau avec plugins locaux, utilisateurs et stats.
Author: Cyrille de Gourcy <cyrille@gourcy.net>
Version: 1.1
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
    echo '<h1>Vue d’ensemble des sites</h1>';
    echo '<table class="widefat striped">';
    echo '<thead><tr>
            <th>Site</th>
            <th>Plugins activés localement</th>
            <th>Utilisateurs</th>
            <th>Statistiques</th>
        </tr></thead>';
    echo '<tbody>';

    foreach ( $sites as $site ) {
        switch_to_blog( $site->blog_id );

        // Plugins locaux
        $active_plugins = get_option( 'active_plugins', [] );
        $local_plugins = array_diff( $active_plugins, $network_plugins );
        $plugins_list = ! empty($local_plugins)
            ? '<ul><li>' . implode('</li><li>', array_map('esc_html', $local_plugins)) . '</li></ul>'
            : '<em>Aucun</em>';

        // Utilisateurs du site
        $users = get_users([ 'blog_id' => $site->blog_id ]);
        if ( $users ) {
            $user_list = '<ul>';
            foreach ( $users as $user ) {
                $roles = implode(', ', $user->roles);
                $user_list .= '<li>' . esc_html($user->user_login) . ' (' . esc_html($roles) . ')</li>';
            }
            $user_list .= '</ul>';
        } else {
            $user_list = '<em>Aucun</em>';
        }

        // Stats du site
        $stats = [
            'Articles' => wp_count_posts('post')->publish ?? 0,
            'Pages'    => wp_count_posts('page')->publish ?? 0,
        ];
        $stats_list = '<ul>';
        foreach ( $stats as $label => $count ) {
            $stats_list .= '<li>' . esc_html($label) . ': ' . intval($count) . '</li>';
        }
        $stats_list .= '</ul>';

        // Ligne tableau
        echo '<tr>';
        echo '<td><strong>' . esc_html( get_bloginfo('name') ) . '</strong><br><a href="' . esc_url( get_site_url() ) . '" target="_blank">' . esc_html( get_site_url() ) . '</a></td>';
        echo '<td>' . $plugins_list . '</td>';
        echo '<td>' . $user_list . '</td>';
        echo '<td>' . $stats_list . '</td>';
        echo '</tr>';

        restore_current_blog();
    }

    echo '</tbody></table>';
    echo '</div>';
}
