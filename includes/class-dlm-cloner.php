<?php
/**
 * DLM_Cloner
 *
 * Duplicate city service pages with find/replace for location labels, slugs, and phones.
 * Reads/writes stored post meta (Elementor JSON) — never rendered HTML.
 */

if (!defined('ABSPATH')) { exit; }

class DLM_Cloner {

    /**
     * Non-Elementor meta keys to copy (with string replacement applied).
     * Elementor data is handled separately via its own API or raw-string copy.
     */
    const COPY_META_KEYS = [
        '_wp_page_template',
        '_yoast_wpseo_title',
        '_yoast_wpseo_metadesc',
        '_yoast_wpseo_focuskw',
        '_yoast_wpseo_opengraph-title',
        '_yoast_wpseo_opengraph-description',
        '_yoast_wpseo_twitter-title',
        '_yoast_wpseo_twitter-description',
    ];

    /** Elementor meta keys handled separately — NOT touched by generic copy. */
    const ELEMENTOR_META_KEYS = [
        '_elementor_data',
        '_elementor_page_settings',
        '_elementor_edit_mode',
        '_elementor_template_type',
        '_elementor_version',
        '_elementor_pro_version',
        '_elementor_css',
        '_elementor_controls_usage',
    ];

    /**
     * Create a page for a coverage gap.
     *
     * @return array{success:bool, post_id?:int, message:string, edit_url?:string}
     */
    public static function create_from_gap($region, $slot, $target_city, $template_post_id = 0, $status = 'draft') {
        $slot = sanitize_title($slot);
        $target = DLM_Settings::find_location($region, $target_city);
        if (!$target) {
            return ['success' => false, 'message' => 'Target location not found in settings.'];
        }

        if (DLM_Registry::city_page($region, $slot, $target_city)) {
            return ['success' => false, 'message' => 'A page already exists for this location and slot.'];
        }

        $template_id = (int) $template_post_id;
        $source      = null;

        if ($template_id > 0) {
            $source = self::detect_source_from_template($template_id);
        }
        if (!$source) {
            $found = DLM_Registry::find_template_for_slot($slot);
            if ($found) {
                $template_id = $found['post_id'];
                $source = DLM_Settings::find_location($found['region'], $found['city']);
            }
        }
        if ($template_id <= 0 || !$source) {
            $fallback_id = self::fallback_template_from_slot($slot);
            if ($fallback_id > 0) {
                $template_id = $fallback_id;
                $source = self::detect_source_from_template($template_id);
            }
        }
        if ($template_id <= 0) {
            return ['success' => false, 'message' => 'No template page found for this slot. Pick a template or create a generic page first.'];
        }
        if (!$source) {
            $source = self::detect_source_from_template($template_id);
        }
        if (!$source) {
            $source = self::generic_source();
        }

        return self::clone_page($template_id, $source, $target, $slot, $status);
    }

    /**
     * Source profile for generic service templates (no city on the page).
     * Uses the configured toll-free generic phone for 800→local swaps.
     *
     * @return array{region:string, city:string, aliases:array, label:string, phone:string}
     */
    public static function generic_source() {
        return [
            'region'  => '',
            'city'    => '',
            'aliases' => [],
            'label'   => '',
            'phone'   => DLM_Settings::generic_phone(),
        ];
    }

    /**
     * True when cloning from a generic template with no source city context.
     */
    public static function is_generic_source(array $source) {
        return trim($source['city'] ?? '') === '' && trim($source['label'] ?? '') === '';
    }

    /**
     * Resolve source location from admin form values or template auto-detect.
     *
     * @param int    $template_id
     * @param string $source_key  Location key, empty for auto-detect, or __generic__
     * @return array Location row for build_replace_pairs().
     */
    public static function resolve_source($template_id, $source_key = '') {
        $source_key = (string) $source_key;
        if ($source_key === '__generic__') {
            return self::generic_source();
        }

        if ($source_key !== '') {
            $parts = explode('|', $source_key, 2);
            $found = DLM_Settings::find_location($parts[0] ?? '', $parts[1] ?? '');
            if ($found) {
                return $found;
            }
        }

        $detected = self::detect_source_from_template((int) $template_id);
        if ($detected) {
            return $detected;
        }

        return self::generic_source();
    }

