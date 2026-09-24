<?php
/**
 * Title: Sponsor tiers
 * Slug: westshore/sponsors
 * Categories: westshore
 * Description: All three sponsor tiers, pulled from the Sponsors section of the admin. Add or remove a sponsor there; this updates itself.
 * Keywords: sponsors, partners, sponsorship
 * Viewport width: 1200
 *
 * @package Westshore
 */

?>
<!-- wp:group {"templateLock":"contentOnly","style":{"spacing":{"margin":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--40);margin-bottom:var(--wp--preset--spacing--50)">
	<!-- wp:heading {"textAlign":"center"} -->
	<h2 class="wp-block-heading has-text-align-center"><?php echo esc_html_x( 'Support the Shore!', 'sponsors pattern heading', 'westshore' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"align":"center"} -->
	<p class="has-text-align-center"><?php echo esc_html_x( 'We thank all of our sponsors and partners below for supporting our club, and rugby at every level. We encourage all members and friends of the club to visit and support our sponsors. Let them know you are a Westshore RFC member who uses their services.', 'sponsors pattern intro', 'westshore' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:shortcode -->
	[westshore_sponsors]
	<!-- /wp:shortcode -->
</div>
<!-- /wp:group -->
