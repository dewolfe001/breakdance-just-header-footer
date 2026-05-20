<?php
/**
 * Template file for "[Breakdance] Just Header / Footer".
 *
 * Loaded via the `template_include` filter from the parent plugin
 * (breakdance-just-header-footer.php). It deliberately does NOT use
 * get_header() / get_sidebar() / get_footer(), which is what suppresses
 * the theme's widget areas. wp_body_open() and wp_footer() are still
 * called, so Breakdance's global header and footer render normally.
 *
 * @package Web321\BreakdanceJustHF
 */

defined('ABSPATH') || exit;
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php
    /*
     * Breakdance hooks its global header rendering onto wp_body_open.
     * Calling it here is what makes the Breakdance header appear without
     * dragging in the active theme's header.php (and any sidebars it
     * normally includes).
     */
    wp_body_open();
?>

<main id="primary" class="site-main bdjhf-content" role="main">
<?php
while (have_posts()) {
    the_post();
    the_content();
}
?>
</main>

<?php
    /*
     * Breakdance hooks its global footer rendering onto wp_footer at an
     * early priority. Calling it here triggers the global footer and the
     * standard wp_footer assets without loading the theme's footer.php.
     */
    wp_footer();
?>
</body>
</html>

