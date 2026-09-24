<?php
/**
 * Westshore theme setup.
 *
 * Deliberately small. Styling belongs in theme.json so the club can change it
 * from Appearance > Editor > Styles; anything hard-coded here is something a
 * volunteer cannot change without a developer.
 *
 * @package Westshore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue the stylesheet.
 *
 * Version comes from the theme header so a deploy busts the cache without
 * anyone remembering to bump a constant.
 */
function westshore_enqueue_styles() {
	$theme = wp_get_theme();

	wp_enqueue_style(
		'westshore-style',
		get_stylesheet_uri(),
		array(),
		$theme->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'westshore_enqueue_styles' );

/**
 * Enqueue the one script the front end has.
 *
 * Deferred rather than in the head: nothing renders differently until a reader
 * follows an anchor, and a blocking script for that is not worth it. See the
 * file itself for why it exists.
 */
function westshore_enqueue_scripts() {
	$theme = wp_get_theme();

	wp_enqueue_script(
		'westshore-details-anchor',
		get_theme_file_uri( 'assets/details-anchor.js' ),
		array(),
		$theme->get( 'Version' ),
		array( 'strategy' => 'defer', 'in_footer' => true )
	);
}
add_action( 'wp_enqueue_scripts', 'westshore_enqueue_scripts' );

/**
 * Theme supports.
 *
 * Block themes get most of this automatically. Listed explicitly only where
 * the default is not what the club needs.
 */
function westshore_setup() {
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );

	// The club writes in one language and the menu is already too long.
	// Nothing here registers classic menus on purpose: navigation is a block.
	load_theme_textdomain( 'westshore', get_template_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'westshore_setup' );

/**
 * Point a Navigation block at the right menu, by slug rather than by ID.
 *
 * The Navigation block only understands a numeric `ref`, which is a post ID.
 * Hard-coding one into a theme file breaks the moment the theme is deployed to
 * a second site, because staging and production will not agree on IDs. Worse,
 * a Navigation block with no ref silently falls back to listing every published
 * page, which on this site is 47 items and looks like a working menu.
 *
 * So the header and footer parts carry a class instead, and the ID is resolved
 * here at render time from the wp_navigation post slug, which a setup
 * script creates on each site.
 *
 * @param array<string, mixed> $block Parsed block.
 * @return array<string, mixed>
 */
function westshore_resolve_navigation_ref( $block ) {
	if ( 'core/navigation' !== ( $block['blockName'] ?? '' ) ) {
		return $block;
	}

	if ( ! empty( $block['attrs']['ref'] ) ) {
		return $block;
	}

	$class = $block['attrs']['className'] ?? '';

	$slugs = array(
		'westshore-nav-main'   => 'westshore-main',
		'westshore-nav-footer' => 'westshore-footer',
	);

	foreach ( $slugs as $marker => $slug ) {
		if ( false === strpos( $class, $marker ) ) {
			continue;
		}

		$menu = get_posts(
			array(
				'post_type'        => 'wp_navigation',
				'post_status'      => 'publish',
				'name'             => $slug,
				'numberposts'      => 1,
				'fields'           => 'ids',
				'suppress_filters' => false,
			)
		);

		if ( $menu ) {
			$block['attrs']['ref'] = $menu[0];
		}

		break;
	}

	return $block;
}
add_filter( 'render_block_data', 'westshore_resolve_navigation_ref' );

/**
 * Register the pattern category the club's patterns live in.
 *
 * One category, named for the club, so the inserter shows volunteers a short
 * list of blessed layouts rather than the full core pattern library.
 */
function westshore_register_pattern_category() {
	if ( ! function_exists( 'register_block_pattern_category' ) ) {
		return;
	}

	register_block_pattern_category(
		'westshore',
		array(
			'label'       => __( 'Westshore', 'westshore' ),
			'description' => __( 'Layouts built for Westshore Rugby. Edit the text and images; the structure is locked.', 'westshore' ),
		)
	);
}
add_action( 'init', 'westshore_register_pattern_category' );

/**
 * Let a locked hero have its alt text and its crop edited.
 *
 * templateLock "contentOnly" allows editing whatever each block marks as a
 * content attribute, and core/cover marks exactly one: the image URL. So a
 * volunteer who swaps the hero photograph gets the new picture with the old
 * photograph's alt text still attached to it, and no way to correct that or to
 * say where the new picture should be cropped.
 *
 * That matters here because the band is shallow. A squad line-up shows about
 * 72% of its height at desktop widths and a 3:2 photograph about 45%, so the
 * difference between a good crop and a decapitated one is the focal point, and
 * it was the one thing the lock took away.
 *
 * The structure stays locked. This adds two attributes to what counts as
 * content, and nothing else: the block still cannot be moved, removed or taken
 * apart.
 *
 * The theme's object-position rules are defaults, not overrides. A focal point
 * set here renders as an inline style on the image, which beats a stylesheet
 * rule, so whatever the volunteer picks wins.
 *
 * @param array<string, mixed> $args     Block type arguments.
 * @param string               $name     Block name.
 * @return array<string, mixed>
 */
function westshore_cover_content_attributes( $args, $name ) {
	if ( 'core/cover' !== $name && 'core/image' !== $name ) {
		return $args;
	}

	foreach ( array( 'alt', 'focalPoint' ) as $attribute ) {
		if ( isset( $args['attributes'][ $attribute ] ) ) {
			$args['attributes'][ $attribute ]['role'] = 'content';
		}
	}

	return $args;
}
add_filter( 'register_block_type_args', 'westshore_cover_content_attributes', 10, 2 );

/**
 * An attachment ID from its slug, for a pattern that ships an image.
 *
 * A pattern is rendered at request time on whichever site it is deployed to,
 * and staging and production do not agree on attachment IDs, so a pattern
 * cannot carry an ID the way a page can once convert-pages.php has resolved
 * one. It carries the slug instead and looks it up here.
 *
 * A direct query on post_name rather than get_posts(). An attachment's status
 * is "inherit" and WP_Query resolves that against the parent, so an attachment
 * uploaded to a page that has since been drafted cannot be found by name any
 * other way. Finding that out cost an afternoon.
 *
 * Cached for the request: the same pattern renders more than once when AIOSEO
 * runs the content again for its meta description.
 *
 * @param string $slug Attachment post_name.
 * @return int Attachment ID, or 0 when there is none.
 */
function westshore_attachment_id_by_slug( $slug ) {
	static $cache = array();

	if ( isset( $cache[ $slug ] ) ) {
		return $cache[ $slug ];
	}

	global $wpdb;

	$id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_name = %s ORDER BY ID ASC LIMIT 1",
			$slug
		)
	);

	$cache[ $slug ] = (int) $id;

	return $cache[ $slug ];
}

