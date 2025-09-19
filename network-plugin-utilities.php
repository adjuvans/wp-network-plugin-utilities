<?php
/*
Plugin Name: Network Plugin Utilities (MU)
Description: Liste les sites du réseau avec plugins locaux, utilisateurs, stats et taxonomies.
Author: Cyrille de Gourcy <cyrille@gourcy.net>
Version: 1.2
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
            <th>Statistiques (posts & CPT)</th>
            <th>Taxonomies</th>
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

        // Stats du site → tous les post types publics
        $post_types = get_post_types([ 'public' => true ], 'objects');
        $stats_list = '<ul>';
        foreach ( $post_types as $pt ) {
            $count = wp_count_posts($pt->name)->publish ?? 0;
            $stats_list .= '<li>' . esc_html($pt->labels->name) . ': ' . intval($count) . '</li>';
        }
        $stats_list .= '</ul>';

        // Taxonomies du site
        $taxonomies = get_taxonomies([ 'public' => true ], 'objects');
        if ( $taxonomies ) {
            $taxo_list = '<ul>';
            foreach ( $taxonomies as $tax ) {
                $terms = get_terms([ 'taxonomy' => $tax->name, 'hide_empty' => false ]);
                $count = is_array($terms) ? count($terms) : 0;
                $taxo_list .= '<li>' . esc_html($tax->labels->name) . ': ' . intval($count) . '</li>';
            }
            $taxo_list .= '</ul>';
        } else {
            $taxo_list = '<em>Aucune</em>';
        }

        // Ligne tableau
        echo '<tr>';
        echo '<td><strong>' . esc_html( get_bloginfo('name') ) . '</strong><br><a href="' . esc_url( get_site_url() ) . '" target="_blank">' . esc_html( get_site_url() ) . '</a></td>';
        echo '<td>' . $plugins_list . '</td>';
        echo '<td>' . $user_list . '</td>';
        echo '<td>' . $stats_list . '</td>';
        echo '<td>' . $taxo_list . '</td>';
        echo '</tr>';

        restore_current_blog();
    }

    echo '</tbody></table>';
    echo '</div>';
}