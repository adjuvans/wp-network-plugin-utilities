<?php

if (! defined('ABSPATH')) {
    exit;
}

class NPU_Network_Sites_Menu
{
    private const ITEM_TYPE = 'network_site';
    private const ITEM_OBJECT = 'network_site';

    public static function init(): void
    {
        add_action('load-nav-menus.php', [__CLASS__, 'addNavMenuMetabox']);
        add_shortcode('network_sites_menu', [__CLASS__, 'shortcodeNetworkSitesMenu']);

        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueueAssets']);
        add_filter('wp_setup_nav_menu_item', [__CLASS__, 'hydrateMenuItem']);
        add_action('wp_update_nav_menu_item', [__CLASS__, 'persistMenuItem'], 10, 3);
        add_action('admin_head-nav-menus.php', [__CLASS__, 'renderLockingAssets']);
        add_filter('wp_nav_menu_item_custom_fields', [__CLASS__, 'renderLockedNotice'], 10, 4);
    }

    /**
     * Vérifie si le menu des sites du réseau est activé.
     */
    protected static function isMenuEnabled(): bool
    {
        return (bool) get_site_option('npu_enable_network_menu', true);
    }

    public static function enqueueAssets($hook): void
    {
        if ($hook !== 'nav-menus.php') {
            return;
        }

        wp_enqueue_style(
            'npu-admin',
            NPU_URL . 'assets/css/npu-admin.css',
            [],
            '1.7'
        );
    }

    /**
     * Récupère les sites publics du réseau.
     */
    protected static function getSitesList(): array
    {
        if (! self::isMenuEnabled()) {
            return [];
        }

        $sites = get_sites([
            'public'   => 1,
            'archived' => 0,
            'deleted'  => 0,
        ]);

        $out = [];
        foreach ($sites as $site) {
            $out[] = [
                'id'   => (int) $site->blog_id,
                'url'  => self::getSiteUrl((int) $site->blog_id),
                'name' => self::getSiteTitle((int) $site->blog_id),
            ];
        }

        return $out;
    }

    /**
     * Ajoute la metabox "Sites du réseau" dans Apparence > Menus.
     */
    public static function addNavMenuMetabox(): void
    {
        add_meta_box(
            'network_sites_nav_links',
            __('Sites du réseau', 'npu-core'),
            [__CLASS__, 'renderNavMenuMetabox'],
            'nav-menus',
            'side',
            'default'
        );
    }

    /**
     * Rend la metabox qui alimente les items de type network_site.
     */
    public static function renderNavMenuMetabox(): void
    {
        $sites = self::getSitesList();
        ?>
        <div id="posttype-network-sites" class="posttypediv">
            <div id="tabs-panel-posttype-network-sites" class="tabs-panel tabs-panel-active">
                <ul id="posttype-network-sites-checklist" class="categorychecklist form-no-clear">
                    <?php foreach ($sites as $i => $site) :
                        $item_id = -($i + 1);
                    ?>
                        <li>
                            <label class="menu-item-title">
                                <input type="checkbox"
                                       class="menu-item-checkbox"
                                       name="menu-item[<?php echo esc_attr($item_id); ?>][menu-item-object-id]"
                                       value="<?php echo esc_attr($site['id']); ?>" />
                                <?php echo esc_html($site['name']); ?>
                            </label>

                            <input type="hidden" class="menu-item-db-id"
                                   name="menu-item[<?php echo esc_attr($item_id); ?>][menu-item-db-id]"
                                   value="0" />
                            <input type="hidden" class="menu-item-object"
                                   name="menu-item[<?php echo esc_attr($item_id); ?>][menu-item-object]"
                                   value="<?php echo esc_attr(self::ITEM_OBJECT); ?>" />
                            <input type="hidden" class="menu-item-parent-id"
                                   name="menu-item[<?php echo esc_attr($item_id); ?>][menu-item-parent-id]"
                                   value="0" />
                            <input type="hidden" class="menu-item-type"
                                   name="menu-item[<?php echo esc_attr($item_id); ?>][menu-item-type]"
                                   value="<?php echo esc_attr(self::ITEM_TYPE); ?>" />
                            <input type="hidden" class="menu-item-title"
                                   name="menu-item[<?php echo esc_attr($item_id); ?>][menu-item-title]"
                                   value="<?php echo esc_attr($site['name']); ?>" />
                            <input type="hidden" class="menu-item-url"
                                   name="menu-item[<?php echo esc_attr($item_id); ?>][menu-item-url]"
                                   value="<?php echo esc_url($site['url']); ?>" />
                            <input type="hidden" class="menu-item-target"
                                   name="menu-item[<?php echo esc_attr($item_id); ?>][menu-item-target]"
                                   value="" />
                            <input type="hidden" class="menu-item-attr-title"
                                   name="menu-item[<?php echo esc_attr($item_id); ?>][menu-item-attr-title]"
                                   value="" />
                            <input type="hidden" class="menu-item-classes"
                                   name="menu-item[<?php echo esc_attr($item_id); ?>][menu-item-classes]"
                                   value="" />
                            <input type="hidden" class="menu-item-xfn"
                                   name="menu-item[<?php echo esc_attr($item_id); ?>][menu-item-xfn]"
                                   value="" />
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <p class="button-controls">
                <span class="add-to-menu">
                    <input type="submit"<?php disabled(empty($sites)); ?>
                           class="button-secondary submit-add-to-menu right"
                           value="<?php esc_attr_e('Ajouter au menu'); ?>"
                           name="add-custom-menu-item"
                           id="submit-posttype-network-sites" />
                    <span class="spinner"></span>
                </span>
            </p>
        </div>
        <?php
    }

