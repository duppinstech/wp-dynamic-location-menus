<?php
/**
 * Plugin Name:       Dynamic Location Menus
 * Plugin URI:        https://duppinstechnology.com/plugins/dynamic-location-menus/
 * Description:       Rewrites nav menu links on the fly based on the visitor's current location context. Build ONE master menu with generic service URLs; on city-specific pages, each link automatically swaps to the matching city page. Locations, URL patterns, and service slots are all admin-configurable.
 * Version:           2.0.0
 * Author:            Duppins Technology
 * Author URI:        https://duppinstechnology.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dynamic-location-menus
 * Requires PHP:      7.4
 * Requires at least: 6.0
 * Update URI:        https://github.com/oduppinsjr/wp-dynamic-location-menus
 */

if (!defined('ABSPATH')) { exit; }

define('DLM_VERSION', '2.0.0');
define('DLM_PATH', plugin_dir_path(__FILE__));
define('DLM_URL', plugin_dir_url(__FILE__));
define('DLM_FILE', __FILE__);

require_once DLM_PATH . 'includes/class-dlm-settings.php';
require_once DLM_PATH . 'includes/class-dlm-slug.php';
require_once DLM_PATH . 'includes/class-dlm-pattern.php';
require_once DLM_PATH . 'includes/class-dlm-registry.php';
require_once DLM_PATH . 'includes/class-dlm-context.php';
require_once DLM_PATH . 'includes/class-dlm-resolver.php';
require_once DLM_PATH . 'includes/class-dlm-menu-filter.php';
require_once DLM_PATH . 'includes/class-dlm-scanner.php';
require_once DLM_PATH . 'includes/class-dlm-content.php';
require_once DLM_PATH . 'includes/class-dlm-audit.php';
require_once DLM_PATH . 'includes/class-dlm-cloner.php';
require_once DLM_PATH . 'includes/class-dlm-admin.php';
require_once DLM_PATH . 'includes/class-dlm-updater.php';

/**
 * Bootstrap.
 */
function dlm_init() {
    DLM_Menu_Filter::register();
    if (is_admin()) {
        DLM_Admin::register();
        DLM_Updater::register();
    }
    DLM_Scanner::register_cron();
}
add_action('plugins_loaded', 'dlm_init');

/**
 * Activation: seed defaults if nothing is configured yet, schedule cron.
 */
register_activation_hook(__FILE__, function () {
    DLM_Settings::install_defaults();
    DLM_Scanner::scan_now();
    if (!wp_next_scheduled('dlm_daily_scan')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'dlm_daily_scan');
    }
});

register_deactivation_hook(__FILE__, function () {
    $ts = wp_next_scheduled('dlm_daily_scan');
    if ($ts) { wp_unschedule_event($ts, 'dlm_daily_scan'); }
});
