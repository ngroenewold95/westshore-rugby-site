<?php
/**
 * Title: Instagram feed
 * Slug: westshore/instagram-feed
 * Categories: westshore
 * Description: The club's Instagram posts, pulled in automatically, under the account's own header. Nothing to update here: post to Instagram and the page follows.
 * Keywords: instagram, social, news, photos
 * Viewport width: 1200
 *
 * @package Westshore
 */

?>
<!-- wp:group {"templateLock":"contentOnly","style":{"spacing":{"margin":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--50);margin-bottom:var(--wp--preset--spacing--50)">
	<!-- wp:shortcode -->
	[westshore_instagram]
	<!-- /wp:shortcode -->

	<!-- wp:buttons -->
	<div class="wp-block-buttons"><!-- wp:button -->
	<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://www.instagram.com/westshorerfc/" target="_blank" rel="noreferrer noopener"><?php echo esc_html_x( 'Follow us on Instagram', 'instagram pattern button', 'westshore' ); ?></a></div>
	<!-- /wp:button --></div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
