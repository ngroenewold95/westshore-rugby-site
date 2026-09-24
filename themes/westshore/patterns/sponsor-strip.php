<?php
/**
 * Title: Sponsor logo strip
 * Slug: westshore/sponsor-strip
 * Categories: westshore
 * Description: One run of sponsor logos, no tier headings. For the front page and anywhere the full sponsors page would be too much.
 * Keywords: sponsors, partners, logos
 * Viewport width: 1200
 *
 * @package Westshore
 */

$westshore_sponsors_page = get_page_by_path( 'sponsorship' );
$westshore_sponsors_page = $westshore_sponsors_page ? get_permalink( $westshore_sponsors_page ) : home_url( '/sponsorship/' );

?>
<!-- wp:group {"templateLock":"contentOnly","style":{"spacing":{"margin":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--50);margin-bottom:var(--wp--preset--spacing--50)">
	<!-- wp:heading {"textAlign":"center"} -->
	<h2 class="wp-block-heading has-text-align-center"><?php echo esc_html_x( 'Our sponsors', 'sponsor strip heading', 'westshore' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"align":"center"} -->
	<p class="has-text-align-center"><?php echo esc_html_x( 'These businesses back Westshore rugby. Please support them.', 'sponsor strip intro', 'westshore' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:shortcode -->
	[westshore_sponsors layout="strip"]
	<!-- /wp:shortcode -->

	<!-- wp:paragraph {"align":"center","className":"westshore-textlink","fontSize":"small"} -->
	<p class="has-text-align-center westshore-textlink has-small-font-size"><a href="<?php echo esc_url( $westshore_sponsors_page ); ?>"><?php echo esc_html_x( 'Sponsor the club', 'sponsor strip link', 'westshore' ); ?></a></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
