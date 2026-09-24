<?php
/**
 * Title: Team facts
 * Slug: westshore/team-facts
 * Categories: westshore
 * Description: Training times, where, who to ask, and a Register button. The four things someone looking up a team actually wants.
 * Keywords: team, training, times, contact, practice, register
 * Viewport width: 1200
 *
 * Built from what the traffic says. Senior Mens is 142 words, Minis 130,
 * Senior Womens 107, and between them they take a quarter of the site's
 * visits. Nobody is reading those pages; they are checking when training is
 * and where to turn up.
 *
 * Keep the answers here and the prose underneath.
 *
 * @package Westshore
 */

?>
<!-- wp:group {"templateLock":"contentOnly","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|30","right":"var:preset|spacing|30"},"margin":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|30"},"border":{"radius":"6px"}},"backgroundColor":"blue-light","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-blue-light-background-color has-background" style="border-radius:6px;margin-top:var(--wp--preset--spacing--40);margin-bottom:var(--wp--preset--spacing--40);padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--30)">
	<!-- wp:heading {"level":2,"fontSize":"large"} -->
	<h2 class="wp-block-heading has-large-font-size"><?php echo esc_html_x( 'Training and games', 'team facts heading', 'westshore' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"default"}} -->
	<div class="wp-block-group"><!-- wp:paragraph -->
	<p><strong><?php echo esc_html_x( 'Training', 'team facts label', 'westshore' ); ?></strong><br><?php echo esc_html_x( 'Tuesdays and Thursdays, 6:30pm', 'team facts value', 'westshore' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:paragraph -->
	<p><strong><?php echo esc_html_x( 'Where', 'team facts label', 'westshore' ); ?></strong><br><?php echo esc_html_x( 'Juan de Fuca Rugby Field, Colwood', 'team facts value', 'westshore' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:paragraph -->
	<p><strong><?php echo esc_html_x( 'Who to ask', 'team facts label', 'westshore' ); ?></strong><br><?php echo esc_html_x( 'Name, and how to reach them', 'team facts value', 'westshore' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:paragraph {"fontSize":"small"} -->
	<p class="has-small-font-size"><?php echo esc_html_x( 'New players are always welcome. No kit needed for a first visit.', 'team facts note', 'westshore' ); ?></p>
	<!-- /wp:paragraph --></div>
	<!-- /wp:group -->

	<!-- wp:buttons -->
	<div class="wp-block-buttons"><!-- wp:button -->
	<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/admin/registration/"><?php echo esc_html_x( 'Register', 'team facts button', 'westshore' ); ?></a></div>
	<!-- /wp:button --></div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
