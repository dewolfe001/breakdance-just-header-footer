<?php
/**
 * Plugin Name:       Breakdance Just Header / Footer Template
 * Plugin URI:        https://web321.co
 * Description:       Adds a "[Breakdance] Just Header / Footer" page template. Pages assigned to it render the Breakdance global header and footer but skip the active theme's sidebar, widget areas, and other surrounding chrome.
 * Version:           1.1.0
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Author:            dewolfe001 (shawn@shawndewolfe.com)
 * Author URI:        https://web321.co
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       web321-bdjhf
 */

namespace Web321\BreakdanceJustHF;

defined('ABSPATH') || exit;

const TEMPLATE_SLUG       = 'breakdance-just-header-footer.php';
const TEMPLATE_LABEL      = '[Breakdance] Just Header / Footer';
const BODY_CLASS          = 'breakdance-just-header-footer';

const DOCS_OPTION         = 'web321_bdjhf_docs_page_id';
const OPT_SUPPRESS_DOCS   = 'web321_bdjhf_suppress_docs';
const OPT_SUPPRESS_DONATE = 'web321_bdjhf_suppress_donate';

const SETTINGS_GROUP      = 'web321_bdjhf';
const SETTINGS_PAGE_SLUG  = 'web321-bdjhf-settings';
const DONATE_URL          = 'https://www.paypal.com/paypalme/web321co/10';

/* ------------------------------------------------------------------
 * Template registration (Classic + Gutenberg).
 * ------------------------------------------------------------------ */
add_filter('theme_page_templates', static function (array $templates): array {
    $templates[TEMPLATE_SLUG] = TEMPLATE_LABEL;
    return $templates;
});

/* Serve our template file when this template is selected. */
add_filter('template_include', static function (string $template): string {
    if (!is_singular()) {
        return $template;
    }
    if (get_page_template_slug(get_queried_object_id()) !== TEMPLATE_SLUG) {
        return $template;
    }
    $custom = __DIR__ . '/templates/' . TEMPLATE_SLUG;
    return file_exists($custom) ? $custom : $template;
});

/* Body class so CSS can target this layout. */
add_filter('body_class', static function (array $classes): array {
    if (is_singular() && get_page_template_slug(get_queried_object_id()) === TEMPLATE_SLUG) {
        $classes[] = BODY_CLASS;
    }
    return $classes;
});

/* ------------------------------------------------------------------
 * Plugins-screen action links + row meta (respects suppression flags).
 * ------------------------------------------------------------------ */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), static function (array $links): array {
    $settings_url = admin_url('options-general.php?page=' . SETTINGS_PAGE_SLUG);
    array_unshift(
        $links,
        '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'web321-bdjhf') . '</a>'
    );
    return $links;
});

add_filter('plugin_row_meta', static function (array $links, string $file): array {
    if ($file !== plugin_basename(__FILE__)) {
        return $links;
    }

    if (!Settings::is_docs_suppressed() && Activator::docs_page_exists()) {
        $links[] = '<a href="' . esc_url(Activator::get_docs_url()) . '">'
            . esc_html__('Docs', 'web321-bdjhf') . '</a>';
        $links[] = '<a href="' . esc_url(Activator::get_docs_edit_url()) . '">'
            . esc_html__('Edit Docs', 'web321-bdjhf') . '</a>';
    }

    if (!Settings::is_donate_suppressed()) {
        $links[] = '<a href="' . esc_url(DONATE_URL) . '" target="_blank" rel="noopener">'
            . esc_html__('Donate', 'web321-bdjhf') . '</a>';
    }

    return $links;
}, 10, 2);

/* ------------------------------------------------------------------
 * Activation: maybe create the docs page (respects the suppress flag).
 * ------------------------------------------------------------------ */
register_activation_hook(__FILE__, [Activator::class, 'maybe_create_docs_page']);

/* ------------------------------------------------------------------
 * Settings page registration.
 * ------------------------------------------------------------------ */
add_action('admin_menu', [Settings::class, 'register_menu']);
add_action('admin_init', [Settings::class, 'register_settings']);

/* ================================================================== */

final class Activator {

    /**
     * Activation entry point. Respects the "suppress docs" flag, so
     * activating with that flag already on will not create the page.
     */
    public static function maybe_create_docs_page(): void {
        if (Settings::is_docs_suppressed()) {
            return;
        }
        self::ensure_docs_page();
    }