    /**
     * Clone template to multiple target locations.
     *
     * @param array[] $targets Location rows from settings.
     * @return array{created: array, skipped: array, errors: array}
     */
    public static function clone_batch($template_post_id, array $source, array $targets, $slot, $status = 'draft', $skip_existing = true) {
        $slot = sanitize_title($slot);
        $result = ['created' => [], 'skipped' => [], 'errors' => []];

        foreach ($targets as $target) {
            $region = isset($target['region']) ? (string) $target['region'] : '';
            $city   = isset($target['city']) ? (string) $target['city'] : '';
            if ($city === '') {
                continue;
            }

            if ($skip_existing && DLM_Registry::city_page($region, $slot, $city)) {
                $result['skipped'][] = [
                    'city'  => $city,
                    'label' => $target['label'] ?? $city,
                    'reason'=> 'Page already exists',
                ];
                continue;
            }

            $r = self::clone_page((int) $template_post_id, $source, $target, $slot, $status);
            if ($r['success']) {
                $result['created'][] = [
                    'city'    => $city,
                    'label'   => $target['label'] ?? $city,
                    'post_id' => $r['post_id'],
                    'edit_url'=> $r['edit_url'] ?? '',
                ];
            } else {
                $result['errors'][] = [
                    'city'    => $city,
                    'label'   => $target['label'] ?? $city,
                    'message' => $r['message'],
                ];
            }
        }

        if (!empty($result['created'])) {
            DLM_Scanner::scan_now();
        }

        return $result;
    }

    /**
     * Clone one page from source location context to target.
     *
     * @return array{success:bool, post_id?:int, message:string, edit_url?:string}
     */
    public static function clone_page($template_post_id, array $source, array $target, $slot, $status = 'draft') {
        $template_post_id = (int) $template_post_id;
        $post = get_post($template_post_id);
        if (!$post || $post->post_type !== 'page') {
            return ['success' => false, 'message' => 'Invalid template page.'];
        }

        $slot = sanitize_title($slot);
        $target_region = isset($target['region']) ? (string) $target['region'] : '';
        $target_city   = isset($target['city']) ? (string) $target['city'] : '';
        if ($target_city === '') {
            return ['success' => false, 'message' => 'Target city is required.'];
        }

        $new_path = DLM_Settings::build_city_path($target_region, $slot, $target_city);
        if ($new_path === '' || $new_path === '/') {
            return ['success' => false, 'message' => 'Could not build target URL from pattern. Check Advanced pattern settings.'];
        }

        $slug = trim($new_path, '/');
        $slug_parts = explode('/', $slug);
        $post_name = sanitize_title(end($slug_parts));

        $pairs      = self::build_replace_pairs($source, $target);
        $tgt_phone  = isset($target['phone']) ? (string) $target['phone'] : '';
        $title      = self::customize_title_for_target(
            self::apply_replacements($post->post_title, $pairs, $tgt_phone),
            $target,
            $source
        );

        // post_content on Elementor pages is the legacy/shortcode fallback —
        // keep it in sync but the real layout lives in _elementor_data.
        $content = self::apply_replacements($post->post_content, $pairs, $tgt_phone);

        $new_id = wp_insert_post([
            'post_type'    => 'page',
            'post_status'  => in_array($status, ['publish', 'draft', 'pending', 'private'], true) ? $status : 'draft',
            'post_title'   => $title,
            'post_name'    => $post_name,
            'post_content' => $content,
            'post_excerpt' => self::apply_replacements($post->post_excerpt, $pairs, $tgt_phone),
            'post_author'  => get_current_user_id() ?: (int) $post->post_author,
        ], true);

        if (is_wp_error($new_id)) {
            return ['success' => false, 'message' => $new_id->get_error_message()];
        }

        $thumb = get_post_thumbnail_id($template_post_id);
        if ($thumb) {
            set_post_thumbnail($new_id, $thumb);
        }

        // Copy known non-Elementor meta with string replacement.
        self::copy_and_replace_meta($template_post_id, $new_id, $pairs, $tgt_phone);

        // Copy Elementor data through its own API / raw-string fallback.
        self::copy_elementor_data($template_post_id, $new_id, $pairs, $tgt_phone);

        self::finalize_hero_headings($new_id, $target);

        DLM_Scanner::scan_now();

        return [
            'success'  => true,
            'post_id'  => (int) $new_id,
            'message'  => 'Page created as ' . $status . '.',
            'edit_url' => get_edit_post_link($new_id, 'raw'),
        ];
    }

