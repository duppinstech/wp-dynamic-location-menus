<?php
/**
 * DLM_Registry
 *
 * Reads configuration from DLM_Settings and exposes helpers used by the
 * context detector, resolver, and scanner. Also stores the cached "page map"
 * (which city pages actually exist).
 */

if (!defined('ABSPATH')) { exit; }

class DLM_Registry {

    const OPTION_PAGE_MAP  = 'dlm_page_map';
    const OPTION_LAST_SCAN = 'dlm_last_scan';

    /**
     * Page map structure:
     *
     *   [
     *     'missouri' => [             // region (or '' when locations have no region)
     *       'emergency-towing' => [
     *         'blue-springs' => '/service-areas/missouri/emergency-towing/blue-springs/',
     *         ...
     *       ],
     *       '__highways__' => [ city => url, ... ]
     *     ],
     *     '' => [ ... ]                // region-less entries
     *   ]
     */
    public static function page_map() {
        $map = get_option(self::OPTION_PAGE_MAP, []);
        return is_array($map) ? $map : [];
    }

    public static function save_page_map(array $map) {
        update_option(self::OPTION_PAGE_MAP, $map, false);
        update_option(self::OPTION_LAST_SCAN, time(), false);
    }

    public static function last_scan() {
        return (int) get_option(self::OPTION_LAST_SCAN, 0);
    }

    /**
     * Lookup the city page for (region, slot, city).
     */
    public static function city_page($region, $slot, $city) {
        $map = self::page_map();
        $region = (string) $region;
        $slot   = sanitize_title($slot);
        $city   = (string) $city;

        if (empty($map[$region][$slot][$city])) {
            return null;
        }

        $url = $map[$region][$slot][$city];

        // Guard against stale/wrong index entries.
        foreach (DLM_Settings::slots() as $row) {
            if (sanitize_title($row['slot'] ?? '') !== $slot) {
                continue;
            }
            if (!DLM_Slug::path_matches_slot_row($url, $row)) {
                return null;
            }
            break;
        }

        if (DLM_Slug::path_priority_for_slot($url, $slot, $city, $region) < 0) {
            return null;
        }

        return $url;
    }

    /**
     * Lookup the highway-style page (or whatever the alternate pattern produces).
     */
    public static function highway_page($region, $city) {
        $map = self::page_map();
        $region = (string) $region;
        $label = DLM_Settings::highway_label();
        if (!empty($map[$region][$label][$city])) {
            return $map[$region][$label][$city];
        }
        return null;
    }

