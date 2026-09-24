<?php
/**
 * Rendering the sponsor tiers.
 *
 * Output matches the hand-written markup that was on page 4403, class names and
 * all, so the page looks the same the day it stops being hand-written. The
 * difference is where the content comes from: sponsor posts, not HTML in a
 * widget.
 *
 * Exposed as a shortcode rather than a block on purpose. A dynamic block needs
 * a JavaScript build to appear in the inserter, and a build step is one more
 * thing that rots. The theme ships a pattern wrapping this shortcode, so a
 * volunteer inserts a pattern and never types the shortcode by hand.
 *
 * @package Westshore_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load the stylesheet, once, and only on a page that renders sponsors.
 *
 * Was a register on wp_enqueue_scripts plus an enqueue in the renderer, where
 * people used a lazy enqueue behind a wp_style_is() guard. Two conventions for
 * one job; this is the people one.
 */
function westshore_sponsors_styles() {
	westshore_lazy_style( 'westshore-sponsors', 'sponsors.css' );
}

/**
 * Fetch the published sponsors in one tier, in menu order then title.
 *
 * @param string $slug Tier term slug.
 * @return WP_Post[]
 */
function westshore_get_sponsors_in_tier( $slug ) {
	return get_posts(
		array(
			'post_type'        => 'sponsor',
			'post_status'      => 'publish',
			'numberposts'      => -1,
			'orderby'          => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'suppress_filters' => false,
			'tax_query'        => array(
				array(
					'taxonomy' => 'sponsor_tier',
					'field'    => 'slug',
					'terms'    => $slug,
				),
			),
		)
	);
}

/**
 * Render one sponsor card.
 *
 * A sponsor with a website is a link; one without is a plain block. That is why
 * the tag varies rather than emitting an empty href.
 *
 * @param WP_Post $sponsor     The sponsor.
 * @param int     $logo_height Tier logo height in pixels.
 * @return string
 */
function westshore_render_sponsor_card( $sponsor, $logo_height ) {
	$url  = get_post_meta( $sponsor->ID, '_westshore_sponsor_url', true );
	$note = get_post_meta( $sponsor->ID, '_westshore_sponsor_note', true );
	$name = get_the_title( $sponsor );

	$logo = get_the_post_thumbnail(
		$sponsor->ID,
		'medium_large',
		array(
			'alt'     => $name,
			'loading' => 'lazy',
			'style'   => 'max-height:' . (int) $logo_height . 'px;',
		)
	);

	if ( ! $logo ) {
		// No logo is a content problem, and silence would hide it. The name
		// still renders so the sponsor is not simply missing from the page.
		$logo = '<span class="wsr-nologo">' . esc_html( $name ) . '</span>';
	}

	$inner = sprintf(
		'<span class="wsr-logo" style="height:%1$dpx">%2$s</span><p class="wsr-name">%3$s</p>%4$s',
		(int) $logo_height,
		$logo,
		esc_html( $name ),
		$note ? '<p class="wsr-meta">' . esc_html( $note ) . '</p>' : ''
	);

	if ( $url ) {
		return sprintf(
			'<a class="wsr-card" href="%1$s" target="_blank" rel="noopener">%2$s</a>',
			esc_url( $url ),
			$inner
		);
	}

	return '<div class="wsr-card">' . $inner . '</div>';
}

/**
 * Render every tier.
 *
 * An empty tier still renders, with its "partnerships are available" panel.
 * That panel is the sales pitch, so an empty tier is a feature here.
 *
 * Driven by the tier terms rather than a list in code, so a tier the club adds
 * in the admin actually appears. Before this it did not: the loop walked the
 * hard-coded array, and a new tier was invisible with no error anywhere.
 *
 * @return string
 */
