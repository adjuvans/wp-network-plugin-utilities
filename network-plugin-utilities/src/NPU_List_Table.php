<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class NPU_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'site',
            'plural'   => 'sites',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'site'           => __( 'Site', 'rdc-core-mu-utilities' ),
            'infos'          => __( 'Infos techniques', 'rdc-core-mu-utilities' ),
            'users'          => __( 'Utilisateurs', 'rdc-core-mu-utilities' ),
            'cpt_builtin'    => __( 'CPT natifs', 'rdc-core-mu-utilities' ),
            'cpt_custom'     => __( 'CPT personnalisés', 'rdc-core-mu-utilities' ),
            'taxo_builtin'   => __( 'Taxonomies natives', 'rdc-core-mu-utilities' ),
            'taxo_custom'    => __( 'Taxonomies personnalisées', 'rdc-core-mu-utilities' ),
            'plugins'        => __( 'Plugins locaux', 'rdc-core-mu-utilities' ),
        ];
    }

    public function get_sortable_columns() {
        return [
            'site' => [ 'site', true ],
        ];
    }

    public function get_hidden_columns() {
        return [ 'cpt_custom', 'taxo_custom' ];
    }

    public function prepare_items() {
        $columns  = $this->get_columns();
        $hidden   = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        $network_plugins = array_keys( get_site_option( 'active_sitewide_plugins', [] ) );
        $sites = get_sites([ 'number' => 0 ]);

        $data = [];
        foreach ( $sites as $site ) {
            switch_to_blog( $site->blog_id );
            $admin_url = get_admin_url();

            // Infos site
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
            $plugins_list   = $local_plugins ? '<ul>' : '<em>' . __( 'Aucun', 'rdc-core-mu-utilities' ) . '</em>';
            foreach ( $local_plugins as $plugin ) {
                $plugins_list .= '<li><a href="' . esc_url( $admin_url . 'plugins.php' ) . '" target="_blank">' . esc_html( $plugin ) . '</a></li>';
            }
            if ($local_plugins) $plugins_list .= '</ul>';

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

            // CPT
            $post_types = get_post_types([], 'objects');
            $cpt_builtin = [];
            $cpt_custom  = [];

            foreach ($post_types as $pt) {
                if (in_array($pt->name, ['revision','nav_menu_item','custom_css','customize_changeset','oembed_cache'])) continue;

                $count = wp_count_posts($pt->name)->publish ?? 0;
                $item  = '<li><a href="' . esc_url($admin_url . 'edit.php?post_type=' . $pt->name) . '" target="_blank">'
                    . esc_html($pt->labels->name) . '</a>: ' . intval($count) . '</li>';

                if ($pt->_builtin) $cpt_builtin[] = $item; else $cpt_custom[] = $item;
            }

            $cpt_builtin_list = $cpt_builtin ? '<ul>' . implode('', $cpt_builtin) . '</ul>' : '<em>Aucun</em>';
            $cpt_custom_list  = $cpt_custom  ? '<ul>' . implode('', $cpt_custom)  . '</ul>' : '<em>Aucun</em>';

            // Taxonomies
            $taxonomies   = get_taxonomies([], 'objects');
            $tax_builtin  = [];
            $tax_custom   = [];

            foreach ($taxonomies as $tax) {
                if (in_array($tax->name, ['nav_menu','link_category','post_format'])) continue;

                $terms = get_terms([ 'taxonomy' => $tax->name, 'hide_empty' => false ]);
                $count = is_array($terms) ? count($terms) : 0;

                $item = '<li><a href="' . esc_url($admin_url . 'edit-tags.php?taxonomy=' . $tax->name) . '" target="_blank">'
                    . esc_html($tax->labels->name) . '</a>: ' . intval($count) . '</li>';

                if ($tax->_builtin) $tax_builtin[] = $item; else $tax_custom[] = $item;
            }

            $tax_builtin_list = $tax_builtin ? '<ul>' . implode('', $tax_builtin) . '</ul>' : '<em>Aucune</em>';
            $tax_custom_list  = $tax_custom  ? '<ul>' . implode('', $tax_custom)  . '</ul>' : '<em>Aucune</em>';


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
            $total_attachments = wp_count_posts('attachment');
            $attachments_total = array_sum((array) $total_attachments);
            $valid_media_count = 0;
            foreach ( ['inherit','publish'] as $status ) {
                if ( isset($total_attachments->$status) ) $valid_media_count += $total_attachments->$status;
            }
            $media_files = isset($total_attachments->inherit) ? (int) $total_attachments->inherit : 0;
            $last_post_date = get_lastpostdate('blog');

            $infos = '<ul>';
            $infos .= '<li>' . __( 'Thème', 'rdc-core-mu-utilities' ) . ': ' . $theme_info . '</li>';
            $infos .= '<li>' . __( 'Version', 'rdc-core-mu-utilities' ) . ': ' . $wp_info . '</li>';
            $infos .= '<li>' . __( 'Langue', 'rdc-core-mu-utilities' ) . ': ' . esc_html( $lang ) . '</li>';
            $infos .= '<li>' . __( 'Pièces jointes (total)', 'rdc-core-mu-utilities' ) . ': ' . intval($attachments_total) . '</li>';
            $infos .= '<li><span title="' . esc_attr__( 'Médias avec statut inherit ou publish (utilisables)', 'rdc-core-mu-utilities' ) . '" style="cursor:help;border-bottom:1px dotted #666;">'
                    . __( 'Médias valides', 'rdc-core-mu-utilities' ) . '</span>: ' . intval($valid_media_count) . '</li>';
            $infos .= '<li><span title="' . esc_attr__( 'Équivalent du compteur standard WordPress : pièces jointes en statut inherit uniquement', 'rdc-core-mu-utilities' ) . '" style="cursor:help;border-bottom:1px dotted #666;">'
                    . __( 'Fichiers média', 'rdc-core-mu-utilities' ) . '</span>: ' . intval($media_files) . '</li>';
            $infos .= '<li>' . __( 'Dernier contenu', 'rdc-core-mu-utilities' ) . ': ' . esc_html($last_post_date ?: __( 'N/A', 'rdc-core-mu-utilities' )) . '</li>';
            $infos .= '</ul>';

            $data[] = [
                'site'         => $site_name,
                'infos'        => $infos,
                'users'        => $user_list,
                'cpt_builtin'  => $cpt_builtin_list,
                'cpt_custom'   => $cpt_custom_list,
                'taxo_builtin' => $tax_builtin_list,
                'taxo_custom'  => $tax_custom_list,
                'plugins'      => $plugins_list,
            ];

            restore_current_blog();
        }

        // Pagination (selon les préférences "Options de l’écran")
        $per_page     = $this->get_items_per_page('sites_per_page', 20);
        $current_page = $this->get_pagenum();
        $total_items  = count($data);

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