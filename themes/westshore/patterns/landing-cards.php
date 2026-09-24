<?php
/**
 * Title: Link card grid
 * Slug: westshore/landing-cards
 * Categories: westshore
 * Description: A grid of cards linking to other pages. Use it on any page whose job is to point at the pages under it: teams, history, awards, club documents.
 * Keywords: cards, grid, links, landing, teams, index
 * Viewport width: 1200
 *
 * The workhorse. Five of the seven page layouts being rebuilt are this and
 * nothing else: a grid of links to child pages.
 *
 * Each card carries a real heading rather than a graphic with the label baked
 * into it. The pages this replaces were nine linked images with empty alt
 * text, which meant a screen reader got nothing at all from one of the club's
 * highest traffic pages, and a volunteer could not rename a card without
 * opening an image editor.
 *
 * @package Westshore
 */

?>
<!-- wp:group {"templateLock":"contentOnly","style":{"spacing":{"margin":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--40);margin-bottom:var(--wp--preset--spacing--50)">
	<!-- wp:heading -->
	<h2 class="wp-block-heading"><?php echo esc_html_x( 'Where to next', 'link card grid heading', 'westshore' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:columns {"className":"westshore-cards","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|30","left":"var:preset|spacing|30"}}}} -->
	<div class="wp-block-columns westshore-cards">
		<!-- wp:column {"className":"westshore-card westshore-card--link"} -->
		<div class="wp-block-column westshore-card westshore-card--link"><!-- wp:image {"sizeSlug":"large","linkDestination":"custom","className":"westshore-card__image"} -->
		<figure class="wp-block-image size-large westshore-card__image"><img alt=""/></figure>
		<!-- /wp:image -->

		<!-- wp:heading {"level":3,"fontSize":"large"} -->
		<h3 class="wp-block-heading has-large-font-size"><?php echo esc_html_x( 'Senior Mens', 'link card title', 'westshore' ); ?></h3>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"fontSize":"small"} -->
		<p class="has-small-font-size"><?php echo esc_html_x( 'One line on what this is. Replace it, or delete it if the heading says enough.', 'link card body', 'westshore' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"className":"westshore-card__link","fontSize":"small"} -->
		<p class="westshore-card__link has-small-font-size"><a href="#"><?php echo esc_html_x( 'Read more', 'link card link', 'westshore' ); ?></a></p>
		<!-- /wp:paragraph --></div>
		<!-- /wp:column -->

		<!-- wp:column {"className":"westshore-card westshore-card--link"} -->
		<div class="wp-block-column westshore-card westshore-card--link"><!-- wp:image {"sizeSlug":"large","linkDestination":"custom","className":"westshore-card__image"} -->
		<figure class="wp-block-image size-large westshore-card__image"><img alt=""/></figure>
		<!-- /wp:image -->

		<!-- wp:heading {"level":3,"fontSize":"large"} -->
		<h3 class="wp-block-heading has-large-font-size"><?php echo esc_html_x( 'Senior Womens', 'link card title', 'westshore' ); ?></h3>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"fontSize":"small"} -->
		<p class="has-small-font-size"><?php echo esc_html_x( 'One line on what this is. Replace it, or delete it if the heading says enough.', 'link card body', 'westshore' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"className":"westshore-card__link","fontSize":"small"} -->
		<p class="westshore-card__link has-small-font-size"><a href="#"><?php echo esc_html_x( 'Read more', 'link card link', 'westshore' ); ?></a></p>
		<!-- /wp:paragraph --></div>
		<!-- /wp:column -->

		<!-- wp:column {"className":"westshore-card westshore-card--link"} -->
		<div class="wp-block-column westshore-card westshore-card--link"><!-- wp:image {"sizeSlug":"large","linkDestination":"custom","className":"westshore-card__image"} -->
		<figure class="wp-block-image size-large westshore-card__image"><img alt=""/></figure>
		<!-- /wp:image -->

		<!-- wp:heading {"level":3,"fontSize":"large"} -->
		<h3 class="wp-block-heading has-large-font-size"><?php echo esc_html_x( 'Juniors', 'link card title', 'westshore' ); ?></h3>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"fontSize":"small"} -->
		<p class="has-small-font-size"><?php echo esc_html_x( 'One line on what this is. Replace it, or delete it if the heading says enough.', 'link card body', 'westshore' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"className":"westshore-card__link","fontSize":"small"} -->
		<p class="westshore-card__link has-small-font-size"><a href="#"><?php echo esc_html_x( 'Read more', 'link card link', 'westshore' ); ?></a></p>
		<!-- /wp:paragraph --></div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
