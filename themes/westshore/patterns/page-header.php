<?php
/**
 * Title: Page header, no photo
 * Slug: westshore/page-header
 * Categories: westshore
 * Description: A navy header band with a heading and one line. For a page with no photograph good enough to lead with.
 * Keywords: header, hero, banner, title, top
 * Viewport width: 1200
 *
 * The alternative to the Page hero, and it exists because most pages do not
 * have a photograph worth a full-bleed band.
 *
 * The media audit found one professional action photograph in a library of
 * 1,155 images. Everything else is a posed squad, which crops badly to a hero
 * band and is unreadable at 375px once 25 faces are shrunk into it. Juniors and
 * Minis have no photography at all, and those are two of the pages parents
 * arrive on.
 *
 * Forcing a hero onto a bad photograph looks worse than not having one, so a
 * page without a good image gets colour and type instead.
 *
 * @package Westshore
 */

?>
<!-- wp:group {"templateLock":"contentOnly","align":"full","className":"westshore-page-header","backgroundColor":"navy","textColor":"white","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|30","right":"var:preset|spacing|30"},"margin":{"bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|20"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull westshore-page-header has-white-color has-navy-background-color has-text-color has-background" style="margin-bottom:var(--wp--preset--spacing--50);padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--30)">
	<!-- wp:paragraph {"className":"westshore-eyebrow","fontSize":"small"} -->
	<p class="westshore-eyebrow has-small-font-size"><?php echo esc_html_x( 'Westshore Rugby Football Club', 'page header eyebrow', 'westshore' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:heading {"level":1,"textColor":"white","fontSize":"xx-large"} -->
	<h1 class="wp-block-heading has-white-color has-text-color has-xx-large-font-size"><?php echo esc_html_x( 'Page title', 'page header heading', 'westshore' ); ?></h1>
	<!-- /wp:heading -->

	<!-- wp:paragraph -->
	<p><?php echo esc_html_x( 'One line saying who this page is for. Keep it short: most people read this on a phone.', 'page header body', 'westshore' ); ?></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