    /**
     * Create the docs page if missing, or restore it from the trash.
     * Bypasses the suppress check — call this only from contexts where
     * the suppression decision has already been made.
     */
    public static function ensure_docs_page(): void {
        $page_id = (int) get_option(DOCS_OPTION);

        if ($page_id) {
            $status = get_post_status($page_id);

            if ($status && $status !== 'trash') {
                return; // Already exists in a non-trash state.
            }

            if ($status === 'trash') {
                // Restore the existing page so user edits to the docs
                // content are preserved across suppress / unsuppress.
                wp_untrash_post($page_id);
                wp_update_post([
                    'ID'          => $page_id,
                    'post_status' => 'publish',
                ]);
                return;
            }
            // Status === false → post deleted entirely; fall through.
        }

        $new_id = wp_insert_post([
            'post_title'     => 'Breakdance Just Header / Footer — Documentation',
            'post_name'      => 'breakdance-just-header-footer-docs',
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'post_author'    => get_current_user_id() ?: 1,
            'post_content'   => self::docs_html(),
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
            'meta_input'     => ['_managed_page' => 1],
        ]);

        if (!is_wp_error($new_id) && $new_id) {
            update_option(DOCS_OPTION, (int) $new_id);
        }
    }

    /**
     * Move the docs page to the trash if it is currently published.
     */
    public static function trash_docs_page(): void {
        $page_id = (int) get_option(DOCS_OPTION);
        if (!$page_id) {
            return;
        }
        $status = get_post_status($page_id);
        if ($status && $status !== 'trash') {
            wp_trash_post($page_id);
        }
    }

    public static function get_docs_url(): string {
        $id = (int) get_option(DOCS_OPTION);
        if (!$id) {
            return admin_url();
        }
        $url = get_permalink($id);
        return $url ?: admin_url();
    }

    public static function get_docs_edit_url(): string {
        $id = (int) get_option(DOCS_OPTION);
        if (!$id) {
            return admin_url();
        }
        $url = get_edit_post_link($id, 'admin');
        return $url ?: admin_url();
    }

    public static function docs_page_exists(): bool {
        $id = (int) get_option(DOCS_OPTION);
        if (!$id) {
            return false;
        }
        $status = get_post_status($id);
        return $status && $status !== 'trash';
    }

    private static function docs_html(): string {
        ob_start(); ?>
<h2>What this plugin does</h2>
<p>This <a href="https://web321.co/knowledgebase/wordpress/" target="_blank" rel="noopener">WordPress</a> plugin registers a new page template called <code>[Breakdance] Just Header / Footer</code>. Pages assigned to it render with the Breakdance global header and global footer but without the active theme's sidebar, widget areas, or other surrounding chrome.</p>
<p>It is a middle ground between the theme's default page template (which keeps theme sidebars and widgets) and Breakdance's built-in <code>[Breakdance] No Header / Footer</code> template (which strips everything, including the Breakdance globals).</p>

<h2>Setup</h2>
<ol>
  <li>Activate the plugin.</li>
  <li>Edit any Page in the block editor.</li>
  <li>In the right sidebar, open <em>Page → Template</em> and choose <strong>[Breakdance] Just Header / Footer</strong>.</li>
  <li>Update / publish the page and view it on the front end.</li>
</ol>

<h2>How it works</h2>
<p>Breakdance injects its global header on the <code>wp_body_open</code> hook and its global footer on the <code>wp_footer</code> hook. The plugin's template file calls both hooks but bypasses the theme's <code>header.php</code>, <code>sidebar.php</code>, and <code>footer.php</code>, so Breakdance's globals appear while theme widget areas do not.</p>

<h2>Settings</h2>
<p>Find the settings under <em>Settings → BD Just Header / Footer</em> in <a href="https://web321.co/knowledgebase/wordpress/" target="_blank" rel="noopener">WordPress</a> admin. Two toggles are available:</p>
<ul>
  <li><strong>Suppress documentation page.</strong> Trashes this docs page and stops the activator from recreating it. Disabling the toggle restores the page (your edits are preserved if the page is still in the trash).</li>
  <li><strong>Suppress donation link.</strong> Hides the PayPal "Donate" link on the Plugins list row meta and on the settings page sidebar.</li>
</ul>

<h2>Configuration reference</h2>
<table>
  <thead><tr><th>Item</th><th>Value</th></tr></thead>
  <tbody>
    <tr><td>Template label</td><td><code>[Breakdance] Just Header / Footer</code></td></tr>
    <tr><td>Template file</td><td><code>templates/breakdance-just-header-footer.php</code></td></tr>
    <tr><td>Body class added</td><td><code>breakdance-just-header-footer</code></td></tr>
    <tr><td>Docs page option key</td><td><code>web321_bdjhf_docs_page_id</code></td></tr>
    <tr><td>Suppress docs option key</td><td><code>web321_bdjhf_suppress_docs</code></td></tr>
    <tr><td>Suppress donate option key</td><td><code>web321_bdjhf_suppress_donate</code></td></tr>
  </tbody>
</table>

<h2>Troubleshooting</h2>
<p><strong>Header or footer not appearing.</strong> Open Breakdance → Headers (or Footers) and confirm the relevant Location rule or Condition matches the page. The template only renders what Breakdance is configured to display.</p>
<p><strong>Theme widgets still appearing.</strong> A few themes inject widget areas via <code>wp_head</code> or <code>wp_footer</code> callbacks rather than from their template files. If leftover chrome appears, identify the offending action with a <code>print_r($wp_filter['wp_footer'])</code> dump and <code>remove_action()</code> it inside a small theme-specific add-on.</p>
<p><strong>Layout looks unstyled.</strong> The theme's stylesheet is still enqueued, but if the theme depends on wrapper markup from its <code>header.php</code> or <code>footer.php</code>, you may need to add a small scoped CSS rule against <code>.breakdance-just-header-footer</code>.</p>

<h2>FAQ</h2>
<p><strong>Does this affect existing pages?</strong> No. Only pages explicitly assigned the new template are affected.</p>
<p><strong>What's the difference between this and Breakdance's built-in "No Header / Footer"?</strong> The built-in template strips both the Breakdance globals and the theme chrome. This template strips only the theme chrome, leaving Breakdance's globals intact.</p>
<p><strong>Will the docs page survive plugin updates?</strong> Yes. The docs page ID is stored in <code>wp_options</code> under <code>web321_bdjhf_docs_page_id</code> and the activator only recreates the page if it is missing or trashed.</p>
<p><strong>Does it work with Breakdance Zero Theme?</strong> Yes — in that case it behaves the same as Breakdance's default page rendering, because Zero Theme has no sidebars to suppress in the first place.</p>
<?php
        return (string) ob_get_clean();
    }
}