    /**
     * True when a path is already stored in the page map.
     */
    public static function path_in_page_map($path) {
        $path = DLM_Slug::normalize_path($path);
        foreach (self::page_map() as $by_region) {
            if (!is_array($by_region)) {
                continue;
            }
            foreach ($by_region as $by_slot) {
                if (!is_array($by_slot)) {
                    continue;
                }
                foreach ($by_slot as $url) {
                    if (DLM_Slug::normalize_path($url) === $path) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Distinct list of region slugs from configured locations.
     */
    public static function regions() {
        $regions = [];
        foreach (DLM_Settings::locations() as $loc) {
            $r = isset($loc['region']) ? (string) $loc['region'] : '';
            if (!in_array($r, $regions, true)) {
                $regions[] = $r;
            }
        }
        return $regions;
    }

    /**
     * Distinct list of city slugs from configured locations.
     */
    public static function cities() {
        $cities = [];
        foreach (DLM_Settings::locations() as $loc) {
            $c = isset($loc['city']) ? (string) $loc['city'] : '';
            if ($c !== '' && !in_array($c, $cities, true)) {
                $cities[] = $c;
            }
            if (!empty($loc['aliases']) && is_array($loc['aliases'])) {
                foreach ($loc['aliases'] as $alias) {
                    $alias = (string) $alias;
                    if ($alias !== '' && !in_array($alias, $cities, true)) {
                        $cities[] = $alias;
                    }
                }
            }
        }
        return $cities;
    }

    /**
     * Cities grouped by region: [region => [city, city, ...]]
     */
    public static function cities_by_region() {
        $out = [];
        foreach (DLM_Settings::locations() as $loc) {
            $r = isset($loc['region']) ? (string) $loc['region'] : '';
            $c = isset($loc['city']) ? (string) $loc['city'] : '';
            if ($c === '') continue;
            if (!isset($out[$r])) $out[$r] = [];
            if (!in_array($c, $out[$r], true)) $out[$r][] = $c;
        }
        return $out;
    }

    /**
     * Resolve a page map path to a post ID.
     *
     * Uses get_page_by_path() so hierarchical URLs like
     * /service-areas/missouri/commercial-towing/joplin/ resolve to the correct
     * page even when many pages share the same post_name (e.g. multiple
     * city slugs like "joplin" under different parent paths).
     * Falls back to url_to_postid() for flat / non-standard permalink structures.
     */
    public static function path_to_post_id($path) {
        $path = DLM_Slug::normalize_path($path);
        $slug = trim($path, '/');
        if ($slug === '') {
            return 0;
        }

        // get_page_by_path walks the hierarchy via path segments.
        $page = get_page_by_path($slug, OBJECT, 'page');
        if ($page && (int) $page->ID > 0) {
            $actual = DLM_Slug::normalize_path(
                (string) wp_parse_url(get_permalink($page->ID), PHP_URL_PATH)
            );
            if ($actual === $path) {
                return (int) $page->ID;
            }
        }

        // Fallback for flat slugs or custom permalink structures.
        $post_id = (int) url_to_postid(home_url($path));
        return $post_id > 0 ? $post_id : 0;
    }

    /**
     * All indexed pages for a location: slot => path.
     *
     * @return array<string, string>
     */
    public static function pages_for_location($region, $city) {
        $map = self::page_map();
        $region = (string) $region;
        $city   = (string) $city;
        $out    = [];

        if (empty($map[$region]) || !is_array($map[$region])) {
            return $out;
        }
        foreach ($map[$region] as $slot => $by_city) {
            if (!is_array($by_city) || empty($by_city[$city])) {
                continue;
            }
            $out[$slot] = $by_city[$city];
        }
        return $out;
    }

    /**
     * First existing page for a slot (any location), for use as clone template.
     *
     * @return array{region:string, city:string, path:string, post_id:int}|null
     */
    public static function find_template_for_slot($slot) {
        $slot = sanitize_title($slot);
        $map  = self::page_map();

        foreach ($map as $region => $by_slot) {
            if (!is_array($by_slot) || empty($by_slot[$slot])) {
                continue;
            }
            foreach ($by_slot[$slot] as $city => $path) {
                if ($path === '') {
                    continue;
                }
                $pid = self::path_to_post_id($path);
                if ($pid > 0) {
                    return [
                        'region'  => (string) $region,
                        'city'    => (string) $city,
                        'path'    => $path,
                        'post_id' => $pid,
                    ];
                }
            }
        }
        return null;
    }

    /**
     * Slot keys that have at least one indexed city page in the page map.
     *
     * @return string[]
     */
    public static function slots_with_pages() {
        $slots = [];
        foreach (self::page_map() as $by_region) {
            if (!is_array($by_region)) {
                continue;
            }
            foreach ($by_region as $slot => $by_city) {
                if (!is_array($by_city) || empty($by_city)) {
                    continue;
                }
                $slots[sanitize_title($slot)] = true;
            }
        }
        $out = array_keys($slots);
        sort($out);
        return $out;
    }

    /**
     * Locations missing a page for the given slot.
     *
     * @return array<int, array>
     */
    public static function missing_locations_for_slot($slot) {
        $slot = sanitize_title($slot);
        $missing = [];
        foreach (DLM_Settings::locations() as $loc) {
            $region = isset($loc['region']) ? (string) $loc['region'] : '';
            $city   = isset($loc['city']) ? (string) $loc['city'] : '';
            if ($city === '') {
                continue;
            }
            if (self::city_page($region, $slot, $city)) {
                continue;
            }
            $missing[] = $loc;
        }
        return $missing;
    }
}