function westshore_render_sponsor_tiers() {
	westshore_sponsors_styles();

	$out = '<div class="wsr-sponsors">';

	foreach ( westshore_get_sponsor_tier_terms() as $term ) {
		$tier     = westshore_sponsor_tier_settings( $term );
		$sponsors = westshore_get_sponsors_in_tier( $term->slug );

		westshore_sponsor_tier_columns_css( $term, (int) $tier['columns'] );

		$out .= sprintf(
			'<section class="wsr-tier" style="--wsr-accent:%1$s"><div class="wsr-tier-head"><h3>%2$s</h3><p>%3$s</p></div><div class="wsr-grid wsr-grid--%4$s">',
			esc_attr( $tier['accent'] ),
			esc_html( $term->name ),
			esc_html( $term->description ),
			esc_attr( $term->slug )
		);

		if ( $sponsors ) {
			foreach ( $sponsors as $sponsor ) {
				$out .= westshore_render_sponsor_card( $sponsor, $tier['logo_height'] );
			}
		} else {
			$out .= '<div class="wsr-open"><p>' . wp_kses_post( $tier['empty'] ) . '</p></div>';
		}

		$out .= '</div></section>';
	}

	$out .= '</div>';

	return $out;
}

/**
 * Give a club-added tier the column count its setting asks for.
 *
 * The Columns setting used to do nothing at all. The renderer wrote
 * style="--wsr-cols:N" on the grid and no CSS anywhere read that property; the
 * real column counts came from three per-slug media queries in sponsors.css.
 * So the admin showed a control that changed nothing, and worse, a tier the
 * club added matched none of those three rules and rendered as one column at
 * every width. That is the exact scenario the tier rework was done for.
 *
 * The three seeded tiers keep their hand-written rules and are not touched
 * here. They do not share a formula, going 1/1/2/2, 1/1/2/3 and 1/2/3/4 across
 * the breakpoints, because the column count was tuned to how big each tier's
 * logos are rather than derived from anything. Reproducing that with arithmetic
 * is not possible, and a formula that came close would still move logos on a
 * page nobody asked to change.
 *
 * Keyed on the slug rather than on whether the term has explicit meta,
 * deliberately: the tier edit form pre-fills from the effective settings, so
 * merely opening a seeded tier and pressing Update writes the seed value into
 * meta. Keying on meta would have let an unrelated save silently re-lay-out
 * Club Partners.
 *
 * The ladder for everything else steps down one column per breakpoint and never
 * below one, so a phone always gets a single column whatever the setting says.
 *
 * repeat() needs a literal integer, so this cannot be done with calc() or min()
 * in the stylesheet. It has to be generated.
 *
 * @param WP_Term $term    Tier.
 * @param int     $columns Columns on a wide screen.
 */
function westshore_sponsor_tier_columns_css( $term, $columns ) {
	if ( isset( westshore_sponsor_tiers()[ $term->slug ] ) ) {
		return;
	}

	// Once per tier per request. The shortcode gets rendered more than once on
	// a page: AIOSEO runs the content through again to build its meta
	// description, and wp_add_inline_style appends rather than replaces, so
	// without this the rules stack up a copy per render.
	static $done = array();

	if ( isset( $done[ $term->slug ] ) ) {
		return;
	}

	$done[ $term->slug ] = true;

	$columns = max( 1, $columns );

	$css = sprintf(
		'@media(min-width:480px){.wsr-grid--%1$s{grid-template-columns:repeat(%2$d,1fr)}}'
			. '@media(min-width:700px){.wsr-grid--%1$s{grid-template-columns:repeat(%3$d,1fr)}}'
			. '@media(min-width:960px){.wsr-grid--%1$s{grid-template-columns:repeat(%4$d,1fr)}}',
		sanitize_html_class( $term->slug ),
		max( 1, $columns - 2 ),
		max( 1, $columns - 1 ),
		$columns
	);

	wp_add_inline_style( 'westshore-sponsors', $css );
}