/* ================================================================== */

final class Settings {

    public static function is_docs_suppressed(): bool {
        return (bool) get_option(OPT_SUPPRESS_DOCS, false);
    }

    public static function is_donate_suppressed(): bool {
        return (bool) get_option(OPT_SUPPRESS_DONATE, false);
    }

    public static function register_menu(): void {
        add_options_page(
            __('Breakdance Just Header / Footer', 'web321-bdjhf'),
            __('BD Just Header / Footer', 'web321-bdjhf'),
            'manage_options',
            SETTINGS_PAGE_SLUG,
            [self::class, 'render_page']
        );
    }

    public static function register_settings(): void {
        register_setting(SETTINGS_GROUP, OPT_SUPPRESS_DOCS, [
            'type'              => 'boolean',
            'sanitize_callback' => [self::class, 'sanitize_suppress_docs'],
            'default'           => false,
            'show_in_rest'      => false,
        ]);

        register_setting(SETTINGS_GROUP, OPT_SUPPRESS_DONATE, [
            'type'              => 'boolean',
            'sanitize_callback' => [self::class, 'sanitize_boolean'],
            'default'           => false,
            'show_in_rest'      => false,
        ]);

        add_settings_section(
            'web321_bdjhf_display',
            __('Display Options', 'web321-bdjhf'),
            static function (): void {
                echo '<p>' . esc_html__('Toggle the documentation page and donation link off if you do not want them visible.', 'web321-bdjhf') . '</p>';
            },
            SETTINGS_PAGE_SLUG
        );

        add_settings_field(
            OPT_SUPPRESS_DOCS,
            __('Documentation page', 'web321-bdjhf'),
            [self::class, 'field_suppress_docs'],
            SETTINGS_PAGE_SLUG,
            'web321_bdjhf_display'
        );

        add_settings_field(
            OPT_SUPPRESS_DONATE,
            __('Donation link', 'web321-bdjhf'),
            [self::class, 'field_suppress_donate'],
            SETTINGS_PAGE_SLUG,
            'web321_bdjhf_display'
        );
    }

    public static function sanitize_boolean($value): bool {
        return (bool) rest_sanitize_boolean($value);
    }

    /**
     * Sanitize the suppress-docs flag, and trigger the side-effect
     * (trash or restore the docs page) when the value actually changes.
     */
    public static function sanitize_suppress_docs($value): bool {
        $new = self::sanitize_boolean($value);
        $old = (bool) get_option(OPT_SUPPRESS_DOCS, false);

        if ($new === $old) {
            return $new;
        }

        if ($new) {
            // Suppression turned ON → trash the docs page.
            Activator::trash_docs_page();
        } else {
            // Suppression turned OFF → restore or recreate the docs page.
            // Call ensure_docs_page directly so the about-to-be-saved
            // option value (still false in the DB at this moment) does
            // not cause maybe_create_docs_page() to short-circuit.
            Activator::ensure_docs_page();
        }

        return $new;
    }

