<?php
/**
 * Title: Facts band
 * Slug: westshore/facts-band
 * Categories: westshore
 * Description: A full-width navy band carrying the practical answers: when training is, where the ground is, how to register. For the front page and the top of a team page.
 * Keywords: training, times, ground, where, when, register, facts
 * Viewport width: 1200
 *
 * The answer to the question the traffic says people arrive with.
 *
 * Senior Mens and Senior Womens take about 1,500 views a year between them and
 * neither page says when the team trains. Juniors and Minis both publish theirs
 * to the quarter hour. The pages that carry the traffic are 107 to 226 words
 * long, so people are not reading them: they are checking a time and a ground.
 *
 * This is the full-width club-level version. The lighter constrained panel for
 * inside a team page is the Team facts pattern, which carries a contact as well.
 * Two patterns rather than one because a band spanning the page and a panel
 * sitting in a column want different weights, and a volunteer choosing between
 * them is choosing where it goes, not what it says.
 *
 * @package Westshore
 */

?>
<!-- wp:group {"templateLock":"contentOnly","align":"full","className":"westshore-facts-band","backgroundColor":"navy","textColor":"white","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|30","right":"var:preset|spacing|30"},"margin":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull westshore-facts-band has-white-color has-navy-background-color has-text-color has-background" style="margin-top:var(--wp--preset--spacing--50);margin-bottom:var(--wp--preset--spacing--50);padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--30)">
	<!-- wp:heading {"level":2,"textColor":"white","fontSize":"large"} -->
	<h2 class="wp-block-heading has-white-color has-text-color has-large-font-size"><?php echo esc_html_x( 'When and where', 'facts band heading', 'westshore' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:group {"className":"westshore-facts","style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"default"}} -->
	<div class="wp-block-group westshore-facts"><!-- wp:paragraph -->
	<p><strong><?php echo esc_html_x( 'Seniors', 'facts band label', 'westshore' ); ?></strong><?php echo esc_html_x( 'Tuesdays and Thursdays, 6:30pm', 'facts band value', 'westshore' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:paragraph -->
	<p><strong><?php echo esc_html_x( 'Juniors', 'facts band label', 'westshore' ); ?></strong><?php echo esc_html_x( 'Wednesdays, 5:45pm', 'facts band value', 'westshore' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:paragraph -->
	<p><strong><?php echo esc_html_x( 'Minis', 'facts band label', 'westshore' ); ?></strong><?php echo esc_html_x( 'Saturdays, 9:30am', 'facts band value', 'westshore' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:paragraph -->
	<p><strong><?php echo esc_html_x( 'Ground', 'facts band label', 'westshore' ); ?></strong><?php echo esc_html_x( 'Juan de Fuca Rugby Field, Colwood', 'facts band value', 'westshore' ); ?></p>
	<!-- /wp:paragraph --></div>
	<!-- /wp:group -->

	<!-- wp:buttons -->
	<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"red-dark","textColor":"white"} -->
	<div class="wp-block-button"><a class="wp-block-button__link has-white-color has-red-dark-background-color has-text-color has-background wp-element-button" href="/admin/registration/"><?php echo esc_html_x( 'Register for 2026/27', 'facts band button', 'westshore' ); ?></a></div>
	<!-- /wp:button --></div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
