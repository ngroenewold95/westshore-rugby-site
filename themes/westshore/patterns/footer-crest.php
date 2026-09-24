<?php
/**
 * Title: Footer crest
 * Slug: westshore/footer-crest
 * Categories: westshore
 * Description: The club crest with the white wordmark, linked home. Drawn for a navy ground, so it sits in the footer.
 * Keywords: crest, logo, footer
 * Viewport width: 400
 * Inserter: no
 *
 * The artwork is the club's own SVG (Makz, 2026-09-15), kept in the theme at
 * assets/crest-white-wordmark.svg rather than in the media library: WordPress
 * does not accept SVG uploads and the file is part of the design, not content.
 * A pattern rather than plain markup in parts/footer.html because a template
 * part cannot compute the theme URL.
 *
 * @package Westshore
 */

$westshore_crest = get_theme_file_uri( 'assets/crest-white-wordmark.svg' );

?>
<!-- wp:image {"width":"104px","align":"center","sizeSlug":"full","linkDestination":"custom","className":"westshore-footer-crest"} -->
<figure class="wp-block-image aligncenter size-full is-resized westshore-footer-crest"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><img src="<?php echo esc_url( $westshore_crest ); ?>" alt="<?php echo esc_attr_x( 'Westshore RFC', 'footer crest alt', 'westshore' ); ?>" style="width:104px" width="1516" height="1852"/></a></figure>
<!-- /wp:image -->
