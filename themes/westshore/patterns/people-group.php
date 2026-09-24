<?php
/**
 * Title: People from one group
 * Slug: westshore/people-group
 * Categories: westshore
 * Description: A list of people with their photo, role and contact details, taken from one group in the People section. Change the group name in the shortcode to whichever list you want.
 * Keywords: people, contacts, board, directors, coaches, staff
 * Viewport width: 1200
 *
 * The one place a volunteer has to type something, so the shortcode takes the
 * group name as it appears in the admin rather than its slug: group="Board of
 * Directors" works, and so does group="board". The pattern ships with the group
 * that exists on every version of this site, so inserting it and changing
 * nothing still renders something rather than a blank space.
 *
 * The paragraph above the shortcode is instructions for whoever is editing the
 * page. Delete it once the group is set; it is not meant to be published.
 *
 * @package Westshore
 */

?>
<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--40);margin-bottom:var(--wp--preset--spacing--40)">
	<!-- wp:heading -->
	<h2 class="wp-block-heading"><?php echo esc_html_x( 'Get in touch', 'people group pattern heading', 'westshore' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"fontSize":"small","textColor":"slate"} -->
	<p class="has-slate-color has-text-color has-small-font-size"><em><?php echo esc_html_x( 'Editing note, delete this line before publishing: change the group below to the list you want. The names are the ones under People then Groups in the admin, for example "Board of Directors", "U16 Boys" or "Registration contact".', 'people group pattern note', 'westshore' ); ?></em></p>
	<!-- /wp:paragraph -->

	<!-- wp:shortcode -->
	[westshore_people group="Board of Directors" heading="no"]
	<!-- /wp:shortcode -->
</div>
<!-- /wp:group -->
