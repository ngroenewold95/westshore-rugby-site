<?php
/**
 * Sponsors: the post type, the tier taxonomy, and the two extra fields.
 *
 * Adding a sponsor is meant to be: upload a logo, pick a tier, publish. Every
 * decision here is in service of that. If a step ever needs HTML, it is wrong.
 *
 * @package Westshore_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The sponsor post type.
 *
 * Not public on the front end: a sponsor has no page of its own, it appears in
 * the tier grids. publicly_queryable is false so /sponsor/tube-shack/ does not
 * become a thin page nobody maintains.
 */
function westshore_register_sponsor_post_type() {
	register_post_type(
		'sponsor',
		array(
			'labels'             => array(
				'name'               => __( 'Sponsors', 'westshore' ),
				'singular_name'      => __( 'Sponsor', 'westshore' ),
				'add_new_item'       => __( 'Add sponsor', 'westshore' ),
				'edit_item'          => __( 'Edit sponsor', 'westshore' ),
				'new_item'           => __( 'New sponsor', 'westshore' ),
				'view_item'          => __( 'View sponsor', 'westshore' ),
				'search_items'       => __( 'Search sponsors', 'westshore' ),
				'not_found'          => __( 'No sponsors yet', 'westshore' ),
				'not_found_in_trash' => __( 'No sponsors in the trash', 'westshore' ),
				'menu_name'          => __( 'Sponsors', 'westshore' ),
			),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			// Under the one Westshore menu rather than a top-level of its own.
			// The edit screen's URL does not change, so nothing that links to
			// it breaks. See includes/admin-menu.php.
			'show_in_menu'       => WESTSHORE_ADMIN_MENU_SLUG,
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-heart',
			'supports'           => array( 'title', 'thumbnail', 'page-attributes' ),
			'has_archive'        => false,
			'rewrite'            => false,
			'hierarchical'       => false,
		)
	);
}
add_action( 'init', 'westshore_register_sponsor_post_type' );

/**
 * The tier taxonomy.
 *
 * Radio-button behaviour is not enforced in code. The club has three tiers and
 * a sponsor sits in one. If someone ticks two the sponsor appears twice, which
 * is visible and self-correcting rather than a silent failure.
 */
function westshore_register_sponsor_tier_taxonomy() {
	register_taxonomy(
		'sponsor_tier',
		'sponsor',
		array(
			'labels'            => array(
				'name'          => __( 'Sponsor tiers', 'westshore' ),
				'singular_name' => __( 'Sponsor tier', 'westshore' ),
				'add_new_item'  => __( 'Add tier', 'westshore' ),
				'edit_item'     => __( 'Edit tier', 'westshore' ),
				'menu_name'     => __( 'Tiers', 'westshore' ),
			),
			'public'            => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'hierarchical'      => true,
			'rewrite'           => false,
		)
	);
}
add_action( 'init', 'westshore_register_sponsor_tier_taxonomy' );

/**
 * The three tiers, in display order, with the design each one carries.
 *
 * Order, accent colour, column count and logo height live here rather than in
 * the CSS, so the renderer and any future editor UI read from one list.
 *
 * @return array<string, array<string, mixed>> Keyed by term slug.
 */
function westshore_sponsor_tiers() {
	return array(
		'club-partners'      => array(
			'name'        => __( 'Club Partners', 'westshore' ),
			'description' => __( 'Our premium tier. Partners backing the whole club.', 'westshore' ),
			'accent'      => '#1d3a63',
			'columns'     => 2,
			'logo_height' => 150,
			'empty'       => __( 'Club partnerships are available.', 'westshore' ),
		),
		'program-partners'   => array(
			'name'        => __( 'Program Partners', 'westshore' ),
			'description' => __( 'Backing a specific team or program within the club.', 'westshore' ),
			'accent'      => '#415F91',
			'columns'     => 3,
			'logo_height' => 120,
			'empty'       => __( 'Program partnerships are available. Back a Senior, Junior or Mini program.', 'westshore' ),
		),
		'community-partners' => array(
			'name'        => __( 'Community Partners', 'westshore' ),
			'description' => __( 'Local businesses backing the grassroots and minis game on the Westshore.', 'westshore' ),
			'accent'      => '#7a95bb',
			'columns'     => 4,
			'logo_height' => 96,
			'empty'       => __( 'Community partnerships are available.', 'westshore' ),
		),
	);
}