    /**
     * Empêche la modification des champs natifs et affiche une note dans l'éditeur de menu.
     */
    public static function renderLockedNotice($item_id, $item, $depth, $args): void
    {
        if (self::ITEM_OBJECT !== $item->object && self::ITEM_TYPE !== $item->type) {
            return;
        }

        printf(
            '<p class="description description-wide npu-network-site-lock">%s</p>',
            esc_html__('URL verrouillée (site du réseau). Le titre peut être personnalisé.', 'npu-core')
        );
    }

    /**
     * Ajoute un style/script léger pour masquer les champs URL/Titre dans l'admin.
     */
    public static function renderLockingAssets(): void
    {
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.menu-item-type-<?php echo esc_attr(self::ITEM_TYPE); ?> .edit-menu-item-url').forEach(function (input) {
                    input.setAttribute('readonly', 'readonly');
                    input.classList.add('disabled');
                });
            });
        </script>
        <?php
    }

    /**
     * Force le type network_site à hydrater automatiquement URL et titre.
     */
    public static function hydrateMenuItem($item)
    {
        if (self::ITEM_OBJECT !== $item->object && self::ITEM_TYPE !== $item->type) {
            return $item;
        }

        $blog_id = (int) $item->object_id;
        $item->type = self::ITEM_TYPE;
        $item->object = self::ITEM_OBJECT;
        $item->type_label = __('Site du réseau', 'npu-core');
        $item->url = self::getSiteUrl($blog_id);
        if (empty($item->title)) {
            $item->title = self::getSiteTitle($blog_id);
            $item->post_title = $item->title;
        }

        return $item;
    }

    /**
     * Force la sauvegarde du type network_site et neutralise les modifications manuelles.
     */
    public static function persistMenuItem($menu_id, $menu_item_db_id, $args): void
    {
        if (! isset($args['menu-item-object']) || self::ITEM_OBJECT !== $args['menu-item-object']) {
            return;
        }

        $blog_id = isset($args['menu-item-object-id']) ? absint($args['menu-item-object-id']) : 0;

        // Si l'ID n'est pas envoyé (édition), récupérer celui stocké.
        if (! $blog_id && $menu_item_db_id) {
            $blog_id = (int) get_post_meta($menu_item_db_id, '_menu_item_object_id', true);
        }

        if (! $blog_id) {
            return;
        }

        update_post_meta($menu_item_db_id, '_menu_item_type', self::ITEM_TYPE);
        update_post_meta($menu_item_db_id, '_menu_item_object', self::ITEM_OBJECT);
        update_post_meta($menu_item_db_id, '_menu_item_object_id', $blog_id);

        $url = self::getSiteUrl($blog_id);
        update_post_meta($menu_item_db_id, '_menu_item_url', esc_url_raw($url));
    }

    /**
     * Affichage dynamique (shortcode).
     */
    public static function shortcodeNetworkSitesMenu($atts)
    {
        $atts = shortcode_atts([
            'class'   => 'network-sites-menu',
            'wrapper' => 'ul',
        ], $atts);

        return self::renderSitesList($atts['wrapper'], $atts['class']);
    }

    /**
     * Rend la liste HTML des sites pour l'affichage front.
     */
    public static function renderSitesList($wrapper = 'ul', $class = 'network-sites-menu')
    {
        $sites = self::getSitesList();
        if (empty($sites)) {
            return '';
        }

        ob_start();
        echo '<' . tag_escape($wrapper) . ' class="' . esc_attr($class) . '">';
        foreach ($sites as $site) {
            echo '<li><a href="' . esc_url($site['url']) . '">' . esc_html($site['name']) . '</a></li>';
        }
        echo '</' . tag_escape($wrapper) . '>';

        return ob_get_clean();
    }

    private static function getSiteUrl(int $blog_id): string
    {
        return get_site_url($blog_id);
    }

    private static function getSiteTitle(int $blog_id): string
    {
        $title = get_blog_option($blog_id, 'blogname');

        if (! $title) {
            $title = sprintf(__('Site #%d', 'npu-core'), $blog_id);
        }

        return (string) $title;
    }
}

/**
 * Fonction helper globale pour l'appel direct en PHP.
 */
function rdc_network_sites_menu($wrapper = 'ul', $class = 'network-sites-menu')
{
    echo NPU_Network_Sites_Menu::renderSitesList($wrapper, $class);
}
