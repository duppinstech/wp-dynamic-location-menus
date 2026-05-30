<?php
/**
 * DLM_Slug
 *
 * Slug-in-URL matching for location context and city page discovery.
 * Works across service-areas paths, services paths, and flat landing-page slugs.
 */

if (!defined('ABSPATH')) { exit; }

class DLM_Slug {

    /**
     * Normalize a URL path for comparisons.
     */
    public static function normalize_path($path) {
        $path = '/' . trim(strtolower((string) $path), '/') . '/';
        return ($path === '//') ? '/' : $path;
    }

    /**
     * Flat list of slug entries (canonical + aliases), longest slug first.
     *
     * @return array<int, array{region:string, city:string, slug:string}>
     */
    public static function slug_entries() {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $entries = [];
        foreach (DLM_Settings::locations() as $loc) {
            $region = isset($loc['region']) ? (string) $loc['region'] : '';
            $city   = isset($loc['city']) ? (string) $loc['city'] : '';
            if ($city === '') {
                continue;
            }

            $slugs = [$city];
            if (!empty($loc['aliases']) && is_array($loc['aliases'])) {
                foreach ($loc['aliases'] as $alias) {
                    $alias = (string) $alias;
                    if ($alias !== '' && $alias !== $city) {
                        $slugs[] = $alias;
                    }
                }
            }

            foreach (array_unique($slugs) as $slug) {
                $entries[] = [
                    'region' => $region,
                    'city'   => $city,
                    'slug'   => $slug,
                    'len'    => strlen($slug),
                ];
            }
        }

        usort($entries, function ($a, $b) {
            return $b['len'] - $a['len'];
        });

        $cache = $entries;
        return $cache;
    }

    /**
     * All configured slot names, longest first.
     *
     * @return string[]
     */
    public static function all_slot_names() {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $names = [];
        foreach (DLM_Settings::slots() as $row) {
            if (!empty($row['slot'])) {
                $names[] = sanitize_title($row['slot']);
            }
        }
        $hw = sanitize_title(DLM_Settings::highway_label());
        if ($hw !== '' && DLM_Settings::highway_features_enabled()) {
            $names[] = $hw;
        }

        $names = array_values(array_unique(array_filter($names)));
        usort($names, function ($a, $b) {
            return strlen($b) - strlen($a);
        });

        $cache = $names;
        return $cache;
    }

    /**
     * True when a slug appears in the path with segment/hyphen boundaries.
     */
    public static function path_contains_slug($path, $slug) {
        $slug = strtolower(trim((string) $slug));
        if ($slug === '') {
            return false;
        }
        $path = self::normalize_path($path);
        $quoted = preg_quote($slug, '#');
        return (bool) preg_match('#(?:^|[/_\-])' . $quoted . '(?:[/_\-]|$)#', $path);
    }

    /**
     * True when a service slot name appears in the path.
     *
     * Unlike city slugs, short slot tokens must not match as a prefix inside a
     * longer slot segment (e.g. "towing" must not match "towing-services").
     */
    public static function path_contains_slot($path, $slot) {
        $slot = sanitize_title($slot);
        if ($slot === '') {
            return false;
        }

        $path     = self::normalize_path($path);
        $all      = self::all_slot_names();
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        foreach ($segments as $segment) {
            if ($segment === $slot) {
                return true;
            }
        }

        foreach ($segments as $segment) {
            if (!preg_match('#(?:^|-)' . preg_quote($slot, '#') . '(?:-|$)#', $segment)) {
                continue;
            }
            if ($segment === $slot) {
                return true;
            }
            // Landing-page prefix form: heavy-duty-towing-joplin-mo
            if (preg_match('#^' . preg_quote($slot, '#') . '-#', $segment)) {
                foreach ($all as $longer) {
                    if (strlen($longer) > strlen($slot) && $segment === $longer) {
                        continue 2;
                    }
                }
                return true;
            }
            // Suffix-only inside a compound segment (e.g. "services" in "towing-services").
            if (preg_match('#-' . preg_quote($slot, '#') . '$#', $segment)) {
                continue;
            }
            return true;
        }

        return false;
    }