/**
 * The front page hero rotates, one photograph per visit, and the volunteer
 * owns the set.
 *
 * The extra photographs are ordinary cover blocks on the front page, in a
 * group carrying the class westshore-hero-extras, which renders nothing on the
 * site. Each cover holds one photograph and its own focal point, set with the
 * same picker as the hero itself, so a new one is Duplicate, Replace, drag the
 * circle. No list in code, no settings screen: the page is the list.
 *
 * Why not pick one in PHP: production runs LiteSpeed page cache, so a random
 * choice made on the server is made once and served to everyone until the
 * cache expires. The choice has to happen in the browser.
 *
 * Why the image is emptied first: the browser's preload scanner fetches every
 * img src it sees before any script runs, so swapping the src afterwards would
 * download two hero photographs, one of them for nothing. The cover's own img
 * stays in place with its src moved into the candidate list, the script right
 * after it picks one and fills the src back in, and a noscript copy of the
 * original img covers a reader with scripting off. The navy dim span sits over
 * the image either way, so there is no white flash while the script runs.
 *
 * The srcset goes along too: wp_filter_content_tags() adds one by matching the
 * src, and the emptied img has none to match. A phone then gets the 1024 file
 * rather than the 2560 one.
 *
 * Alt text comes from the attachment for every entry, the hero's own included,
 * so a fix made in Media reaches the rotation. The block's alt only reaches
 * the noscript copy.
 */

/**
 * Photograph and focal point from a cover block's attributes.
 *
 * Core stores the focal point as fractions; object-position wants percentages.
 * A cover with no point set gets the stylesheet default, 50% 34%.
 *
 * @param array $attrs Cover block attributes.
 * @return array{src: string, srcset: string, alt: string, pos: string}|null
 */
