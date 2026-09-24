<?php
/**
 * Title: Registration call to action
 * Slug: westshore/registration-cta
 * Categories: westshore
 * Description: The navy registration band with the Play HQ button, the prices link and the registrar. The same on every team page.
 * Keywords: registration, register, play hq, cta, band
 * Viewport width: 1200
 *
 * One copy of the band, referenced from the four team pages (Senior Mens,
 * Senior Womens, Juniors, Minis) as a pattern rather than pasted into each.
 * Before this there were four copies with three different headings, two
 * different body lines and two different ideas of which contact detail to
 * show, and none of the differences was a decision anybody had made.
 *
 * A pattern reference takes no attributes, which is the point: one wording, on
 * every page, changed in one place. The registrar shows both an email and a
 * phone number here. Which of the two a page showed used to depend on which
 * page it was, and that was history rather than design.
 *
 * The prices link is by page ID. Page IDs agree between staging and production
 * because staging is a clone; attachment IDs do not, which is why the other
 * pattern with an image in it looks its image up by slug.
 *
 * @package Westshore
 */

?>
<!-- wp:group {"templateLock":"contentOnly","align":"full","className":"westshore-rule-top","backgroundColor":"blue-dark","textColor":"white","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|30","right":"var:preset|spacing|30"},"margin":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|20"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull westshore-rule-top has-white-color has-blue-dark-background-color has-text-color has-background" style="margin-top:var(--wp--preset--spacing--50);margin-bottom:var(--wp--preset--spacing--50);padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--30)">
	<!-- wp:heading {"level":2,"textColor":"white"} -->
	<h2 class="wp-block-heading has-white-color has-text-color"><?php echo esc_html_x( 'Ready to register?', 'registration cta heading', 'westshore' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph -->
	<p><?php echo esc_html_x( 'Every player in BC registers through Play HQ. New players are always welcome.', 'registration cta body', 'westshore' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:buttons -->
	<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"red-dark","textColor":"white"} -->
	<div class="wp-block-button"><a class="wp-block-button__link has-white-color has-red-dark-background-color has-text-color has-background wp-element-button" href="[westshore_register_url]" target="_blank" rel="noreferrer noopener"><?php echo esc_html_x( 'Register on Play HQ', 'registration cta button', 'westshore' ); ?></a></div>
	<!-- /wp:button -->

	<!-- wp:button {"className":"is-style-outline","textColor":"white"} -->
	<div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-white-color has-text-color wp-element-button" href="<?php echo esc_url( get_permalink( 1110 ) ); ?>"><?php echo esc_html_x( 'Prices and refund policy', 'registration cta button', 'westshore' ); ?></a></div>
	<!-- /wp:button --></div>
	<!-- /wp:buttons -->

	<!-- wp:paragraph {"fontSize":"small"} -->
	<p class="has-small-font-size"><?php echo esc_html_x( 'Questions about registering?', 'registration cta contact line', 'westshore' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:shortcode -->
	[westshore_people group="registration" layout="cards" heading="no" fields="email,phone"]
	<!-- /wp:shortcode -->
</div>
<!-- /wp:group -->