/**
 * Create the tier terms if they are missing.
 *
 * Never overwrites an existing term or its settings. That mattered less when
 * the settings were hard-coded and could not be edited; now that a tier's
 * order, colour, columns and logo height are all editable in the admin, an
 * activation that reset them would quietly undo real work.
 */
function westshore_seed_sponsor_tiers() {
	$order = 0;
	$seeds = array();

	foreach ( westshore_sponsor_tiers() as $slug => $tier ) {
		$order += 10;

		$seeds[ $slug ] = array(
			'name'        => $tier['name'],
			'description' => $tier['description'],
			'meta'        => array(
				'westshore_order'       => $order,
				'westshore_accent'      => $tier['accent'],
				'westshore_columns'     => $tier['columns'],
				'westshore_logo_height' => $tier['logo_height'],
				'westshore_empty'       => $tier['empty'],
			),
		);
	}

	westshore_seed_terms( 'sponsor_tier', $seeds );
}

/**
 * The tiers to render, in the club's order.
 *
 * Reads the terms rather than the array above, so a tier added in the admin
 * actually appears. It could not before: the renderer walked the hard-coded
 * list, so a new tier was invisible with no error anywhere, which looks exactly
 * like it worked.
 *
 * @return array<int, WP_Term>
 */
function westshore_get_sponsor_tier_terms() {
	return westshore_ordered_terms( 'sponsor_tier' );
}

/**
 * One tier's display settings.
 *
 * Term meta first, then the seed array for a tier that predates these fields,
 * then a plain default for a tier the club invents later. Three layers so the
 * three existing tiers render exactly as they did before this change.
 *
 * @param WP_Term $term Tier.
 * @return array<string, mixed>
 */
function westshore_sponsor_tier_settings( $term ) {
	$seeds = westshore_sponsor_tiers();
	$seed  = isset( $seeds[ $term->slug ] ) ? $seeds[ $term->slug ] : array();

	$read = function ( $key, $fallback ) use ( $term, $seed ) {
		$value = get_term_meta( $term->term_id, 'westshore_' . $key, true );

		if ( '' !== $value && null !== $value ) {
			return $value;
		}

		return isset( $seed[ $key ] ) ? $seed[ $key ] : $fallback;
	};

	return array(
		'accent'      => (string) $read( 'accent', '#415F91' ),
		'columns'     => (int) $read( 'columns', 3 ),
		'logo_height' => (int) $read( 'logo_height', 120 ),
		'empty'       => (string) $read( 'empty', __( 'Partnerships are available in this tier.', 'westshore' ) ),
	);
}

/**
 * The tier fields, described once so the two forms and the save cannot drift.
 *
 * Registered through the shared term-settings API, which builds the add form,
 * the edit form and the save from this one array. It also brings the hidden
 * marker people's groups needed, so a Quick Edit rename can never clear a
 * setting the volunteer was not shown.
 *
 * columns and logo_height carry a floor of 1. Both feed CSS directly, and a
 * tier saved with 0 rendered its logos at height:0px with no error anywhere.
 *
 * @return array<string, array<string, mixed>>
 */
function westshore_sponsor_tier_fields() {
	return array(
		'order'       => array(
			'label'       => __( 'Order', 'westshore' ),
			'type'        => 'number',
			'meta'        => 'westshore_order',
			'placeholder' => '10',
			'help'        => __( 'Lower numbers come first. Leave it empty and the tier sorts to the end.', 'westshore' ),
		),
		'accent'      => array(
			'label'       => __( 'Accent colour', 'westshore' ),
			'type'        => 'text',
			'meta'        => 'westshore_accent',
			'placeholder' => '#415F91',
			'help'        => '',
		),
		'columns'     => array(
			'label'       => __( 'Columns on a wide screen', 'westshore' ),
			'type'        => 'number',
			'meta'        => 'westshore_columns',
			'placeholder' => '3',
			'min'         => 1,
			'help'        => __( 'Fewer columns means bigger logos. A phone shows one column whatever this says.', 'westshore' ),
		),
		'logo_height' => array(
			'label'       => __( 'Logo height in pixels', 'westshore' ),
			'type'        => 'number',
			'meta'        => 'westshore_logo_height',
			'placeholder' => '120',
			'min'         => 1,
			'help'        => '',
		),
		'empty'       => array(
			'label'       => __( 'Line shown when the tier is empty', 'westshore' ),
			'type'        => 'text',
			'meta'        => 'westshore_empty',
			'placeholder' => '',
			'help'        => __( 'An empty tier is a sales pitch, not a mistake, so it still renders with this line in it.', 'westshore' ),
		),
	);
}

