<?php
/**
 * Title: Age brackets
 * Slug: westshore/age-brackets
 * Categories: westshore
 * Description: The "which age group" heading, its one line, and BC Rugby's birth year chart. Shared by the Juniors and Minis pages.
 * Keywords: age, birth year, chart, juniors, minis
 * Viewport width: 1200
 *
 * The Juniors and Minis pages both open their program section with the same
 * chart and the same question, and one said "athlete" where the other said
 * "child". One copy now, and it says "child": the chart is for a parent
 * working out where their kid plays.
 *
 * Only the shared part is here. The two pages carry different PDFs and
 * different flyers under this, and those stay on the page, in a group that
 * follows the pattern with no top margin so the two read as one section.
 *
 * The chart is looked up by slug at render time. Staging and production do not
 * agree on attachment IDs, so a pattern cannot name one. If the slug finds
 * nothing the figure is left out rather than printed broken, and the page
 * still reads.
 *
 * @package Westshore
 */

$westshore_chart_id = westshore_attachment_id_by_slug( 'screenshot-2024-09-03-at-8-57-57-am' );
?>
<!-- wp:group {"templateLock":"contentOnly","style":{"spacing":{"margin":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|20"},"blockGap":"var:preset|spacing|20"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--40);margin-bottom:var(--wp--preset--spacing--20)">
	<!-- wp:heading -->
	<h2 class="wp-block-heading"><?php echo esc_html_x( 'Age brackets and program information', 'age brackets heading', 'westshore' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph -->
	<p><?php echo esc_html_x( 'Not sure which age group your child plays in? The chart below sets out the birth years.', 'age brackets body', 'westshore' ); ?></p>
	<!-- /wp:paragraph -->
<?php if ( $westshore_chart_id ) : ?>

	<!-- wp:image {"lightbox":{"enabled":true},"id":<?php echo (int) $westshore_chart_id; ?>,"sizeSlug":"large","linkDestination":"none"} -->
	<figure class="wp-block-image size-large"><img src="<?php echo esc_url( wp_get_attachment_image_url( $westshore_chart_id, 'large' ) ); ?>" alt="<?php echo esc_attr_x( 'Chart of birth years for each age grade, published for the 2024-2025 season', 'age brackets chart alt', 'westshore' ); ?>" class="wp-image-<?php echo (int) $westshore_chart_id; ?>"/><figcaption class="wp-element-caption"><?php echo esc_html_x( 'This is BC Rugby’s most recent chart. They have not published one since 2024-2025.', 'age brackets chart caption', 'westshore' ); ?></figcaption></figure>
	<!-- /wp:image -->
<?php endif; ?>
</div>
<!-- /wp:group -->
