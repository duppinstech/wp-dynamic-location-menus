<?php
/**
 * DLM_Content
 *
 * Extract searchable text from stored page source (post_content + builder meta).
 * Used by the consistency audit — never fetches rendered HTML (Nimba swaps phones).
 */

if (!defined('ABSPATH')) { exit; }

class DLM_Content {

    /**
     * Elementor JSON keys whose values are human-readable text.
     * Searching only these keys prevents false positives from widget IDs,
     * CSS class names, element identifiers, and numeric config values.
     */
    const ELEMENTOR_TEXT_KEYS = [
        'text', 'title', 'description', 'editor', 'caption', 'html',
        'content', 'phone', 'email', 'address', 'fax', 'tel',
        'before_text', 'after_text', 'heading', 'sub_heading',
        'button_text', 'anchor', 'label', 'placeholder', 'message',
    ];

    /**
     * All stored text for a page (content + builder text fields only).
     */
    public static function get_stored_text($post_id) {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return '';
        }

        $parts = [];
        $post = get_post($post_id);
        if ($post) {
            $parts[] = $post->post_title;
            $parts[] = $post->post_content;
            $parts[] = $post->post_excerpt;
        }

        // Yoast SEO fields — title, meta description, focus keyphrase.
        $yoast_keys = [
            '_yoast_wpseo_title',
            '_yoast_wpseo_metadesc',
            '_yoast_wpseo_focuskw',
        ];
        foreach ($yoast_keys as $key) {
            $val = get_post_meta($post_id, $key, true);
            if (is_string($val) && $val !== '') {
                $parts[] = $val;
            }
        }

        // Extract only human-readable strings from Elementor JSON.
        // Raw JSON search would match widget IDs, element hashes, CSS values, etc.
        $elementor_raw = get_post_meta($post_id, '_elementor_data', true);
        if (is_string($elementor_raw) && $elementor_raw !== '') {
            $decoded = json_decode($elementor_raw, true);
            if (is_array($decoded)) {
                $parts[] = self::extract_elementor_text($decoded);
            }
        }

        $page_settings = get_post_meta($post_id, '_elementor_page_settings', true);
        if (is_array($page_settings)) {
            $parts[] = self::extract_elementor_text($page_settings);
        }

