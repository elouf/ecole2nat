<?php
if (!defined('ABSPATH')) {
    exit;
}

$parentPortalTitle = \Ecole2Nat\Support\Config::portalTitle();
$parentPortalLogoId = \Ecole2Nat\Support\Config::portalLogoId();
$parentSwimmer = null;
$isParentPreview = isset($_GET['e2n_parent_preview']) || isset($_GET['e2n_coach_preview']);
$isLogoutRequest = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['e2n_parent_action'] ?? '') === 'logout';
if (!$isParentPreview && !$isLogoutRequest) {
    $parentAccess = new \Ecole2Nat\ParentPortal\ParentAccessService();
    $parentSwimmerId = $parentAccess->authenticatedSwimmerId();
    if ($parentSwimmerId > 0) {
        $parentReport = $parentAccess->report($parentSwimmerId);
        $parentSwimmer = is_array($parentReport) ? ($parentReport['swimmer'] ?? null) : null;
    }
}
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
    <?php if (is_array($parentSwimmer)) :
        $parentSwimmerName = trim((string) $parentSwimmer['first_name'] . ' ' . (string) $parentSwimmer['last_name']);
        $parentInitial = mb_strtoupper(mb_substr((string) $parentSwimmer['first_name'], 0, 1));
        ?>
        <details class="e2n-parent-user-menu">
            <summary aria-label="<?php esc_attr_e('Menu du nageur', 'ecole2nat'); ?>"><span aria-hidden="true"><?php echo esc_html($parentInitial); ?></span></summary>
            <div>
                <strong><?php echo esc_html($parentSwimmerName); ?></strong>
                <form method="post">
                    <?php wp_nonce_field('e2n_parent_logout'); ?>
                    <input type="hidden" name="e2n_parent_action" value="logout">
                    <button type="submit"><?php esc_html_e('Déconnexion', 'ecole2nat'); ?></button>
                </form>
            </div>
        </details>
    <?php endif; ?>
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
