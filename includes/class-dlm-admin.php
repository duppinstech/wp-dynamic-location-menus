<?php
/**
 * DLM_Admin
 *
 * Settings page under Settings → Dynamic Location Menus.
 * Provides:
 *   - URL pattern config (with advanced toggle)
 *   - Locations repeater (region optional, city + label)
 *   - Service slot repeater (url_path, slot, fallback)
 *   - Coverage matrix
 *   - URL preview tool
 *   - "Rescan now" button
 */

if (!defined('ABSPATH')) { exit; }

class DLM_Admin {

    const PAGE_SLUG = 'dlm-settings';

    /**
     * Read a GET value for read-only admin UI (tabs, notices, preview fields).
     *
     * @param mixed $default
     * @return mixed
     */
    private static function query_get($key, $default = '') {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only admin navigation and notice flags.
        if (!isset($_GET[$key])) {
            return $default;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        return sanitize_text_field((string) wp_unslash($_GET[$key]));
    }

    public static function register() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_post_dlm_rescan', [__CLASS__, 'handle_rescan']);
        add_action('admin_post_dlm_clone_gap', [__CLASS__, 'handle_clone_gap']);
        add_action('admin_post_dlm_clone_batch', [__CLASS__, 'handle_clone_batch']);
        add_action('admin_post_dlm_clone_slot_missing', [__CLASS__, 'handle_clone_slot_missing']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function add_menu() {
        add_options_page(
            'Dynamic Location Menus',
            'Location Menus',
            'manage_options',
            self::PAGE_SLUG,
            [__CLASS__, 'render_page']
        );
    }

    public static function register_settings() {
        register_setting(
            'dlm_settings_group',
            DLM_Settings::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => ['DLM_Settings', 'sanitize'],
                'default'           => DLM_Settings::defaults(),
            ]
        );
    }

    public static function enqueue_assets($hook) {
        if ($hook !== 'settings_page_' . self::PAGE_SLUG) return;
        wp_enqueue_style('dlm-admin', DLM_URL . 'assets/admin.css', [], DLM_VERSION);
        wp_enqueue_script('dlm-admin', DLM_URL . 'assets/admin.js', ['jquery'], DLM_VERSION, true);

        $missing_by_slot = [];
        foreach (DLM_Settings::slots() as $row) {
            $slot = sanitize_title($row['slot'] ?? '');
            if ($slot === '') continue;
            $keys = [];
            foreach (DLM_Registry::missing_locations_for_slot($slot) as $loc) {
                $keys[] = ($loc['region'] ?? '') . '|' . ($loc['city'] ?? '');
            }
            $missing_by_slot[$slot] = $keys;
        }
        wp_localize_script('dlm-admin', 'dlmAdmin', [
            'missingBySlot' => $missing_by_slot,
        ]);
    }

    public static function handle_rescan() {
        if (!current_user_can('manage_options')) wp_die('Nope.');
        check_admin_referer('dlm_rescan');
        DLM_Scanner::scan_now();
        $tab = isset($_POST['tab']) ? sanitize_key(wp_unslash($_POST['tab'])) : 'coverage';
        wp_safe_redirect(add_query_arg([
            'page'      => self::PAGE_SLUG,
            'tab'       => $tab,
            'rescanned' => 1,
        ], admin_url('options-general.php')));
        exit;
    }

    public static function handle_clone_gap() {
        if (!current_user_can('edit_pages')) wp_die('Nope.');
        check_admin_referer('dlm_clone_gap');

        $region = isset($_POST['region']) ? sanitize_title(wp_unslash($_POST['region'])) : '';
        $slot   = isset($_POST['slot']) ? sanitize_title(wp_unslash($_POST['slot'])) : '';
        $city   = isset($_POST['city']) ? sanitize_title(wp_unslash($_POST['city'])) : '';
        $tpl    = isset($_POST['template_id']) ? (int) $_POST['template_id'] : 0;
        $status = isset($_POST['status']) && $_POST['status'] === 'publish' ? 'publish' : 'draft';

        $result = DLM_Cloner::create_from_gap($region, $slot, $city, $tpl, $status);

        $args = ['page' => self::PAGE_SLUG, 'tab' => 'coverage'];
        if ($result['success']) {
            $args['cloned'] = 1;
            $args['post_id'] = (int) $result['post_id'];
        } else {
            $args['clone_error'] = rawurlencode($result['message']);
        }
        wp_safe_redirect(add_query_arg($args, admin_url('options-general.php')));
        exit;
    }

    public static function handle_clone_slot_missing() {
        if (!current_user_can('edit_pages')) wp_die('Nope.');
        check_admin_referer('dlm_clone_slot_missing');

        $slot   = isset($_POST['slot']) ? sanitize_title(wp_unslash($_POST['slot'])) : '';
        $tpl    = isset($_POST['template_id']) ? (int) $_POST['template_id'] : 0;
        $status = isset($_POST['status']) && $_POST['status'] === 'publish' ? 'publish' : 'draft';

        $template = $tpl > 0 ? $tpl : 0;
        $source   = null;
        if ($template > 0) {
            $source = DLM_Cloner::detect_source_from_template($template);
        } else {
            $found = DLM_Registry::find_template_for_slot($slot);
            if ($found) {
                $template = $found['post_id'];
                $source   = DLM_Settings::find_location($found['region'], $found['city']);
            }
        }
        if ($template <= 0 || !$source) {
            wp_safe_redirect(add_query_arg([
                'page'        => self::PAGE_SLUG,
                'tab'         => 'coverage',
                'clone_error' => rawurlencode('No template page found for slot: ' . $slot),
            ], admin_url('options-general.php')));
            exit;
        }

        $targets = DLM_Registry::missing_locations_for_slot($slot);
        $batch   = DLM_Cloner::clone_batch($template, $source, $targets, $slot, $status, true);

        set_transient('dlm_clone_batch_result', $batch, 300);
        wp_safe_redirect(add_query_arg([
            'page'       => self::PAGE_SLUG,
            'tab'        => 'clone',
            'batch_done' => 1,
        ], admin_url('options-general.php')));
        exit;
    }

    public static function handle_clone_batch() {
        if (!current_user_can('edit_pages')) wp_die('Nope.');
        check_admin_referer('dlm_clone_batch');

        $template_id = isset($_POST['template_id']) ? (int) $_POST['template_id'] : 0;
        $slot        = isset($_POST['slot']) ? sanitize_title(wp_unslash($_POST['slot'])) : '';
        $status      = isset($_POST['status']) && $_POST['status'] === 'publish' ? 'publish' : 'draft';
        // Unchecked checkboxes are omitted from POST — only skip when explicitly checked.
        $skip        = isset($_POST['skip_existing']) && $_POST['skip_existing'] === '1';

        $source_region = isset($_POST['source_region']) ? sanitize_title(wp_unslash($_POST['source_region'])) : '';
        $source_city   = isset($_POST['source_city']) ? sanitize_title(wp_unslash($_POST['source_city'])) : '';
        $source_key    = isset($_POST['source_key']) ? sanitize_text_field(wp_unslash($_POST['source_key'])) : '';
        if ($source_city === '' && $source_key !== '' && $source_key !== '__generic__') {
            $parts = explode('|', $source_key, 2);
            $source_region = $parts[0] ?? '';
            $source_city   = $parts[1] ?? '';
        }
        $source = DLM_Cloner::resolve_source($template_id, $source_key);

        $target_keys = [];
        if (isset($_POST['targets']) && is_array($_POST['targets'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each array value sanitized via map_deep.
            $target_keys = map_deep(wp_unslash($_POST['targets']), 'sanitize_text_field');
        }
        $targets     = [];
        foreach (DLM_Settings::locations() as $loc) {
            $key = ($loc['region'] ?? '') . '|' . ($loc['city'] ?? '');
            if (in_array($key, $target_keys, true)) {
                $targets[] = $loc;
            }
        }

        if ($template_id <= 0 || empty($targets) || $slot === '') {
            wp_safe_redirect(add_query_arg([
                'page'        => self::PAGE_SLUG,
                'tab'         => 'clone',
                'clone_error' => rawurlencode('Template, slot, and at least one target are required.'),
            ], admin_url('options-general.php')));
            exit;
        }

        $batch = DLM_Cloner::clone_batch($template_id, $source, $targets, $slot, $status, $skip);
        set_transient('dlm_clone_batch_result', $batch, 300);
        wp_safe_redirect(add_query_arg([
            'page'       => self::PAGE_SLUG,
            'tab'        => 'clone',
            'batch_done' => 1,
        ], admin_url('options-general.php')));
        exit;
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) return;
        $opts       = DLM_Settings::get();
        $rescanned  = self::query_get('rescanned') !== '';
        $updated    = self::query_get('settings-updated') !== '';
        $cloned     = self::query_get('cloned') !== '';
        $clone_post = (int) self::query_get('post_id', 0);
        $clone_error = sanitize_text_field((string) self::query_get('clone_error', ''));
        $batch_done  = self::query_get('batch_done') !== '';
        $active_tab  = sanitize_key((string) self::query_get('tab', 'settings'));
        ?>
        <div class="wrap dlm-wrap">
            <h1>Dynamic Location Menus</h1>
            <p class="dlm-tagline">By Duppins Technology — one master menu, location-aware links, page cloning, and consistency checks.</p>

            <?php if ($updated) : ?>
                <div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
            <?php endif; ?>
            <?php if ($rescanned) : ?>
                <div class="notice notice-success is-dismissible"><p>Page registry rescanned.</p></div>
            <?php endif; ?>
            <?php if ($cloned && $clone_post) : ?>
                <div class="notice notice-success is-dismissible"><p>Page created. <a href="<?php echo esc_url(get_edit_post_link($clone_post, 'raw')); ?>">Edit page</a></p></div>
            <?php endif; ?>
            <?php if ($clone_error !== '') : ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html($clone_error); ?></p></div>
            <?php endif; ?>
            <?php if ($batch_done) :
                self::render_batch_notice();
            endif; ?>

            <h2 class="nav-tab-wrapper" style="margin-bottom:16px">
                <a href="#tab-settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>" data-tab="settings">Settings</a>
                <a href="#tab-coverage" class="nav-tab <?php echo $active_tab === 'coverage' ? 'nav-tab-active' : ''; ?>" data-tab="coverage">Coverage matrix</a>
                <a href="#tab-clone" class="nav-tab <?php echo $active_tab === 'clone' ? 'nav-tab-active' : ''; ?>" data-tab="clone">Clone pages</a>
                <a href="#tab-audit" class="nav-tab <?php echo $active_tab === 'audit' ? 'nav-tab-active' : ''; ?>" data-tab="audit">Consistency</a>
                <a href="#tab-preview" class="nav-tab <?php echo $active_tab === 'preview' ? 'nav-tab-active' : ''; ?>" data-tab="preview">URL preview</a>
                <a href="#tab-help" class="nav-tab <?php echo $active_tab === 'help' ? 'nav-tab-active' : ''; ?>" data-tab="help">How it works</a>
            </h2>

            <div class="dlm-tab" data-tab="settings" <?php echo $active_tab !== 'settings' ? 'style="display:none"' : ''; ?>>
                <form method="post" action="options.php">
                    <?php settings_fields('dlm_settings_group'); ?>
                    <?php self::render_settings_tab($opts); ?>
                    <?php submit_button('Save settings'); ?>
                </form>
            </div>

            <div class="dlm-tab" data-tab="coverage" <?php echo $active_tab !== 'coverage' ? 'style="display:none"' : ''; ?>>
                <?php self::render_coverage_tab(); ?>
            </div>

            <div class="dlm-tab" data-tab="clone" <?php echo $active_tab !== 'clone' ? 'style="display:none"' : ''; ?>>
                <?php self::render_clone_tab(); ?>
            </div>

            <div class="dlm-tab" data-tab="audit" <?php echo $active_tab !== 'audit' ? 'style="display:none"' : ''; ?>>
                <?php self::render_audit_tab(); ?>
            </div>

            <div class="dlm-tab" data-tab="preview" <?php echo $active_tab !== 'preview' ? 'style="display:none"' : ''; ?>>
                <?php self::render_preview_tab(); ?>
            </div>

            <div class="dlm-tab" data-tab="help" <?php echo $active_tab !== 'help' ? 'style="display:none"' : ''; ?>>
                <?php self::render_help_tab(); ?>
            </div>
        </div>
        <?php
    }

    /* -------------------------------------------------------------------- */

    private static function render_settings_tab($opts) {
        $opt_key = DLM_Settings::OPTION_KEY;
        ?>
        <h2>When a city page doesn't exist</h2>
        <table class="form-table">
            <tr>
                <th><label>Missing-page behavior</label></th>
                <td>
                    <?php $m = $opts['on_missing']; ?>
                    <label><input type="radio" name="<?php echo esc_attr($opt_key); ?>[on_missing]" value="fallback" <?php checked($m, 'fallback'); ?>> Use the generic fallback URL (most informative)</label><br>
                    <label><input type="radio" name="<?php echo esc_attr($opt_key); ?>[on_missing]" value="leave"    <?php checked($m, 'leave'); ?>> Leave the menu item's original URL untouched</label><br>
                    <label><input type="radio" name="<?php echo esc_attr($opt_key); ?>[on_missing]" value="hide"     <?php checked($m, 'hide'); ?>> Hide the menu item</label>
                </td>
            </tr>
        </table>

        <h2>Locations</h2>
        <p>Add one row per city. Include the <strong>phone number</strong> stored in page content (not the Nimba-swapped live number). Used for cloning and consistency checks.</p>
        <table class="form-table">
            <tr>
                <th><label for="dlm-generic-phone">Generic page phone</label></th>
                <td>
                    <input type="text" id="dlm-generic-phone" name="<?php echo esc_attr($opt_key); ?>[generic_phone]"
                           value="<?php echo esc_attr($opts['generic_phone'] ?? ''); ?>"
                           class="regular-text" placeholder="(800) 927-5201">
                    <p class="description">Toll-free number on generic service pages (e.g. <code>/services/emergency-towing/</code>). When cloning a generic template with no source location, this number is replaced with each target city's local phone.</p>
                </td>
            </tr>
        </table>
        <?php self::render_locations_repeater($opts['locations']); ?>

        <h2>Service slots</h2>
        <p>Each row maps a generic menu URL to a slot name. Use <strong>City page slug(s)</strong> when the slug in city URLs differs from the slot name (e.g. menu slot <code>tow-services</code>, city URL uses <code>towing-services</code>).</p>
        <?php self::render_slots_repeater($opts['slots']); ?>

        <details style="margin-top:32px">
            <summary style="cursor:pointer;font-size:14px;font-weight:600">Advanced: legacy URL patterns (optional)</summary>
            <p class="description" style="margin-top:12px">Slug-based detection is used by default. These patterns are kept for reference and are no longer required for menu rewriting.</p>
            <table class="form-table">
                <tr>
                    <th><label>Pattern</label></th>
                    <td>
                        <input type="text" name="<?php echo esc_attr($opt_key); ?>[pattern]"
                               value="<?php echo esc_attr($opts['pattern']); ?>"
                               class="regular-text code" style="width:480px">
                    </td>
                </tr>
                <tr>
                    <th><label>Advanced patterns</label></th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr($opt_key); ?>[pattern_advanced]" value="1" <?php checked(!empty($opts['pattern_advanced'])); ?>>
                            Enable secondary merged pattern settings
                        </label>
                    </td>
                </tr>
                <tr class="dlm-advanced" <?php if (empty($opts['pattern_advanced'])) echo 'style="display:none"'; ?>>
                    <th><label>Secondary pattern</label></th>
                    <td>
                        <input type="text" name="<?php echo esc_attr($opt_key); ?>[highway_pattern]"
                               value="<?php echo esc_attr($opts['highway_pattern']); ?>"
                               class="regular-text code" style="width:480px">
                    </td>
                </tr>
                <tr class="dlm-advanced" <?php if (empty($opts['pattern_advanced'])) echo 'style="display:none"'; ?>>
                    <th><label>Highway slot name</label></th>
                    <td>
                        <input type="text" name="<?php echo esc_attr($opt_key); ?>[highway_label]"
                               value="<?php echo esc_attr($opts['highway_label']); ?>"
                               class="regular-text">
                        <p class="description">Use a specific slug such as <code>highways</code>. Do <strong>not</strong> use <code>services</code> — that breaks every <code>/services/...</code> menu link.</p>
                        <?php if (sanitize_title($opts['highway_label']) === 'services') : ?>
                            <p class="description" style="color:#b32d2e"><strong>Warning:</strong> Highway slot name is set to <code>services</code>. This is ignored by the plugin because it is too generic.</p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </details>
        <?php
    }

    private static function render_locations_repeater($rows) {
        $opt_key = DLM_Settings::OPTION_KEY;
        if (empty($rows)) $rows = [];
        ?>
        <table class="dlm-repeater widefat" data-repeater="locations">
            <thead>
                <tr>
                    <th style="width:14%">Region (optional)</th>
                    <th style="width:16%">City slug</th>
                    <th style="width:18%">Aliases (comma-separated)</th>
                    <th style="width:22%">Display label</th>
                    <th style="width:18%">Phone</th>
                    <th style="width:8%"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $i => $row):
                $aliases = isset($row['aliases']) && is_array($row['aliases']) ? implode(', ', $row['aliases']) : ''; ?>
                <tr class="dlm-row">
                    <td><input type="text" name="<?php echo esc_attr($opt_key); ?>[locations][<?php echo (int)$i; ?>][region]" value="<?php echo esc_attr($row['region'] ?? ''); ?>" placeholder="Region"></td>
                    <td><input type="text" name="<?php echo esc_attr($opt_key); ?>[locations][<?php echo (int)$i; ?>][city]"   value="<?php echo esc_attr($row['city'] ?? ''); ?>"   placeholder="Slug" required></td>
                    <td><input type="text" name="<?php echo esc_attr($opt_key); ?>[locations][<?php echo (int)$i; ?>][aliases]" value="<?php echo esc_attr($aliases); ?>" placeholder="Aliases"></td>
                    <td><input type="text" name="<?php echo esc_attr($opt_key); ?>[locations][<?php echo (int)$i; ?>][label]"  value="<?php echo esc_attr($row['label'] ?? ''); ?>"  placeholder="Display label"></td>
                    <td><input type="text" name="<?php echo esc_attr($opt_key); ?>[locations][<?php echo (int)$i; ?>][phone]"  value="<?php echo esc_attr($row['phone'] ?? ''); ?>"  placeholder="(555) 555-0100"></td>
                    <td><button type="button" class="button-link-delete dlm-remove">Remove</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p><button type="button" class="button dlm-add" data-template="location">+ Add location</button></p>

        <script type="text/template" id="dlm-tpl-location">
            <tr class="dlm-row">
                <td><input type="text" name="<?php echo esc_attr($opt_key); ?>[locations][__i__][region]" value="" placeholder="Region"></td>
                <td><input type="text" name="<?php echo esc_attr($opt_key); ?>[locations][__i__][city]"   value="" placeholder="Slug" required></td>
                <td><input type="text" name="<?php echo esc_attr($opt_key); ?>[locations][__i__][aliases]" value="" placeholder="Aliases"></td>
                <td><input type="text" name="<?php echo esc_attr($opt_key); ?>[locations][__i__][label]"  value="" placeholder="Display label"></td>
                <td><input type="text" name="<?php echo esc_attr($opt_key); ?>[locations][__i__][phone]"  value="" placeholder="(555) 555-0100"></td>
                <td><button type="button" class="button-link-delete dlm-remove">Remove</button></td>
            </tr>
        </script>
        <?php
    }

    private static function render_slots_repeater($rows) {
        $opt_key = DLM_Settings::OPTION_KEY;
        if (empty($rows)) $rows = [];
        ?>
        <table class="dlm-repeater widefat" data-repeater="slots">
            <thead>
                <tr>
                    <th style="width:30%">URL path (menu item, no slashes)</th>
                    <th style="width:18%">Slot name</th>
                    <th style="width:18%">City page slug(s)</th>
                    <th style="width:27%">Fallback URL</th>
                    <th style="width:7%"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $i => $row):
                $city_slugs = isset($row['city_slugs']) && is_array($row['city_slugs']) ? implode(', ', $row['city_slugs']) : ''; ?>
                <tr class="dlm-row">
                    <td><input type="text" name="<?php echo esc_attr($opt_key); ?>[slots][<?php echo (int)$i; ?>][url_path]" value="<?php echo esc_attr($row['url_path'] ?? ''); ?>" placeholder="URL path"></td>
                    <td><input type="text" name="<?php echo esc_attr($opt_key); ?>[slots][<?php echo (int)$i; ?>][slot]"     value="<?php echo esc_attr($row['slot'] ?? ''); ?>"     placeholder="Slot"></td>
                    <td><input type="text" name="<?php echo esc_attr($opt_key); ?>[slots][<?php echo (int)$i; ?>][city_slugs]" value="<?php echo esc_attr($city_slugs); ?>" placeholder="City page slug(s)"></td>
                    <td><input type="text" name="<?php echo esc_attr($opt_key); ?>[slots][<?php echo (int)$i; ?>][fallback]" value="<?php echo esc_attr($row['fallback'] ?? ''); ?>" placeholder="Fallback URL"></td>
                    <td><button type="button" class="button-link-delete dlm-remove">Remove</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p><button type="button" class="button dlm-add" data-template="slot">+ Add slot</button></p>

        <script type="text/template" id="dlm-tpl-slot">
            <tr class="dlm-row">
                <td><input type="text" name="<?php echo esc_attr($opt_key); ?>[slots][__i__][url_path]" value="" placeholder="URL path"></td>
                <td><input type="text" name="<?php echo esc_attr($opt_key); ?>[slots][__i__][slot]"     value="" placeholder="Slot"></td>
                <td><input type="text" name="<?php echo esc_attr($opt_key); ?>[slots][__i__][city_slugs]" value="" placeholder="City page slug(s)"></td>
                <td><input type="text" name="<?php echo esc_attr($opt_key); ?>[slots][__i__][fallback]" value="" placeholder="Fallback URL"></td>
                <td><button type="button" class="button-link-delete dlm-remove">Remove</button></td>
            </tr>
        </script>
        <?php
    }

