<?php
/**
 * Title: Coaches, every team
 * Slug: westshore/coaches
 * Categories: westshore
 * Description: Every team's coaches, pulled from the People section of the admin. Add a coach there and this updates itself. Nothing to edit here.
 * Keywords: coaches, coaching, staff, teams
 * Viewport width: 1200
 *
 * There is nothing to configure, which is the point. A team appears here as
 * soon as its group is ticked "show on the Coaches page" in the admin, so a new
 * side needs no change to this pattern and no change to any page.
 *
 * @package Westshore
 */

?>
<!-- wp:group {"templateLock":"contentOnly","style":{"spacing":{"margin":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--40);margin-bottom:var(--wp--preset--spacing--40)">
	<!-- wp:heading -->
	<h2 class="wp-block-heading"><?php echo esc_html_x( 'Coaches', 'coaches pattern heading', 'westshore' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:shortcode -->
	[westshore_coaches]
	<!-- /wp:shortcode -->
</div>
<!-- /wp:group -->
