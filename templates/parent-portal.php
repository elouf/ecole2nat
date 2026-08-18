<?php
if (!defined('ABSPATH')) {
    exit;
}

$parentPortalTitle = \Ecole2Nat\Support\Config::portalTitle();
$parentPortalLogoId = \Ecole2Nat\Support\Config::portalLogoId();
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('e2n-parent-app-page'); ?>>
<?php wp_body_open(); ?>
<header class="e2n-parent-app-head">
    <span class="e2n-parent-brand">
        <?php if ($parentPortalLogoId > 0) : ?>
            <?php echo wp_get_attachment_image($parentPortalLogoId, 'thumbnail', false, ['class' => 'e2n-parent-brand-image']); ?>
        <?php else : ?>
            <span aria-hidden="true">E2N</span>
        <?php endif; ?>
        <?php echo esc_html($parentPortalTitle); ?>
    </span>
</header>
<main id="main" class="e2n-parent-app" tabindex="-1">
    <?php
    while (have_posts()) {
        the_post();
        the_content();
    }
    ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
