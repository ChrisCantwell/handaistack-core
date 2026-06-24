<?php
/**
 * Plugin Name: HandAIStack Core
 * Description: Central dashboard, module registry, and CTA Stack coordinator for HandAIMan WordPress tools.
 * Version: 0.1.2
 * Author: HandAIMan / ChatGPT
 * License: GPLv2 or later
 * Update URI: https://thehandaiman.com/handaistack/core
 */

if (!defined('ABSPATH')) { exit; }

class HandAIStack_Core {
    const VERSION = '0.1.2';
    const OPTION_KEY = 'handaistack_core_options';
    const MENU_SLUG = 'handaistack';

    private static $external_modules = array();
    private static $style_printed = false;
    private static $script_printed = false;

    public static function init() {
        register_activation_hook(__FILE__, array(__CLASS__, 'activate'));

        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 7);
        add_action('admin_init', array(__CLASS__, 'register_settings'));

        add_shortcode('handaistack_cta', array(__CLASS__, 'cta_shortcode'));
        add_shortcode('handaiman_cta_stack', array(__CLASS__, 'cta_shortcode'));
        add_shortcode('ha_cta_stack', array(__CLASS__, 'cta_shortcode'));

        add_filter('the_content', array(__CLASS__, 'maybe_auto_append_cta'), 40);
    }

    public static function activate() {
        self::options();
    }

    public static function register_module($slug, $args = array()) {
        $slug = sanitize_key($slug);
        if ($slug === '') { return; }
        self::$external_modules[$slug] = wp_parse_args($args, array(
            'name' => $slug,
            'version' => '',
            'description' => '',
            'settings_url' => '',
            'status' => 'registered',
        ));
    }

    private static function defaults() {
        return array(
            'cta' => array(
                'heading' => 'Keep the chaos at bay',
                'intro' => 'Subscribe, support the project, or send TheHandAIMan a note.',
                'show_heading' => 1,
                'auto_append_posts' => 0,
                'auto_append_podcasts' => 0,
                'only_one_open' => 1,
                'show_follow' => 1,
                'show_support' => 1,
                'show_contact' => 1,
                'follow_order' => 10,
                'support_order' => 20,
                'contact_order' => 30,
                'follow_summary' => 'Subscribe / Follow',
                'support_summary' => 'Support the Project',
                'contact_summary' => 'Contact TheHandAIMan',
            ),
            'settings' => array(
                'show_module_hints' => 1,
                'future_manifest_url' => '',
            ),
        );
    }

    private static function options() {
        $defaults = self::defaults();
        $saved = get_option(self::OPTION_KEY, array());
        if (!is_array($saved)) { $saved = array(); }
        $merged = self::deep_merge($defaults, $saved);
        if ($merged !== $saved) {
            update_option(self::OPTION_KEY, $merged, false);
        }
        return $merged;
    }

    private static function deep_merge($base, $override) {
        foreach ($override as $key => $value) {
            if (isset($base[$key]) && is_array($base[$key]) && is_array($value)) {
                $base[$key] = self::deep_merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }

    public static function register_settings() {
        register_setting('handaistack_core_settings', self::OPTION_KEY, array(__CLASS__, 'sanitize_options'));
    }

    public static function sanitize_options($input) {
        $old = self::options();
        $out = self::defaults();
        $input = is_array($input) ? $input : array();

        $cta = isset($input['cta']) && is_array($input['cta']) ? $input['cta'] : array();
        $out['cta']['heading'] = isset($cta['heading']) ? sanitize_text_field($cta['heading']) : $old['cta']['heading'];
        $out['cta']['intro'] = isset($cta['intro']) ? sanitize_textarea_field($cta['intro']) : $old['cta']['intro'];
        $out['cta']['show_heading'] = empty($cta['show_heading']) ? 0 : 1;
        $out['cta']['auto_append_posts'] = empty($cta['auto_append_posts']) ? 0 : 1;
        $out['cta']['auto_append_podcasts'] = empty($cta['auto_append_podcasts']) ? 0 : 1;
        $out['cta']['only_one_open'] = empty($cta['only_one_open']) ? 0 : 1;
        $out['cta']['show_follow'] = empty($cta['show_follow']) ? 0 : 1;
        $out['cta']['show_support'] = empty($cta['show_support']) ? 0 : 1;
        $out['cta']['show_contact'] = empty($cta['show_contact']) ? 0 : 1;
        $out['cta']['follow_order'] = isset($cta['follow_order']) ? intval($cta['follow_order']) : $old['cta']['follow_order'];
        $out['cta']['support_order'] = isset($cta['support_order']) ? intval($cta['support_order']) : $old['cta']['support_order'];
        $out['cta']['contact_order'] = isset($cta['contact_order']) ? intval($cta['contact_order']) : $old['cta']['contact_order'];
        $out['cta']['follow_summary'] = isset($cta['follow_summary']) ? sanitize_text_field($cta['follow_summary']) : $old['cta']['follow_summary'];
        $out['cta']['support_summary'] = isset($cta['support_summary']) ? sanitize_text_field($cta['support_summary']) : $old['cta']['support_summary'];
        $out['cta']['contact_summary'] = isset($cta['contact_summary']) ? sanitize_text_field($cta['contact_summary']) : $old['cta']['contact_summary'];

        $settings = isset($input['settings']) && is_array($input['settings']) ? $input['settings'] : array();
        $out['settings']['show_module_hints'] = empty($settings['show_module_hints']) ? 0 : 1;
        $out['settings']['future_manifest_url'] = isset($settings['future_manifest_url']) ? esc_url_raw($settings['future_manifest_url']) : $old['settings']['future_manifest_url'];

        return $out;
    }

    public static function admin_menu() {
        add_menu_page(
            'HandAIStack',
            'HandAIStack',
            'manage_options',
            self::MENU_SLUG,
            array(__CLASS__, 'dashboard_page'),
            'dashicons-admin-generic',
            57
        );

        add_submenu_page(
            self::MENU_SLUG,
            'Dashboard',
            'Dashboard',
            'manage_options',
            self::MENU_SLUG,
            array(__CLASS__, 'dashboard_page')
        );

        add_submenu_page(
            self::MENU_SLUG,
            'Modules',
            'Modules',
            'manage_options',
            'handaistack-modules',
            array(__CLASS__, 'modules_page')
        );

        add_submenu_page(
            self::MENU_SLUG,
            'CTA Stack',
            'CTA Stack',
            'manage_options',
            'handaistack-cta',
            array(__CLASS__, 'cta_settings_page')
        );

        add_submenu_page(
            self::MENU_SLUG,
            'Settings',
            'Settings',
            'manage_options',
            'handaistack-settings',
            array(__CLASS__, 'settings_page')
        );
    }

    private static function known_modules() {
        $mods = array(
            'follow' => array(
                'name' => 'HandAIMan Follow & Subscribe',
                'description' => 'Podcast subscription and social follow links.',
                'plugin_file' => 'handaiman-follow-subscribe/handaiman-follow-subscribe.php',
                'class' => 'HandAIMan_Follow_Subscribe_Plugin',
                'shortcode' => 'handaiman_follow',
                'settings_url' => admin_url('admin.php?page=handaiman-follow'),
                'fallback_settings_url' => admin_url('admin.php?page=handaiman-follow'),
            ),
            'support' => array(
                'name' => 'HandAIMan Support My Work',
                'description' => 'Support links, fresh BTC/BCH/LTC addresses, and static alternative crypto networks.',
                'plugin_file' => 'handaiman-btc-xpub/handaiman-btc-xpub.php',
                'class' => 'HandAIMan_Crypto_Contributions',
                'shortcode' => 'handaiman_support',
                'settings_url' => admin_url('options-general.php?page=handaiman-support-my-work'),
                'fallback_settings_url' => admin_url('options-general.php?page=handaiman-support-my-work'),
            ),
            'contact' => array(
                'name' => 'HandAIMan Contact',
                'description' => 'Local contact form, message inbox, notification email, and quiet anti-spam.',
                'plugin_file' => 'handaiman-contact/handaiman-contact.php',
                'class' => 'HandAIMan_Contact_Plugin',
                'shortcode' => 'handaiman_contact',
                'settings_url' => admin_url('admin.php?page=handaiman-contact-settings'),
                'fallback_settings_url' => admin_url('admin.php?page=handaiman-contact-settings'),
            ),
        );

        foreach (self::$external_modules as $slug => $module) {
            $mods[$slug] = wp_parse_args($module, array(
                'name' => $slug,
                'description' => '',
                'plugin_file' => '',
                'class' => '',
                'shortcode' => '',
                'settings_url' => '',
                'fallback_settings_url' => '',
            ));
        }

        return $mods;
    }

    private static function module_statuses() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = get_plugins();
        $mods = self::known_modules();
        $out = array();

        foreach ($mods as $slug => $mod) {
            $file = isset($mod['plugin_file']) ? $mod['plugin_file'] : '';
            $installed = $file && isset($plugins[$file]);
            $active = false;
            if ($file && function_exists('is_plugin_active')) {
                $active = is_plugin_active($file);
            }
            if (!$active && !empty($mod['class']) && class_exists($mod['class'])) {
                $active = true;
            }
            if (!$installed && !empty($mod['class']) && class_exists($mod['class'])) {
                $installed = true;
            }

            $version = '';
            if ($installed && $file && isset($plugins[$file]['Version'])) {
                $version = $plugins[$file]['Version'];
            }
            if (!$version && !empty($mod['class']) && class_exists($mod['class'])) {
                $ref = new ReflectionClass($mod['class']);
                if ($ref->hasConstant('VERSION')) {
                    $version = (string) $ref->getConstant('VERSION');
                }
            }

            $shortcode_exists = !empty($mod['shortcode']) && shortcode_exists($mod['shortcode']);

            $out[$slug] = array_merge($mod, array(
                'installed' => $installed,
                'active' => $active,
                'version' => $version,
                'shortcode_exists' => $shortcode_exists,
            ));
        }

        return $out;
    }

    private static function individual_auto_append_status() {
        $status = array();

        $follow = get_option('ha_follow_options', array());
        if (is_array($follow) && isset($follow['global'])) {
            if (!empty($follow['global']['auto_append_posts']) || !empty($follow['global']['auto_append_podcasts'])) {
                $status[] = 'Follow & Subscribe';
            }
        }

        $support = get_option('ha_crypto_xpub_options', array());
        if (is_array($support) && isset($support['global'])) {
            if (!empty($support['global']['auto_append_posts']) || !empty($support['global']['auto_append_podcasts'])) {
                $status[] = 'Support My Work';
            }
        }

        $contact = get_option('ha_contact_options', array());
        if (is_array($contact)) {
            if (!empty($contact['auto_append_posts']) || !empty($contact['auto_append_podcast'])) {
                $status[] = 'Contact';
            }
        }

        return $status;
    }

    public static function dashboard_page() {
        if (!current_user_can('manage_options')) { return; }
        $modules = self::module_statuses();
        $opts = self::options();
        ?>
        <div class="wrap handaistack-admin">
            <h1>HandAIStack</h1>
            <p class="description">Central dashboard for HandAIMan site infrastructure plugins.</p>

            <?php self::admin_cards($modules); ?>

            <h2>Installed Modules</h2>
            <?php self::render_modules_table($modules, true); ?>

            <h2>CTA Stack</h2>
            <p>The CTA Stack can coordinate collapsed Subscribe / Follow, Support, and Contact panels from one shortcode or one auto-append point.</p>
            <p>
                <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=handaistack-cta')); ?>">Configure CTA Stack</a>
                <code>[handaistack_cta]</code>
            </p>
            <?php if (!empty($opts['cta']['auto_append_posts']) || !empty($opts['cta']['auto_append_podcasts'])): ?>
                <div class="notice notice-info inline"><p>HandAIStack CTA auto-append is enabled.</p></div>
            <?php else: ?>
                <div class="notice notice-warning inline"><p>HandAIStack CTA auto-append is off. This is safe for testing. Use the shortcode or enable auto-append from CTA Stack when ready.</p></div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function admin_cards($modules) {
        $installed = 0;
        $active = 0;
        foreach ($modules as $m) {
            if (!empty($m['installed'])) { $installed++; }
            if (!empty($m['active'])) { $active++; }
        }
        ?>
        <div style="display:flex; gap:12px; flex-wrap:wrap; margin:18px 0;">
            <div class="postbox" style="min-width:180px; padding:14px 16px;"><strong>Core</strong><br><span style="font-size:22px;">v<?php echo esc_html(self::VERSION); ?></span></div>
            <div class="postbox" style="min-width:180px; padding:14px 16px;"><strong>Installed modules</strong><br><span style="font-size:22px;"><?php echo esc_html($installed); ?></span></div>
            <div class="postbox" style="min-width:180px; padding:14px 16px;"><strong>Active modules</strong><br><span style="font-size:22px;"><?php echo esc_html($active); ?></span></div>
        </div>
        <?php
    }

    public static function modules_page() {
        if (!current_user_can('manage_options')) { return; }
        $modules = self::module_statuses();
        ?>
        <div class="wrap handaistack-admin">
            <h1>HandAIStack Modules</h1>
            <p>This page detects the current HandAIMan modules. Future versions can add install and manual update controls here.</p>
            <?php self::render_modules_table($modules, false); ?>

            <h2>Future update layer</h2>
            <p>v0.1.0 intentionally does not install or update plugins. The planned next step is a manual update notice/check system, not automatic background updates.</p>
        </div>
        <?php
    }

    private static function render_modules_table($modules, $compact = false) {
        ?>
        <table class="widefat striped" style="max-width:1100px;">
            <thead>
                <tr>
                    <th>Module</th>
                    <th>Status</th>
                    <th>Version</th>
                    <?php if (!$compact): ?><th>Description</th><?php endif; ?>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($modules as $slug => $m): ?>
                    <tr>
                        <td><strong><?php echo esc_html($m['name']); ?></strong><br><code><?php echo esc_html($slug); ?></code></td>
                        <td>
                            <?php if (!empty($m['active'])): ?>
                                <span style="color:#008a20;font-weight:600;">Active</span>
                            <?php elseif (!empty($m['installed'])): ?>
                                <span style="color:#996800;font-weight:600;">Installed, inactive</span>
                            <?php else: ?>
                                <span style="color:#b32d2e;font-weight:600;">Not installed</span>
                            <?php endif; ?>
                            <?php if (!empty($m['shortcode'])): ?><br><code>[<?php echo esc_html($m['shortcode']); ?>]</code><?php endif; ?>
                        </td>
                        <td><?php echo $m['version'] ? esc_html($m['version']) : '&mdash;'; ?></td>
                        <?php if (!$compact): ?><td><?php echo esc_html($m['description']); ?></td><?php endif; ?>
                        <td>
                            <?php if (!empty($m['settings_url']) && !empty($m['active'])): ?>
                                <a class="button" href="<?php echo esc_url($m['settings_url']); ?>">Settings</a>
                            <?php elseif (!empty($m['installed'])): ?>
                                <a class="button" href="<?php echo esc_url(admin_url('plugins.php')); ?>">Plugins</a>
                            <?php else: ?>
                                <span class="description">Install later</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    public static function cta_settings_page() {
        if (!current_user_can('manage_options')) { return; }
        $opts = self::options();
        $cta = $opts['cta'];
        $individual = self::individual_auto_append_status();
        ?>
        <div class="wrap handaistack-admin">
            <h1>HandAIStack CTA Stack</h1>
            <p>Use <code>[handaistack_cta]</code>, <code>[handaiman_cta_stack]</code>, or <code>[ha_cta_stack]</code>.</p>

            <?php if ($individual): ?>
                <div class="notice notice-warning inline"><p><strong>Possible duplicate footer CTAs:</strong> individual auto-append is enabled in <?php echo esc_html(implode(', ', $individual)); ?>. If you enable HandAIStack auto-append, consider turning off those individual auto-append settings.</p></div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('handaistack_core_settings'); ?>
                <?php $all = self::options(); ?>
                <?php self::hidden_settings_fields($all, 'settings'); ?>
                <?php submit_button('Save CTA Stack Settings', 'primary', 'submit', false); ?>

                <h2>Display</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="handaistack-cta-heading">Heading</label></th>
                        <td><input id="handaistack-cta-heading" class="regular-text" type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[cta][heading]" value="<?php echo esc_attr($cta['heading']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="handaistack-cta-intro">Intro</label></th>
                        <td><textarea id="handaistack-cta-intro" class="large-text" rows="3" name="<?php echo esc_attr(self::OPTION_KEY); ?>[cta][intro]"><?php echo esc_textarea($cta['intro']); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row">Options</th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[cta][show_heading]" value="1" <?php checked($cta['show_heading'], 1); ?>> Show heading/intro above panels</label><br>
                            <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[cta][only_one_open]" value="1" <?php checked($cta['only_one_open'], 1); ?>> Keep only one top-level CTA panel open at a time</label>
                        </td>
                    </tr>
                </table>

                <h2>Auto-append</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Append CTA Stack</th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[cta][auto_append_posts]" value="1" <?php checked($cta['auto_append_posts'], 1); ?>> Auto-append to blog posts</label><br>
                            <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[cta][auto_append_podcasts]" value="1" <?php checked($cta['auto_append_podcasts'], 1); ?>> Auto-append to podcast episodes</label>
                            <p class="description">Leave this off while individual Follow, Support, and Contact plugins are auto-appending themselves.</p>
                        </td>
                    </tr>
                </table>

                <h2>Panels</h2>
                <table class="widefat striped" style="max-width:950px;">
                    <thead><tr><th>Show</th><th>Order</th><th>Panel</th><th>Collapsed label</th><th>Required shortcode</th></tr></thead>
                    <tbody>
                        <?php self::cta_panel_row('follow', 'Subscribe / Follow', 'handaiman_follow', $cta); ?>
                        <?php self::cta_panel_row('support', 'Support the Project', 'handaiman_support', $cta); ?>
                        <?php self::cta_panel_row('contact', 'Contact TheHandAIMan', 'handaiman_contact', $cta); ?>
                    </tbody>
                </table>

                <p style="margin-top:18px;">
                    <?php submit_button('Save CTA Stack Settings', 'primary', 'submit', false); ?>
                </p>
            </form>

            <h2>Preview</h2>
            <p class="description">This preview uses the saved settings. Save changes above to refresh the preview.</p>
            <div style="max-width:820px; background:#f6f7f7; border:1px solid #dcdcde; padding:16px; border-radius:8px;">
                <?php echo self::cta_shortcode(array('preview' => 'yes')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </div>
        <?php
    }

    private static function cta_panel_row($key, $label, $shortcode, $cta) {
        $show_key = 'show_' . $key;
        $order_key = $key . '_order';
        $summary_key = $key . '_summary';
        ?>
        <tr>
            <td><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[cta][<?php echo esc_attr($show_key); ?>]" value="1" <?php checked($cta[$show_key], 1); ?>></td>
            <td><input type="number" style="width:80px;" name="<?php echo esc_attr(self::OPTION_KEY); ?>[cta][<?php echo esc_attr($order_key); ?>]" value="<?php echo esc_attr($cta[$order_key]); ?>"></td>
            <td><strong><?php echo esc_html($label); ?></strong></td>
            <td><input class="regular-text" type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[cta][<?php echo esc_attr($summary_key); ?>]" value="<?php echo esc_attr($cta[$summary_key]); ?>"></td>
            <td>
                <code>[<?php echo esc_html($shortcode); ?>]</code>
                <?php if (!shortcode_exists($shortcode)): ?><br><span style="color:#b32d2e;">not available</span><?php endif; ?>
            </td>
        </tr>
        <?php
    }

    public static function settings_page() {
        if (!current_user_can('manage_options')) { return; }
        $opts = self::options();
        ?>
        <div class="wrap handaistack-admin">
            <h1>HandAIStack Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('handaistack_core_settings'); ?>
                <?php self::hidden_cta_fields($opts['cta']); ?>
                <?php submit_button('Save HandAIStack Settings', 'primary', 'submit', false); ?>

                <h2>General</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Module hints</th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[settings][show_module_hints]" value="1" <?php checked($opts['settings']['show_module_hints'], 1); ?>> Show module guidance and warnings in HandAIStack admin screens</label></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="handaistack-manifest-url">Future manifest URL</label></th>
                        <td>
                            <input id="handaistack-manifest-url" class="regular-text" type="url" name="<?php echo esc_attr(self::OPTION_KEY); ?>[settings][future_manifest_url]" value="<?php echo esc_attr($opts['settings']['future_manifest_url']); ?>" placeholder="Not used yet">
                            <p class="description">Reserved for the future manual update checker. v0.1.0 does not contact a manifest or install updates.</p>
                        </td>
                    </tr>
                </table>

                <p style="margin-top:18px;">
                    <?php submit_button('Save HandAIStack Settings', 'primary', 'submit', false); ?>
                </p>
            </form>
        </div>
        <?php
    }

    private static function hidden_cta_fields($cta) {
        foreach ($cta as $key => $value) {
            if (is_scalar($value)) {
                echo '<input type="hidden" name="' . esc_attr(self::OPTION_KEY) . '[cta][' . esc_attr($key) . ']" value="' . esc_attr($value) . '">' . "\n";
            }
        }
    }

    private static function hidden_settings_fields($opts, $section) {
        if ($section !== 'settings') { return; }
        if (!isset($opts['settings']) || !is_array($opts['settings'])) { return; }
        foreach ($opts['settings'] as $key => $value) {
            if (is_scalar($value)) {
                echo '<input type="hidden" name="' . esc_attr(self::OPTION_KEY) . '[settings][' . esc_attr($key) . ']" value="' . esc_attr($value) . '">' . "\n";
            }
        }
    }

    public static function maybe_auto_append_cta($content) {
        if (is_admin() || is_feed() || !is_singular()) { return $content; }
        if (has_shortcode($content, 'handaistack_cta') || has_shortcode($content, 'handaiman_cta_stack') || has_shortcode($content, 'ha_cta_stack')) { return $content; }

        $opts = self::options();
        $post_type = get_post_type();
        $enabled = false;
        if ($post_type === 'post' && !empty($opts['cta']['auto_append_posts'])) { $enabled = true; }
        if ($post_type === 'podcast' && !empty($opts['cta']['auto_append_podcasts'])) { $enabled = true; }
        if (!$enabled) { return $content; }

        return $content . "\n\n" . self::cta_shortcode(array());
    }

    public static function cta_shortcode($atts = array()) {
        $opts = self::options();
        $cta = $opts['cta'];
        $atts = shortcode_atts(array(
            'heading' => $cta['heading'],
            'intro' => $cta['intro'],
            'show_heading' => $cta['show_heading'] ? 'yes' : 'no',
            'follow' => $cta['show_follow'] ? 'yes' : 'no',
            'support' => $cta['show_support'] ? 'yes' : 'no',
            'contact' => $cta['show_contact'] ? 'yes' : 'no',
            'only_one_open' => $cta['only_one_open'] ? 'yes' : 'no',
            'preview' => 'no',
        ), $atts, 'handaistack_cta');

        $panels = array();
        if (self::truthy($atts['follow'])) {
            $panels[] = array(
                'key' => 'follow',
                'order' => intval($cta['follow_order']),
                'shortcode' => 'handaiman_follow',
                'summary' => $cta['follow_summary'],
                'fallback' => 'Follow & Subscribe plugin is not active.',
            );
        }
        if (self::truthy($atts['support'])) {
            $panels[] = array(
                'key' => 'support',
                'order' => intval($cta['support_order']),
                'shortcode' => 'handaiman_support',
                'summary' => $cta['support_summary'],
                'fallback' => 'Support My Work plugin is not active.',
            );
        }
        if (self::truthy($atts['contact'])) {
            $panels[] = array(
                'key' => 'contact',
                'order' => intval($cta['contact_order']),
                'shortcode' => 'handaiman_contact',
                'summary' => $cta['contact_summary'],
                'fallback' => 'Contact plugin is not active.',
            );
        }

        usort($panels, function($a, $b) {
            if ($a['order'] === $b['order']) { return strcmp($a['key'], $b['key']); }
            return $a['order'] < $b['order'] ? -1 : 1;
        });

        $pieces = array();
        foreach ($panels as $panel) {
            $content = '';
            if (shortcode_exists($panel['shortcode'])) {
                $content = do_shortcode('[' . $panel['shortcode'] . ' collapsed="yes" summary="' . esc_attr($panel['summary']) . '"]');
            } elseif (is_admin() || current_user_can('manage_options') || self::truthy($atts['preview'])) {
                $content = '<details class="handaistack-missing-panel"><summary>' . esc_html($panel['summary']) . '</summary><div style="padding:12px 0;color:#b32d2e;">' . esc_html($panel['fallback']) . '</div></details>';
            }
            if ($content !== '') {
                $pieces[] = '<div class="handaistack-cta-section handaistack-cta-section-' . esc_attr($panel['key']) . '">' . $content . '</div>';
            }
        }

        if (!$pieces) { return ''; }

        ob_start();
        ?>
        <div class="handaistack-cta" data-one-open="<?php echo self::truthy($atts['only_one_open']) ? '1' : '0'; ?>">
            <?php if (self::truthy($atts['show_heading'])): ?>
                <div class="handaistack-cta-heading-wrap">
                    <?php if (trim((string) $atts['heading']) !== ''): ?><h3 class="handaistack-cta-heading"><?php echo esc_html($atts['heading']); ?></h3><?php endif; ?>
                    <?php if (trim((string) $atts['intro']) !== ''): ?><p class="handaistack-cta-intro"><?php echo esc_html($atts['intro']); ?></p><?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="handaistack-cta-sections">
                <?php echo implode("\n", $pieces); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </div>
        <?php
        $html = ob_get_clean();
        return $html . self::inline_frontend_assets(self::truthy($atts['only_one_open']));
    }

    private static function truthy($value) {
        if (is_bool($value)) { return $value; }
        $value = strtolower(trim((string) $value));
        return in_array($value, array('1', 'yes', 'true', 'on'), true);
    }

    private static function inline_frontend_assets($include_script) {
        $out = '';
        if (!self::$style_printed) {
            self::$style_printed = true;
            $out .= '<style>
                .handaistack-cta{max-width:760px;margin:1.5em 0;box-sizing:border-box;}
                .handaistack-cta-heading-wrap{margin-bottom:0.9em;}
                .handaistack-cta-heading{margin:0 0 0.25em 0;}
                .handaistack-cta-intro{margin:0;opacity:0.86;}
                .handaistack-cta-section > details{max-width:760px;margin:0 0 0.9em 0;}
                .handaistack-cta-section > details:last-child{margin-bottom:0;}
                .handaistack-cta .handaistack-cta-section-follow details{border:1px solid #dcdcde!important;padding:16px!important;border-radius:8px!important;max-width:760px!important;margin:0 0 0.9em 0!important;background:#fff!important;box-sizing:border-box!important;display:block!important;}
                .handaistack-cta .handaistack-cta-section-follow details > summary{cursor:pointer!important;font-weight:600!important;font-size:1.05em!important;}
                .handaistack-cta .handaistack-cta-section-follow .ha-follow-details-inner{margin-top:14px!important;}
                .handaistack-cta-section > details.ha-contact-collapsible,
                .handaistack-cta-section > details.ha-support-collapsible,
                .handaistack-cta-section > details.ha-follow-details,
                .handaistack-missing-panel{box-sizing:border-box;}
                .handaistack-missing-panel{border:1px solid #dcdcde;border-radius:8px;background:#fff;padding:16px;box-sizing:border-box;}
                .handaistack-missing-panel > summary{cursor:pointer;font-weight:600;}
            </style>';
        }
        if ($include_script && !self::$script_printed) {
            self::$script_printed = true;
            $out .= '<script>(function(){
                if (window.handaistackCtaLoaded) return;
                window.handaistackCtaLoaded = true;
                document.addEventListener("toggle", function(e){
                    var details = e.target;
                    if (!details || details.tagName !== "DETAILS" || !details.open) return;
                    var stack = details.closest(".handaistack-cta");
                    if (!stack || stack.getAttribute("data-one-open") !== "1") return;
                    var section = details.closest(".handaistack-cta-section");
                    if (!section) return;
                    var isTopLevel = false;
                    for (var i = 0; i < section.children.length; i++) {
                        if (section.children[i] === details) { isTopLevel = true; break; }
                    }
                    if (!isTopLevel) return;
                    var allSections = stack.querySelectorAll(".handaistack-cta-section");
                    for (var s = 0; s < allSections.length; s++) {
                        var children = allSections[s].children;
                        for (var c = 0; c < children.length; c++) {
                            if (children[c].tagName === "DETAILS" && children[c] !== details) {
                                children[c].open = false;
                            }
                        }
                    }
                }, true);
            })();</script>';
        }
        return $out;
    }
}

HandAIStack_Core::init();

if (!function_exists('handaistack_core_loaded')) {
    function handaistack_core_loaded() { return true; }
}

if (!function_exists('handaistack_parent_slug')) {
    function handaistack_parent_slug() { return HandAIStack_Core::MENU_SLUG; }
}

if (!function_exists('handaistack_register_module')) {
    function handaistack_register_module($slug, $args = array()) {
        HandAIStack_Core::register_module($slug, $args);
    }
}
