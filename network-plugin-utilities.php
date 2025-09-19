<?php
/*
Plugin Name: Network Plugin Utilities (MU)
Description: Liste les sites du réseau avec plugins locaux, utilisateurs, stats et taxonomies.
Author: Cyrille de Gourcy <cyrille@gourcy.net>
Version: 1.4
Text Domain: rdc-core-mu-utilities
*/

if ( ! defined( 'ABSPATH' ) ) exit;

add_action('network_admin_menu', function() {
    add_submenu_page(
        'sites.php',
        esc_html__( 'Analyse du réseau (MU)', 'rdc-core-mu-utilities' ),
        esc_html__( 'Analyse du réseau (MU)', 'rdc-core-mu-utilities' ),
        'manage_network_plugins',
        'network-plugins-overview',
        'npo_render_page'
    );
});

function npo_render_page() {
    if ( ! current_user_can('manage_network_plugins') ) {
        wp_die( esc_html__( 'Accès interdit', 'rdc-core-mu-utilities' ) );
    }

    $network_plugins = array_keys( get_site_option( 'active_sitewide_plugins', [] ) );
    $sites = get_sites([ 'number' => 0 ]);

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Vue d’ensemble des sites du réseaux', 'rdc-core-mu-utilities' ) . '</h1>';
    echo '<table class="widefat striped">';
    echo '<thead><tr>
            <th>' . esc_html__( 'Site', 'rdc-core-mu-utilities' ) . '</th>
            <th>' . esc_html__( 'Utilisateurs', 'rdc-core-mu-utilities' ) . '</th>
            <th>' . esc_html__( 'Contenus', 'rdc-core-mu-utilities' ) . '</th>
            <th>' . esc_html__( 'Taxonomies', 'rdc-core-mu-utilities' ) . '</th>
            <th>' . esc_html__( 'Plugins activés localement', 'rdc-core-mu-utilities' ) . '</th>
        </tr></thead>';
    echo '<tbody>';

    foreach ( $sites as $site ) {
        switch_to_blog( $site->blog_id );

        $admin_url = get_admin_url();

        // Plugins locaux
        $active_plugins = get_option( 'active_plugins', [] );
        $local_plugins = array_diff( $active_plugins, $network_plugins );
        if ( ! empty($local_plugins) ) {
            $plugins_list = '<ul>';
            foreach ( $local_plugins as $plugin ) {
                $plugins_list .= '<li><a href="' . esc_url( $admin_url . 'plugins.php' ) . '" target="_blank">' . esc_html($plugin) . '</a></li>';
            }
            $plugins_list .= '</ul>';
        } else {
            $plugins_list = '<em>' . esc_html__( 'Aucun', 'rdc-core-mu-utilities' ) . '</em>';
        }

        // Utilisateurs
        $users = get_users([ 'blog_id' => $site->blog_id ]);
        if ( $users ) {
            $user_list = '<ul>';
            foreach ( $users as $user ) {
                $roles = implode(', ', $user->roles);
                $user_list .= '<li><a href="' . esc_url( $admin_url . 'user-edit.php?user_id=' . $user->ID ) . '" target="_blank">' . esc_html($user->user_login) . '</a> (' . esc_html($roles) . ')</li>';
            }
            $user_list .= '</ul>';
        } else {
            $user_list = '<em>' . esc_html__( 'Aucun', 'rdc-core-mu-utilities' ) . '</em>';
        }

        // Stats du site → tous les CPT publics
        $post_types = get_post_types([ 'public' => true ], 'objects');
        $stats_list = '<ul>';
        foreach ( $post_types as $pt ) {
            $count = wp_count_posts($pt->name)->publish ?? 0;
            $stats_list .= '<li><a href="' . esc_url( $admin_url . 'edit.php?post_type=' . $pt->name ) . '" target="_blank">' 
                . esc_html( $pt->labels->name ) . '</a>: ' . intval($count) . '</li>';
        }
        $stats_list .= '</ul>';

        // Taxonomies
        $taxonomies = get_taxonomies([ 'public' => true ], 'objects');
        if ( $taxonomies ) {
            $taxo_list = '<ul>';
            foreach ( $taxonomies as $tax ) {
                $terms = get_terms([ 'taxonomy' => $tax->name, 'hide_empty' => false ]);
                $count = is_array($terms) ? count($terms) : 0;
                $taxo_list .= '<li><a href="' . esc_url( $admin_url . 'edit-tags.php?taxonomy=' . $tax->name ) . '" target="_blank">'
                    . esc_html( $tax->labels->name ) . '</a>: ' . intval($count) . '</li>';
            }
            $taxo_list .= '</ul>';
        } else {
            $taxo_list = '<em>' . esc_html__( 'Aucune', 'rdc-core-mu-utilities' ) . '</em>';
        }

        // Ligne tableau
        echo '<tr>';
        echo '<td><strong><a href="' . esc_url( $admin_url ) . '" target="_blank">' . esc_html( get_bloginfo('name') ) . '</a></strong><br>'
            . '<a href="' . esc_url( get_site_url() ) . '" target="_blank">' . esc_html( get_site_url() ) . '</a></td>';
        echo '<td>' . $user_list . '</td>';
        echo '<td>' . $stats_list . '</td>';
        echo '<td>' . $taxo_list . '</td>';
        echo '<td>' . $plugins_list . '</td>';
        echo '</tr>';

        restore_current_blog();
    }

    echo '</tbody></table>';
    echo '</div>';
}