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
                    . __("Le cache a été rafraîchi avec succès.", 'npu-core')
                    . '</p></div>';
            } else {
                echo '<div class="notice notice-warning is-dismissible"><p>'
                    . __("Veuillez patienter avant de rafraîchir à nouveau le cache.", 'npu-core')
                    . '</p></div>';
            }
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__("Vue d'ensemble des sites du réseau", 'npu-core');

        // Bouton de rafraîchissement
        $refresh_url = wp_nonce_url(
            add_query_arg('refresh_cache', '1'),
            'npu_refresh_cache',
            'npu_nonce'
        );
        echo ' <a href="' . esc_url($refresh_url) . '" class="page-title-action">'
            . __("Rafraîchir le cache", 'npu-core')
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
            . __("Exporter CSV", 'npu-core')
            . '</a>';
        echo ' <a href="' . esc_url($export_json_url) . '" class="page-title-action">'
            . __("Exporter JSON", 'npu-core')
            . '</a>';

        echo '</h1>';

        // Afficher le résumé des alertes
        echo self::render_alerts_panel();

        // Afficher la barre de filtres et recherche
        echo self::render_filters_bar();

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
        $icon_site = '<span class="dashicons dashicons-admin-site"></span> ';
        $icon_info = '<span class="dashicons dashicons-info"></span> ';
        $icon_users = '<span class="dashicons dashicons-admin-users"></span> ';
        $icon_posts = '<span class="dashicons dashicons-admin-post"></span> ';
        $icon_custom = '<span class="dashicons dashicons-admin-customizer"></span> ';
        $icon_plugins = '<span class="dashicons dashicons-admin-plugins"></span> ';

        $tooltips = [
            'site'            => __('Nom du site, alertes et métadonnées techniques (ID, versions WP/PHP/MySQL, locale).', 'npu-core'),
            'infos'           => __('Thème actif, volumes de médias et dernière activité détectée.', 'npu-core'),
            'users'           => __('Utilisateurs ayant accès au site avec leurs rôles.', 'npu-core'),
            'contents_builtin'=> __('Post types et taxonomies WordPress par défaut.', 'npu-core'),
            'contents_custom' => __('Custom Post Types et taxonomies ajoutées par plugins ou thèmes.', 'npu-core'),
            'plugins'         => __('Plugins activés uniquement sur ce site (hors plugins réseau).', 'npu-core'),
        ];

        return [
            // Colonne triable : tooltip sur icône dédiée (injectée côté JS pour rester hors lien de tri)
            'site'             => $icon_site . __( 'Site', 'npu-core' ) . '<span class="npu-header-tip-data" data-tooltip="' . esc_attr($tooltips['site']) . '"></span>',

            // Colonnes non triables : tooltip directement sur le titre
            'infos'            => $icon_info . '<span class="npu-tooltip npu-header-title" data-tooltip="' . esc_attr($tooltips['infos']) . '">' . __( 'Infos techniques', 'npu-core' ) . '</span>',
            'users'            => $icon_users . '<span class="npu-tooltip npu-header-title" data-tooltip="' . esc_attr($tooltips['users']) . '">' . __( 'Utilisateurs', 'npu-core' ) . '</span>',
            'contents_builtin' => $icon_posts . '<span class="npu-tooltip npu-header-title" data-tooltip="' . esc_attr($tooltips['contents_builtin']) . '">' . __( 'Contenus natifs', 'npu-core' ) . '</span>',
            'contents_custom'  => $icon_custom . '<span class="npu-tooltip npu-header-title" data-tooltip="' . esc_attr($tooltips['contents_custom']) . '">' . __( 'Contenus personnalisés', 'npu-core' ) . '</span>',
            'plugins'          => $icon_plugins . '<span class="npu-tooltip npu-header-title" data-tooltip="' . esc_attr($tooltips['plugins']) . '">' . __( 'Plugins locaux', 'npu-core' ) . '</span>',
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
     * Affiche la barre de filtres et recherche
     *
     * @return string HTML
     */
    private static function render_filters_bar() {
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $filter_alert = isset($_GET['npu_filter_alert']) ? sanitize_key($_GET['npu_filter_alert']) : '';
        $filter_users = isset($_GET['npu_filter_users']) ? sanitize_key($_GET['npu_filter_users']) : '';
        $current_page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';

        ob_start();
        ?>
        <div class="npu-filters">
            <form method="get" class="npu-search-form" action="">
                <input type="hidden" name="page" value="<?php echo esc_attr($current_page); ?>">
                <?php if ($filter_alert): ?>
                    <input type="hidden" name="npu_filter_alert" value="<?php echo esc_attr($filter_alert); ?>">
                <?php endif; ?>
                <?php if ($filter_users): ?>
                    <input type="hidden" name="npu_filter_users" value="<?php echo esc_attr($filter_users); ?>">
                <?php endif; ?>
                <input type="search"
                       name="s"
                       value="<?php echo esc_attr($search); ?>"
                       placeholder="<?php esc_attr_e('Rechercher un site par nom ou URL...', 'npu-core'); ?>"
                       class="npu-search-input">
                <button type="submit" class="button npu-search-button">
                    <span class="dashicons dashicons-search"></span>
                    <?php _e('Rechercher', 'npu-core'); ?>
                </button>
                <?php if ($search || $filter_alert || $filter_users): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $current_page)); ?>" class="button">
                        <span class="dashicons dashicons-dismiss"></span>
                        <?php _e('Réinitialiser', 'npu-core'); ?>
                    </a>
                <?php endif; ?>
            </form>

            <form method="get" action="" class="npu-filter-form">
                <input type="hidden" name="page" value="<?php echo esc_attr($current_page); ?>">
                <?php if ($search): ?>
                    <input type="hidden" name="s" value="<?php echo esc_attr($search); ?>">
                <?php endif; ?>
                <select name="npu_filter_alert" class="npu-filter-select" onchange="this.form.submit()">
                    <option value=""><?php _e('Toutes les alertes', 'npu-core'); ?></option>
                    <option value="error" <?php selected($filter_alert, 'error'); ?>>🔴 <?php _e('Critiques uniquement', 'npu-core'); ?></option>
                    <option value="warning" <?php selected($filter_alert, 'warning'); ?>>🟠 <?php _e('Avertissements uniquement', 'npu-core'); ?></option>
                    <option value="none" <?php selected($filter_alert, 'none'); ?>>✅ <?php _e('Sans alerte', 'npu-core'); ?></option>
                </select>
            </form>

            <form method="get" action="" class="npu-filter-form">
                <input type="hidden" name="page" value="<?php echo esc_attr($current_page); ?>">
                <?php if ($search): ?>
                    <input type="hidden" name="s" value="<?php echo esc_attr($search); ?>">
                <?php endif; ?>
                <?php if ($filter_alert): ?>
                    <input type="hidden" name="npu_filter_alert" value="<?php echo esc_attr($filter_alert); ?>">
                <?php endif; ?>
                <select name="npu_filter_users" class="npu-filter-select" onchange="this.form.submit()">
                    <option value=""><?php _e('Tous les sites', 'npu-core'); ?></option>
                    <option value="no_users" <?php selected($filter_users, 'no_users'); ?>><?php _e('Sans utilisateurs', 'npu-core'); ?></option>
                    <option value="with_users" <?php selected($filter_users, 'with_users'); ?>><?php _e('Avec utilisateurs', 'npu-core'); ?></option>
                </select>
            </form>
        </div>
        <?php
        return ob_get_clean();
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

        // Filtrage par recherche textuelle
        if (! empty($_GET['s'])) {
            $search = sanitize_text_field($_GET['s']);
            $all_sites = array_filter($all_sites, function($site) use ($search) {
                $name = get_blog_option($site->blog_id, 'blogname');
                $url = $site->domain . $site->path;
                return stripos($name, $search) !== false || stripos($url, $search) !== false;
            });
            $all_sites = array_values($all_sites); // Réindexer le tableau
        }

        // Filtrage par utilisateurs (nécessite de charger les données)
        if (! empty($_GET['npu_filter_users'])) {
            $filter_users = sanitize_key($_GET['npu_filter_users']);
            $filtered_sites = [];

            foreach ($all_sites as $site) {
                $site_data = NPU_Cache::get_site_data($site, $network_plugins);
                if ($site_data === false) continue;

                $has_users = !empty($site_data['users']);

                if ($filter_users === 'no_users' && !$has_users) {
                    $filtered_sites[] = $site;
                } elseif ($filter_users === 'with_users' && $has_users) {
                    $filtered_sites[] = $site;
                }
            }
            $all_sites = $filtered_sites;
        }

        // Filtrage par alertes (nécessite de charger les données)
        if (! empty($_GET['npu_filter_alert'])) {
            $filter_alert = sanitize_key($_GET['npu_filter_alert']);
            $filtered_sites = [];

            foreach ($all_sites as $site) {
                $site_data = NPU_Cache::get_site_data($site, $network_plugins);
                if ($site_data === false) continue;

                $alerts = NPU_Alerts::get_site_alerts($site_data);
                $has_alerts = !empty($alerts);

                if ($filter_alert === 'none' && !$has_alerts) {
                    $filtered_sites[] = $site;
                } elseif ($filter_alert === 'error' && $has_alerts) {
                    // Vérifier si au moins une alerte est de type error
                    $has_error = false;
                    foreach ($alerts as $alert) {
                        if ($alert['severity'] === 'error') {
                            $has_error = true;
                            break;
                        }
                    }
                    if ($has_error) {
                        $filtered_sites[] = $site;
                    }
                } elseif ($filter_alert === 'warning' && $has_alerts) {
                    // Vérifier si au moins une alerte est de type warning
                    $has_warning = false;
                    foreach ($alerts as $alert) {
                        if ($alert['severity'] === 'warning') {
                            $has_warning = true;
                            break;
                        }
                    }
                    if ($has_warning) {
                        $filtered_sites[] = $site;
                    }
                }
            }
            $all_sites = $filtered_sites;
        }

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
                        '<strong>%s</strong><br><span class="npu-error">%s</span>',
                        esc_html($site->domain . $site->path),
                        __("Erreur de chargement", 'npu-core')
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
        $site_submeta = '<div class="npu-site-meta">';
        $site_submeta .= '<span class="npu-badge npu-badge--meta"><span class="dashicons dashicons-admin-site"></span> ID #' . intval($site_data['blog_id']) . '</span>';
        $site_submeta .= '<span class="npu-badge npu-badge--info"><span class="dashicons dashicons-wordpress"></span> WP ' . esc_html($tech_meta['wp_version'] ?? '') . '</span>';
        $site_submeta .= '<span class="npu-badge npu-badge--info">PHP ' . esc_html($tech_meta['php_version'] ?? '') . '</span>';
        $site_submeta .= '<span class="npu-badge npu-badge--info">MySQL ' . esc_html($tech_meta['mysql_version'] ?? '') . '</span>';
        $site_submeta .= '<span class="npu-badge npu-badge--meta"><span class="dashicons dashicons-translation"></span> ' . esc_html($tech_meta['locale'] ?? '') . '</span>';
        $site_submeta .= '</div>';

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

        // Infos techniques avec wrapper uniforme
        $tech = $site_data['technical_info'];
        $theme_info = sprintf(
            '<a href="%s" target="_blank">%s</a> (v%s)',
            esc_url($admin_url . 'themes.php'),
            esc_html($tech['theme_name']),
            esc_html($tech['theme_version'])
        );

        $infos = '<div class="npu-table-cell-content"><ul>';
        $infos .= '<li><span class="dashicons dashicons-admin-appearance"></span>' . __("Thème", 'npu-core') . ': ' . $theme_info . '</li>';
        $infos .= '<li><span class="dashicons dashicons-format-image"></span>' . __("Médias", 'npu-core') . ': '
            . '<span class="npu-badge npu-badge--success" data-tooltip="' . esc_attr__("Médias avec statut inherit ou publish (utilisables)", 'npu-core') . '"><span class="dashicons dashicons-yes"></span> '
            . __("Valides", 'npu-core') . ': ' . intval($tech['valid_media_count']) . '</span> '
            . '<span class="npu-badge npu-badge--info" data-tooltip="' . esc_attr__("Équivalent du compteur standard WordPress : pièces jointes en statut inherit uniquement", 'npu-core') . '"><span class="dashicons dashicons-media-document"></span> '
            . __("Fichiers", 'npu-core') . ': ' . intval($tech['media_files']) . '</span> '
            . '<span class="npu-badge npu-badge--meta"><span class="dashicons dashicons-images-alt2"></span> ' . __("Total", 'npu-core') . ': ' . intval($tech['attachments_total']) . '</span></li>';
        $last_content = $tech['last_content'] ?? null;
        if ($last_content && ! empty($last_content['date'])) {
            $type_label = $this->get_post_type_label($last_content['type'] ?? '');
            $title_label = $last_content['title'] ?? '';
            $relative = $this->format_relative_datetime($last_content['date']);
            $exact = $this->format_exact_datetime($last_content['date']);
            $tooltip = sprintf(
                /* translators: 1: exact datetime, 2: title, 3: type label */
                __('Dernière activité détectée le %1$s sur le contenu "%2$s" (type : %3$s)', 'npu-core'),
                $exact,
                $title_label,
                $type_label
            );

            $infos .= '<li><span class="dashicons dashicons-backup"></span><span class="npu-last-activity npu-tooltip" data-tooltip="' . esc_attr($tooltip) . '">'
                . __('Dernière activité', 'npu-core') . ': ' . esc_html($relative)
                . '</span></li>';
        } else {
            $infos .= '<li><span class="dashicons dashicons-backup"></span>' . __("Dernière activité", 'npu-core') . ': ' . __("N/A", 'npu-core') . '</li>';
        }
        $infos .= '</ul></div>';

        // Utilisateurs avec wrapper uniforme
        $users = $site_data['users'];
        $user_list = '<div class="npu-table-cell-content">';

        if ($users) {
            $user_list .= '<ul>';
            foreach ($users as $user) {
                $user_list .= '<li><span class="dashicons dashicons-admin-users"></span><a href="' . esc_url($admin_url . 'user-edit.php?user_id=' . $user['ID']) . '" target="_blank">'
                    . esc_html($user['login']) . '</a> <span class="npu-muted">(' . esc_html($user['roles']) . ')</span></li>';
            }
            $user_list .= '</ul>';
        } else {
            $user_list .= '<em>' . __("Aucun", 'npu-core') . '</em>';
        }

        $user_list .= '</div>';

        // CPT natifs
        $cpt_builtin_list = $this->format_post_types($site_data['post_types_builtin'], $admin_url);

        // CPT personnalisés
        $cpt_custom_list = $this->format_post_types($site_data['post_types_custom'], $admin_url);

        // Taxonomies natives
        $taxo_builtin_list = $this->format_taxonomies($site_data['taxonomies_builtin'], $admin_url);

        // Taxonomies personnalisées
        $taxo_custom_list = $this->format_taxonomies($site_data['taxonomies_custom'], $admin_url);

        // Plugins locaux avec wrapper uniforme
        $local_plugins = $site_data['local_plugins'];
        $plugins_list = '<div class="npu-table-cell-content">';

        if ($local_plugins) {
            $plugins_list .= '<ul>';
            foreach ($local_plugins as $plugin) {
                $plugin_name = is_array($plugin) ? ($plugin['name'] ?? $plugin['file'] ?? '') : $plugin;
                $search_link = $admin_url . 'plugins.php?s=' . rawurlencode($plugin_name);

                $info_html = '';
                if (is_array($plugin)) {
                    $info_parts = [];

                    if (! empty($plugin['version'])) {
                        $info_parts[] = __('Version', 'npu-core') . ': ' . esc_html($plugin['version']);
                    }

                    if (! empty($plugin['author'])) {
                        $author = esc_html(wp_strip_all_tags($plugin['author']));
                        if (! empty($plugin['author_uri'])) {
                            $info_parts[] = __('Auteur', 'npu-core') . ': <a href="' . esc_url($plugin['author_uri']) . '" target="_blank" rel="noreferrer noopener">' . $author . '</a>';
                        } else {
                            $info_parts[] = __('Auteur', 'npu-core') . ': ' . $author;
                        }
                    }

                    if (! empty($plugin['plugin_uri'])) {
                        $info_parts[] = __('URL', 'npu-core') . ': <a href="' . esc_url($plugin['plugin_uri']) . '" target="_blank" rel="noreferrer noopener">' . esc_html($plugin['plugin_uri']) . '</a>';
                    }

                    if (! empty($info_parts)) {
                        $tooltip_content = implode(' • ', $info_parts);
                        $info_html = sprintf(
                            ' <span class="dashicons dashicons-info-outline npu-plugin-info" aria-label="%s" data-npu-tooltip="%s" tabindex="0"></span>',
                            esc_attr__('Informations du plugin', 'npu-core'),
                            esc_attr($tooltip_content)
                        );
                    }
                }

                $plugins_list .= '<li><span class="dashicons dashicons-admin-plugins"></span><a href="' . esc_url($search_link) . '" target="_blank">' . esc_html($plugin_name) . '</a>' . $info_html . '</li>';
            }
            $plugins_list .= '</ul>';
        } else {
            $plugins_list .= '<em>' . __("Aucun", 'npu-core') . '</em>';
        }

        $plugins_list .= '</div>';

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
            return '<em>' . __("Aucun", 'npu-core') . '</em>';
        }

        $items = [];
        foreach ($post_types as $pt) {
            $items[] = '<li><a href="' . esc_url($admin_url . 'edit.php?post_type=' . $pt['name']) . '" target="_blank">'
                . esc_html($pt['label']) . '</a>: ' . intval($pt['count'])
                . ' <span class="npu-muted">(' . esc_html($pt['origin']) . ')</span></li>';
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
            return '<em>' . __("Aucune", 'npu-core') . '</em>';
        }

        $items = [];
        foreach ($taxonomies as $tax) {
            $items[] = '<li><a href="' . esc_url($admin_url . 'edit-tags.php?taxonomy=' . $tax['name']) . '" target="_blank">'
                . esc_html($tax['label']) . '</a>: ' . intval($tax['count'])
                . ' <span class="npu-muted">(' . esc_html($tax['origin']) . ')</span></li>';
        }

        return '<ul>' . implode('', $items) . '</ul>';
    }

    /**
     * Formate l'affichage combiné des CPT et taxonomies avec wrapper uniforme.
     */
    private function format_contents_column($post_types_html, $taxonomies_html) {
        $output = '<div class="npu-table-cell-content">';

        // Section Post types
        $output .= '<div>';
        $output .= '<strong class="npu-section-title"><span class="dashicons dashicons-edit"></span>' . __("Post types", 'npu-core') . '</strong>';
        $output .= $post_types_html;
        $output .= '</div>';

        // Section Taxonomies
        $output .= '<div>';
        $output .= '<strong class="npu-section-title"><span class="dashicons dashicons-category"></span>' . __("Taxonomies", 'npu-core') . '</strong>';
        $output .= $taxonomies_html;
        $output .= '</div>';

        $output .= '</div>';
        return $output;
    }

    /**
     * Affiche une date relative en français (human_time_diff).
     */
    private function format_relative_datetime($date_string) {
        $timestamp = $this->to_timestamp($date_string);
        if (! $timestamp) {
            return $date_string;
        }

        $now = current_time('timestamp');
        $human = human_time_diff($timestamp, $now);
        return sprintf(__("il y a %s", 'npu-core'), $human);
    }

    /**
     * Formatte une date exacte jj/mm/aaaa à hh:ii selon WP.
     */
    private function format_exact_datetime($date_string) {
        $timestamp = $this->to_timestamp($date_string);
        if (! $timestamp) {
            return $date_string;
        }

        return wp_date('d/m/Y à H:i', $timestamp);
    }

    /**
     * Convertit une date string en timestamp WP.
     */
    private function to_timestamp($date_string) {
        $timestamp = strtotime($date_string . ' UTC');
        if (! $timestamp) {
            $timestamp = strtotime($date_string);
        }
        return $timestamp ?: null;
    }

    /**
     * Récupère un label lisible pour un post type.
     */
    private function get_post_type_label($post_type) {
        if (! $post_type) {
            return '';
        }

        $obj = get_post_type_object($post_type);
        if ($obj && ! empty($obj->labels->singular_name)) {
            return $obj->labels->singular_name;
        }

        return $post_type;
    }

    public function column_default($item, $column_name) {
        return $item[$column_name] ?? '';
    }
}
