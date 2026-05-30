<?php
/**
 * DLM_Resolver
 *
 * Decides what URL each menu item should point to given the current context.
 *
 * Match priority:
 *   1. If no context → return original URL.
 *   2. If URL is external or anchor → leave it.
 *   3. If URL is already a city-specific page → leave it.
 *   4. If URL matches a configured slot rule → swap to the city version when
 *      one exists; otherwise apply on_missing policy (fallback/hide/leave).
 *   5. If URL "looks like" a highway link → swap to city highway page.
 *
 * EXACT-DEPTH MATCH on slot URLs: a configured slot like "services/roadside-assistance"
 * does NOT match a longer URL like "services/roadside-assistance/mobile-tire-change".
 */

if (!defined('ABSPATH')) { exit; }

class DLM_Resolver {

    /**
     * @param string $url      Original URL on the menu item (relative or absolute).
     * @param array|null $context  ['region', 'city', 'slot'] or null
     * @return string|null  Resolved URL — or null when policy says "hide this item".
     */
    public static function resolve($url, $context) {
        if (!$context || empty($context['city'])) {
            return $url;
        }
        $region = isset($context['region']) ? $context['region'] : '';
        $city   = $context['city'];

        $path = self::to_local_path($url);
        if ($path === null) {
            return $url; // external
        }

        if (self::is_city_url_for_context($path, $region, $city)) {
            return $url;
        }

        $slot_info = self::match_slot($path);
        if (!$slot_info) {
            $slot_name = DLM_Slug::match_slot_in_path($path);
            if ($slot_name) {
                $row = DLM_Settings::find_slot($slot_name);
                $slot_info = [
                    'slot'     => $slot_name,
                    'fallback' => ($row && !empty($row['fallback'])) ? $row['fallback'] : '',
                ];
            }
        }
        if ($slot_info) {
            $city_url = DLM_Registry::city_page($region, $slot_info['slot'], $city);
            if ($city_url) {
                return $city_url;
            }
            return self::apply_on_missing($slot_info['fallback'] ?: $url);
        }

        if (self::looks_like_highway($path)) {
            $hw = DLM_Registry::highway_page($region, $city);
            if ($hw) {
                return $hw;
            }
            return self::apply_on_missing($url);
        }

        return $url;
    }

    private static function apply_on_missing($url) {
        $policy = DLM_Settings::on_missing();
        if ($policy === 'hide') return null;
        return $url;
    }

    private static function to_local_path($url) {
        if ($url === '' || $url === '#') return null;
        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            return $url;
        }
        $home = wp_parse_url(home_url());
        $parts = wp_parse_url($url);
        if (!$parts || empty($parts['path'])) return null;
        if (!empty($parts['host']) && isset($home['host']) && $parts['host'] !== $home['host']) {
            return null;
        }
        return $parts['path'];
    }

    /**
     * True when the path is already the current city's page for a known slot.
     */
    private static function is_city_url_for_context($path, $region, $city) {
        $loc = DLM_Slug::match_location($path);
        if (!$loc) {
            return false;
        }
        if ($loc['region'] !== (string) $region || $loc['city'] !== (string) $city) {
            return false;
        }
        return DLM_Slug::match_slot_in_path($path) !== null;
    }

    /**
     * Heuristic for explicit highway menu items only — not every /services/ URL.
     */
    private static function looks_like_highway($path) {
        if (!DLM_Settings::highway_features_enabled()) {
            return false;
        }
        return (bool) preg_match('#highway(s|-)?#i', $path);
    }

    /**
     * Match a URL path against configured slots — EXACT-DEPTH match, then leaf/slot match.
     */
    private static function match_slot($path) {
        $path_segs = array_values(array_filter(explode('/', trim($path, '/')), function ($s) { return $s !== ''; }));
        if (empty($path_segs)) return null;

        foreach (DLM_Settings::slots() as $row) {
            $slot_path = isset($row['url_path']) ? $row['url_path'] : '';
            $slot_segs = array_values(array_filter(explode('/', trim($slot_path, '/')), function ($s) { return $s !== ''; }));
            if (count($slot_segs) !== count($path_segs)) {
                continue;
            }
            if ($slot_segs === $path_segs) {
                return self::slot_match_row($row);
            }
        }

        // Same depth, different parent segment (e.g. menu uses heavy-duty-recovery
        // but url_path still has heavy-duty-towing). Match when leaf equals slot name.
        $leaf = sanitize_title($path_segs[count($path_segs) - 1]);
        $depth = count($path_segs);
        $leaf_matches = [];

        foreach (DLM_Settings::slots() as $row) {
            $slot_path = isset($row['url_path']) ? $row['url_path'] : '';
            $slot_segs = array_values(array_filter(explode('/', trim($slot_path, '/')), function ($s) { return $s !== ''; }));
            if (count($slot_segs) !== $depth) {
                continue;
            }
            $slot = sanitize_title($row['slot'] ?? '');
            $slot_leaf = !empty($slot_segs) ? sanitize_title($slot_segs[count($slot_segs) - 1]) : '';
            if ($leaf === $slot || $leaf === $slot_leaf) {
                $leaf_matches[] = $row;
            }
        }

        if (count($leaf_matches) === 1) {
            return self::slot_match_row($leaf_matches[0]);
        }

        return null;
    }

    private static function slot_match_row(array $row) {
        return [
            'slot'     => $row['slot'],
            'fallback' => isset($row['fallback']) ? $row['fallback'] : '',
        ];
    }
}
