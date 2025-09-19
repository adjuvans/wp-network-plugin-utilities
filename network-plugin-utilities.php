<?php
/*
Plugin Name: Network Plugin Utilities (MU)
Description: Liste les sites du réseau avec plugins locaux, utilisateurs, stats, taxonomies et infos techniques (thème, version WP, langue, médias, dernière mise à jour).
Author: Cyrille de Gourcy <cyrille@gourcy.net>
Version: 1.5
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

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class NPO_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'site',
            'plural'   => 'sites',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'site'      => __( 'Site', 'rdc-core-mu-utilities' ),
            'infos'     => __( 'Infos techniques', 'rdc-core-mu-utilities' ),
            'users'     => __( 'Utilisateurs', 'rdc-core-mu-utilities' ),
            'contents'  => __( 'Contenus', 'rdc-core-mu-utilities' ),
            'taxos'     => __( 'Taxonomies', 'rdc-core-mu-utilities' ),
            'plugins'   => __( 'Plugins locaux', 'rdc-core-mu-utilities' ),
        ];
    }

    public function get_sortable_columns() {
        return [
            'site' => [ 'site', true ],
        ];
    }

    public function prepare_items() {
        $columns  = $this->get_columns();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, [], $sortable];

        $network_plugins = array_keys( get_site_option( 'active_sitewide_plugins', [] ) );
        $sites = get_sites([ 'number' => 0 ]);

        $data = [];
        foreach ( $sites as $site ) {
            switch_to_blog( $site->blog_id );
            $admin_url = get_admin_url();

            // Infos site (nom + URL)
            $site_name = sprintf(
                '<strong><a href="%s">%s</a></strong><br><a href="%s" target="_blank">%s</a>',
                esc_url( $admin_url ),
                esc_html( get_bloginfo('name') ),
                esc_url( get_site_url() ),
                esc_html( get_site_url() )
            );

            // Plugins locaux
            $active_plugins = get_option( 'active_plugins', [] );
            $local_plugins  = array_diff( $active_plugins, $network_plugins );
            if ( ! empty( $local_plugins ) ) {
                $plugins_list = '<ul>';
                foreach ( $local_plugins as $plugin ) {
                    $plugins_list .= '<li><a href="' . esc_url( $admin_url . 'plugins.php' ) . '" target="_blank">' . esc_html( $plugin ) . '</a></li>';
                }
                $plugins_list .= '</ul>';
            } else {
                $plugins_list = '<em>' . __( 'Aucun', 'rdc-core-mu-utilities' ) . '</em>';
            }

            // Utilisateurs
            $users = get_users([ 'blog_id' => $site->blog_id ]);
            if ( $users ) {
                $user_list = '<ul>';
                foreach ( $users as $user ) {
                    $roles = implode(', ', $user->roles);
                    $user_list .= '<li><a href="' . esc_url( $admin_url . 'user-edit.php?user_id=' . $user->ID ) . '" target="_blank">' . esc_html( $user->user_login ) . '</a> (' . esc_html( $roles ) . ')</li>';
                }
                $user_list .= '</ul>';
            } else {
                $user_list = '<em>' . __( 'Aucun', 'rdc-core-mu-utilities' ) . '</em>';
            }

            // Contenus (CPT publics)
            $post_types = get_post_types([], 'objects');

            $builtin = [];
            $custom  = [];

            foreach ($post_types as $pt) {
                // On ignore les types purement techniques
                if ( in_array($pt->name, ['revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache']) ) {
                    continue;
                }

                $count = wp_count_posts($pt->name)->publish ?? 0;
                $item  = '<li><a href="' . esc_url($admin_url . 'edit.php?post_type=' . $pt->name) . '" target="_blank">'
                    . esc_html($pt->labels->name) . '</a>: ' . intval($count) . '</li>';

                if ($pt->_builtin) {
                    $builtin[] = $item;
                } else {
                    $custom[]  = $item;
                }
            }

            $contents_list  = '<ul>';
            if ($builtin) {
                $contents_list .= '<li><strong>' . __( 'Types natifs', 'rdc-core-mu-utilities' ) . '</strong><ul>' . implode('', $builtin) . '</ul></li>';
            }
            if ($custom) {
                $contents_list .= '<li><strong>' . __( 'Types personnalisés', 'rdc-core-mu-utilities' ) . '</strong><ul>' . implode('', $custom) . '</ul></li>';
            }
            $contents_list .= '</ul>';

            // Taxonomies
            $taxonomies = get_taxonomies([ 'public' => true ], 'objects' );
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
                $taxo_list = '<em>' . __( 'Aucune', 'rdc-core-mu-utilities' ) . '</em>';
            }

            // Infos techniques
            $theme = wp_get_theme();
            $theme_info = sprintf(
                '<a href="%s">%s</a> (v%s)',
                esc_url( $admin_url . 'themes.php' ),
                esc_html( $theme->get('Name') ),
                esc_html( $theme->get('Version') )
            );

            global $wp_version;
            $wp_info = 'WordPress ' . esc_html( $wp_version );

            $lang = get_locale();

            // Comptage des médias
            $total_attachments = wp_count_posts( 'attachment' );

            // Pièces jointes (tous statuts)
            $attachments_total = 0;
            foreach ( (array) $total_attachments as $status => $count ) {
                $attachments_total += $count;
            }

            // Médias valides (inherit + publish)
            $valid_media_count = 0;
            foreach ( ['inherit','publish'] as $status ) {
                if ( isset( $total_attachments->$status ) ) {
                    $valid_media_count += $total_attachments->$status;
                }
            }

            // "Fichiers média" = équivalent affichage WP (inherit uniquement)
            $media_files = isset( $total_attachments->inherit ) ? (int) $total_attachments->inherit : 0;

            // Dernier contenu publié
            $last_post_date = get_lastpostdate( 'blog' );

            $infos = '<ul>';
            $infos .= '<li>' . __( 'Thème', 'rdc-core-mu-utilities' ) . ': ' . $theme_info . '</li>';
            $infos .= '<li>' . __( 'Version', 'rdc-core-mu-utilities' ) . ': ' . $wp_info . '</li>';
            $infos .= '<li>' . __( 'Langue', 'rdc-core-mu-utilities' ) . ': ' . esc_html( $lang ) . '</li>';
            $infos .= '<li>' . __( 'Pièces jointes (total)', 'rdc-core-mu-utilities' ) . ': ' . intval( $attachments_total ) . '</li>';
            $infos .= '<li><span title="' . esc_attr__( 'Médias avec statut inherit ou publish (utilisables)', 'rdc-core-mu-utilities' ) . '">'
                . __( 'Médias valides', 'rdc-core-mu-utilities' ) . '</span>: ' . intval( $valid_media_count ) . '</li>';

            $infos .= '<li><span title="' . esc_attr__( 'Équivalent du compteur standard WordPress : pièces jointes en statut inherit uniquement', 'rdc-core-mu-utilities' ) . '">'
                . __( 'Fichiers média', 'rdc-core-mu-utilities' ) . '</span>: ' . intval( $media_files ) . '</li>';
            $infos .= '<li>' . __( 'Dernier contenu', 'rdc-core-mu-utilities' ) . ': ' . esc_html( $last_post_date ?: __( 'N/A', 'rdc-core-mu-utilities' ) ) . '</li>';
            $infos .= '</ul>';

            $data[] = [
                'site'     => $site_name,
                'infos'    => $infos,
                'users'    => $user_list,
                'contents' => $contents_list,
                'taxos'    => $taxo_list,
                'plugins'  => $plugins_list,
            ];

            restore_current_blog();
        }

        // Pagination
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = count($data);

        $this->items = array_slice($data, (($current_page-1)*$per_page), $per_page);
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items/$per_page),
        ]);
    }

    public function column_default($item, $column_name) {
        return $item[$column_name] ?? '';
    }
}

function npo_render_page() {
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Vue d’ensemble des sites du réseau', 'rdc-core-mu-utilities' ) . '</h1>';

    $table = new NPO_List_Table();
    $table->prepare_items();
    $table->display();

    echo '</div>';
}

add_action('admin_head', function() {
    $screen = get_current_screen();
    if ( $screen && $screen->id === 'sites_page_network-plugins-overview-network' ) {
        echo '<style>
            .wp-list-table {
                table-layout: fixed;
                width: 100%;
            }
            .wp-list-table th,
            .wp-list-table td {
                overflow: hidden;
                text-overflow: ellipsis;
                vertical-align: top;
                word-wrap: break-word;
            }
            .wp-list-table th.column-site,
            .wp-list-table td.column-site { width: 18%; }
            .wp-list-table th.column-infos,
            .wp-list-table td.column-infos { width: 18%; }
            .wp-list-table th.column-users,
            .wp-list-table td.column-users { width: 16%; }
            .wp-list-table th.column-contents,
            .wp-list-table td.column-contents { width: 16%; }
            .wp-list-table th.column-taxos,
            .wp-list-table td.column-taxos { width: 16%; }
            .wp-list-table th.column-plugins,
            .wp-list-table td.column-plugins { width: 16%; }

            .wp-list-table span[title] {
                cursor: help;
                border-bottom: 1px dotted #666; /* optionnel : souligne pour indiquer l’info-bulle */
            }
        </style>';
    }
});
