<?php
/**
 * Gestion des exports de données
 *
 * @package NPU
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NPU_Export {

    /**
     * Initialisation
     */
    public static function init() {
        add_action('admin_init', [__CLASS__, 'handle_export']);
    }

    /**
     * Gère les requêtes d'export
     */
    public static function handle_export() {
        if (!isset($_GET['npu_export'])) {
            return;
        }

        // Vérifier les permissions
        if (!current_user_can('manage_network_plugins')) {
            wp_die(__("Vous n'avez pas les permissions nécessaires.", 'rdc-core-mu-utilities'));
        }

        // Vérifier le nonce
        if (!isset($_GET['npu_nonce']) || !wp_verify_nonce($_GET['npu_nonce'], 'npu_export')) {
            wp_die(__("Nonce invalide.", 'rdc-core-mu-utilities'));
        }

        $format = sanitize_text_field($_GET['npu_export']);

        switch ($format) {
            case 'csv':
                self::export_csv();
                break;
            case 'json':
                self::export_json();
                break;
            default:
                wp_die(__("Format d'export non supporté.", 'rdc-core-mu-utilities'));
        }
    }

    /**
     * Export au format CSV
     */
    private static function export_csv() {
        $data = self::get_export_data();

        // Headers pour forcer le téléchargement
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="network-sites-' . date('Y-m-d-His') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // UTF-8 BOM pour Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // En-têtes CSV
        $headers = [
            __("ID", 'rdc-core-mu-utilities'),
            __("Nom du site", 'rdc-core-mu-utilities'),
            __("URL", 'rdc-core-mu-utilities'),
            __("Thème", 'rdc-core-mu-utilities'),
            __("Version thème", 'rdc-core-mu-utilities'),
            __("Langue", 'rdc-core-mu-utilities'),
            __("Nombre d'utilisateurs", 'rdc-core-mu-utilities'),
            __("Pièces jointes totales", 'rdc-core-mu-utilities'),
            __("Médias valides", 'rdc-core-mu-utilities'),
            __("Fichiers média", 'rdc-core-mu-utilities'),
            __("Dernier contenu", 'rdc-core-mu-utilities'),
            __("Plugins locaux", 'rdc-core-mu-utilities'),
            __("CPT natifs", 'rdc-core-mu-utilities'),
            __("CPT personnalisés", 'rdc-core-mu-utilities'),
            __("Taxonomies natives", 'rdc-core-mu-utilities'),
            __("Taxonomies personnalisées", 'rdc-core-mu-utilities'),
        ];
        fputcsv($output, $headers);

        // Données
        foreach ($data as $site_data) {
            $row = [
                $site_data['blog_id'],
                $site_data['site_info']['name'],
                $site_data['site_info']['url'],
                $site_data['technical_info']['theme_name'],
                $site_data['technical_info']['theme_version'],
                $site_data['technical_info']['locale'],
                count($site_data['users']),
                $site_data['technical_info']['attachments_total'],
                $site_data['technical_info']['valid_media_count'],
                $site_data['technical_info']['media_files'],
                $site_data['technical_info']['last_post_date'] ?: 'N/A',
                self::format_array_for_csv($site_data['local_plugins']),
                self::format_post_types_for_csv($site_data['post_types_builtin']),
                self::format_post_types_for_csv($site_data['post_types_custom']),
                self::format_taxonomies_for_csv($site_data['taxonomies_builtin']),
                self::format_taxonomies_for_csv($site_data['taxonomies_custom']),
            ];
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    /**
     * Export au format JSON
     */
    private static function export_json() {
        $data = self::get_export_data();

        // Headers pour forcer le téléchargement
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="network-sites-' . date('Y-m-d-His') . '.json"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $export = [
            'meta' => [
                'export_date' => current_time('mysql'),
                'network_url' => network_site_url(),
                'total_sites' => count($data),
                'plugin_version' => '1.6.0',
            ],
            'sites' => $data,
        ];

        echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Récupère les données à exporter
     *
     * @return array
     */
    private static function get_export_data() {
        $sites = get_sites(['number' => 0]);
        $network_plugins = array_keys(get_site_option('active_sitewide_plugins', []));
        $data = [];

        foreach ($sites as $site) {
            $site_data = NPU_Cache::get_site_data($site, $network_plugins);

            if ($site_data !== false) {
                $data[] = $site_data;
            }
        }

        return $data;
    }

    /**
     * Formate un tableau pour l'affichage CSV
     *
     * @param array $items
     * @return string
     */
    private static function format_array_for_csv($items) {
        if (empty($items)) {
            return '';
        }
        return implode(', ', $items);
    }

    /**
     * Formate les post types pour CSV
     *
     * @param array $post_types
     * @return string
     */
    private static function format_post_types_for_csv($post_types) {
        if (empty($post_types)) {
            return '';
        }

        $formatted = [];
        foreach ($post_types as $pt) {
            $formatted[] = sprintf('%s (%d)', $pt['label'], $pt['count']);
        }
        return implode(', ', $formatted);
    }

    /**
     * Formate les taxonomies pour CSV
     *
     * @param array $taxonomies
     * @return string
     */
    private static function format_taxonomies_for_csv($taxonomies) {
        if (empty($taxonomies)) {
            return '';
        }

        $formatted = [];
        foreach ($taxonomies as $tax) {
            $formatted[] = sprintf('%s (%d)', $tax['label'], $tax['count']);
        }
        return implode(', ', $formatted);
    }
}
