<?php
/**
 * Καθαρή σελίδα της εφαρμογής, ανεξάρτητη από το θέμα του WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'sd-body' ); ?>>
<?php wp_body_open(); ?>
<header class="sd-header">
	<div class="sd-header-inner">
		<a class="sd-brand" href="<?php echo esc_url( SD_Install::app_url() ); ?>">
			<?php echo SD_Frontend::logo_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<span class="sd-brand-txt"><span class="sd-site"><?php bloginfo( 'name' ); ?></span><span class="sd-school"><?php echo esc_html( SD_Frontend::school_name() ); ?></span></span>
		</a>
	</div>
</header>
<main class="sd-main">
	<?php echo do_shortcode( '[sxolikes_draseis]' ); // phpcs:ignore WordPress.Security.EscapeOutput -- η έξοδος διαφεύγει μέσα στον κώδικα της εφαρμογής. ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