    /**
     * Copy Elementor builder data from one page to another, applying replacements.
     *
     * Uses raw JSON string copy with targeted find/replace — never Elementor's
     * document save() API, which clears _elementor_data on brand-new posts and
     * leaves only flattened post_content (one text widget in the editor).
     */
    private static function copy_elementor_data($from_id, $to_id, array $pairs, $target_phone = '') {
        $raw = get_post_meta($from_id, '_elementor_data', true);
        if (is_string($raw) && $raw !== '') {
            $replaced = self::apply_replacements($raw, $pairs, $target_phone);
            // wp_slash preserves JSON \/ escapes through update_post_meta.
            update_post_meta($to_id, '_elementor_data', wp_slash($replaced));
        }

        $ps = get_post_meta($from_id, '_elementor_page_settings', true);
        if (is_array($ps)) {
            update_post_meta($to_id, '_elementor_page_settings', self::replace_in_array($ps, $pairs, $target_phone));
        } elseif (is_string($ps) && $ps !== '') {
            update_post_meta($to_id, '_elementor_page_settings', self::apply_replacements($ps, $pairs, $target_phone));
        }

        foreach (self::ELEMENTOR_META_KEYS as $flag) {
            if ($flag === '_elementor_data' || $flag === '_elementor_page_settings' || $flag === '_elementor_css') {
                continue;
            }
            $val = get_post_meta($from_id, $flag, true);
            if ($val !== '' && $val !== false) {
                update_post_meta($to_id, $flag, $val);
            }
        }

        update_post_meta($to_id, '_elementor_edit_mode', 'builder');
        delete_post_meta($to_id, '_elementor_css');
    }

