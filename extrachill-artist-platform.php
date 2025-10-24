<?php
/**
 * Plugin Name: Extra Chill Artist Platform
 * Plugin URI: https://extrachill.com
 * Description: Artist platform for musicians with profiles, link pages, analytics, and subscriber management.
 * Version: 1.0.0
 * Author: Chris Huber
 * Author URI: https://chubes.net
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: extrachill-artist-platform
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: bbpress
 */

defined( 'ABSPATH' ) || exit;
define( 'EXTRACHILL_ARTIST_PLATFORM_VERSION', '1.0.0' );
define( 'EXTRACHILL_ARTIST_PLATFORM_PLUGIN_FILE', __FILE__ );
define( 'EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EXTRACHILL_ARTIST_PLATFORM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EXTRACHILL_ARTIST_PLATFORM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

if ( ! defined( 'EXTRCH_LINKPAGE_DEV' ) ) {
    define( 'EXTRCH_LINKPAGE_DEV', false );
}

// Load Composer autoloader for dependencies (Endroid QR Code)
if ( file_exists( EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'vendor/autoload.php';
}

class ExtraChillArtistPlatform {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/core/artist-platform-post-types.php';
        $this->init_hooks();
    }


    private function init_hooks() {
        add_action( 'init', array( $this, 'init' ), 15 );
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    }

    public function init() {
        $this->load_includes();
    }

    public function load_textdomain() {
        load_plugin_textdomain( 
            'extrachill-artist-platform', 
            false, 
            dirname( EXTRACHILL_ARTIST_PLATFORM_PLUGIN_BASENAME ) . '/languages' 
        );
    }

    private function load_includes() {
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/core/class-templates.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/core/artist-platform-assets.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/core/artist-platform-rewrite-rules.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/core/filters/ids.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/core/filters/defaults.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/core/filters/create.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/core/filters/social-icons.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/core/filters/fonts.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/core/filters/templates.php';

        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/artist-profiles/admin/user-linking.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/artist-profiles/admin/meta-boxes.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/artist-profiles/artist-forums.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/core/filters/permissions.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/artist-profiles/artist-following.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/artist-profiles/artist-forum-section-overrides.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/artist-profiles/frontend/frontend-forms.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/artist-profiles/frontend/artist-grid.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/artist-profiles/frontend/breadcrumbs.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/artist-profiles/subscribe-data-functions.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/artist-profiles/blog-coverage.php';

        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/link-pages/create-link-page.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/link-pages/live/ajax/analytics.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/link-pages/live/ajax/edit-permission.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/link-pages/live/link-page-head.php';

        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/link-pages/management/ajax/background.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/link-pages/management/ajax/qrcode.php';
        
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/link-pages/management/ajax/social.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/link-pages/management/ajax/links.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/link-pages/management/ajax/subscribe.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/link-pages/management/ajax/analytics.php';
        
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/link-pages/management/live-preview/class-live-preview-handler.php';
        
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/link-pages/management/advanced-tab/link-expiration.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/link-pages/management/advanced-tab/temporary-redirect.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/link-pages/management/advanced-tab/youtube-embed-control.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/link-pages/management/advanced-tab/subscription-settings.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/link-pages/management/advanced-tab/meta-pixel-tracking.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/link-pages/management/advanced-tab/google-tag-tracking.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/link-pages/management/advanced-tab/link-page-weekly-email.php';

        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/link-pages/subscribe-functions.php';

        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/artist-profiles/roster/manage-roster-ui.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/artist-profiles/roster/roster-ajax-handlers.php';

        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/notifications/artist-notifications.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/notifications/artist-notification-cards.php';

        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/database/subscriber-db.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/database/link-page-analytics-db.php';

        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/core/actions/save.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/core/actions/sync.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/core/actions/delete.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/core/actions/add.php';

        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/home/homepage-hooks.php';
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/home/homepage-artist-card-actions.php';

        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/join/join-flow.php';
        
        require_once EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/core/filters/data.php';

        ExtraChillArtistPlatform_PageTemplates::instance();
        ExtraChillArtistPlatform_Assets::instance();
        ExtraChillArtistPlatform_SocialLinks::instance();
        ExtraChillArtistPlatform_Fonts::instance();

        add_action('after_switch_theme', 'extrch_create_subscribers_table');
    }

    public static function activate() {
        flush_rewrite_rules();
        update_option( 'extrachill_artist_platform_activated', true );
    }

    public static function deactivate() {
        flush_rewrite_rules();
        delete_option( 'extrachill_artist_platform_activated' );

        // Unschedule analytics cron jobs
        if (function_exists('extrch_unschedule_analytics_aggregation_cron')) {
            extrch_unschedule_analytics_aggregation_cron();
        }

        if (function_exists('extrch_unschedule_analytics_pruning_cron')) {
            extrch_unschedule_analytics_pruning_cron();
        }
    }

}

function extrachill_artist_platform() {
    return ExtraChillArtistPlatform::instance();
}

extrachill_artist_platform();

register_activation_hook( __FILE__, array( 'ExtraChillArtistPlatform', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ExtraChillArtistPlatform', 'deactivate' ) );