/**
 * Register the tier settings screens.
 *
 * The reader keeps the edit form showing the effective value rather than the
 * raw meta, which is what it showed before: the three seeded tiers predate
 * these fields, and a blank box next to a tier that plainly has an accent
 * colour reads as broken. order is the exception and comes straight from the
 * meta, because there is no seed fallback for it to show.
 */
function westshore_register_sponsor_tier_settings() {
	westshore_register_term_settings(
		'sponsor_tier',
		'westshore_tier',
		westshore_sponsor_tier_fields(),
		function ( $term, $key, $meta_key ) {
			if ( 'order' === $key ) {
				return get_term_meta( $term->term_id, $meta_key, true );
			}

			$settings = westshore_sponsor_tier_settings( $term );

			return isset( $settings[ $key ] ) ? $settings[ $key ] : '';
		}
	);
}
add_action( 'init', 'westshore_register_sponsor_tier_settings' );

/**
 * The two fields a logo and a title cannot carry: where the sponsor links, and
 * the small line under the name.
 *
 * A classic meta box on purpose. It works in the block editor with no build
 * step and no JavaScript, which matters for a site nobody is paid to maintain.
 */
function westshore_sponsor_meta_box() {
	add_meta_box(
		'westshore-sponsor-details',
		__( 'Sponsor details', 'westshore' ),
		'westshore_sponsor_meta_box_render',
		'sponsor',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'westshore_sponsor_meta_box' );

/**
 * Render the sponsor details box.
 *
 * @param WP_Post $post The sponsor being edited.
 */
function westshore_sponsor_meta_box_render( $post ) {
	wp_nonce_field( 'westshore_sponsor_save', 'westshore_sponsor_nonce' );

	$url  = get_post_meta( $post->ID, '_westshore_sponsor_url', true );
	$note = get_post_meta( $post->ID, '_westshore_sponsor_note', true );
	?>
	<p>
		<label for="westshore-sponsor-url"><strong><?php esc_html_e( 'Website', 'westshore' ); ?></strong></label><br />
		<input type="url" id="westshore-sponsor-url" name="westshore_sponsor_url" class="large-text"
			value="<?php echo esc_attr( $url ); ?>" placeholder="https://example.com" /><br />
		<span class="description"><?php esc_html_e( 'Optional. Leave it empty and the logo simply will not be a link.', 'westshore' ); ?></span>
	</p>
	<p>
		<label for="westshore-sponsor-note"><strong><?php esc_html_e( 'Small line under the name', 'westshore' ); ?></strong></label><br />
		<input type="text" id="westshore-sponsor-note" name="westshore_sponsor_note" class="large-text"
			value="<?php echo esc_attr( $note ); ?>" placeholder="250-555-1234 &middot; example.com" /><br />
		<span class="description"><?php esc_html_e( 'Optional. A phone number, a town, a web address. Plain text.', 'westshore' ); ?></span>
	</p>
	<?php
}

/**
 * Save the sponsor details.
 *
 * @param int $post_id The sponsor being saved.
 */
function westshore_sponsor_meta_save( $post_id ) {
	if ( ! westshore_can_save_post( $post_id, 'westshore_sponsor_nonce', 'westshore_sponsor_save' ) ) {
		return;
	}

	$url = isset( $_POST['westshore_sponsor_url'] )
		? esc_url_raw( wp_unslash( $_POST['westshore_sponsor_url'] ) )
		: '';

	$note = isset( $_POST['westshore_sponsor_note'] )
		? sanitize_text_field( wp_unslash( $_POST['westshore_sponsor_note'] ) )
		: '';

	update_post_meta( $post_id, '_westshore_sponsor_url', $url );
	update_post_meta( $post_id, '_westshore_sponsor_note', $note );
}
add_action( 'save_post_sponsor', 'westshore_sponsor_meta_save' );

/**
 * Show the logo in the sponsors list.
 *
 * A list of names is useless for spotting the one with the wrong image.
 */
function westshore_register_sponsor_columns() {
	westshore_register_admin_column(
		'sponsor',
		'westshore_logo',
		__( 'Logo', 'westshore' ),
		function ( $post_id ) {
			if ( has_post_thumbnail( $post_id ) ) {
				echo get_the_post_thumbnail( $post_id, array( 60, 60 ), array( 'style' => 'width:60px;height:auto;' ) );

				return;
			}

			echo westshore_admin_missing( __( 'No logo', 'westshore' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		},
		true
	);
}
add_action( 'init', 'westshore_register_sponsor_columns' );
