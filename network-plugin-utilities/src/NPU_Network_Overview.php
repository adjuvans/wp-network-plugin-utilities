<?php
/**
 * Vue d'ensemble des sites du réseau
 *
 * @package NPU
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NPU_Network_Overview extends WP_List_Table {

    /**
     * Rendu de la page (appelée depuis NPU_Core::register_menu)
     */
    public static function render_page() {
        // Gérer le rafraîchissement du cache
        if (isset($_GET['refresh_cache']) && check_admin_referer('npu_refresh_cache', 'npu_nonce')) {
            if (NPU_Cache::can_refresh_cache()) {
                NPU_Cache::clear_cache();
                echo '<div class="notice notice-success is-dismissible"><p>'
                    . __("Le cache a été rafraîchi avec succès.", 'rdc-core-mu-utilities')
                    . '</p></div>';
            } else {
                echo '<div class="notice notice-warning is-dismissible"><p>'
                    . __("Veuillez patienter avant de rafraîchir à nouveau le cache.", 'rdc-core-mu-utilities')
                    . '</p></div>';
            }
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__("Vue d'ensemble des sites du réseau", 'rdc-core-mu-utilities');

        // Bouton de rafraîchissement
        $refresh_url = wp_nonce_url(
            add_query_arg('refresh_cache', '1'),
            'npu_refresh_cache',
            'npu_nonce'
        );
        echo ' <a href="' . esc_url($refresh_url) . '" class="page-title-action">'
            . __("Rafraîchir le cache", 'rdc-core-mu-utilities')
            . '</a>';

        // Boutons d'export
        $export_csv_url = wp_nonce_url(
            add_query_arg('npu_export', 'csv'),
            'npu_export',
            'npu_nonce'
        );
        $export_json_url = wp_nonce_url(
            add_query_arg('npu_export', 'json'),
            'npu_export',
            'npu_nonce'
        );
        echo ' <a href="' . esc_url($export_csv_url) . '" class="page-title-action">'
            . __("Exporter CSV", 'rdc-core-mu-utilities')
            . '</a>';
        echo ' <a href="' . esc_url($export_json_url) . '" class="page-title-action">'
            . __("Exporter JSON", 'rdc-core-mu-utilities')
            . '</a>';

        echo '</h1>';

        // Afficher le résumé des alertes
        echo self::render_alerts_panel();

        // Créer et préparer le tableau
        $table = new self();
        $table->prepare_items();

        // Formulaire pour les actions en masse et la pagination
        echo '<form id="npu-network-sites-filter" method="get">';
        echo '<input type="hidden" name="page" value="' . esc_attr($_REQUEST['page'] ?? '') . '" />';

        // Afficher le tableau avec options d'écran et pagination
        $table->display();

        echo '</form>';
        echo '</div>';
    }

    public function __construct() {
        parent::__construct([
            'singular' => 'site',
            'plural'   => 'sites',
            'ajax'     => false,
            'screen'   => get_current_screen(),
        ]);
    }

    public function get_columns() {
        return [
            'site'           => __( 'Site', 'rdc-core-mu-utilities' ),
            'infos'          => __( 'Infos techniques', 'rdc-core-mu-utilities' ),
            'users'          => __( 'Utilisateurs', 'rdc-core-mu-utilities' ),
            'contents_builtin' => __( 'Contenus natifs', 'rdc-core-mu-utilities' ),
            'contents_custom'  => __( 'Contenus personnalisés', 'rdc-core-mu-utilities' ),
            'plugins'        => __( 'Plugins locaux', 'rdc-core-mu-utilities' ),
        ];
    }

    public function get_sortable_columns() {
        return [
            'site' => [ 'site', true ],
        ];
    }

    /**
     * Colonnes masquées par défaut
     * L'utilisateur peut les afficher via "Options de l'écran"
     */
    public function get_hidden_columns() {
        // Récupérer les préférences utilisateur
        $user = get_current_user_id();
        $screen = get_current_screen();

        if ($screen) {
            $hidden = get_user_option('manage' . $screen->id . 'columnshidden', $user);

            // Si l'utilisateur a des préférences, les utiliser
            if (!empty($hidden)) {
                return $hidden;
            }
        }

        // Sinon, colonnes masquées par défaut
        return [ 'contents_custom' ];
    }

    /**
     * Affiche le panneau de résumé des alertes
     *
     * @return string HTML
     */
    private static function render_alerts_panel() {
        $sites = get_sites(['number' => 0]);
        $network_plugins = array_keys(get_site_option('active_sitewide_plugins', []));
        $all_sites_data = [];

        foreach ($sites as $site) {
            $site_data = NPU_Cache::get_site_data($site, $network_plugins);
            if ($site_data !== false) {
                $all_sites_data[] = $site_data;
            }
        }

        $summary = NPU_Alerts::get_network_alerts_summary($all_sites_data);
        return NPU_Alerts::render_alerts_summary($summary);
    }

    /**
     * Prépare les éléments à afficher
     */
    public function prepare_items() {
        $columns  = $this->get_columns();
        $hidden   = get_hidden_columns($this->screen);
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [ $columns, $hidden, $sortable ];

        $network_plugins = array_keys( get_site_option( 'active_sitewide_plugins', [] ) );

        // Pagination avant de récupérer les données
        $config = NPU_Cache::get_config();
        $per_page = $this->get_items_per_page('sites_per_page', $config['default_per_page']);
        $current_page = $this->get_pagenum();

        // Récupérer tous les sites pour la pagination
        $all_sites = get_sites(['number' => 0]);
        $total_items = count($all_sites);

        // Ne traiter que les sites de la page courante
        $offset = ($current_page - 1) * $per_page;
        $sites_for_page = array_slice($all_sites, $offset, $per_page);

        $data = [];

        foreach ($sites_for_page as $site) {
            // Utiliser le cache
            $site_data = NPU_Cache::get_site_data($site, $network_plugins);

            if ($site_data === false) {
                // En cas d'erreur, afficher un message minimal
                $data[] = [
                    'site' => sprintf(
                        '<strong>%s</strong><br><span style="color:#d63638;">%s</span>',
                        esc_html($site->domain . $site->path),
                        __("Erreur de chargement", 'rdc-core-mu-utilities')
                    ),
                    'infos' => '',
                    'users' => '',
                    'contents_builtin' => '',
                    'contents_custom' => '',
                    'plugins' => '',
                ];
                continue;
            }

            // Formater les données pour l'affichage
            $data[] = $this->format_site_data($site_data);
        }

        $this->items = $data;
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
    }

    /**
     * Formate les données d'un site pour l'affichage
     *
     * @param array $site_data Données du site depuis le cache
     * @return array Données formatées pour le tableau
     */
    private function format_site_data($site_data) {
        $admin_url = $site_data['site_info']['admin_url'];

        // Détecter les alertes
        $alerts = NPU_Alerts::get_site_alerts($site_data);
        $alerts_badge = NPU_Alerts::render_alerts_badge($alerts);

        $tech_meta = $site_data['technical_info'];
        $site_submeta = sprintf(
            '<div class="npu-site-meta"><small>ID #%d | WP %s | PHP %s | %s</small></div>',
            intval($site_data['blog_id']),
            esc_html($tech_meta['wp_version'] ?? ''),
            esc_html($tech_meta['php_version'] ?? ''),
            esc_html($tech_meta['locale'] ?? '')
        );

        // Nom du site avec badge d'alertes + métadonnées
        $site_name = sprintf(
            '<strong><a href="%s" target="_blank">%s</a>%s</strong>%s<br><a href="%s" target="_blank">%s</a>',
            esc_url($admin_url),
            esc_html($site_data['site_info']['name']),
            $alerts_badge,
            $site_submeta,
            esc_url($site_data['site_info']['url']),
            esc_html($site_data['site_info']['url'])
        );

        // Infos techniques
        $tech = $site_data['technical_info'];
        $theme_info = sprintf(
            '<a href="%s" target="_blank">%s</a> (v%s)',
            esc_url($admin_url . 'themes.php'),
            esc_html($tech['theme_name']),
            esc_html($tech['theme_version'])
        );

        $infos = '<ul>';
        $infos .= '<li>' . __("Thème", 'rdc-core-mu-utilities') . ': ' . $theme_info . '</li>';
        $infos .= '<li>' . __("Version", 'rdc-core-mu-utilities') . ': WordPress ' . esc_html($tech['wp_version'] ?? '') . '</li>';
        if (! empty($tech['php_version'])) {
            $infos .= '<li>PHP: ' . esc_html($tech['php_version']) . '</li>';
        }
        if (! empty($tech['mysql_version'])) {
            $infos .= '<li>MySQL: ' . esc_html($tech['mysql_version']) . '</li>';
        }
        $infos .= '<li>' . __("Langue", 'rdc-core-mu-utilities') . ': ' . esc_html($tech['locale']) . '</li>';
        $infos .= '<li>' . __("Pièces jointes (total)", 'rdc-core-mu-utilities') . ': ' . intval($tech['attachments_total']) . '</li>';
        $infos .= '<li><span title="' . esc_attr__("Médias avec statut inherit ou publish (utilisables)", 'rdc-core-mu-utilities') . '" style="cursor:help;border-bottom:1px dotted #666;">'
                . __("Médias valides", 'rdc-core-mu-utilities') . '</span>: ' . intval($tech['valid_media_count']) . '</li>';
        $infos .= '<li><span title="' . esc_attr__("Équivalent du compteur standard WordPress : pièces jointes en statut inherit uniquement", 'rdc-core-mu-utilities') . '" style="cursor:help;border-bottom:1px dotted #666;">'
                . __("Fichiers média", 'rdc-core-mu-utilities') . '</span>: ' . intval($tech['media_files']) . '</li>';
        $infos .= '<li>' . __("Dernier contenu", 'rdc-core-mu-utilities') . ': ' . esc_html($tech['last_post_date'] ?: __("N/A", 'rdc-core-mu-utilities')) . '</li>';
        $infos .= '</ul>';

        // Utilisateurs
        $users = $site_data['users'];
        if ($users) {
            $user_list = '<ul>';
            foreach ($users as $user) {
                $user_list .= '<li><a href="' . esc_url($admin_url . 'user-edit.php?user_id=' . $user['ID']) . '" target="_blank">'
                    . esc_html($user['login']) . '</a> <small style="color:#666;">(' . esc_html($user['roles']) . ')</small></li>';
            }
            $user_list .= '</ul>';
        } else {
            $user_list = '<em>' . __("Aucun", 'rdc-core-mu-utilities') . '</em>';
        }

        // CPT natifs
        $cpt_builtin_list = $this->format_post_types($site_data['post_types_builtin'], $admin_url);

        // CPT personnalisés
        $cpt_custom_list = $this->format_post_types($site_data['post_types_custom'], $admin_url);

        // Taxonomies natives
        $taxo_builtin_list = $this->format_taxonomies($site_data['taxonomies_builtin'], $admin_url);

        // Taxonomies personnalisées
        $taxo_custom_list = $this->format_taxonomies($site_data['taxonomies_custom'], $admin_url);

        // Plugins locaux
        $local_plugins = $site_data['local_plugins'];
        if ($local_plugins) {
            $plugins_list = '<ul>';
            foreach ($local_plugins as $plugin) {
                $plugin_name = is_array($plugin) ? ($plugin['name'] ?? $plugin['file'] ?? '') : $plugin;
                $search_link = $admin_url . 'plugins.php?s=' . rawurlencode($plugin_name);

                $info_html = '';
                if (is_array($plugin)) {
                    $info_parts = [];

                    if (! empty($plugin['version'])) {
                        $info_parts[] = __('Version', 'rdc-core-mu-utilities') . ': ' . esc_html($plugin['version']);
                    }

                    if (! empty($plugin['author'])) {
                        $author = esc_html(wp_strip_all_tags($plugin['author']));
                        if (! empty($plugin['author_uri'])) {
                            $info_parts[] = __('Auteur', 'rdc-core-mu-utilities') . ': <a href="' . esc_url($plugin['author_uri']) . '" target="_blank" rel="noreferrer noopener">' . $author . '</a>';
                        } else {
                            $info_parts[] = __('Auteur', 'rdc-core-mu-utilities') . ': ' . $author;
                        }
                    }

                    if (! empty($plugin['plugin_uri'])) {
                        $info_parts[] = __('URL', 'rdc-core-mu-utilities') . ': <a href="' . esc_url($plugin['plugin_uri']) . '" target="_blank" rel="noreferrer noopener">' . esc_html($plugin['plugin_uri']) . '</a>';
                    }

                    if (! empty($info_parts)) {
                        $info_html = '<details class="npu-plugin-details"><summary>Infos</summary><div class="npu-plugin-meta">'
                            . implode('<br>', $info_parts)
                            . '</div></details>';
                    }
                }

                $plugins_list .= '<li><a href="' . esc_url($search_link) . '" target="_blank">' . esc_html($plugin_name) . '</a>' . $info_html . '</li>';
            }
            $plugins_list .= '</ul>';
        } else {
            $plugins_list = '<em>' . __("Aucun", 'rdc-core-mu-utilities') . '</em>';
        }

        return [
            'site'         => $site_name,
            'infos'        => $infos,
            'users'        => $user_list,
            'contents_builtin' => $this->format_contents_column($cpt_builtin_list, $taxo_builtin_list),
            'contents_custom'  => $this->format_contents_column($cpt_custom_list, $taxo_custom_list),
            'plugins'      => $plugins_list,
        ];
    }

    /**
     * Formate les post types pour l'affichage
     *
     * @param array $post_types Liste des post types
     * @param string $admin_url URL de l'admin du site
     * @return string HTML formaté
     */
    private function format_post_types($post_types, $admin_url) {
        if (empty($post_types)) {
            return '<em>' . __("Aucun", 'rdc-core-mu-utilities') . '</em>';
        }

        $items = [];
        foreach ($post_types as $pt) {
            $items[] = '<li><a href="' . esc_url($admin_url . 'edit.php?post_type=' . $pt['name']) . '" target="_blank">'
                . esc_html($pt['label']) . '</a>: ' . intval($pt['count'])
                . ' <small style="color:#666;">(' . esc_html($pt['origin']) . ')</small></li>';
        }

        return '<ul>' . implode('', $items) . '</ul>';
    }

    /**
     * Formate les taxonomies pour l'affichage
     *
     * @param array $taxonomies Liste des taxonomies
     * @param string $admin_url URL de l'admin du site
     * @return string HTML formaté
     */
    private function format_taxonomies($taxonomies, $admin_url) {
        if (empty($taxonomies)) {
            return '<em>' . __("Aucune", 'rdc-core-mu-utilities') . '</em>';
        }

        $items = [];
        foreach ($taxonomies as $tax) {
            $items[] = '<li><a href="' . esc_url($admin_url . 'edit-tags.php?taxonomy=' . $tax['name']) . '" target="_blank">'
                . esc_html($tax['label']) . '</a>: ' . intval($tax['count'])
                . ' <small style="color:#666;">(' . esc_html($tax['origin']) . ')</small></li>';
        }

        return '<ul>' . implode('', $items) . '</ul>';
    }

    /**
     * Formate l'affichage combiné des CPT et taxonomies.
     */
    private function format_contents_column($post_types_html, $taxonomies_html) {
        $post_types_section = '<div><strong>' . __("Post types", 'rdc-core-mu-utilities') . '</strong><br>' . $post_types_html . '</div>';
        $taxonomies_section = '<div style="margin-top:8px;"><strong>' . __("Taxonomies", 'rdc-core-mu-utilities') . '</strong><br>' . $taxonomies_html . '</div>';
        return $post_types_section . $taxonomies_section;
    }

    public function column_default($item, $column_name) {
        return $item[$column_name] ?? '';
    }
}
