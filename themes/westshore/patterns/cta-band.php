<?php
/**
 * Title: Call to action band
 * Slug: westshore/cta-band
 * Categories: westshore
 * Description: A full-width band with one heading, one line and one button. Use it to send people to Registration from the bottom of a team page.
 * Keywords: cta, register, join, action, band
 * Viewport width: 1200
 *
 * One button. A band offering three choices is not a call to action, it is a
 * menu, and the page already has one of those.
 *
 * Registration is the second highest traffic page on the site and used to be
 * buried three levels down under "Admin". Every team page should end here.
 *
 * The button is red-dark rather than the club red. #ED2025 measures 4.35:1 on
 * white and is an accent; #C4161C measures 6.04:1 and is the shade to use when
 * a button has to be red rather than navy.
 *
 * @package Westshore
 */

?>
<!-- wp:group {"templateLock":"contentOnly","align":"full","className":"westshore-rule-top","backgroundColor":"blue-dark","textColor":"white","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|30","right":"var:preset|spacing|30"},"margin":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|20"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull westshore-rule-top has-white-color has-blue-dark-background-color has-text-color has-background" style="margin-top:var(--wp--preset--spacing--50);margin-bottom:var(--wp--preset--spacing--50);padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--30)">
	<!-- wp:heading {"level":2,"textColor":"white"} -->
	<h2 class="wp-block-heading has-white-color has-text-color"><?php echo esc_html_x( 'Come and play', 'cta band heading', 'westshore' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph -->
	<p><?php echo esc_html_x( 'New players are always welcome, at every age group. No kit needed for a first visit.', 'cta band body', 'westshore' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:buttons -->
	<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"red-dark","textColor":"white"} -->
	<div class="wp-block-button"><a class="wp-block-button__link has-white-color has-red-dark-background-color has-text-color has-background wp-element-button" href="/admin/registration/"><?php echo esc_html_x( 'Register', 'cta band button', 'westshore' ); ?></a></div>
	<!-- /wp:button --></div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