function westshore_hero_candidate( $attrs ) {
	$id = isset( $attrs['id'] ) ? (int) $attrs['id'] : 0;

	if ( ! $id ) {
		return null;
	}

	$src = wp_get_attachment_image_url( $id, 'full' );

	if ( ! $src ) {
		return null;
	}

	$focal = isset( $attrs['focalPoint'] ) && is_array( $attrs['focalPoint'] ) ? $attrs['focalPoint'] : array();
	$x     = isset( $focal['x'] ) ? round( (float) $focal['x'] * 100 ) : 50;
	$y     = isset( $focal['y'] ) ? round( (float) $focal['y'] * 100 ) : 34;

	return array(
		'src'    => $src,
		'srcset' => (string) wp_get_attachment_image_srcset( $id, 'full' ),
		'alt'    => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
		'pos'    => $x . '% ' . $y . '%',
	);
}

/**
 * Every cover block inside the westshore-hero-extras group of this post.
 *
 * Parsed from post_content rather than collected as blocks render, because
 * the hero renders before the extras do and needs them then.
 *
 * @param array $blocks Parsed blocks.
 * @param bool  $inside Whether an ancestor is the extras group.
 * @return array<int, array> Cover block attribute arrays.
 */
function westshore_hero_extra_covers( $blocks, $inside = false ) {
	$found = array();

	foreach ( $blocks as $block ) {
		$class = isset( $block['attrs']['className'] ) ? $block['attrs']['className'] : '';
		$here  = $inside || ( 'core/group' === $block['blockName'] && preg_match( '/(^|\s)westshore-hero-extras(\s|$)/', $class ) );

		if ( $here && 'core/cover' === $block['blockName'] ) {
			$found[] = $block['attrs'];
		}

		if ( ! empty( $block['innerBlocks'] ) ) {
			$found = array_merge( $found, westshore_hero_extra_covers( $block['innerBlocks'], $here ) );
		}
	}

	return $found;
}

/**
 * Rotate the hero: the hero's own photograph plus the extras.
 *
 * @param string $content Rendered block.
 * @param array  $block   Parsed block.
 * @return string
 */
function westshore_rotate_hero( $content, $block ) {
	$class = isset( $block['attrs']['className'] ) ? $block['attrs']['className'] : '';

	if ( ! preg_match( '/(^|\s)westshore-hero(\s|$)/', $class ) ) {
		return $content;
	}

	$post = get_post();

	if ( ! $post ) {
		return $content;
	}

	$photos = array();
	$own    = westshore_hero_candidate( $block['attrs'] );

	if ( $own ) {
		$photos[ $own['src'] ] = $own;
	}

	foreach ( westshore_hero_extra_covers( parse_blocks( $post->post_content ) ) as $attrs ) {
		$extra = westshore_hero_candidate( $attrs );

		if ( $extra && ! isset( $photos[ $extra['src'] ] ) ) {
			$photos[ $extra['src'] ] = $extra;
		}
	}

	// One photograph is not a rotation. Leave the block as the editor wrote it.
	if ( count( $photos ) < 2 ) {
		return $content;
	}

	if ( ! preg_match( '/<img\b[^>]*\bwp-block-cover__image-background\b[^>]*>/', $content, $match ) ) {
		return $content;
	}

	$original = $match[0];
	$emptied  = preg_replace( '/\ssrc="[^"]*"/', ' src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="', $original, 1 );

	$script = sprintf(
		'<script>(function(){var s=document.currentScript,i=s.previousElementSibling,l=%s,p=l[Math.floor(Math.random()*l.length)];if(p.srcset){i.srcset=p.srcset;i.sizes="100vw";}i.src=p.src;i.alt=p.alt;i.style.objectPosition=p.pos;})();</script>',
		wp_json_encode( array_values( $photos ), JSON_HEX_TAG | JSON_HEX_AMP )
	);

	return str_replace( $original, $emptied . $script . '<noscript>' . $original . '</noscript>', $content );
}
add_filter( 'render_block_core/cover', 'westshore_rotate_hero', 10, 2 );

/**
 * The extras group is for the editor only. On the site it renders nothing.
 *
 * @param string $content Rendered block.
 * @param array  $block   Parsed block.
 * @return string
 */
function westshore_hide_hero_extras( $content, $block ) {
	$class = isset( $block['attrs']['className'] ) ? $block['attrs']['className'] : '';

	return preg_match( '/(^|\s)westshore-hero-extras(\s|$)/', $class ) ? '' : $content;
}
add_filter( 'render_block_core/group', 'westshore_hide_hero_extras', 10, 2 );
