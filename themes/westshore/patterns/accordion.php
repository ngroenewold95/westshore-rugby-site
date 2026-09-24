<?php
/**
 * Title: Accordion sections
 * Slug: westshore/accordion
 * Categories: westshore
 * Description: Collapsible sections, one per topic. For a long page that is really several short ones: team photos by decade, awards by name, a list of questions.
 * Keywords: accordion, details, collapse, faq, decades, archive
 * Viewport width: 1200
 *
 * Built on core/details, so it is HTML that works with every plugin removed
 * and needs no JavaScript. That matters here more than usual: this pattern
 * exists to carry the page merges, and a merged page whose sections stop
 * opening is worse than the seven pages it replaced.
 *
 * The merges are six team-photo pages into one and five award pages into one.
 * Concatenating them would produce a page a phone scrolls through for a
 * minute; collapsed sections give the same content one tap away instead.
 *
 * Leave the first section open. A page that opens as a stack of closed bars
 * looks broken, and a reader who cannot see what is inside one does not know
 * whether to tap it.
 *
 * @package Westshore
 */

?>
<!-- wp:group {"templateLock":"contentOnly","style":{"spacing":{"margin":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|20"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--40);margin-bottom:var(--wp--preset--spacing--50)">
	<!-- wp:details {"showContent":true,"className":"westshore-details"} -->
	<details class="wp-block-details westshore-details" open><summary><?php echo esc_html_x( 'First section', 'accordion summary', 'westshore' ); ?></summary><!-- wp:paragraph -->
	<p><?php echo esc_html_x( 'What is in this section. Leave this one open so the page does not read as a stack of closed bars.', 'accordion body', 'westshore' ); ?></p>
	<!-- /wp:paragraph --></details>
	<!-- /wp:details -->

	<!-- wp:details {"className":"westshore-details"} -->
	<details class="wp-block-details westshore-details"><summary><?php echo esc_html_x( 'Second section', 'accordion summary', 'westshore' ); ?></summary><!-- wp:paragraph -->
	<p><?php echo esc_html_x( 'What is in this section.', 'accordion body', 'westshore' ); ?></p>
	<!-- /wp:paragraph --></details>
	<!-- /wp:details -->

	<!-- wp:details {"className":"westshore-details"} -->
	<details class="wp-block-details westshore-details"><summary><?php echo esc_html_x( 'Third section', 'accordion summary', 'westshore' ); ?></summary><!-- wp:paragraph -->
	<p><?php echo esc_html_x( 'What is in this section.', 'accordion body', 'westshore' ); ?></p>
	<!-- /wp:paragraph --></details>
	<!-- /wp:details -->
</div>
<!-- /wp:group -->