    /* -------------------------------------------------------------------- */

    private static function render_coverage_tab() {
        $map       = DLM_Registry::page_map();
        $last_scan = DLM_Registry::last_scan();
        $locations = DLM_Settings::locations();

        // Only show service slots that have at least one indexed city page.
        $slots = DLM_Registry::slots_with_pages();
        ?>
        <p>Last scan:
            <strong><?php echo $last_scan ? esc_html(human_time_diff($last_scan) . ' ago') : 'never'; ?></strong>
            <?php if ($last_scan): ?><span class="dlm-mute"> (<?php echo esc_html(date_i18n('Y-m-d H:i', $last_scan)); ?>)</span><?php endif; ?>
        </p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:24px">
            <?php wp_nonce_field('dlm_rescan'); ?>
            <input type="hidden" name="action" value="dlm_rescan">
            <input type="hidden" name="tab" value="coverage">
            <button class="button button-primary">Rescan pages now</button>
            <span class="dlm-mute">Scans published pages only (not blog posts). Runs daily and when a matching page is saved.</span>
        </form>

        <?php if (empty($locations) || empty($slots)): ?>
            <p><em>Configure locations and slots first, then click Rescan.</em></p>
        <?php else: ?>
            <table class="widefat striped dlm-matrix">
                <thead>
                    <tr>
                        <th>Location</th>
                        <?php foreach ($slots as $s):
                            $missing_count = count(DLM_Registry::missing_locations_for_slot($s));
                            $tpl = DLM_Registry::find_template_for_slot($s); ?>
                            <th>
                                <?php echo esc_html($s); ?>
                                <?php if ($missing_count > 0 && $tpl): ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="dlm-inline-form" title="Clone to all missing cities">
                                        <?php wp_nonce_field('dlm_clone_slot_missing'); ?>
                                        <input type="hidden" name="action" value="dlm_clone_slot_missing">
                                        <input type="hidden" name="slot" value="<?php echo esc_attr($s); ?>">
                                        <input type="hidden" name="template_id" value="<?php echo (int) $tpl['post_id']; ?>">
                                        <button type="submit" class="button button-small dlm-clone-col" onclick="return confirm('Clone <?php echo esc_js($s); ?> to <?php echo (int) $missing_count; ?> missing cities as drafts?');">+<?php echo (int) $missing_count; ?></button>
                                    </form>
                                <?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($locations as $loc):
                    $region = $loc['region'] ?? '';
                    $city   = $loc['city'] ?? '';
                    $label  = $loc['label'] ?: ($city . ($region ? ", $region" : '')); ?>
                    <tr>
                        <td><strong><?php echo esc_html($label); ?></strong></td>
                        <?php foreach ($slots as $s):
                            $url = $map[$region][$s][$city] ?? null;
                            $post_id = $url ? DLM_Registry::path_to_post_id($url) : 0;
                            $tpl = DLM_Registry::find_template_for_slot($s); ?>
                            <td class="<?php echo $url ? 'dlm-yes' : 'dlm-no'; ?>">
                                <?php if ($url): ?>
                                    <a href="<?php echo esc_url(home_url($url)); ?>" target="_blank" title="<?php echo esc_attr($url); ?>">✓</a>
                                    <?php if ($post_id): ?>
                                        <a href="<?php echo esc_url(get_edit_post_link($post_id, 'raw')); ?>" class="dlm-edit-link" title="Edit">✎</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="dlm-inline-form">
                                        <?php wp_nonce_field('dlm_clone_gap'); ?>
                                        <input type="hidden" name="action" value="dlm_clone_gap">
                                        <input type="hidden" name="region" value="<?php echo esc_attr($region); ?>">
                                        <input type="hidden" name="slot" value="<?php echo esc_attr($s); ?>">
                                        <input type="hidden" name="city" value="<?php echo esc_attr($city); ?>">
                                        <?php if ($tpl): ?>
                                            <input type="hidden" name="template_id" value="<?php echo (int) $tpl['post_id']; ?>">
                                        <?php endif; ?>
                                        <button type="submit" class="button button-small" title="<?php echo $tpl ? esc_attr('Clone from existing ' . $s . ' page') : esc_attr('Create from fallback template'); ?>">Create</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="description">Gap cells use <strong>Create</strong> to clone from an existing page for that slot (or the slot fallback). Column <strong>+N</strong> clones to all missing cities for that service.</p>
        <?php endif;
    }

