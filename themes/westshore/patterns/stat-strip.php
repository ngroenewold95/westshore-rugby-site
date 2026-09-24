<?php
/**
 * Title: Honours strip
 * Slug: westshore/stat-strip
 * Categories: westshore
 * Description: A row of figures: championships, years running, players sent on. For the top of a team page, where the honours are currently buried in a paragraph.
 * Keywords: stats, honours, championships, record, numbers
 * Viewport width: 1200
 *
 * The senior pages carry their honours as prose, which is how the Valkyries
 * page came to say the team has won the BC Premier Championship seven times
 * and then list six years. A figure in a box is checkable at a glance; the
 * same figure buried mid-sentence is not, and nobody noticed for years.
 *
 * Three or four figures. Six is a table, and a table is a different pattern.
 *
 * @package Westshore
 */

?>
<!-- wp:group {"templateLock":"contentOnly","style":{"spacing":{"margin":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|20"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--40);margin-bottom:var(--wp--preset--spacing--40)">
	<!-- wp:columns {"className":"westshore-stats","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|20","left":"var:preset|spacing|20"}}}} -->
	<div class="wp-block-columns westshore-stats">
		<!-- wp:column {"className":"westshore-stat"} -->
		<div class="wp-block-column westshore-stat"><!-- wp:paragraph {"className":"westshore-stat__figure"} -->
		<p class="westshore-stat__figure"><?php echo esc_html_x( '7', 'honours figure', 'westshore' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"className":"westshore-stat__label","fontSize":"small"} -->
		<p class="westshore-stat__label has-small-font-size"><?php echo esc_html_x( 'BC Premier titles', 'honours label', 'westshore' ); ?></p>
		<!-- /wp:paragraph --></div>
		<!-- /wp:column -->

		<!-- wp:column {"className":"westshore-stat"} -->
		<div class="wp-block-column westshore-stat"><!-- wp:paragraph {"className":"westshore-stat__figure"} -->
		<p class="westshore-stat__figure"><?php echo esc_html_x( '13', 'honours figure', 'westshore' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"className":"westshore-stat__label","fontSize":"small"} -->
		<p class="westshore-stat__label has-small-font-size"><?php echo esc_html_x( 'Final appearances', 'honours label', 'westshore' ); ?></p>
		<!-- /wp:paragraph --></div>
		<!-- /wp:column -->

		<!-- wp:column {"className":"westshore-stat"} -->
		<div class="wp-block-column westshore-stat"><!-- wp:paragraph {"className":"westshore-stat__figure"} -->
		<p class="westshore-stat__figure"><?php echo esc_html_x( '1968', 'honours figure', 'westshore' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"className":"westshore-stat__label","fontSize":"small"} -->
		<p class="westshore-stat__label has-small-font-size"><?php echo esc_html_x( 'Playing since', 'honours label', 'westshore' ); ?></p>
		<!-- /wp:paragraph --></div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
