<?php
/**
 * DLM_Settings
 *
 * All plugin configuration lives in a single option, 'dlm_settings'. Structure:
 *
 *   [
 *     'pattern' => '/service-areas/{region}/{slot}/{city}/',
 *     'pattern_advanced' => false,            // when true, user can edit pattern
 *     'highway_pattern' => '',                // optional secondary pattern like "/service-areas/{region}/heavy-duty-towing/highways-{city}/"
 *     'highway_label'   => 'highways',        // slot name to emit when highway pattern matches
     *     'locations' => [
     *       ['region' => 'missouri', 'city' => 'blue-springs', 'aliases' => ['blue-springs-mo'], 'label' => 'Blue Springs, MO', 'phone' => '(816) 555-0100'],
     *       ['region' => '',         'city' => 'austin',       'aliases' => [],               'label' => 'Austin'],
     *     ],
 *     'slots' => [
 *       ['url_path' => 'services/emergency-towing',     'slot' => 'emergency-towing', 'fallback' => '/services/emergency-towing/'],
 *       ['url_path' => 'services/heavy-duty-towing/rv-towing', 'slot' => 'rv-towing', 'fallback' => '/services/heavy-duty-towing/rv-towing/'],
 *     ],
 *     'on_missing' => 'fallback',             // fallback | hide | leave
 *     'generic_phone' => '(800) 927-5201',  // toll-free on generic service pages; replaced when cloning to a city
 *   ]
 *
 * url_path is the path UNDER the site root WITHOUT leading/trailing slashes,
 * matched against actual menu item URLs.
 */

if (!defined('ABSPATH')) { exit; }

class DLM_Settings {

    const OPTION_KEY = 'dlm_settings';

    /**
     * Get the full settings array, with defaults applied.
     */
    public static function get() {
        $opts = get_option(self::OPTION_KEY, []);
        if (!is_array($opts)) { $opts = []; }
        return array_merge(self::defaults(), $opts);
    }

    public static function save(array $opts) {
        $clean = self::sanitize($opts);
        update_option(self::OPTION_KEY, $clean, false);
        return $clean;
    }

    public static function defaults() {
        return [
            'pattern'          => '/service-areas/{region}/{slot}/{city}/',
            'pattern_advanced' => false,
            'highway_pattern'  => '',
            'highway_label'    => 'highways',
            'locations'        => [],
            'slots'            => [],
            'on_missing'       => 'fallback', // fallback | hide | leave
            'generic_phone'    => '(800) 927-5201',
        ];
    }

    /**
     * On activation: only write defaults if nothing has ever been saved.
     */
    public static function install_defaults() {
        $existing = get_option(self::OPTION_KEY, null);
        if ($existing === null) {
            update_option(self::OPTION_KEY, self::defaults(), false);
        }
    }

