<?php
/**
 * DLM_Scanner
 *
 * Walks all published pages and builds the page map keyed by (region, slot, city).
 * A page is indexed when its URL contains a city slug and matches a slot row's
 * url_path (not just a partial slot-name substring).
 */

if (!defined('ABSPATH')) { exit; }

class DLM_Scanner {

    public static function register_cron() {
        add_action('dlm_daily_scan', [__CLASS__, 'scan_now']);
        add_action('save_post', [__CLASS__, 'on_post_save'], 10, 3);
    }

    public static function on_post_save($post_ID, $post, $update) {
        if (!$post || $post->post_status !== 'publish') return;
        if (!in_array($post->post_type, self::scan_post_types(), true)) return;
        $url = get_permalink($post_ID);
        if (!$url) return;
        $path = wp_parse_url($url, PHP_URL_PATH);
        if (!$path) return;

        if (DLM_Slug::path_has_any_city_slug($path) || DLM_Slug::match_slot_in_path($path)) {
            self::scan_now();
        }
    }

    /**
     * Post types included when building the city page map.
     * Defaults to pages only — blog posts are excluded so they never become menu targets.
     *
     * @return string[]
     */
    public static function scan_post_types() {
        $types = apply_filters('dlm_scan_post_types', ['page']);
        $types = array_values(array_filter(array_map('strval', (array) $types)));
        return !empty($types) ? $types : ['page'];
    }

    /**
     * Walk published pages, classify, save the map.
     */
    public static function scan_now() {
        $map = [];

        if (empty(DLM_Settings::locations()) || empty(DLM_Settings::slots())) {
            DLM_Registry::save_page_map($map);
            return $map;
        }

        $paged = 1;
        do {
            $q = new WP_Query([
                'post_type'      => self::scan_post_types(),
                'post_status'    => 'publish',
                'posts_per_page' => 100,
                'paged'          => $paged,
                'fields'         => 'ids',
                'no_found_rows'  => false,
            ]);
            if (empty($q->posts)) break;

            foreach ($q->posts as $pid) {
                $url = get_permalink($pid);
                if (!$url) continue;
                $path = wp_parse_url($url, PHP_URL_PATH);
                if (!$path) continue;
                $path = DLM_Slug::normalize_path($path);

                $loc = DLM_Slug::match_location($path);
                if (!$loc) {
                    continue;
                }

                $slot_match = DLM_Slug::match_slot_row_in_path($path);
                if ($slot_match === null) {
                    continue;
                }

                $region = $loc['region'];
                $city   = $loc['city'];
                $slot   = $slot_match['slot'];

                if (!isset($map[$region])) {
                    $map[$region] = [];
                }
                if (!isset($map[$region][$slot])) {
                    $map[$region][$slot] = [];
                }

                $score = DLM_Slug::path_priority_for_slot($path, $slot, $city, $region);
                if ($score < 0) {
                    continue;
                }

                $existing = isset($map[$region][$slot][$city]) ? $map[$region][$slot][$city] : null;
                if ($existing === null) {
                    $map[$region][$slot][$city] = $path;
                    continue;
                }

                $existing_score = DLM_Slug::path_priority_for_slot($existing, $slot, $city, $region);
                if ($score > $existing_score) {
                    $map[$region][$slot][$city] = $path;
                }
            }

            $paged++;
            if ($paged > $q->max_num_pages) break;
        } while (true);

        DLM_Registry::save_page_map($map);
        return $map;
    }
}
