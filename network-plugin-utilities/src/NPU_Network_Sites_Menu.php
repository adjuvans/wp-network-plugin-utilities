<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class NPU_Network_Sites_Menu {

    public static function init() {
        // Admin : ajouter une meta box dans les menus
        //add_action( 'admin_init', [ __CLASS__, 'add_nav_menu_metabox' ] );
        //add_action( 'admin_head-nav-menus.php', [ __CLASS__, 'add_nav_menu_metabox' ] );
        add_action( 'load-nav-menus.php', [ __CLASS__, 'add_nav_menu_metabox' ] );

        // Shortcode : [network_sites_menu]
        add_shortcode( 'network_sites_menu', [ __CLASS__, 'shortcode_network_sites_menu' ] );
    }

    /**
     * Vérifie si le menu des sites du réseau est activé
     */
    protected static function is_menu_enabled() {
        return (bool) get_site_option('npu_enable_network_menu', true);
    }

    /**
     * Récupère les sites du réseau
     */
    protected static function get_sites_list() {
        if ( ! self::is_menu_enabled() ) {
        return [];
    }

        $sites = get_sites([
            'public'   => 1,
            'archived' => 0,
            'deleted'  => 0
        ]);

        $out = [];
        foreach ( $sites as $site ) {
            $out[] = [
                'id'   => $site->blog_id,
                'url'  => get_site_url( $site->blog_id ),
                'name' => get_blog_option( $site->blog_id, 'blogname' )
            ];
        }
        return $out;
    }

    /**
     * Ajout d’une metabox "Sites du réseau" dans l’écran Apparence > Menus
     */
    public static function add_nav_menu_metabox() {
        add_meta_box(
            'network_sites_nav_links',
            __( 'Sites du réseau', 'rdc-core-mu-utilities' ),
            [ __CLASS__, 'render_nav_menu_metabox' ],
            'nav-menus',
            'side',
            'default'
        );
    }

    public static function render_nav_menu_metabox() {
        $sites = self::get_sites_list();
        ?>
        <div id="posttype-network-sites" class="posttypediv">
            <div id="tabs-panel-posttype-network-sites" class="tabs-panel tabs-panel-active">
                <ul id="posttype-network-sites-checklist" class="categorychecklist form-no-clear">
                    <?php foreach ( $sites as $i => $site ) : 
                        $item_id = - ( $i + 1 );
                    ?>
                        <li>
                            <label class="menu-item-title">
                                <input type="checkbox"
                                    class="menu-item-checkbox"
                                    name="menu-item[<?php echo esc_attr( $item_id ); ?>][menu-item-object-id]"
                                    value="-1" />
                                <?php echo esc_html( $site['name'] ); ?>
                            </label>

                            <input type="hidden" class="menu-item-db-id"
                                name="menu-item[<?php echo esc_attr( $item_id ); ?>][menu-item-db-id]"
                                value="0" />
                            <input type="hidden" class="menu-item-object"
                                name="menu-item[<?php echo esc_attr( $item_id ); ?>][menu-item-object]"
                                value="custom" />
                            <input type="hidden" class="menu-item-parent-id"
                                name="menu-item[<?php echo esc_attr( $item_id ); ?>][menu-item-parent-id]"
                                value="0" />
                            <input type="hidden" class="menu-item-type"
                                name="menu-item[<?php echo esc_attr( $item_id ); ?>][menu-item-type]"
                                value="custom" />
                            <input type="hidden" class="menu-item-title"
                                name="menu-item[<?php echo esc_attr( $item_id ); ?>][menu-item-title]"
                                value="<?php echo esc_attr( $site['name'] ); ?>" />
                            <input type="hidden" class="menu-item-url"
                                name="menu-item[<?php echo esc_attr( $item_id ); ?>][menu-item-url]"
                                value="<?php echo esc_url( $site['url'] ); ?>" />
                            <input type="hidden" class="menu-item-target"
                                name="menu-item[<?php echo esc_attr( $item_id ); ?>][menu-item-target]"
                                value="" />
                            <input type="hidden" class="menu-item-attr-title"
                                name="menu-item[<?php echo esc_attr( $item_id ); ?>][menu-item-attr-title]"
                                value="" />
                            <input type="hidden" class="menu-item-classes"
                                name="menu-item[<?php echo esc_attr( $item_id ); ?>][menu-item-classes]"
                                value="" />
                            <input type="hidden" class="menu-item-xfn"
                                name="menu-item[<?php echo esc_attr( $item_id ); ?>][menu-item-xfn]"
                                value="" />
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <p class="button-controls">
                <span class="add-to-menu">
                    <input type="submit"<?php disabled( empty( $sites ) ); ?>
                        class="button-secondary submit-add-to-menu right"
                        value="<?php esc_attr_e( 'Ajouter au menu' ); ?>"
                        name="add-custom-menu-item"
                        id="submit-posttype-network-sites" />
                    <span class="spinner"></span>
                </span>
            </p>
        </div>
        <?php
    }

    /**
     * Affichage dynamique (shortcode)
     */
    public static function shortcode_network_sites_menu( $atts ) {
        $atts = shortcode_atts([
            'class'   => 'network-sites-menu',
            'wrapper' => 'ul'
        ], $atts );

        return self::render_sites_list( $atts['wrapper'], $atts['class'] );
    }

    /**
     * Rend la liste HTML des sites
     */
    public static function render_sites_list( $wrapper = 'ul', $class = 'network-sites-menu' ) {
        $sites = self::get_sites_list();
        if ( empty( $sites ) ) {
            return '';
        }

        ob_start();
        echo '<' . tag_escape( $wrapper ) . ' class="' . esc_attr( $class ) . '">';
        foreach ( $sites as $site ) {
            echo '<li><a href="' . esc_url( $site['url'] ) . '">' . esc_html( $site['name'] ) . '</a></li>';
        }
        echo '</' . tag_escape( $wrapper ) . '>';
        return ob_get_clean();
    }
}

/**
 * Fonction helper globale pour l'appel direct en PHP
 */
function rdc_network_sites_menu( $wrapper = 'ul', $class = 'network-sites-menu' ) {
    echo NPU_Network_Sites_Menu::render_sites_list( $wrapper, $class );
}