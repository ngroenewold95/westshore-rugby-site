<?php
/**
 * Title: Image and text
 * Slug: westshore/image-text
 * Categories: westshore
 * Description: A photograph beside a heading and a paragraph. Use two or three down a page, alternating which side the image sits on.
 * Keywords: image, text, photo, side, alternating, story
 * Viewport width: 1200
 *
 * The section rhythm needs something between a card grid and a full-bleed
 * band, and this is it: one photograph carrying one idea.
 *
 * Media and Text rather than two columns, because it stacks image-first on a
 * phone on its own and keeps the two halves vertically centred on a wide
 * screen without either being given a height.
 *
 * The club's photography is posed squads, which read badly small and badly
 * cropped. At this size they work: big enough to see faces, not so wide that
 * a 25-person line-up becomes a smear.
 *
 * @package Westshore
 */

?>
<!-- wp:media-text {"mediaType":"image","mediaWidth":48,"templateLock":"contentOnly","style":{"spacing":{"margin":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}}} -->
<div class="wp-block-media-text is-stacked-on-mobile" style="grid-template-columns:48% auto;margin-top:var(--wp--preset--spacing--50);margin-bottom:var(--wp--preset--spacing--50)"><figure class="wp-block-media-text__media"></figure><div class="wp-block-media-text__content"><!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php echo esc_html_x( 'A heading for this section', 'image and text heading', 'westshore' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php echo esc_html_x( 'Two or three sentences. Say the one thing this section is for and stop, because the pages people actually read on this site are about 150 words long.', 'image and text body', 'westshore' ); ?></p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:media-text -->
