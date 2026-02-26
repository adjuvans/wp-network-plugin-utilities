<?php
/**
 * Système d'alertes pour détecter les sites nécessitant de l'attention
 *
 * @package NPU
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NPU_Alerts {

    /**
     * Types d'alertes disponibles
     */
    const ALERT_NO_USERS = 'no_users';
    const ALERT_INACTIVE = 'inactive';
    const ALERT_HIGH_MEDIA = 'high_media';

    /**
     * Configuration
     */
    private static $config;

    /**
     * Initialisation
     */
    public static function init() {
        self::$config = NPU_Cache::get_config();
    }

    /**
     * Analyse un site et retourne toutes ses alertes
     *
     * @param array $site_data Données du site depuis le cache
     * @return array Liste des alertes avec détails
     */
    public static function get_site_alerts($site_data) {
        $alerts = [];

        // Alerte : Pas d'utilisateurs
        if (self::check_no_users($site_data)) {
            $alerts[] = [
                'type' => self::ALERT_NO_USERS,
                'severity' => 'error',
                'message' => __("Site sans utilisateurs", 'npu-core'),
                'icon' => '⚠️',
            ];
        }

        // Alerte : Site inactif
        $inactive_info = self::check_inactive($site_data);
        if ($inactive_info) {
            $alerts[] = [
                'type' => self::ALERT_INACTIVE,
                'severity' => 'warning',
                'message' => sprintf(
                    __("Inactif depuis %d mois", 'npu-core'),
                    $inactive_info['months']
                ),
                'icon' => '⏰',
            ];
        }

        // Alerte : Trop de médias
        $media_info = self::check_high_media($site_data);
        if ($media_info) {
            $alerts[] = [
                'type' => self::ALERT_HIGH_MEDIA,
                'severity' => 'info',
                'message' => sprintf(
                    __("%d médias (seuil: %d)", 'npu-core'),
                    $media_info['count'],
                    $media_info['threshold']
                ),
                'icon' => '📁',
            ];
        }

        return $alerts;
    }

    /**
     * Vérifie si le site n'a pas d'utilisateurs
     *
     * @param array $site_data
     * @return bool
     */
    private static function check_no_users($site_data) {
        $thresholds = self::$config['alert_thresholds'] ?? [];

        if (!isset($thresholds['no_users']) || !$thresholds['no_users']) {
            return false;
        }

        return empty($site_data['users']);
    }

    /**
     * Vérifie si le site est inactif
     *
     * @param array $site_data
     * @return array|false Informations sur l'inactivité ou false
     */
    private static function check_inactive($site_data) {
        $thresholds = self::$config['alert_thresholds'] ?? [];
        $inactive_months = $thresholds['inactive_months'] ?? 6;

        $last_post_date = $site_data['technical_info']['last_post_date'] ?? null;

        if (!$last_post_date) {
            return false;
        }

        $last_post_timestamp = strtotime($last_post_date);
        $now = current_time('timestamp');
        $months_since_last_post = floor(($now - $last_post_timestamp) / (30 * DAY_IN_SECONDS));

        if ($months_since_last_post >= $inactive_months) {
            return [
                'months' => $months_since_last_post,
                'last_post_date' => $last_post_date,
            ];
        }

        return false;
    }

    /**
     * Vérifie si le site a trop de médias
     *
     * @param array $site_data
     * @return array|false Informations sur les médias ou false
     */
    private static function check_high_media($site_data) {
        $thresholds = self::$config['alert_thresholds'] ?? [];
        $high_media_count = $thresholds['high_media_count'] ?? 1000;

        $media_count = $site_data['technical_info']['attachments_total'] ?? 0;

        if ($media_count >= $high_media_count) {
            return [
                'count' => $media_count,
                'threshold' => $high_media_count,
            ];
        }

        return false;
    }

    /**
     * Génère un badge HTML pour les alertes
     *
     * @param array $alerts Liste des alertes
     * @return string HTML du badge
     */
    public static function render_alerts_badge($alerts) {
        if (empty($alerts)) {
            return '';
        }

        $count = count($alerts);
        $severity = self::get_highest_severity($alerts);

        $title = self::build_alerts_tooltip($alerts);

        return sprintf(
            '<span class="npu-alert-badge npu-alert-%s" data-tooltip="%s">%d</span>',
            esc_attr($severity),
            esc_attr($title),
            $count
        );
    }

    /**
     * Détermine la sévérité la plus élevée parmi les alertes
     *
     * @param array $alerts
     * @return string
     */
    private static function get_highest_severity($alerts) {
        $severity_order = ['error' => 3, 'warning' => 2, 'info' => 1];
        $max_severity = 'info';
        $max_value = 0;

        foreach ($alerts as $alert) {
            $severity = $alert['severity'] ?? 'info';
            $value = $severity_order[$severity] ?? 1;

            if ($value > $max_value) {
                $max_value = $value;
                $max_severity = $severity;
            }
        }

        return $max_severity;
    }

    /**
     * Construit le texte du tooltip pour les alertes
     *
     * @param array $alerts
     * @return string
     */
    private static function build_alerts_tooltip($alerts) {
        $messages = [];

        foreach ($alerts as $alert) {
            $messages[] = ($alert['icon'] ?? '•') . ' ' . $alert['message'];
        }

        return implode(' | ', $messages);
    }

    /**
     * Génère un résumé des alertes pour tout le réseau
     *
     * @param array $all_sites_data Toutes les données des sites
     * @return array Statistiques des alertes
     */
    public static function get_network_alerts_summary($all_sites_data) {
        $summary = [
            'total_sites' => count($all_sites_data),
            'sites_with_alerts' => 0,
            'by_type' => [
                self::ALERT_NO_USERS => 0,
                self::ALERT_INACTIVE => 0,
                self::ALERT_HIGH_MEDIA => 0,
            ],
            'by_severity' => [
                'error' => 0,
                'warning' => 0,
                'info' => 0,
            ],
        ];

        foreach ($all_sites_data as $site_data) {
            $alerts = self::get_site_alerts($site_data);

            if (!empty($alerts)) {
                $summary['sites_with_alerts']++;

                foreach ($alerts as $alert) {
                    $summary['by_type'][$alert['type']]++;
                    $summary['by_severity'][$alert['severity']]++;
                }
            }
        }

        return $summary;
    }

    /**
     * Affiche un panneau de résumé des alertes
     *
     * @param array $summary Résumé des alertes
     * @return string HTML
     */
    public static function render_alerts_summary($summary) {
        if ($summary['sites_with_alerts'] === 0) {
            return '<div class="notice notice-success inline"><p>'
                . __("✓ Aucune alerte détectée sur le réseau", 'npu-core')
                . '</p></div>';
        }

        $html = '<div class="notice notice-warning inline npu-alert-box">';
        $html .= '<p><strong>'
            . sprintf(
                __("⚠️ %d site(s) nécessite(nt) de l'attention", 'npu-core'),
                $summary['sites_with_alerts']
            )
            . '</strong></p>';

        $html .= '<ul class="npu-alert-list">';

        if ($summary['by_type'][self::ALERT_NO_USERS] > 0) {
            $html .= '<li>'
                . sprintf(
                    __("👤 %d site(s) sans utilisateurs", 'npu-core'),
                    $summary['by_type'][self::ALERT_NO_USERS]
                )
                . '</li>';
        }

        if ($summary['by_type'][self::ALERT_INACTIVE] > 0) {
            $html .= '<li>'
                . sprintf(
                    __("⏰ %d site(s) inactif(s)", 'npu-core'),
                    $summary['by_type'][self::ALERT_INACTIVE]
                )
                . '</li>';
        }

        if ($summary['by_type'][self::ALERT_HIGH_MEDIA] > 0) {
            $html .= '<li>'
                . sprintf(
                    __("📁 %d site(s) avec beaucoup de médias", 'npu-core'),
                    $summary['by_type'][self::ALERT_HIGH_MEDIA]
                )
                . '</li>';
        }

        $html .= '</ul>';
        $html .= '</div>';

        return $html;
    }
}
