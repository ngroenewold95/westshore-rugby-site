<?php
/**
 * Title: Page hero
 * Slug: westshore/hero
 * Categories: westshore
 * Description: A photo, a heading, one line and one button. Use it at the top of a page that wants to lead with an image.
 * Keywords: hero, banner, header, top
 * Viewport width: 1200
 *
 * One button, deliberately. A hero with three competing calls to action asks a
 * visitor to make a decision before they have read anything.
 *
 * @package Westshore
 */

?>
<!-- wp:cover {"dimRatio":60,"overlayColor":"navy","minHeight":360,"minHeightUnit":"px","templateLock":"contentOnly","align":"full","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-cover alignfull" style="margin-bottom:var(--wp--preset--spacing--50);min-height:360px"><span aria-hidden="true" class="wp-block-cover__background has-navy-background-color has-background-dim-60 has-background-dim"></span>
	<div class="wp-block-cover__inner-container"><!-- wp:heading {"textAlign":"center","level":1,"style":{"color":{"text":"#ffffff"}},"fontSize":"xx-large"} -->
	<h1 class="wp-block-heading has-text-align-center has-text-color has-xx-large-font-size" style="color:#ffffff"><?php echo esc_html_x( 'Rugby on the Westshore', 'hero heading', 'westshore' ); ?></h1>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"align":"center","style":{"color":{"text":"#ffffff"}}} -->
	<p class="has-text-align-center has-text-color" style="color:#ffffff"><?php echo esc_html_x( 'One line saying who this page is for. Keep it short: most people read this on a phone.', 'hero body', 'westshore' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
	<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"white","textColor":"navy"} -->
	<div class="wp-block-button"><a class="wp-block-button__link has-navy-color has-white-background-color has-text-color has-background wp-element-button" href="#"><?php echo esc_html_x( 'Register', 'hero button', 'westshore' ); ?></a></div>
	<!-- /wp:button --></div>
	<!-- /wp:buttons --></div>
</div>
<!-- /wp:cover -->
