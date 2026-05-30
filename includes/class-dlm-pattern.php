<?php
/**
 * DLM_Pattern
 *
 * Converts a URL pattern template like "/service-areas/{region}/{slot}/{city}/"
 * into:
 *   - a compiled regex that can EXTRACT {region}, {slot}, {city} from an incoming URL
 *   - a builder that BUILDS a URL given those values
 *
 * Supported tokens: {region}, {slot}, {city}
 *   - {region} is optional. If empty for a location, the slash segment is collapsed:
 *     pattern  "/service-areas/{region}/{slot}/{city}/"  + region=""  =>  "/service-areas/{slot}/{city}/"
 *
 * Tokens may also appear MERGED inside a single path segment, e.g.:
 *     "/service-areas/{region}/heavy-duty-towing/highways-{city}/"
 * The parser handles that — literal text around a token in the same segment
 * becomes part of the regex.
 */

if (!defined('ABSPATH')) { exit; }

class DLM_Pattern {

    /**
     * Compile a pattern to a regex that captures named groups.
     * Returns null if the pattern is invalid.
     *
     * @param string $template e.g. "/service-areas/{region}/{slot}/{city}/"
     * @param string[] $known_regions Optional list of region slugs to restrict matches.
     * @param string[] $known_cities  Optional list of city slugs to restrict matches.
     * @return string|null PCRE pattern with named groups (?<region>...), (?<slot>...), (?<city>...)
     */
    public static function compile($template, $known_regions = [], $known_cities = []) {
        if (!$template) return null;
        $template = '/' . trim($template, '/') . '/';
        $segments = array_filter(explode('/', $template), function ($s) { return $s !== ''; });

        $regex_parts = [];
        foreach ($segments as $seg) {
            $regex_parts[] = self::segment_to_regex($seg, $known_regions, $known_cities);
        }

        $regex = '^/' . implode('/', $regex_parts) . '/$';
        // Use # as delimiter so we don't have to escape forward slashes.
        return '#' . $regex . '#';
    }

    /**
     * Build a URL from a pattern and a values array.
     *
     * @param string $template
     * @param array $values  ['region' => 'missouri', 'slot' => 'emergency-towing', 'city' => 'joplin']
     * @return string|null Path (e.g., "/service-areas/missouri/emergency-towing/joplin/") or null on missing required values.
     */
    public static function build($template, array $values) {
        if (!$template) return null;
        $template = '/' . trim($template, '/') . '/';
        $segments = array_filter(explode('/', $template), function ($s) { return $s !== ''; });

        $out = [];
        foreach ($segments as $seg) {
            $built = self::build_segment($seg, $values);
            // If a {region} segment came back as empty, skip it (region is optional).
            if ($built === null) { return null; } // required token missing
            if ($built === '')   { continue; }    // optional region collapsed
            $out[] = $built;
        }
        return '/' . implode('/', $out) . '/';
    }

    /**
     * Convert one segment to its regex piece. Handles literal text + tokens.
     */
    private static function segment_to_regex($seg, $known_regions, $known_cities) {
        // Split the segment into a sequence of literal chunks and token names.
        // We walk by finding {token} occurrences.
        $cursor = 0;
        $parts = [];
        $len = strlen($seg);
        while ($cursor < $len) {
            $start = strpos($seg, '{', $cursor);
            if ($start === false) {
                $parts[] = ['literal', substr($seg, $cursor)];
                break;
            }
            if ($start > $cursor) {
                $parts[] = ['literal', substr($seg, $cursor, $start - $cursor)];
            }
            $end = strpos($seg, '}', $start);
            if ($end === false) {
                // Malformed — treat rest as literal
                $parts[] = ['literal', substr($seg, $start)];
                break;
            }
            $token = substr($seg, $start + 1, $end - $start - 1);
            $parts[] = ['token', $token];
            $cursor = $end + 1;
        }

        // Build regex for this segment.
        $rx = '';
        foreach ($parts as [$type, $val]) {
            if ($type === 'literal') {
                $rx .= preg_quote($val, '#');
            } else {
                $name = preg_replace('/[^a-z]/', '', strtolower($val));
                // Restrict regions/cities if we have known values; otherwise match any slug.
                if ($name === 'region' && !empty($known_regions)) {
                    $rx .= '(?<region>' . self::alt($known_regions) . ')';
                } elseif ($name === 'city' && !empty($known_cities)) {
                    $rx .= '(?<city>' . self::alt($known_cities) . ')';
                } else {
                    // Generic token — any slug-ish chars, no slashes.
                    $rx .= '(?<' . $name . '>[a-z0-9][a-z0-9-]*)';
                }
            }
        }
        return $rx;
    }

    private static function alt(array $items) {
        $escaped = array_map(function ($s) { return preg_quote($s, '#'); }, $items);
        return '(?:' . implode('|', $escaped) . ')';
    }

    /**
     * Build one segment from a template piece. Returns:
     *   null  - required token missing (caller should give up on the build)
     *   ''    - optional token (region) empty — caller should skip this segment
     *   string - the built segment
     */
    private static function build_segment($seg, array $values) {
        // Fast path: no tokens
        if (strpos($seg, '{') === false) {
            return $seg;
        }

        $rebuilt = '';
        $had_required_missing = false;
        $only_token = null;     // if the whole segment is JUST {token}, remember which
        $had_only_one = (strpos($seg, '}') === strrpos($seg, '}'));

        // First scan: if segment is exactly "{token}", treat empty values specially.
        if (preg_match('/^\{([a-z_]+)\}$/i', $seg, $m)) {
            $only_token = strtolower($m[1]);
            $val = isset($values[$only_token]) ? (string) $values[$only_token] : '';
            if ($val === '') {
                // Region is optional — return '' to collapse the segment.
                if ($only_token === 'region') return '';
                return null;
            }
            return $val;
        }

        // Mixed segment: substitute tokens inline.
        $rebuilt = preg_replace_callback('/\{([a-z_]+)\}/i', function ($m) use ($values, &$had_required_missing) {
            $name = strtolower($m[1]);
            $val = isset($values[$name]) ? (string) $values[$name] : '';
            if ($val === '' && $name !== 'region') {
                $had_required_missing = true;
            }
            return $val;
        }, $seg);

        if ($had_required_missing) return null;
        return $rebuilt;
    }
}
