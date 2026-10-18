<?php
/**
 * Uninstall handler.
 *
 * Removes plugin options and scheduled events when the plugin is deleted.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('dlm_settings');
delete_option('dlm_page_map');
delete_option('dlm_last_scan');
delete_site_option('dlm_github_release');

$timestamp = wp_next_scheduled('dlm_daily_scan');
if ($timestamp) {
    wp_unschedule_event($timestamp, 'dlm_daily_scan');
}