    /**
     * Build targeted search/replace pairs — city name variations and phone formats only.
     *
     * Rules:
     *  - City names: full label, label without comma, city-only, lowercase, URL slug form.
     *  - Phone: pretty (NXX) NXX-XXXX, dash NXX-NXX-XXXX, dot NXX.NXX.XXXX.
     *  - Nothing else is touched — Elementor structure, CSS, IDs, region names are left alone.
     *  - Sorted longest-first so "Joplin, MO" is replaced before "Joplin".
     *
     * @return array<int, array{search:string, replace:string}>
     */
    public static function build_replace_pairs(array $source, array $target) {
        $raw = []; // [[search, replace]]

        // --- City / label ---
        $src_label = trim($source['label'] ?? '');
        $tgt_label = trim($target['label'] ?? '');
        $src_parts = preg_split('/\s*,\s*/', $src_label, 2);
        $tgt_parts = preg_split('/\s*,\s*/', $tgt_label, 2);
        $src_city  = trim($src_parts[0] ?? '');
        $src_state = trim($src_parts[1] ?? '');
        $tgt_city  = trim($tgt_parts[0] ?? '');
        $tgt_state = trim($tgt_parts[1] ?? '');

        // "Joplin, MO" → "Gardner, KS"
        if ($src_label !== '' && $tgt_label !== '') {
            $raw[] = [$src_label, $tgt_label];
        }
        // "Joplin,MO" → "Gardner,KS"  (no space after comma)
        if ($src_city !== '' && $src_state !== '') {
            $raw[] = ["$src_city,$src_state", "$tgt_city,$tgt_state"];
        }
        // "Joplin MO" → "Gardner, KS"  (space, no comma — output always uses label with comma)
        if ($src_city !== '' && $src_state !== '') {
            $tgt_loc = $tgt_label !== '' ? $tgt_label : trim("$tgt_city $tgt_state");
            $raw[] = ["$src_city $src_state", $tgt_loc];
            $raw[] = ["$src_city,$src_state", $tgt_loc];
        }
        // "Joplin" / "Gardner"  (title-case city only)
        if ($src_city !== '' && $tgt_city !== '' && $src_city !== $tgt_city) {
            $raw[] = [$src_city, $tgt_city];
            // lowercase prose form: "joplin" → "gardner"
            $raw[] = [strtolower($src_city), strtolower($tgt_city)];
            // URL slug form: "joplin" already covered above if already lowercase;
            // hyphenated multi-word e.g. "blue-springs" → "overland-park"
            $src_slug = sanitize_title($src_city);
            $tgt_slug = sanitize_title($tgt_city);
            if ($src_slug !== strtolower($src_city)) {
                $raw[] = [$src_slug, $tgt_slug];
            }
        }

        // State abbreviations — Yoast title templates often use "%%title%% MO %%page%%".
        if ($src_state !== '' && $tgt_state !== '' && strtoupper($src_state) !== strtoupper($tgt_state)) {
            $ss = strtoupper($src_state);
            $ts = strtoupper($tgt_state);
            $raw[] = [" $ss ", " $ts "];
            $raw[] = ["%%title%% $ss ", "%%title%% $ts "];
            $raw[] = ["%%title%% $ss%%", "%%title%% $ts%%"];
        }

        // Same-city cleanup: "Blue Springs MO" → "Blue Springs, MO" in stored content.
        if ($tgt_label !== '' && $tgt_city !== '' && $tgt_state !== '') {
            $ts = strtoupper($tgt_state);
            foreach (["$tgt_city $ts", "$tgt_city " . strtolower($ts), "$tgt_city,$ts", "$tgt_city,$tgt_state"] as $form) {
                if ($form !== $tgt_label) {
                    $raw[] = [$form, $tgt_label];
                }
            }
        }

        // --- Phone formats ---
        $src_digits = DLM_Content::normalize_phone($source['phone'] ?? '');
        $tgt_digits = DLM_Content::normalize_phone($target['phone'] ?? '');

        if ($src_digits !== '' && $tgt_digits !== '' && $src_digits !== $tgt_digits) {
            $sa = substr($src_digits, 0, 3);
            $sb = substr($src_digits, 3, 3);
            $sc = substr($src_digits, 6, 4);
            $ta = substr($tgt_digits, 0, 3);
            $tb = substr($tgt_digits, 3, 3);
            $tc = substr($tgt_digits, 6, 4);
            $tgt_dashed = "$ta-$tb-$tc";
            $tgt_tel    = 'tel:' . $tgt_dashed;
            // Pretty: (NXX) NXX-XXXX
            $raw[] = ["($sa) $sb-$sc", "($ta) $tb-$tc"];
            // Dash: NXX-NXX-XXXX
            $raw[] = ["$sa-$sb-$sc", $tgt_dashed];
            // Dot: NXX.NXX.XXXX
            $raw[] = ["$sa.$sb.$sc", "$ta.$tb.$tc"];
            // tel: links — always clone to tel:XXX-XXX-XXXX (no +1).
            foreach (DLM_Content::tel_link_search_variants($source['phone'] ?? '') as $src_tel) {
                $raw[] = [$src_tel, $tgt_tel];
            }
            // Raw values Elementor prepends with tel: at render time.
            $raw[] = ['+1' . $src_digits, $tgt_dashed];
            $raw[] = ['+1-' . $sa . '-' . $sb . '-' . $sc, $tgt_dashed];
            $raw[] = [$src_digits, $tgt_dashed];
        }

        // Sort longest search string first to prevent partial replacements.
        usort($raw, function ($a, $b) {
            return strlen($b[0]) - strlen($a[0]);
        });

        // Dedupe and convert.
        $seen = [];
        $out  = [];
        foreach ($raw as [$search, $replace]) {
            if ($search === '' || $search === $replace) {
                continue;
            }
            if (isset($seen[$search])) {
                continue;
            }
            $seen[$search] = true;
            $out[] = ['search' => $search, 'replace' => $replace];
        }
        return $out;
    }

    public static function apply_replacements($text, array $pairs, $target_phone = '') {
        $text = (string) $text;
        foreach ($pairs as $pair) {
            if ($pair['search'] === '') {
                continue;
            }
            $text = str_replace($pair['search'], $pair['replace'], $text);
        }
        if ($target_phone !== '') {
            $text = DLM_Content::normalize_cloned_phones($text, $target_phone);
        }
        return $text;
    }