    /**
     * Sanitize a submitted settings array. Removes empty rows, normalizes slugs,
     * and strips leading/trailing slashes from url_path entries.
     */
    public static function sanitize(array $opts) {
        $out = self::defaults();

        if (!empty($opts['pattern'])) {
            $out['pattern'] = self::sanitize_pattern($opts['pattern']);
        }
        $out['pattern_advanced'] = !empty($opts['pattern_advanced']);
        if (!empty($opts['highway_pattern'])) {
            $out['highway_pattern'] = self::sanitize_pattern($opts['highway_pattern']);
        }
        if (!empty($opts['highway_label'])) {
            $out['highway_label'] = sanitize_title($opts['highway_label']);
        }
        if (!empty($opts['on_missing']) && in_array($opts['on_missing'], ['fallback', 'hide', 'leave'], true)) {
            $out['on_missing'] = $opts['on_missing'];
        }
        if (isset($opts['generic_phone'])) {
            $out['generic_phone'] = sanitize_text_field($opts['generic_phone']);
        }

        // Locations
        if (!empty($opts['locations']) && is_array($opts['locations'])) {
            foreach ($opts['locations'] as $row) {
                $region = isset($row['region']) ? sanitize_title($row['region']) : '';
                $city   = isset($row['city']) ? sanitize_title($row['city']) : '';
                $label  = isset($row['label']) ? sanitize_text_field($row['label']) : '';
                if ($city === '') { continue; }

                $aliases = [];
                $aliases_raw = isset($row['aliases']) ? $row['aliases'] : '';
                if (is_array($aliases_raw)) {
                    $aliases_list = $aliases_raw;
                } else {
                    $aliases_list = preg_split('/\s*,\s*/', (string) $aliases_raw, -1, PREG_SPLIT_NO_EMPTY);
                }
                foreach ($aliases_list as $alias) {
                    $alias = sanitize_title($alias);
                    if ($alias !== '' && $alias !== $city && !in_array($alias, $aliases, true)) {
                        $aliases[] = $alias;
                    }
                }

                $phone = isset($row['phone']) ? sanitize_text_field($row['phone']) : '';

                $out['locations'][] = [
                    'region'  => $region,
                    'city'    => $city,
                    'aliases' => $aliases,
                    'label'   => $label,
                    'phone'   => $phone,
                ];
            }
        }

        // Slots
        if (!empty($opts['slots']) && is_array($opts['slots'])) {
            foreach ($opts['slots'] as $row) {
                $url_path = isset($row['url_path']) ? trim($row['url_path'], "/ \t\n\r\0\x0B") : '';
                $slot     = isset($row['slot']) ? sanitize_title($row['slot']) : '';
                $fallback = isset($row['fallback']) ? esc_url_raw($row['fallback']) : '';
                if ($url_path === '' || $slot === '') { continue; }

                $city_slugs = [];
                $city_raw = isset($row['city_slugs']) ? $row['city_slugs'] : '';
                if (is_array($city_raw)) {
                    $city_parts = $city_raw;
                } else {
                    $city_parts = preg_split('/\s*,\s*/', (string) $city_raw, -1, PREG_SPLIT_NO_EMPTY);
                }
                foreach ($city_parts as $part) {
                    $part = sanitize_title($part);
                    if ($part !== '' && $part !== $slot && !in_array($part, $city_slugs, true)) {
                        $city_slugs[] = $part;
                    }
                }

                $out['slots'][] = [
                    'url_path'   => $url_path,
                    'slot'       => $slot,
                    'city_slugs' => $city_slugs,
                    'fallback'   => $fallback,
                ];
            }
        }

        return $out;
    }

    private static function sanitize_pattern($p) {
        $p = trim($p);
        if ($p === '') return '';
        if (strpos($p, '/') !== 0) { $p = '/' . $p; }
        if (substr($p, -1) !== '/') { $p .= '/'; }
        return $p;
    }

    /* ---- convenience accessors used elsewhere ---- */

    public static function locations() {
        $opts = self::get();
        return $opts['locations'];
    }
    public static function slots() {
        $opts = self::get();
        return $opts['slots'];
    }
    public static function pattern() {
        $opts = self::get();
        return $opts['pattern'];
    }
    public static function highway_pattern() {
        $opts = self::get();
        return $opts['highway_pattern'];
    }
    public static function highway_label() {
        $opts = self::get();
        return $opts['highway_label'];
    }
    public static function on_missing() {
        $opts = self::get();
        return $opts['on_missing'];
    }

    /**
     * Toll-free number used on generic service pages (replaced when cloning to a city).
     */
    public static function generic_phone() {
        $opts = self::get();
        return isset($opts['generic_phone']) ? (string) $opts['generic_phone'] : '';
    }

    /**
     * Legacy highway pattern support — disabled when the label is too generic.
     */
    public static function highway_features_enabled() {
        $opts = self::get();
        if (empty($opts['pattern_advanced']) || empty($opts['highway_pattern'])) {
            return false;
        }
        $label = sanitize_title($opts['highway_label']);
        if ($label === '' || in_array($label, ['services', 'service', 'towing', 'tow'], true)) {
            return false;
        }
        return (bool) preg_match('#highway#i', $label);
    }

    /**
     * Find a configured location by region + canonical city slug.
     */
    public static function find_location($region, $city) {
        $region = (string) $region;
        $city   = (string) $city;
        foreach (self::locations() as $loc) {
            $r = isset($loc['region']) ? (string) $loc['region'] : '';
            $c = isset($loc['city']) ? (string) $loc['city'] : '';
            if ($r === $region && $c === $city) {
                return $loc;
            }
        }
        return null;
    }

    /**
     * Build a city page path from the configured URL pattern.
     */
    public static function build_city_path($region, $slot, $city) {
        $pattern = self::pattern();
        if ($pattern === '') {
            return '';
        }
        $path = str_replace(
            ['{region}', '{slot}', '{city}'],
            [(string) $region, sanitize_title($slot), (string) $city],
            $pattern
        );
        return DLM_Slug::normalize_path($path);
    }

    /**
     * Slot row for a slot name, or null.
     */
    public static function find_slot($slot) {
        $slot = sanitize_title($slot);
        foreach (self::slots() as $row) {
            if (sanitize_title($row['slot'] ?? '') === $slot) {
                return $row;
            }
        }
        return null;
    }
}
