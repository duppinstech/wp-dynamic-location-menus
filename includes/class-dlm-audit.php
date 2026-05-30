<?php
/**
 * DLM_Audit
 *
 * Consistency checks per location: phones and location names in stored page source.
 */

if (!defined('ABSPATH')) { exit; }

class DLM_Audit {

    /**
     * Run audit for one configured location.
     *
     * @return array{
     *   location: array,
     *   canonical_phone: string,
     *   canonical_digits: string,
     *   pages: array<int, array{slot:string, path:string, post_id:int, title:string, phones:string[], has_canonical_phone:bool, location_terms:string[], foreign_hits:array}>,
     *   phone_summary: array{pass:bool, distinct: array<string, int>, missing: array, wrong: array},
     *   location_summary: array{pass:bool, missing_pages: array, foreign_pages: array},
     *   pass: bool
     * }
     */
    public static function scan_location(array $location) {
        $region = isset($location['region']) ? (string) $location['region'] : '';
        $city   = isset($location['city']) ? (string) $location['city'] : '';
        $phone  = isset($location['phone']) ? (string) $location['phone'] : '';
        $digits = DLM_Content::normalize_phone($phone);

        $pages_by_slot = DLM_Registry::pages_for_location($region, $city);
        $pages            = [];
        // phone_pages: [formatted_phone => [{slot, post_id, title, edit_url}]]
        $phone_pages      = [];
        $missing_phone    = [];
        $wrong_phone      = [];
        $missing_location = [];

        foreach ($pages_by_slot as $slot => $path) {
            $post_id = DLM_Registry::path_to_post_id($path);
            if ($post_id <= 0) {
                continue;
            }

            $title    = get_the_title($post_id);
            $edit_url = get_edit_post_link($post_id, 'raw');
            $view_url = home_url($path);

            $text   = DLM_Content::get_stored_text($post_id);
            $phones = DLM_Content::find_phones($text);
            $has_canonical = ($digits !== '') ? DLM_Content::text_has_phone($text, $phone) : null;
            $yoast  = DLM_Content::get_yoast_fields($post_id);

            foreach ($phones as $p) {
                $d = DLM_Content::normalize_phone($p);
                if ($d === '') {
                    continue;
                }
                $display = DLM_Content::format_phone($d);
                if (!isset($phone_pages[$display])) {
                    $phone_pages[$display] = [];
                }
                $already = false;
                foreach ($phone_pages[$display] as $existing) {
                    if ($existing['post_id'] === $post_id) { $already = true; break; }
                }
                if (!$already) {
                    $phone_pages[$display][] = [
                        'slot'     => $slot,
                        'post_id'  => $post_id,
                        'title'    => $title,
                        'edit_url' => $edit_url,
                    ];
                }
            }

            $loc_terms = DLM_Content::find_location_terms($text, $location);

            if ($digits !== '' && !$has_canonical) {
                $missing_phone[] = [
                    'slot'     => $slot,
                    'post_id'  => $post_id,
                    'title'    => $title,
                    'edit_url' => $edit_url,
                    'phones'   => $phones,
                ];
            } elseif ($digits !== '' && $has_canonical && count($phones) > 1) {
                $other = array_values(array_filter($phones, function ($p) use ($digits) {
                    return DLM_Content::normalize_phone($p) !== $digits;
                }));
                if (!empty($other)) {
                    $wrong_phone[] = [
                        'slot'     => $slot,
                        'post_id'  => $post_id,
                        'title'    => $title,
                        'edit_url' => $edit_url,
                        'phones'   => $phones,
                        'extra'    => $other,
                    ];
                }
            }

            // Only check that the page's own city name appears — other locations on the
            // same page are expected (service area lists, etc.) and are not flagged.
            if (empty($loc_terms) && !empty($location['label'])) {
                $missing_location[] = [
                    'slot'     => $slot,
                    'post_id'  => $post_id,
                    'title'    => $title,
                    'edit_url' => $edit_url,
                ];
            }

            $pages[] = [
                'slot'                => $slot,
                'path'                => $path,
                'post_id'             => $post_id,
                'title'               => $title,
                'edit_url'            => $edit_url,
                'view_url'            => $view_url,
                'phones'              => $phones,
                'has_canonical_phone' => $has_canonical,
                'location_terms'      => $loc_terms,
                'foreign_hits'        => [],
                'yoast'               => $yoast,
            ];
        }

        $phone_pass = ($digits === '') ? null : (empty($missing_phone) && empty($wrong_phone));
        $loc_pass   = empty($missing_location);
        $pass       = ($phone_pass !== false) && $loc_pass;

        return [
            'location'         => $location,
            'canonical_phone'  => $phone,
            'canonical_digits' => $digits,
            'pages'            => $pages,
            'phone_summary'    => [
                'pass'    => $phone_pass,
                'by_phone'=> $phone_pages,   // [phone => [{slot,post_id,title,edit_url}]]
                'missing' => $missing_phone,
                'wrong'   => $wrong_phone,
            ],
            'location_summary' => [
                'pass'          => $loc_pass,
                'missing_pages' => $missing_location,
            ],
            'pass'             => (bool) $pass,
        ];
    }

    /**
     * Detect other configured location names/slugs in text.
     *
     * @return array<int, array{label:string, term:string}>
     */
    private static function find_foreign_locations($text, array $current) {
        $current_city   = isset($current['city']) ? (string) $current['city'] : '';
        $current_region = isset($current['region']) ? (string) $current['region'] : '';
        $hits = [];

        foreach (DLM_Settings::locations() as $loc) {
            $r = isset($loc['region']) ? (string) $loc['region'] : '';
            $c = isset($loc['city']) ? (string) $loc['city'] : '';
            if ($c === $current_city && $r === $current_region) {
                continue;
            }
            $label = !empty($loc['label']) ? $loc['label'] : $c;
            foreach (DLM_Content::location_search_terms($loc) as $term) {
                if (strlen($term) < 3) {
                    continue;
                }
                if (stripos($text, $term) !== false) {
                    $hits[] = ['label' => $label, 'term' => $term];
                    break;
                }
            }
        }
        return $hits;
    }
}