    private static function render_clone_tab() {
        $locations = DLM_Settings::locations();
        $slots     = DLM_Settings::slots();
        $pages     = get_pages(['sort_column' => 'post_title', 'post_status' => ['publish', 'draft']]);

        $prefill_slot = sanitize_title((string) self::query_get('slot', ''));
        $prefill_tpl  = (int) self::query_get('template_id', 0);
        ?>
        <p>Clone a template page to other locations. Replaces city names, slugs, and phone numbers in stored content and Elementor data.</p>
        <p class="description">For <strong>generic service pages</strong> (no city in the URL, usually with your 800 number), leave source on auto-detect or choose <strong>Generic template</strong>. The cloner swaps <?php echo esc_html(DLM_Settings::generic_phone() ?: 'the generic phone'); ?> for each target city's local number. The hero <strong>H1</strong> becomes <code>{service} in Lenexa, KS</code>; the page title is <code>{service} Lenexa, KS</code> (no “in”).</p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="dlm-clone-form">
            <?php wp_nonce_field('dlm_clone_batch'); ?>
            <input type="hidden" name="action" value="dlm_clone_batch">

            <table class="form-table">
                <tr>
                    <th><label for="dlm-template">Template page</label></th>
                    <td>
                        <select name="template_id" id="dlm-template" required style="min-width:360px">
                            <option value="">— Select page —</option>
                            <?php foreach ($pages as $p): ?>
                                <option value="<?php echo (int) $p->ID; ?>" <?php selected($prefill_tpl, $p->ID); ?>>
                                    <?php echo esc_html($p->post_title); ?> (<?php echo esc_html($p->post_status); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="dlm-source">Source location</label></th>
                    <td>
                        <select name="source_key" id="dlm-source" style="min-width:360px">
                            <option value="">— Auto-detect from template (or generic if none) —</option>
                            <option value="__generic__">Generic template (no source location)</option>
                            <?php foreach ($locations as $loc):
                                $key = ($loc['region'] ?? '') . '|' . ($loc['city'] ?? ''); ?>
                                <option value="<?php echo esc_attr($key); ?>"
                                        data-region="<?php echo esc_attr($loc['region'] ?? ''); ?>"
                                        data-city="<?php echo esc_attr($loc['city'] ?? ''); ?>">
                                    <?php echo esc_html($loc['label'] ?: $loc['city']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="dlm-slot">Service slot</label></th>
                    <td>
                        <select name="slot" id="dlm-slot" required style="min-width:360px">
                            <option value="">— Select slot —</option>
                            <?php foreach ($slots as $row):
                                $s = sanitize_title($row['slot'] ?? '');
                                if ($s === '') continue; ?>
                                <option value="<?php echo esc_attr($s); ?>" <?php selected($prefill_slot, $s); ?>><?php echo esc_html($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Target locations</th>
                    <td>
                        <p>
                            <button type="button" class="button button-small dlm-select-all-targets">Select all</button>
                            <button type="button" class="button button-small dlm-select-missing-targets">Only missing for slot</button>
                            <button type="button" class="button button-small dlm-clear-targets">Clear</button>
                        </p>
                        <div class="dlm-target-grid">
                            <?php foreach ($locations as $loc):
                                $key = ($loc['region'] ?? '') . '|' . ($loc['city'] ?? ''); ?>
                                <label class="dlm-target-item">
                                    <input type="checkbox" name="targets[]" value="<?php echo esc_attr($key); ?>"
                                           data-region="<?php echo esc_attr($loc['region'] ?? ''); ?>"
                                           data-city="<?php echo esc_attr($loc['city'] ?? ''); ?>">
                                    <?php echo esc_html($loc['label'] ?: $loc['city']); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th>Options</th>
                    <td>
                        <label><input type="radio" name="status" value="draft" checked> Create as draft (recommended)</label><br>
                        <label><input type="radio" name="status" value="publish"> Publish immediately</label><br>
                        <label style="margin-top:8px;display:inline-block"><input type="checkbox" name="skip_existing" value="1" checked> Skip locations that already have this slot page</label>
                        <p class="description" style="margin:4px 0 0">Uncheck to clone even when a page is already indexed for that slot (e.g. existing AdWords landing pages). WordPress may append <code>-2</code> to the slug if the target path already exists.</p>
                    </td>
                </tr>
            </table>

            <p><button type="submit" class="button button-primary" onclick="return confirm('Clone the template to selected locations?');">Clone pages</button></p>
        </form>

        <?php self::render_batch_result_table(); ?>
        <?php
    }

    private static function render_audit_tab() {
        $locations = DLM_Settings::locations();
        $selected = sanitize_text_field((string) self::query_get('audit_location', ''));
        ?>
        <p>Scan stored page source (content + Elementor) for phone and city name consistency. Does <strong>not</strong> use rendered HTML — Nimba-swapped numbers are ignored.</p>
        <p class="description">After changing a phone number in <strong>Settings</strong>, save and return here. The <em>Configured phone</em> line below confirms what value this scan is checking against.</p>

        <form method="get" action="" class="dlm-audit-scan-form" style="margin-bottom:20px">
            <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>">
            <input type="hidden" name="tab" value="audit">
            <select name="audit_location" style="min-width:280px">
                <option value="">— Select location —</option>
                <?php foreach ($locations as $loc):
                    $key = ($loc['region'] ?? '') . '|' . ($loc['city'] ?? ''); ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($selected, $key); ?>>
                        <?php echo esc_html($loc['label'] ?: $loc['city']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="button button-primary">Scan</button>
        </form>

        <?php
        if ($selected !== '') {
            $parts = explode('|', $selected, 2);
            $region = $parts[0] ?? '';
            $city   = $parts[1] ?? '';
            $loc    = DLM_Settings::find_location($region, $city);
            if ($loc) {
                self::render_audit_results(DLM_Audit::scan_location($loc));
            }
        }
    }

    private static function render_audit_results(array $report) {
        $loc    = $report['location'];
        $label  = $loc['label'] ?: ($loc['city'] ?? '');
        $phone  = $report['canonical_phone'];
        $digits = $report['canonical_digits'];
        $pages  = $report['pages'];
        ?>
        <div class="dlm-audit-report">
            <h3><?php echo esc_html($label); ?></h3>

            <table class="dlm-audit-meta-table">
                <tr>
                    <th>Configured phone:</th>
                    <td>
                        <?php if ($phone !== ''): ?>
                            <strong><?php echo esc_html($phone); ?></strong>
                            <?php if ($digits === ''): ?>
                                <span class="dlm-audit-warn-inline"> — format not recognized as a valid US number. Update the phone in Settings.</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <em class="dlm-mute">None — add a phone in Settings → Locations to enable phone checks.</em>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Pages in map:</th>
                    <td><?php echo count($pages); ?></td>
                </tr>
            </table>

            <?php if ($report['pass']): ?>
                <p class="dlm-audit-overall dlm-audit-pass">✓ All checks passed</p>
            <?php else: ?>
                <p class="dlm-audit-overall dlm-audit-fail">✗ Issues found — review the table below</p>
            <?php endif; ?>

            <?php if (empty($pages)): ?>
                <p><em>No indexed pages found for this location. Run a rescan first.</em></p>
            <?php else: ?>

            <!-- Per-page detail table -->
            <h4 style="margin-top:20px"><?php echo count($pages); ?> indexed page(s)</h4>
            <table class="widefat striped dlm-audit-table">
                <thead>
                    <tr>
                        <th>Page</th>
                        <th>Slot</th>
                        <th>Phones found in stored content</th>
                        <?php if ($digits !== ''): ?>
                            <th>Has <?php echo esc_html($phone); ?>?</th>
                        <?php endif; ?>
                        <th>City name present?</th>
                        <th>Yoast meta description</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pages as $pg):
                    $has_canon  = $pg['has_canonical_phone'];
                    $phone_ok   = ($digits === '') ? null : (bool) $has_canon;
                    $loc_ok     = !empty($pg['location_terms']);
                    $yoast      = $pg['yoast'] ?? ['title' => '', 'metadesc' => '', 'focuskw' => ''];
                    $meta_desc  = $yoast['metadesc'];
                    $seo_title  = $yoast['title'];
                    $yoast_city_ok  = empty($loc['label']) ? null
                        : (stripos($meta_desc . ' ' . $seo_title, explode(',', $loc['label'])[0]) !== false);
                    $yoast_phone_ok = ($digits === '' || $meta_desc === '') ? null
                        : DLM_Content::text_has_phone($meta_desc . ' ' . $seo_title, $phone);
                    $row_class  = (!$phone_ok && $digits !== '') || !$loc_ok ? 'dlm-audit-row-warn' : '';
                ?>
                    <tr class="<?php echo esc_attr($row_class); ?>">
                        <td>
                            <a href="<?php echo esc_url($pg['edit_url']); ?>" title="Edit this page">
                                <?php echo esc_html($pg['title']); ?>
                            </a>
                            <br><a href="<?php echo esc_url($pg['view_url']); ?>" target="_blank" class="dlm-mute" style="font-size:11px">View ↗</a>
                        </td>
                        <td><code><?php echo esc_html($pg['slot']); ?></code></td>
                        <td>
                            <?php if (empty($pg['phones'])): ?>
                                <span class="dlm-mute">none detected</span>
                            <?php else: ?>
                                <?php foreach ($pg['phones'] as $p):
                                    $is_canon = ($digits !== '' && DLM_Content::normalize_phone($p) === $digits);
                                ?>
                                    <span class="dlm-phone-chip <?php echo $is_canon ? 'dlm-chip-ok' : 'dlm-chip-warn'; ?>">
                                        <?php echo esc_html($p); ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <?php if ($digits !== ''): ?>
                            <td class="<?php echo $phone_ok ? 'dlm-audit-pass' : 'dlm-audit-fail'; ?>" style="text-align:center">
                                <?php echo $phone_ok ? '✓' : '✗'; ?>
                            </td>
                        <?php endif; ?>
                        <td class="<?php echo $loc_ok ? '' : 'dlm-audit-fail'; ?>" style="text-align:center">
                            <?php echo $loc_ok ? '✓' : '✗'; ?>
                        </td>
                        <td class="dlm-audit-seo-cell">
                            <?php if ($meta_desc !== ''): ?>
                                <span class="dlm-seo-desc"><?php echo esc_html($meta_desc); ?></span>
                                <span class="dlm-seo-badges">
                                    <?php if ($yoast_city_ok !== null): ?>
                                        <span class="dlm-phone-chip <?php echo $yoast_city_ok ? 'dlm-chip-ok' : 'dlm-chip-warn'; ?>" title="City name in meta description">
                                            city <?php echo $yoast_city_ok ? '✓' : '✗'; ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($yoast_phone_ok !== null): ?>
                                        <span class="dlm-phone-chip <?php echo $yoast_phone_ok ? 'dlm-chip-ok' : 'dlm-chip-warn'; ?>" title="Phone in meta description">
                                            phone <?php echo $yoast_phone_ok ? '✓' : '✗'; ?>
                                        </span>
                                    <?php endif; ?>
                                </span>
                            <?php elseif ($seo_title !== ''): ?>
                                <span class="dlm-mute">No meta desc</span>
                                <?php if ($seo_title !== ''): ?>
                                    <br><span class="description">Title: <?php echo esc_html($seo_title); ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="dlm-mute">—</span>
                                <span class="description" style="font-size:11px"> Yoast not active or not configured for this page</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php endif; // pages not empty ?>

            <!-- Phone breakdown: which pages carry each distinct number -->
            <?php if ($digits !== ''): ?>
                <div class="dlm-audit-card <?php echo $report['phone_summary']['pass'] ? 'dlm-audit-pass' : 'dlm-audit-fail'; ?>" style="margin-top:20px">
                    <h4>Phone breakdown</h4>
                    <?php if ($report['phone_summary']['pass']): ?>
                        <p class="dlm-audit-status">✓ All pages carry the canonical number <?php echo esc_html($phone); ?></p>
                    <?php else: ?>
                        <p class="dlm-audit-status">✗ Canonical number <?php echo esc_html($phone); ?> is missing or extra numbers are present on some pages</p>
                    <?php endif; ?>

                    <?php if (!empty($report['phone_summary']['by_phone'])): ?>
                        <table class="widefat" style="margin-top:10px">
                            <thead>
                                <tr>
                                    <th>Number found in content</th>
                                    <th>Canonical?</th>
                                    <th>Page(s)</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($report['phone_summary']['by_phone'] as $p => $page_list):
                                $is_canon = (DLM_Content::normalize_phone($p) === $digits); ?>
                                <tr>
                                    <td>
                                        <span class="dlm-phone-chip <?php echo $is_canon ? 'dlm-chip-ok' : 'dlm-chip-warn'; ?>">
                                            <?php echo esc_html($p); ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center"><?php echo $is_canon ? '✓ Yes' : '✗ No'; ?></td>
                                    <td>
                                        <?php foreach ($page_list as $pl): ?>
                                            <a href="<?php echo esc_url($pl['edit_url']); ?>">
                                                <?php echo esc_html($pl['title']); ?>
                                            </a>
                                            <span class="dlm-mute">(<?php echo esc_html($pl['slot']); ?>)</span>
                                            <br>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p><em>No phone numbers detected in stored content.</em> Verify that the phone number in Settings matches the format in your Elementor content.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- City name missing -->
            <?php if (!$report['location_summary']['pass'] && !empty($report['location_summary']['missing_pages'])): ?>
                <div class="dlm-audit-card dlm-audit-fail" style="margin-top:16px">
                    <h4>City name not found</h4>
                    <p>The following pages did not contain the expected city name (<strong><?php echo esc_html($label); ?></strong>) anywhere in their stored content:</p>
                    <ul>
                        <?php foreach ($report['location_summary']['missing_pages'] as $row): ?>
                            <li>
                                <a href="<?php echo esc_url($row['edit_url']); ?>"><?php echo esc_html($row['title']); ?></a>
                                <span class="dlm-mute">(<?php echo esc_html($row['slot']); ?>)</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

        </div>
        <?php
    }

    private static function render_batch_notice() {
        $batch = get_transient('dlm_clone_batch_result');
        if (!$batch || !is_array($batch)) {
            return;
        }
        $created = count($batch['created'] ?? []);
        $skipped = count($batch['skipped'] ?? []);
        $errors  = count($batch['errors'] ?? []);
        ?>
        <div class="notice notice-success is-dismissible">
            <p>Batch clone complete: <?php echo (int) $created; ?> created, <?php echo (int) $skipped; ?> skipped, <?php echo (int) $errors; ?> errors.</p>
        </div>
        <?php
    }

    private static function render_batch_result_table() {
        $batch = get_transient('dlm_clone_batch_result');
        if (!$batch || !is_array($batch)) {
            return;
        }
        delete_transient('dlm_clone_batch_result');
        if (empty($batch['created']) && empty($batch['skipped']) && empty($batch['errors'])) {
            return;
        }
        ?>
        <h3>Last batch result</h3>
        <?php if (!empty($batch['created'])): ?>
            <p><strong>Created (<?php echo count($batch['created']); ?>):</strong></p>
            <ul>
                <?php foreach ($batch['created'] as $row): ?>
                    <li><?php echo esc_html($row['label']); ?> — <a href="<?php echo esc_url($row['edit_url']); ?>">Edit</a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if (!empty($batch['skipped'])): ?>
            <p><strong>Skipped (<?php echo count($batch['skipped']); ?>):</strong>
                <?php echo esc_html(implode(', ', array_column($batch['skipped'], 'label'))); ?></p>
        <?php endif; ?>
        <?php if (!empty($batch['errors'])): ?>
            <p><strong>Errors:</strong></p>
            <ul>
                <?php foreach ($batch['errors'] as $row): ?>
                    <li><?php echo esc_html($row['label']); ?>: <?php echo esc_html($row['message']); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif;
    }

    private static function render_preview_tab() {
        $url = esc_url_raw((string) self::query_get('preview_url', ''));
        ?>
        <p>Paste any URL on your site to see which location context the plugin detects and how it would rewrite common slot URLs.</p>
        <form method="get" action="">
            <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>">
            <input type="hidden" name="tab"  value="preview">
            <input type="url" name="preview_url" value="<?php echo esc_attr($url); ?>"
                   placeholder="https://example.com/page-path/"
                   style="width:560px">
            <button class="button">Preview</button>
        </form>
        <?php if ($url):
            $path = wp_parse_url($url, PHP_URL_PATH);
            $ctx  = $path ? DLM_Context::detect_from_url($path) : null;
            ?>
            <div class="dlm-preview-result">
                <h3>Detected context</h3>
                <?php if ($ctx): ?>
                    <ul>
                        <li>Region: <code><?php echo esc_html($ctx['region'] !== '' ? $ctx['region'] : '(none)'); ?></code></li>
                        <li>City:   <code><?php echo esc_html($ctx['city']); ?></code></li>
                        <?php if (!empty($ctx['matched_slug']) && $ctx['matched_slug'] !== $ctx['city']): ?>
                            <li>Matched slug: <code><?php echo esc_html($ctx['matched_slug']); ?></code></li>
                        <?php endif; ?>
                        <li>Slot:   <code><?php echo esc_html($ctx['slot'] ?? '(none)'); ?></code></li>
                    </ul>
                    <h3>Sample resolutions</h3>
                    <p>Each configured slot's URL is shown along with what the plugin would rewrite it to in this context.</p>
                    <table class="widefat striped" style="max-width:900px">
                        <thead><tr><th>Original</th><th>Rewritten</th></tr></thead>
                        <tbody>
                        <?php foreach (DLM_Settings::slots() as $row):
                            $orig = '/' . trim($row['url_path'], '/') . '/';
                            $res  = DLM_Resolver::resolve($orig, $ctx);
                            $changed = ($res !== $orig);
                            ?>
                            <tr>
                                <td><code><?php echo esc_html($orig); ?></code></td>
                                <td class="<?php echo $changed ? 'dlm-yes' : ''; ?>"><code><?php echo $res === null ? '<em>(hidden)</em>' : esc_html($res); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><em>No location context detected — menus on this URL would render with their original links unchanged.</em></p>
                <?php endif; ?>
            </div>
        <?php endif;
    }

    private static function render_help_tab() {
        ?>
        <h2>How to use this plugin</h2>
        <ol>
            <li><strong>Configure locations</strong> — one row per city with display label and <strong>phone</strong> (the number stored in page content, not the Nimba live swap).</li>
            <li><strong>Configure service slots</strong> — one row per generic menu URL.</li>
            <li><strong>Build ONE master menu</strong> in Appearance → Menus using your generic URLs.</li>
            <li><strong>Coverage matrix</strong> — use <strong>Create</strong> on gap cells or column <strong>+N</strong> to clone pages.</li>
            <li><strong>Consistency tab</strong> — scan each city to verify phones and location names match across indexed pages.</li>
            <li><strong>Click Rescan</strong> after publishing new city pages.</li>
        </ol>

        <h2>Page cloning (v2)</h2>
        <p>Clones read and write stored post content and Elementor JSON — never rendered HTML. City names, slugs, aliases, and phone numbers are replaced from Settings.</p>

        <h2>How detection works</h2>
        <p>The plugin looks for a configured city slug anywhere in the current page URL. That works for service-areas paths, services paths, and flat landing-page slugs like <code>/heavy-duty-towing-blue-springs-mo/</code>.</p>
        <p>City pages are indexed from published <strong>pages</strong> only. Blog posts are ignored so a longer post URL never replaces a service page in the menu.</p>

        <h2>Sub-service URLs are left alone</h2>
        <p>Menu matching still uses exact depth. A slot of <code>services/roadside-assistance</code> does not match <code>services/roadside-assistance/battery-boost/</code>. Add an explicit slot row to rewrite sub-service URLs.</p>
        <?php
    }
}