/**
 * Render every sponsor as one flat strip, no tier headings.
 *
 * For the front page, where the three-tier layout with its descriptions and its
 * "partnerships are available" panel is more sponsorship pitch than a home page
 * wants. Club Partners come first because the order is the tier order, and the
 * logo height is one size for everybody: a strip that steps down mid-run reads
 * as a rendering fault rather than as a hierarchy.
 *
 * One row that rotates, in CSS, at every width. The first version wrapped to a
 * centred row above 860px on the reasoning that nothing should be hidden off
 * the edge on a screen with room for all of it. That holds for four logos. With
 * eleven it rendered six above five, which reads as a rendering fault, and the
 * sponsor list only grows.
 *
 * Still no JavaScript. The club had an owl-carousel plugin once; it was deleted
 * in Phase 1 and left eight orphaned rows in the database that are there today.
 *
 * The cards are emitted twice. The animation runs the track to exactly -50%, so
 * the second copy arrives where the first began and the loop has no seam. The
 * duplicate is aria-hidden and its links are taken out of the tab order, or a
 * screen reader hears every sponsor twice and a keyboard user tabs through
 * twenty-two links to leave a strip of eleven.
 *
 * @return string
 */
function westshore_render_sponsor_strip() {
	westshore_sponsors_styles();

	$cards = '';
	$count = 0;

	foreach ( westshore_get_sponsor_tier_terms() as $term ) {
		foreach ( westshore_get_sponsors_in_tier( $term->slug ) as $sponsor ) {
			$cards .= westshore_render_sponsor_card( $sponsor, 96 );
			++$count;
		}
	}

	if ( '' === $cards ) {
		// No sponsors at all means the import has not run or every sponsor is
		// a draft. An empty strip would look like a layout bug, so say nothing
		// at all instead.
		return '';
	}

	// A fixed duration makes three logos race and twenty crawl. Seconds per card
	// keeps the apparent speed the same however many the club signs.
	//
	// Eight seconds a card, halved from the first version at the club's ask. A
	// logo somebody is paying for should be readable as it goes past, not
	// glimpsed.
	$duration = max( 36, $count * 8 );

	// Below four logos the doubled track is narrower than a wide viewport, so
	// the "seamless" loop runs out of cards and shows blank space with a visible
	// jump at the wrap. A static centred row is the honest rendering of three
	// sponsors, and the club has had three tiers with as few as two in one.
	if ( $count < 4 ) {
		return '<div class="wsr-sponsors wsr-sponsors--strip wsr-sponsors--static">'
			. '<div class="wsr-strip"><div class="wsr-strip-run">' . $cards . '</div></div></div>';
	}

	// The clone is inert: aria-hidden alone stops it being announced but leaves
	// its links tabbable, which is the well-known half-fix.
	$clone = str_replace( '<a ', '<a tabindex="-1" ', $cards );

	return sprintf(
		'<div class="wsr-sponsors wsr-sponsors--strip" style="--wsr-strip-seconds:%ds">'
			. '<div class="wsr-strip">'
			. '<div class="wsr-strip-track">'
			. '<div class="wsr-strip-run">%s</div>'
			. '<div class="wsr-strip-run" aria-hidden="true">%s</div>'
			. '</div></div></div>',
		$duration,
		$cards,
		$clone
	);
}

/**
 * [westshore_sponsors]
 *
 * One attribute, layout. "tiers" is the full sponsors page; "strip" is the flat
 * run of logos for the front page. Everything else that could be an attribute
 * is a tier setting instead, so there is one place to change it.
 *
 * @param array<string, string>|string $atts Shortcode attributes.
 * @return string
 */
function westshore_sponsors_shortcode( $atts ) {
	$atts = shortcode_atts(
		array( 'layout' => 'tiers' ),
		$atts,
		'westshore_sponsors'
	);

	return 'strip' === $atts['layout']
		? westshore_render_sponsor_strip()
		: westshore_render_sponsor_tiers();
}
add_shortcode( 'westshore_sponsors', 'westshore_sponsors_shortcode' );