    /**
     * Slugs that may appear in city page URLs for a slot row.
     *
     * @return string[]
     */
    public static function slot_city_slugs(array $row) {
        $slugs = [];
        $slot  = isset($row['slot']) ? sanitize_title($row['slot']) : '';
        if ($slot !== '') {
            $slugs[] = $slot;
        }

        $url_path = isset($row['url_path']) ? trim((string) $row['url_path'], '/') : '';
        if ($url_path !== '') {
            $segs = array_values(array_filter(explode('/', $url_path)));
            if (!empty($segs)) {
                $slugs[] = sanitize_title(end($segs));
            }
        }

        $raw = isset($row['city_slugs']) ? $row['city_slugs'] : '';
        if (is_array($raw)) {
            $parts = $raw;
        } else {
            $parts = preg_split('/\s*,\s*/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);
        }
        foreach ($parts as $part) {
            $part = sanitize_title($part);
            if ($part !== '') {
                $slugs[] = $part;
            }
        }

        return array_values(array_unique(array_filter($slugs)));
    }

    /**
     * True when a city page path contains any slug associated with a slot row.
     */
    public static function path_contains_slot_row($path, array $row) {
        return self::path_matches_slot_row($path, $row);
    }

    /**
     * True when a city page path corresponds to a slot row.
     */
    public static function path_matches_slot_row($path, array $row) {
        foreach (self::slot_city_slugs($row) as $slug) {
            if (self::path_contains_slot($path, $slug)) {
                return true;
            }
        }
        return false;
    }

    /**
     * True when a page path corresponds to a slot row's generic menu url_path.
     *
     * @deprecated Use path_matches_slot_row().
     */
    public static function path_matches_slot_url_path($path, $url_path) {
        return self::path_matches_slot_row($path, [
            'url_path' => $url_path,
            'slot'     => '',
        ]);
    }

    /**
     * Match a configured location from a URL path. Returns canonical city + region.
     *
     * @return array{region:string, city:string, matched_slug:string}|null
     */
    public static function match_location($path) {
        foreach (self::slug_entries() as $entry) {
            if (self::path_contains_slug($path, $entry['slug'])) {
                return [
                    'region'       => $entry['region'],
                    'city'         => $entry['city'],
                    'matched_slug' => $entry['slug'],
                ];
            }
        }

        return self::match_location_hub_segment($path);
    }

    /**
     * Match city hub segments like /service-areas/kansas/kansas-city/ to kansas-city-ks.
     *
     * @return array{region:string, city:string, matched_slug:string}|null
     */
    public static function match_location_hub_segment($path) {
        $path     = self::normalize_path($path);
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        if (count($segments) < 3 || $segments[0] !== 'service-areas') {
            return null;
        }

        $region = $segments[1];
        $hub    = $segments[2];

        foreach (DLM_Settings::locations() as $loc) {
            $loc_region = isset($loc['region']) ? (string) $loc['region'] : '';
            if ($loc_region !== $region) {
                continue;
            }

            $city = isset($loc['city']) ? (string) $loc['city'] : '';
            if ($city === '') {
                continue;
            }

            if ($city === $hub) {
                return [
                    'region'       => $region,
                    'city'         => $city,
                    'matched_slug' => $hub,
                ];
            }

            if (strpos($city, $hub . '-') === 0) {
                return [
                    'region'       => $region,
                    'city'         => $city,
                    'matched_slug' => $hub,
                ];
            }

            if (!empty($loc['aliases']) && is_array($loc['aliases'])) {
                foreach ($loc['aliases'] as $alias) {
                    if ((string) $alias === $hub) {
                        return [
                            'region'       => $region,
                            'city'         => $city,
                            'matched_slug' => $hub,
                        ];
                    }
                }
            }

            $label = trim($loc['label'] ?? '');
            if ($label !== '') {
                $parts = preg_split('/\s*,\s*/', $label, 2);
                $city_name = sanitize_title(trim($parts[0] ?? ''));
                if ($city_name !== '' && $city_name === $hub) {
                    return [
                        'region'       => $region,
                        'city'         => $city,
                        'matched_slug' => $hub,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * True when the path contains any configured city slug.
     */
    public static function path_has_any_city_slug($path) {
        return self::match_location($path) !== null;
    }

    /**
     * True when a path is a highway-style city page (highways-{city} segment).
     */
    public static function is_highway_page_path($path) {
        if (!DLM_Settings::highway_features_enabled()) {
            return false;
        }

        $label = sanitize_title(DLM_Settings::highway_label());
        if ($label === '') {
            return false;
        }

        $path = self::normalize_path($path);
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        foreach ($segments as $segment) {
            if (preg_match('#^' . preg_quote($label, '#') . '-[a-z0-9][a-z0-9-]*$#', $segment)) {
                return true;
            }
        }

        $pattern = DLM_Settings::highway_pattern();
        if ($pattern !== '') {
            $regex = DLM_Pattern::compile($pattern, DLM_Registry::regions(), DLM_Registry::cities());
            if ($regex && preg_match($regex, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Most specific service slug implied by a city page URL.
     *
     * Supports service-first (.../service/city/), city-hub (.../city/service/),
     * nested (.../parent/city/subservice/), and compound landing slugs.
     *
     * @return string Sanitized slug, or empty when unknown.
     */
    public static function primary_service_slug_in_path($path, $city = null, $region = null) {
        $path = self::normalize_path($path);

        if ($city === null || $region === null) {
            $loc = self::match_location($path);
            if ($loc) {
                if ($city === null) {
                    $city = $loc['city'];
                }
                if ($region === null) {
                    $region = $loc['region'];
                }
            }
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $start    = 0;
        if (!empty($segments[0]) && $segments[0] === 'service-areas') {
            $start = 1;
            if ($region !== null && $region !== '' && isset($segments[1]) && $segments[1] === $region) {
                $start = 2;
            }
        }

        $work = array_slice($segments, $start);
        if (empty($work)) {
            return '';
        }

        $city_idx = null;
        if ($city !== null && $city !== '') {
            foreach ($work as $i => $seg) {
                if ($seg === $city) {
                    $city_idx = $i;
                    break;
                }
            }
        }

        if ($city_idx !== null) {
            if ($city_idx === count($work) - 1) {
                return $city_idx > 0 ? sanitize_title($work[$city_idx - 1]) : '';
            }
            return sanitize_title($work[count($work) - 1]);
        }

        $last = sanitize_title($work[count($work) - 1]);
        foreach (self::all_slot_names() as $slot) {
            if ($last === $slot || strpos($last, $slot . '-') === 0) {
                return $slot;
            }
        }

        return $last;
    }

    /**
     * Score how well a path represents a slot page for menu indexing (higher = better).
     */
    public static function path_priority_for_slot($path, $slot, $city, $region = null) {
        $slot = sanitize_title($slot);
        $city = (string) $city;
        $path = self::normalize_path($path);

        $primary = self::primary_service_slug_in_path($path, $city, $region);
        if ($primary === '' || ($primary !== $slot && !self::slot_row_matches_primary($slot, $primary))) {
            return -1;
        }

        $score = 0;
        if ($primary === $slot) {
            $score += 1000;
        }

        if (strpos($path, '/service-areas/') !== false) {
            $score += 500;
        }

        // Prefer canonical service pages over deeper/nested paths for the same slot.
        $score -= count(array_filter(explode('/', trim($path, '/'))));

        return $score;
    }

    /**
     * True when a slot row's name or url_path leaf matches the primary service slug.
     */
    private static function slot_row_matches_primary($slot, $primary) {
        $row = DLM_Settings::find_slot($slot);
        if (!$row) {
            return sanitize_title($slot) === sanitize_title($primary);
        }
        if (sanitize_title($row['slot'] ?? '') === sanitize_title($primary)) {
            return true;
        }
        $url_path = trim((string) ($row['url_path'] ?? ''), '/');
        if ($url_path === '') {
            return false;
        }
        $segs = array_values(array_filter(explode('/', $url_path)));
        return !empty($segs) && sanitize_title(end($segs)) === sanitize_title($primary);
    }

    /**
     * Find the best-matching slot for a slot settings row present in a path.
     *
     * @return array{slot:string, url_path:string}|null
     */
    public static function match_slot_row_in_path($path) {
        if (self::is_highway_page_path($path)) {
            $hw_label = sanitize_title(DLM_Settings::highway_label());
            return [
                'slot'     => $hw_label,
                'url_path' => $hw_label,
            ];
        }

        $loc     = self::match_location($path);
        $primary = self::primary_service_slug_in_path(
            $path,
            $loc ? $loc['city'] : null,
            $loc ? $loc['region'] : null
        );

        $candidates = [];

        foreach (DLM_Settings::slots() as $row) {
            $slot     = isset($row['slot']) ? sanitize_title($row['slot']) : '';
            $url_path = isset($row['url_path']) ? (string) $row['url_path'] : '';
            if ($slot === '' || $url_path === '') {
                continue;
            }
            if (!self::path_contains_slot_row($path, $row)) {
                continue;
            }

            $leaf = sanitize_title(end(array_values(array_filter(explode('/', trim($url_path, '/'))))));
            if ($primary !== '' && $primary !== $slot && $primary !== $leaf) {
                continue;
            }

            $candidates[] = [
                'slot'     => $slot,
                'url_path' => $url_path,
                'depth'    => count(array_values(array_filter(explode('/', trim($url_path, '/'))))),
            ];
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, function ($a, $b) {
            if ($a['depth'] !== $b['depth']) {
                return $b['depth'] - $a['depth'];
            }
            return strlen($b['slot']) - strlen($a['slot']);
        });

        return [
            'slot'     => $candidates[0]['slot'],
            'url_path' => $candidates[0]['url_path'],
        ];
    }

    /**
     * Find the best-matching slot name present in a path.
     *
     * @return string|null Slot key from settings.
     */
    public static function match_slot_in_path($path) {
        $match = self::match_slot_row_in_path($path);
        return $match ? $match['slot'] : null;
    }

    /**
     * Match a hyphen/slash-bounded token inside a path.
     */
    public static function path_contains_token($path, $token) {
        return self::path_contains_slug($path, sanitize_title($token));
    }

    /**
     * Whether a page path should be indexed as a city page for (region, slot, city).
     */
    public static function is_city_page_for($path, $region, $city, $slot) {
        $loc = self::match_location($path);
        if (!$loc || $loc['region'] !== (string) $region || $loc['city'] !== (string) $city) {
            return false;
        }
        $matched = self::match_slot_row_in_path($path);
        return $matched !== null && $matched['slot'] === sanitize_title($slot);
    }

}
