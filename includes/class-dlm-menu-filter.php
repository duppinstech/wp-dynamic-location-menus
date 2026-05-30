<?php
/**
 * DLM_Menu_Filter
 *
 * Hooks into nav menu rendering and rewrites each item's URL according to
 * the current location context. Honors the "hide" policy: items the resolver
 * marks null are removed from the menu.
 */

if (!defined('ABSPATH')) { exit; }

class DLM_Menu_Filter {

    public static function register() {
        add_filter('wp_nav_menu_objects', [__CLASS__, 'rewrite_menu_items'], 10, 2);
    }

    public static function rewrite_menu_items($items, $args) {
        if (empty($items) || !is_array($items)) {
            return $items;
        }
        $context = DLM_Context::current();
        if (!$context) {
            return $items;
        }

        $kept = [];
        foreach ($items as $item) {
            if (empty($item->url)) {
                $kept[] = $item;
                continue;
            }
            $original = $item->url;
            $resolved = DLM_Resolver::resolve($original, $context);

            if ($resolved === null) {
                // Hide this item.
                continue;
            }
            if ($resolved !== $original) {
                $item->url = (strpos($resolved, '/') === 0 && strpos($resolved, '//') !== 0)
                    ? home_url($resolved)
                    : $resolved;
                if (is_array($item->classes)) {
                    $item->classes[] = 'dlm-rewritten';
                }
            }
            $kept[] = $item;
        }
        return $kept;
    }
}
