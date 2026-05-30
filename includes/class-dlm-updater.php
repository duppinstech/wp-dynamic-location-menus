<?php
/**
 * DLM_Updater
 *
 * Checks GitHub for new plugin releases and enables one-click updates
 * in the WordPress admin.
 */

if (!defined('ABSPATH')) { exit; }

class DLM_Updater {

    const GITHUB_REPO = 'https://github.com/oduppinsjr/wp-dynamic-location-menus/';

    public static function register() {
        if (!is_admin()) {
            return;
        }

        $puc_bootstrap = DLM_PATH . 'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';
        if (!is_readable($puc_bootstrap)) {
            return;
        }

        require_once $puc_bootstrap;

        $plugin_slug = dirname(plugin_basename(DLM_FILE));

        $checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            self::GITHUB_REPO,
            DLM_FILE,
            $plugin_slug
        );

        $checker->setBranch('main');
        $checker->getVcsApi()->enableReleaseAssets('/dynamic-location-menus.*\.zip$/i');

        if (defined('DLM_GITHUB_TOKEN') && DLM_GITHUB_TOKEN) {
            $checker->setAuthentication(DLM_GITHUB_TOKEN);
        }
    }
}