        return implode("\n", array_filter($parts));
    }

    /**
     * Recursively extract human-readable string values from Elementor JSON.
     * Only values under known text-bearing keys are included.
     */
    public static function extract_elementor_text($data, $depth = 0) {
        if ($depth > 15 || !is_array($data)) {
            return '';
        }
        $parts = [];
        foreach ($data as $key => $val) {
            if (is_array($val)) {
                $parts[] = self::extract_elementor_text($val, $depth + 1);
            } elseif (is_string($val) && $val !== '') {
                $key_lower = strtolower((string) $key);
                $is_text_key = in_array($key_lower, self::ELEMENTOR_TEXT_KEYS, true);
                // Also include any value that is longer than 20 chars and contains a space
                // (likely human-written prose rather than an identifier or CSS rule).
                $looks_like_prose = strlen($val) > 20 && preg_match('/\s/', $val);
                if ($is_text_key || $looks_like_prose) {
                    $parts[] = wp_strip_all_tags($val);
                }
            }
        }
        return implode("\n", array_filter($parts));
    }

    /**
     * Find valid US phone numbers (NANP) in text.
     * Returns unique normalized display strings.
     *
     * NANP rules applied to filter false positives:
     *   - Area code:   first digit 2–9
     *   - Exchange:    first digit 2–9
     *   - Subscriber:  any 4 digits
     * Numbers must not be embedded in a longer run of digits.
     *
     * @return string[]
     */
    public static function find_phones($text) {
        if ($text === '') {
            return [];
        }

        // Parenthesized area code form:  (NXX) NXX-XXXX  or  (NXX)NXX-XXXX
        // Dash/dot/space-separated form: NXX-NXX-XXXX  NXX.NXX.XXXX  NXX NXX XXXX
        // Optional +1 / 1 prefix.
        // Area code and exchange: first digit must be 2-9.
        // Not preceded or followed by a digit (prevent matching inside longer numbers).
        $pattern =
            '/(?<!\d)' .                            // not preceded by digit
            '(?:\+?1[\s.\-]?)?' .                   // optional +1 prefix
            '(?:' .
                '\(([2-9]\d{2})\)[\s.\-]?' .        // (NXX) with optional sep
                '|' .
                '([2-9]\d{2})([\s.\-])' .           // NXX with required sep
            ')' .
            '([2-9]\d{2})' .                        // exchange NXX (first digit 2-9)
            '[\s.\-]?' .
            '(\d{4})' .                             // subscriber XXXX
            '(?!\d)' .                              // not followed by digit
            '/x';

        if (!preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $seen = [];
        $out  = [];
        foreach ($matches as $m) {
            $raw    = $m[0];
            $digits = self::normalize_phone($raw);
            if (strlen($digits) !== 10) {
                continue;
            }
            if (isset($seen[$digits])) {
                continue;
            }
            $seen[$digits] = true;
            $out[] = self::format_phone($digits);
        }
        return $out;
    }

    /**
     * Normalize a phone string to 10 digits (US), or empty string.
     */
    public static function normalize_phone($phone) {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) === 11 && $digits[0] === '1') {
            $digits = substr($digits, 1);
        }
        return strlen($digits) === 10 ? $digits : '';
    }

    /**
     * Format 10-digit phone for display: (XXX) XXX-XXXX
     */
    public static function format_phone($digits) {
        $digits = self::normalize_phone($digits);
        if ($digits === '') {
            return '';
        }
        return sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 4));
    }

    /**
     * Dashed US phone without tel: prefix: XXX-XXX-XXXX
     */
    public static function format_tel_dashed($phone) {
        $digits = self::normalize_phone($phone);
        if ($digits === '') {
            return '';
        }
        return sprintf('%s-%s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 4));
    }

    /**
     * tel: link target format for cloned pages: tel:XXX-XXX-XXXX (no +1).
     */
    public static function format_tel_link($phone) {
        $dashed = self::format_tel_dashed($phone);
        return $dashed === '' ? '' : 'tel:' . $dashed;
    }

    /**
     * tel: URL search strings to replace when cloning a source phone number.
     *
     * @return string[]
     */
    public static function tel_link_search_variants($phone) {
        $digits = self::normalize_phone($phone);
        if ($digits === '') {
            return [];
        }
        $a = substr($digits, 0, 3);
        $b = substr($digits, 3, 3);
        $c = substr($digits, 6, 4);
        return array_values(array_unique([
            'tel:' . $a . '-' . $b . '-' . $c,
            'tel:' . $digits,
            'tel:+1' . $digits,
            'tel:+1-' . $a . '-' . $b . '-' . $c,
            'tel:1' . $digits,
            'tel:1-' . $a . '-' . $b . '-' . $c,
            'tel:(' . $a . ') ' . $b . '-' . $c,
            'tel:+1 (' . $a . ') ' . $b . '-' . $c,
            'tel:' . $a . '.' . $b . '.' . $c,
            'tel:+1.' . $a . '.' . $b . '.' . $c,
        ]));
    }

    /**
     * After clone replacements, force target phone values into dashed / tel: formats.
     */
    public static function normalize_cloned_phones($text, $target_phone) {
        $digits = self::normalize_phone($target_phone);
        if ($digits === '') {
            return (string) $text;
        }

        $text   = (string) $text;
        $a      = substr($digits, 0, 3);
        $b      = substr($digits, 3, 3);
        $c      = substr($digits, 6, 4);
        $dashed = $a . '-' . $b . '-' . $c;
        $tel    = 'tel:' . $dashed;
        $sep    = '[\.\-\s]*';
        $core   = preg_quote($a, '#') . $sep . preg_quote($b, '#') . $sep . preg_quote($c, '#');

        $text = preg_replace('#tel:\+1' . $sep . $core . '#i', $tel, $text);
        $text = preg_replace('#tel:1' . $sep . $core . '#i', $tel, $text);
        $text = preg_replace('#tel:' . $core . '#i', $tel, $text);
        $text = preg_replace('#tel:\+?' . preg_quote($digits, '#') . '#i', $tel, $text);

        // Elementor often stores raw digits and prepends tel: at render time.
        $text = preg_replace('#(?<!tel:)\+1' . preg_quote($digits, '#') . '#', $dashed, $text);
        $text = preg_replace(
            '#(?<!tel:)(?<![\d-])' . preg_quote($digits, '#') . '(?![\d-])#',
            $dashed,
            $text
        );

        return $text;
    }

    /**
     * Phone variants for find/replace when cloning (multiple common formats).
     *
     * @return string[]
     */
    public static function phone_variants($phone) {
        $digits = self::normalize_phone($phone);
        if ($digits === '') {
            return array_filter([(string) $phone]);
        }
        $a = substr($digits, 0, 3);
        $b = substr($digits, 3, 3);
        $c = substr($digits, 6, 4);
        return array_unique([
            $phone,
            self::format_phone($digits),
            "$a-$b-$c",
            "$a.$b.$c",
            "$a $b $c",
            "($a) $b-$c",
            "1-$a-$b-$c",
            "1.$a.$b.$c",
            "+1-$a-$b-$c",
            $digits,
        ]);
    }

    /**
     * Check whether text contains a phone (any format) matching canonical digits.
     */
    public static function text_has_phone($text, $canonical_phone) {
        $want = self::normalize_phone($canonical_phone);
        if ($want === '') {
            return false;
        }
        foreach (self::find_phones($text) as $found) {
            if (self::normalize_phone($found) === $want) {
                return true;
            }
        }
        return false;
    }

    /**
     * Search stored text for configured location terms (label, city, aliases).
     *
     * @return string[] Matched terms found in text.
     */
    public static function find_location_terms($text, array $location) {
        $terms = self::location_search_terms($location);
        $found = [];
        $lower = strtolower($text);
        foreach ($terms as $term) {
            if ($term === '') {
                continue;
            }
            if (stripos($lower, strtolower($term)) !== false) {
                $found[] = $term;
            }
        }
        return array_unique($found);
    }

    /**
     * Raw Yoast SEO fields for a page (for display in the audit table).
     *
     * @return array{title:string, metadesc:string, focuskw:string}
     */
    public static function get_yoast_fields($post_id) {
        $post_id = (int) $post_id;
        return [
            'title'    => (string) get_post_meta($post_id, '_yoast_wpseo_title',    true),
            'metadesc' => (string) get_post_meta($post_id, '_yoast_wpseo_metadesc', true),
            'focuskw'  => (string) get_post_meta($post_id, '_yoast_wpseo_focuskw',  true),
        ];
    }

    /**
     * Build searchable terms for a location row.
     *
     * @return string[]
     */
    public static function location_search_terms(array $location) {
        $terms = [];
        if (!empty($location['label'])) {
            $terms[] = $location['label'];
            // City name without state suffix, e.g. "Blue Springs" from "Blue Springs, MO"
            $parts = preg_split('/\s*,\s*/', $location['label'], 2);
            if (!empty($parts[0]) && $parts[0] !== $location['label']) {
                $terms[] = trim($parts[0]);
            }
        }
        if (!empty($location['city'])) {
            $terms[] = str_replace('-', ' ', $location['city']);
            $terms[] = $location['city'];
        }
        if (!empty($location['aliases']) && is_array($location['aliases'])) {
            foreach ($location['aliases'] as $alias) {
                $terms[] = str_replace('-', ' ', $alias);
                $terms[] = $alias;
            }
        }
        return array_values(array_unique(array_filter($terms)));
    }
}