    private static function copy_and_replace_meta($from_id, $to_id, array $pairs, $target_phone = '') {
        $skip = array_flip(array_merge(
            self::ELEMENTOR_META_KEYS,
            ['_thumbnail_id']  // handled separately via set_post_thumbnail
        ));

        $keys = self::COPY_META_KEYS;
        foreach (array_keys(get_post_meta($from_id)) as $key) {
            if (strpos($key, '_yoast_') === 0 && !in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        foreach ($keys as $key) {
            if (isset($skip[$key])) {
                continue;
            }
            $val = get_post_meta($from_id, $key, true);
            if ($val === '' || $val === false) {
                continue;
            }
            if (is_string($val)) {
                $val = self::apply_replacements($val, $pairs, $target_phone);
            } elseif (is_array($val)) {
                $val = self::replace_in_array($val, $pairs, $target_phone);
            }
            update_post_meta($to_id, $key, $val);
        }
    }

    private static function replace_in_array($data, array $pairs, $target_phone = '') {
        if (!is_array($data)) {
            return is_string($data) ? self::apply_replacements($data, $pairs, $target_phone) : $data;
        }
        foreach ($data as $k => $v) {
            if (is_string($v)) {
                $data[$k] = self::apply_replacements($v, $pairs, $target_phone);
            } elseif (is_array($v)) {
                $data[$k] = self::replace_in_array($v, $pairs, $target_phone);
            }
        }
        return $data;
    }

    /**
     * Location label for headings, e.g. "Lenexa, KS".
     */
    public static function target_location_label(array $target) {
        $label = trim($target['label'] ?? '');
        if ($label !== '') {
            return $label;
        }
        return trim($target['city'] ?? '');
    }

    /**
     * Trailing location phrases to strip from titles/headings (longest match wins).
     *
     * @return string[]
     */
    public static function location_strip_terms(array $loc = null) {
        $terms = [];
        $locations = $loc !== null ? [$loc] : DLM_Settings::locations();

        foreach ($locations as $row) {
            $label = trim($row['label'] ?? '');
            $city  = trim($row['city'] ?? '');

            if ($label !== '') {
                $terms[] = $label;
                $parts = preg_split('/\s*,\s*/', $label, 2);
                $city_name = trim($parts[0] ?? '');
                $state     = trim($parts[1] ?? '');
                if ($city_name !== '' && $state !== '') {
                    $terms[] = $city_name . ', ' . $state;
                    $terms[] = $city_name . ',' . $state;
                    $terms[] = $city_name . ' ' . $state;
                    $terms[] = $city_name . ' ' . strtoupper($state);
                    $terms[] = $city_name . ' ' . strtolower($state);
                }
                if ($city_name !== '') {
                    $terms[] = $city_name;
                }
            }

            if ($city !== '') {
                $terms[] = $city;
                $terms[] = str_replace('-', ' ', $city);
            }

            if (!empty($row['aliases']) && is_array($row['aliases'])) {
                foreach ($row['aliases'] as $alias) {
                    $alias = trim((string) $alias);
                    if ($alias === '') {
                        continue;
                    }
                    $terms[] = $alias;
                    $terms[] = str_replace('-', ' ', $alias);
                }
            }
        }

        $terms = array_values(array_unique(array_filter($terms)));
        usort($terms, function ($a, $b) {
            return strlen($b) - strlen($a);
        });

        return $terms;
    }

    /**
     * Remove trailing location names from a title/heading (with or without a leading "in").
     * Cleans up legacy clones that appended a bare city name to the service title.
     */
    public static function strip_known_locations($text) {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }

        $text = trim((string) preg_replace('/\s+in\s+.+$/iu', '', $text));

        $terms = self::location_strip_terms();

        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($terms as $term) {
                if ($term === '') {
                    continue;
                }
                $next = preg_replace('/\s+' . preg_quote($term, '/') . '\s*$/iu', '', $text);
                if (is_string($next) && $next !== $text) {
                    $text = trim($next);
                    $changed = true;
                }
            }
        }

        return trim($text);
    }

    /**
     * Format hero/service headings: "{service} in Lenexa, KS".
     * Preserves inline HTML wrappers (e.g. accent span) when present.
     */
    public static function format_location_heading($heading, array $target) {
        $label = self::target_location_label($target);
        if ($label === '') {
            return (string) $heading;
        }

        $heading = (string) $heading;
        $plain   = trim(wp_strip_all_tags($heading));
        if ($plain === '') {
            return $heading;
        }

        $suffix = ' in ' . $label;
        $base_plain = self::strip_known_locations($plain);
        if ($base_plain === '') {
            return $heading;
        }

        $new_plain = $base_plain . $suffix;
        if (strcasecmp($plain, $new_plain) === 0) {
            return $heading;
        }

        if ($heading === $plain) {
            return $new_plain;
        }

        if ($plain !== $base_plain) {
            $removed = substr($plain, strlen($base_plain));
            if ($removed !== '') {
                $html = preg_replace('/' . preg_quote($removed, '/') . '\s*$/iu', $suffix, $heading);
                if (is_string($html) && $html !== $heading) {
                    return $html;
                }
            }
        }

        if (preg_match('/\s+in\s+.+$/iu', $plain)) {
            return preg_replace('/\s+in\s+.+$/iu', $suffix, $heading);
        }

        return $heading . $suffix;
    }

