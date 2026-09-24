<?php
/**
 * Redirects for pages that no longer exist on their own.
 *
 * Phase C merged six team photo pages into Team Photos and five award pages
 * into Awards. Those eleven URLs have been linked to for years, from other
 * sites and from people's bookmarks: the George Jones page alone has 13,290
 * all-time views. A retired page that 404s throws that away, so each old path
 * sends a 301 to its section on the parent page.
 *
 * Path matching rather than a slug lookup, deliberately. The retired pages are
 * drafts, and get_page_by_path() does not find a draft, so by the time this
 * runs there is nothing to look up.
 *
 * The target is named twice, by ID and by slug, and both have to agree. An ID
 * alone was the first version, and it is a number that could be anything on a
 * site this plugin has not seen: the three were right on production when
 * checked on 2026-09-04, but nothing enforced it, and a wrong ID sends a
 * bookmark to whatever page happens to hold that number. A slug alone is not
 * enough either, because the parents can be renamed and the keys already
 * distrust their slugs. So: the ID has to carry the slug it is expected to
 * carry, and if it does not, a published page with that slug is looked up
 * instead. If neither holds, the 404 stands. A guess is worse than a 404.
 *
 * In the plugin rather than in .htaccess so staging and production get the same
 * map from the same deploy. The .htaccess files are edited by hand, per site,
 * and are not in this repo.
 *
 * @package Westshore_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Old page path to its new home: page ID, then the anchor on that page.
 *
 * Keyed on the last segment of the URL, not the whole path. These pages are
 * children, so their real URLs run /teams/velox-team-photos-2/<slug>/, and the
 * parent slugs are not guaranteed to be the same on production as on staging.
 * The last segment is unique to the page and survives the parent being renamed
 * or moved.
 *
 * The slugs are the real ones, read off the site rather than derived from the
 * page titles: "velox-team-photos-1970s1980s" is what the 1980s page is
 * actually called, and "greg-claque-award-2015-to-present" carries a typo that
 * has been live for years.
 *
 * Each target is the page ID, the slug that page is expected to have, then the
 * anchor. The three targets are named once each below so a slug is not typed
 * twelve times.
 *
 * @return array<string, array{0:int, 1:string, 2:string}>
 */
function westshore_merged_page_redirects() {
	$photos = array( 2022, 'velox-team-photos-2' );
	$awards = array( 2088, 'westshore-rfc-awards' );
	$code   = array( 8515, 'code-of-conduct' );

	return array(
		'velox-team-photos-1970s'                   => array( $photos[0], $photos[1], 'photos-1960s-1970s' ),
		'velox-team-photos-1970s1980s'              => array( $photos[0], $photos[1], 'photos-1980s' ),
		'velox-team-photos-1990s'                   => array( $photos[0], $photos[1], 'photos-1990s' ),
		'velox-team-photos-2000-to-2010'            => array( $photos[0], $photos[1], 'photos-2000s' ),
		'velox-team-photos'                         => array( $photos[0], $photos[1], 'photos-2010-2015' ),
		'6-westshore-rfc-team-photos-sep-2015-to-present' => array( $photos[0], $photos[1], 'photos-2015-on' ),
		'george-jones-award-winners-1975-to-present' => array( $awards[0], $awards[1], 'george-jones' ),
		'hume-award-winners-2002-to-present'        => array( $awards[0], $awards[1], 'hume' ),
		'greg-claque-award-2015-to-present'         => array( $awards[0], $awards[1], 'greg-clague' ),
		'parker-johnston-award'                     => array( $awards[0], $awards[1], 'parker-johnston' ),
		'jaime-charko-award'                        => array( $awards[0], $awards[1], 'jaime-charko' ),

		// Phase D. The Vision Statement's seven values are the Code of Conduct's
		// values section now.
		'westshore-vision-statement'                => array( $code[0], $code[1], 'values' ),
	);
}

/**
 * The published page a redirect should land on, or null.
 *
 * The ID wins when it is a published page carrying the expected slug. When it
 * is not, whatever it is now, a published page with that slug is the fallback.
 * Nothing else is accepted: a draft with the right slug is not a landing page,
 * and a published page with the right ID and a different slug is some other
 * page that has been given this one's number.
 *
 * Checked on staging 2026-09-11: a wrong ID with the right slug finds the
 * page, a right ID with the wrong slug falls through to the slug, an unknown
 * pair returns null, and a drafted child's slug returns null.
 *
 * @param int    $page_id Expected page ID.
 * @param string $slug    Slug that page is expected to have.
 * @return WP_Post|null
 */
function westshore_redirect_target( $page_id, $slug ) {
	$page = get_post( $page_id );

	if ( $page instanceof WP_Post && 'page' === $page->post_type
		&& 'publish' === $page->post_status && $slug === $page->post_name ) {
		return $page;
	}

	// By post_name, not get_page_by_path(): that wants the whole path, and
	// two of the three targets are children of /teams/, whose slug is the
	// thing this file already refuses to rely on.
	$found = get_posts(
		array(
			'post_type'        => 'page',
			'post_status'      => 'publish',
			'name'             => $slug,
			'numberposts'      => 1,
			'suppress_filters' => false,
		)
	);

	return $found ? $found[0] : null;
}

/**
 * Send a retired page's URL to the section that now holds its content.
 *
 * Only on a 404. While a page is still published WordPress serves it and this
 * does nothing, which is what makes the redirect map safe to deploy before
 * merge-pages.php has run, and safe to leave in place if the merge is rolled
 * back.
 */
function westshore_redirect_merged_pages() {
	if ( ! is_404() || is_admin() ) {
		return;
	}

	$path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );

	if ( ! $path ) {
		return;
	}

	$segments = array_values( array_filter( explode( '/', trim( $path, '/' ) ) ) );
	$slug     = (string) end( $segments );
	$map      = westshore_merged_page_redirects();

	if ( '' === $slug || ! isset( $map[ $slug ] ) ) {
		return;
	}

	list( $page_id, $expected, $anchor ) = $map[ $slug ];

	$page = westshore_redirect_target( $page_id, $expected );

	if ( ! $page ) {
		return;
	}

	$target = get_permalink( $page );

	if ( ! $target ) {
		return;
	}

	wp_redirect( $target . '#' . $anchor, 301 );
	exit;
}
// Before redirect_canonical, which also runs on template_redirect at 10. Left
// at 10 this hook never sees the 2010-2015 page: its slug is close enough to
// the parent's that canonical guesses the parent and redirects there first,
// which lands on the right page with no anchor and drops the visitor at the
// top of ninety-six kilobytes of photographs.
add_action( 'template_redirect', 'westshore_redirect_merged_pages', 5 );
