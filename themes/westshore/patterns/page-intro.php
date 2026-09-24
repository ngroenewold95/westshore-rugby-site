<?php
/**
 * Title: Page intro
 * Slug: westshore/page-intro
 * Categories: westshore
 * Description: A heading and a lead paragraph. The plain start to an ordinary page.
 * Keywords: intro, heading, lead, text
 * Viewport width: 1200
 *
 * Deliberately the dullest pattern here. Most of the site's pages are a title
 * and some prose, and they should stay that way; giving them a decorated
 * layout they do not need is how a site drifts.
 *
 * @package Westshore
 */

?>
<!-- wp:group {"templateLock":"contentOnly","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|20"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="margin-bottom:var(--wp--preset--spacing--40)">
	<!-- wp:heading -->
	<h2 class="wp-block-heading"><?php echo esc_html_x( 'Section heading', 'page intro heading', 'westshore' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"fontSize":"large"} -->
	<p class="has-large-font-size"><?php echo esc_html_x( 'The one paragraph that says what this page is about. Everything else can come after it.', 'page intro lead', 'westshore' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:paragraph -->
	<p><?php echo esc_html_x( 'Ordinary body text goes here.', 'page intro body', 'westshore' ); ?></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
