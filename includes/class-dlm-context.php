<?php
/**
 * DLM_Context
 *
 * Detects the visitor's current location context from the request URL by
 * finding a configured city slug anywhere in the path.
 */

if (!defined('ABSPATH')) { exit; }

class DLM_Context {

    private static $cached = null;

    public static function current() {
        if (self::$cached !== null) {
            return self::$cached === false ? null : self::$cached;
        }

        if (!self::should_detect_on_this_view()) {
            self::$cached = false;
            return null;
        }

        $ctx = self::detect_from_url(self::request_path());
        self::$cached = $ctx ?: false;
        return $ctx;
    }

    /**
     * Limit context detection to views where location menus should rewrite.
     */
    private static function should_detect_on_this_view() {
        if (is_admin()) {
            return false;
        }
        $override = apply_filters('dlm_detect_context', null);
        if ($override !== null) {
            return (bool) $override;
        }
        // Detect from the request path whenever a configured city slug is present —
        // menus often render before is_singular() is reliable in the header.
        if (DLM_Slug::path_has_any_city_slug(self::request_path())) {
            return true;
        }
        return is_singular(['page', 'post']);
    }

    public static function reset() {
        self::$cached = null;
    }

    /**
     * Normalize the current request path (path component only, trailing slash).
     */
    public static function request_path() {
        $raw = '/';
        if (isset($_SERVER['REQUEST_URI'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Path normalized below; text fields strip valid URL characters.
            $raw = wp_unslash($_SERVER['REQUEST_URI']);
        }
        $raw   = is_string($raw) ? $raw : '/';
        $parts = wp_parse_url($raw);
        $path  = isset($parts['path']) ? (string) $parts['path'] : '/';
        $path  = '/' . ltrim(preg_replace('#/+#', '/', $path), '/');
        if ($path !== '/' && substr($path, -1) !== '/') {
            $path .= '/';
        }
        return $path;
    }

    /**
     * Detect (region, city, slot) from a path. Returns null when no city slug matches.
     */
    public static function detect_from_url($path) {
        $loc = DLM_Slug::match_location($path);
        if (!$loc) {
            return null;
        }

        return [
            'region'       => $loc['region'],
            'city'         => $loc['city'],
            'matched_slug' => $loc['matched_slug'],
            'slot'         => DLM_Slug::match_slot_in_path($path),
        ];
    }
}