    /**
     * Set the hero H1 to "{service} in {location label}" in Elementor + post_content.
     */
    private static function finalize_hero_headings($post_id, array $target) {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return;
        }

        $raw = get_post_meta($post_id, '_elementor_data', true);
        if (is_string($raw) && $raw !== '') {
            $data = json_decode($raw, true);
            if (is_array($data) && self::update_first_h1_heading($data, $target)) {
                $encoded = wp_json_encode($data);
                if (is_string($encoded) && $encoded !== '') {
                    update_post_meta($post_id, '_elementor_data', wp_slash($encoded));
                }
            }
        }

        $post = get_post($post_id);
        if (!$post || !is_string($post->post_content) || $post->post_content === '') {
            return;
        }

        $updated = preg_replace_callback(
            '#(<h1\b[^>]*>)(.*?)(</h1>)#is',
            function ($matches) use ($target) {
                static $done = false;
                if ($done) {
                    return $matches[0];
                }
                $done = true;
                return $matches[1] . self::format_location_heading($matches[2], $target) . $matches[3];
            },
            $post->post_content,
            1
        );

        if (is_string($updated) && $updated !== $post->post_content) {
            wp_update_post([
                'ID'           => $post_id,
                'post_content' => $updated,
            ]);
        }
    }

    /**
     * Update the first h1 Elementor heading widget (falls back to first heading).
     *
     * @return bool True when a heading was updated.
     */
    private static function update_first_h1_heading(array &$elements, array $target) {
        $fallback = null;

        foreach ($elements as &$element) {
            if (!is_array($element)) {
                continue;
            }
            if (($element['widgetType'] ?? '') === 'heading') {
                if ($fallback === null) {
                    $fallback = &$element;
                }
                $size = $element['settings']['header_size'] ?? 'h2';
                if ($size === 'h1') {
                    $element['settings']['title'] = self::format_location_heading(
                        $element['settings']['title'] ?? '',
                        $target
                    );
                    return true;
                }
            }
            if (!empty($element['elements']) && is_array($element['elements'])) {
                if (self::update_first_h1_heading($element['elements'], $target)) {
                    return true;
                }
            }
        }

        if ($fallback !== null) {
            $fallback['settings']['title'] = self::format_location_heading(
                $fallback['settings']['title'] ?? '',
                $target
            );
            return true;
        }

        return false;
    }

    /**
     * WordPress post title: "{service} Lenexa, KS" (no "in").
     */
    public static function format_location_title($title, array $target) {
        $label = self::target_location_label($target);
        if ($label === '') {
            return (string) $title;
        }

        $title = trim((string) $title);
        $base = self::strip_known_locations($title);
        if ($base === '') {
            return $title;
        }

        $new = $base . ' ' . $label;
        if (strcasecmp($title, $new) === 0) {
            return $title;
        }

        return $new;
    }

    /**
     * Set post title to "{service} {location label}" for cloned pages.
     */
    private static function customize_title_for_target($title, array $target, array $source) {
        return self::format_location_title($title, $target);
    }

    /**
     * Guess source location from page map / URL context.
     */
    public static function detect_source_from_template($post_id) {
        $url = get_permalink($post_id);
        if (!$url) {
            return null;
        }
        $path = DLM_Slug::normalize_path(wp_parse_url($url, PHP_URL_PATH));
        $loc  = DLM_Slug::match_location($path);
        if (!$loc) {
            return null;
        }
        return DLM_Settings::find_location($loc['region'], $loc['city']);
    }

    /**
     * Resolve fallback generic page from slot row.
     */
    private static function fallback_template_from_slot($slot) {
        $row = DLM_Settings::find_slot($slot);
        if (!$row || empty($row['fallback'])) {
            return 0;
        }
        $path = DLM_Slug::normalize_path(wp_parse_url($row['fallback'], PHP_URL_PATH));
        return DLM_Registry::path_to_post_id($path);
    }
}