    public static function field_suppress_docs(): void {
        $value = self::is_docs_suppressed();
        ?>
        <input type="hidden" name="<?php echo esc_attr(OPT_SUPPRESS_DOCS); ?>" value="0">
        <label>
            <input type="checkbox" name="<?php echo esc_attr(OPT_SUPPRESS_DOCS); ?>" value="1" <?php checked($value); ?>>
            <?php esc_html_e('Suppress documentation page', 'web321-bdjhf'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('When enabled, the public documentation page is moved to the trash and is not recreated on activation. Disabling restores the page from the trash if it is still there.', 'web321-bdjhf'); ?>
        </p>
        <?php
    }

    public static function field_suppress_donate(): void {
        $value = self::is_donate_suppressed();
        ?>
        <input type="hidden" name="<?php echo esc_attr(OPT_SUPPRESS_DONATE); ?>" value="0">
        <label>
            <input type="checkbox" name="<?php echo esc_attr(OPT_SUPPRESS_DONATE); ?>" value="1" <?php checked($value); ?>>
            <?php esc_html_e('Suppress donation link', 'web321-bdjhf'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('Hides the PayPal donation link on the Plugins list row meta and on this settings page sidebar.', 'web321-bdjhf'); ?>
        </p>
        <?php
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $docs_suppressed   = self::is_docs_suppressed();
        $donate_suppressed = self::is_donate_suppressed();
        $docs_exists       = Activator::docs_page_exists();
        ?>
        <div class="wrap bdjhf-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="bdjhf-layout">
                <div class="bdjhf-main">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields(SETTINGS_GROUP);
                        do_settings_sections(SETTINGS_PAGE_SLUG);
                        submit_button();
                        ?>
                    </form>
                </div>

                <aside class="bdjhf-sidebar">
                    <?php if (!$docs_suppressed && $docs_exists): ?>
                        <div class="card">
                            <h2><?php esc_html_e('Documentation', 'web321-bdjhf'); ?></h2>
                            <p><?php esc_html_e('The plugin auto-generates a public docs page describing the template and settings.', 'web321-bdjhf'); ?></p>
                            <p>
                                <a class="button button-primary" href="<?php echo esc_url(Activator::get_docs_url()); ?>" target="_blank" rel="noopener">
                                    <?php esc_html_e('View Docs Page', 'web321-bdjhf'); ?>
                                </a>
                            </p>
                            <p>
                                <a class="button" href="<?php echo esc_url(Activator::get_docs_edit_url()); ?>">
                                    <?php esc_html_e('Edit Docs Page', 'web321-bdjhf'); ?>
                                </a>
                            </p>
                        </div>
                    <?php elseif ($docs_suppressed): ?>
                        <div class="card">
                            <h2><?php esc_html_e('Documentation', 'web321-bdjhf'); ?></h2>
                            <p><em><?php esc_html_e('The documentation page is currently suppressed.', 'web321-bdjhf'); ?></em></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!$donate_suppressed): ?>
                        <div class="card">
                            <h2><?php esc_html_e('Support this plugin', 'web321-bdjhf'); ?></h2>
                            <p><?php esc_html_e('If this plugin saved you time, consider sending a small donation.', 'web321-bdjhf'); ?></p>
                            <p>
                                <a class="button" href="<?php echo esc_url(DONATE_URL); ?>" target="_blank" rel="noopener">
                                    <?php esc_html_e('Donate via PayPal', 'web321-bdjhf'); ?>
                                </a>
                            </p>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <h2><?php esc_html_e('Built by Web321', 'web321-bdjhf'); ?></h2>
                        <p>
                            Shawn DeWolfe<br>
                            Web321 Marketing Ltd.<br>
                            Saanichton, BC<br>
                            <a href="https://web321.co" target="_blank" rel="noopener">web321.co</a><br>
                            <a href="mailto:shawn@web321.co">shawn@web321.co</a>
                        </p>
                    </div>
                </aside>
            </div>
        </div>
        <style>
            .bdjhf-wrap .bdjhf-layout { display: flex; gap: 20px; align-items: flex-start; margin-top: 16px; }
            .bdjhf-wrap .bdjhf-main { flex: 1 1 auto; min-width: 0; background: #fff; padding: 12px 20px; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
            .bdjhf-wrap .bdjhf-sidebar { flex: 0 0 280px; }
            .bdjhf-wrap .bdjhf-sidebar .card { background: #fff; padding: 12px 16px; margin: 0 0 16px; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
            .bdjhf-wrap .bdjhf-sidebar .card h2 { margin-top: 0; font-size: 14px; }
            @media (max-width: 782px) {
                .bdjhf-wrap .bdjhf-layout { flex-direction: column; }
                .bdjhf-wrap .bdjhf-sidebar { width: 100%; flex-basis: auto; }
            }
        </style>
        <?php
    }
}
