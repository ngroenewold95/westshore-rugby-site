<?php
/**
 * People: the post type, the group taxonomy, and the roles a person holds.
 *
 * The problem this solves is duplication. Five email addresses and two phone
 * numbers were published across eight pages, 24 coach entries sat on two pages
 * each, and the registrar was on four. Every copy was edited by hand, so the
 * Coaches page ended up contradicting the team pages on nearly every line.
 *
 * A person is entered once here and every page reads from that entry.
 *
 * Modelled on sponsors.php, which already made adding a sponsor into upload a
 * logo, pick a tier, publish. Same target here: type a name, pick a group, give
 * the role a title. If a step ever needs HTML, it is wrong.
 *
 * One entry per person, not per role. Zoe Williams is Director of Senior Womens
 * on the board and a U18 Girls coach on the Juniors page. She is one entry with
 * two roles, so changing her email is one edit. An entry per role would rebuild
 * the exact problem this file exists to remove.
 *
 * @package Westshore_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How many roles one person can hold.
 *
 * Fixed rows rather than a JavaScript "add another" button, for the same reason
 * the sponsor box is a classic meta box: it works with no build step and no
 * JavaScript on a site nobody is paid to maintain.
 *
 * Six because four was not enough. Zoe Williams sits on the board, is a program
 * contact, coaches U18 Girls and is on the senior womens contact panel, which
 * was exactly four the day the team panels were switched over. A limit you have
 * already reached is the wrong limit.
 */
const WESTSHORE_PERSON_ROLE_SLOTS = 6;

const WESTSHORE_PERSON_ROLES_META = '_westshore_person_roles';
const WESTSHORE_PERSON_EMAIL_META = '_westshore_person_email';
const WESTSHORE_PERSON_PHONE_META = '_westshore_person_phone';

// Term meta on a group. Order is the same key sponsors already use, so the two
// taxonomies sort the same way and there is one convention rather than two.
const WESTSHORE_GROUP_ORDER_META   = 'westshore_order';
const WESTSHORE_GROUP_COACHES_META = 'westshore_on_coaches_page';

/**
 * The person post type.
 *
 * Not public on the front end. A coach has no page of their own: they appear in
 * the group listings, and /westshore_person/aidan-mcleary/ would be a thin page
 * nobody maintains. Same reasoning as the sponsor post type.
 */
function westshore_register_person_post_type() {
	register_post_type(
		'westshore_person',
		array(
			'labels'             => array(
				'name'               => __( 'People', 'westshore' ),
				'singular_name'      => __( 'Person', 'westshore' ),
				'add_new_item'       => __( 'Add person', 'westshore' ),
				'edit_item'          => __( 'Edit person', 'westshore' ),
				'new_item'           => __( 'New person', 'westshore' ),
				'view_item'          => __( 'View person', 'westshore' ),
				'search_items'       => __( 'Search people', 'westshore' ),
				'not_found'          => __( 'Nobody added yet', 'westshore' ),
				'not_found_in_trash' => __( 'Nobody in the trash', 'westshore' ),
				'menu_name'          => __( 'People', 'westshore' ),
			),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			// Under the one Westshore menu. See includes/admin-menu.php.
			'show_in_menu'       => WESTSHORE_ADMIN_MENU_SLUG,
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-groups',
			'supports'           => array( 'title', 'thumbnail' ),
			'has_archive'        => false,
			'rewrite'            => false,
			'hierarchical'       => false,
		)
	);
}
add_action( 'init', 'westshore_register_person_post_type' );

/**
 * A square crop for the portraits.
 *
 * Without a registered size close to what the card asks for, WordPress falls
 * back to the original file. Thirteen of the portraits had no intermediate size
 * anywhere near 160px, so the board page shipped 9MB of photographs to render
 * thirteen 72px circles, one of them a 4.2MB PNG, on a site that runs about 72%
 * mobile in season.
 *
 * Cropped rather than scaled, because these are portraits at every aspect from
 * 100x160 to 160x160 and a circle mask over an uncropped image cuts heads off
 * the tall ones.
 *
 * Existing uploads need the crop generating once: wp media regenerate
 * --only-missing over the person thumbnails.
 */
function westshore_person_image_size() {
	add_image_size( 'westshore-person', 160, 160, true );
}
add_action( 'after_setup_theme', 'westshore_person_image_size' );

/**
 * The group taxonomy: the board, and every team.
 *
 * One list covering both, because a role is always "this person, in this group,
 * with this title". A board seat and a coaching job are the same shape.
 *
 * show_ui is on so the club can add, rename, reorder and remove groups without
 * a developer. Teams fold and teams appear; the Velites folded the week this
 * was written. A hard-coded list would need a code change every time.
 *
 * meta_box_cb is false on purpose. The terms are set from the roles box below,
 * because a group with no role title attached to it says nothing. Two ways to
 * assign a group would let them disagree.
 */
function westshore_register_person_group_taxonomy() {
	register_taxonomy(
		'westshore_group',
		'westshore_person',
		array(
			'labels'            => array(
				'name'          => __( 'Groups', 'westshore' ),
				'singular_name' => __( 'Group', 'westshore' ),
				'add_new_item'  => __( 'Add group', 'westshore' ),
				'edit_item'     => __( 'Edit group', 'westshore' ),
				'menu_name'     => __( 'Groups', 'westshore' ),
			),
			'public'            => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'meta_box_cb'       => false,
			'hierarchical'      => false,
			'rewrite'           => false,
		)
	);
}
add_action( 'init', 'westshore_register_person_group_taxonomy' );

/**
 * The groups the site starts with.
 *
 * Seed values only. Once a term exists, the admin is the source of truth for
 * its name, its order and whether it shows on the Coaches page. This array is
 * never consulted again for a group that already exists.
 *
 * @return array<string, array<string, mixed>> Keyed by term slug.
 */
function westshore_person_group_seeds() {
	return array(
		// One person, on purpose. The registrar is asked for on several pages,
		// so a group of their own means changing who does the job is one edit
		// rather than a hunt.
		'registration'     => array(
			'name'    => __( 'Registration contact', 'westshore' ),
			'order'   => 5,
			'coaches' => false,
		),
		'board'            => array(
			'name'    => __( 'Board of Directors', 'westshore' ),
			'order'   => 10,
			'coaches' => false,
		),
		'senior-mens'      => array(
			'name'    => __( 'Mens Premier Valhallians', 'westshore' ),
			'order'   => 20,
			'coaches' => true,
		),
		'senior-womens'    => array(
			'name'    => __( 'Womens Premier Valkyries', 'westshore' ),
			'order'   => 30,
			'coaches' => true,
		),
		'mens-division-1'  => array(
			'name'    => __( 'Mens Division 1 Velox', 'westshore' ),
			'order'   => 40,
			'coaches' => true,
		),
		// The program as a whole, for the facts the front page publishes about
		// junior rugby generally rather than about one age group. Not on the
		// Coaches page: its coaches are the age groups below it, and a "Juniors"
		// line above them would either duplicate them or sit empty.
		'juniors'          => array(
			'name'    => __( 'Juniors', 'westshore' ),
			'order'   => 45,
			'coaches' => false,
		),
		'u18-boys'         => array(
			'name'    => __( 'U18 Boys', 'westshore' ),
			'order'   => 50,
			'coaches' => true,
		),
		'u16-boys'         => array(
			'name'    => __( 'U16 Boys', 'westshore' ),
			'order'   => 60,
			'coaches' => true,
		),
		'u14-boys'         => array(
			'name'    => __( 'U14 Boys', 'westshore' ),
			'order'   => 70,
			'coaches' => true,
		),
		'u18-girls'        => array(
			'name'    => __( 'U18 Girls', 'westshore' ),
			'order'   => 80,
			'coaches' => true,
		),
		'u16-girls'        => array(
			'name'    => __( 'U16 Girls', 'westshore' ),
			'order'   => 90,
			'coaches' => true,
		),
		'u14-girls'        => array(
			'name'    => __( 'U14 Girls', 'westshore' ),
			'order'   => 100,
			'coaches' => true,
		),
		'minis'            => array(
			'name'    => __( 'Minis', 'westshore' ),
			'order'   => 110,
			'coaches' => true,
		),

		// The mini age groups. They publish different training times from each
		// other, U12 training on weeknights while the rest play on a Sunday
		// morning, so each one needs somewhere of its own to keep that.
		//
		// The four squads the minis actually run, per the youth director on
		// 2026-09-13: U6 and U8 are one mixed squad, U10 is mixed, and U12 is
		// split by side. They were seeded as u6, u8, u10 and u12 before that
		// answer came in; staging renamed and removed those by hand.
		'u6-u8'            => array(
			'name'    => __( 'U6 and U8', 'westshore' ),
			'order'   => 111,
			'coaches' => true,
		),
		'u10'              => array(
			'name'    => __( 'U10', 'westshore' ),
			'order'   => 113,
			'coaches' => true,
		),
		'u12-boys'         => array(
			'name'    => __( 'U12 Boys', 'westshore' ),
			'order'   => 114,
			'coaches' => true,
		),
		'u12-girls'        => array(
			'name'    => __( 'U12 Girls', 'westshore' ),
			'order'   => 115,
			'coaches' => true,
		),

		// The contact panels on the team pages. Separate from the coaching
		// groups above because they are a different list: a captain and a
		// registrar are not coaches, and putting them in a coaching group would
		// put them on the Coaches page under a team.
		'senior-mens-contacts'   => array(
			'name'    => __( 'Senior Mens contacts', 'westshore' ),
			'order'   => 25,
			'coaches' => false,
		),
		'senior-womens-contacts' => array(
			'name'    => __( 'Senior Womens contacts', 'westshore' ),
			'order'   => 35,
			'coaches' => false,
		),
		// Two panels, not one, per the youth director on 2026-09-13: a parent
		// with a boys question and a parent with a girls question each get the
		// director for that side. Seeded as one juniors-contacts group before
		// that, which staging removed by hand.
		'juniors-boys-contacts'  => array(
			'name'    => __( 'Junior Boys contacts', 'westshore' ),
			'order'   => 105,
			'coaches' => false,
		),
		'juniors-girls-contacts' => array(
			'name'    => __( 'Junior Girls contacts', 'westshore' ),
			'order'   => 106,
			'coaches' => false,
		),
		'minis-contacts'         => array(
			'name'    => __( 'Minis contacts', 'westshore' ),
			'order'   => 115,
			'coaches' => false,
		),
		'program-contacts' => array(
			'name'    => __( 'Program contacts', 'westshore' ),
			'order'   => 120,
			'coaches' => false,
		),

		// The Emergency Action Plan. Two groups because the page draws a line
		// between them: the charge and call person are the club's own people,
		// and the facility list is who the call person phones after 911. Two of
		// the five are board members who change with the board, and were the
		// last people on the site typed as prose with a phone number beside
		// them. Neither group is a coaching group, and neither goes near the
		// Coaches page.
		'emergency-charge'   => array(
			'name'    => __( 'Emergency charge and call person', 'westshore' ),
			'order'   => 130,
			'coaches' => false,
		),
		'emergency-facility' => array(
			'name'    => __( 'Westshore Rec Centre facility and security', 'westshore' ),
			'order'   => 131,
			'coaches' => false,
		),

		// The Contact page. One person per question somebody actually arrives
		// with, so the role title here is the question ("Junior boys",
		// "Registration") rather than the job title. Separate from the board
		// group because the board is fifteen people and seven of them publish
		// no way to reach them, which is a roster and not a routing list.
		'who-to-ask'       => array(
			'name'    => __( 'Who to ask', 'westshore' ),
			'order'   => 5,
			'coaches' => false,
		),
	);
}

/**
 * Create the starting groups if they are missing.
 *
 * Never overwrites an existing term or its meta, so reactivating the plugin
 * cannot undo a group the club has renamed, reordered or unticked.
 */
function westshore_seed_person_groups() {
	$seeds = array();

	foreach ( westshore_person_group_seeds() as $slug => $group ) {
		$seeds[ $slug ] = array(
			'name' => $group['name'],
			'meta' => array(
				WESTSHORE_GROUP_ORDER_META   => $group['order'],
				WESTSHORE_GROUP_COACHES_META => $group['coaches'] ? 1 : 0,
			),
		);
	}

	westshore_seed_terms( 'westshore_group', $seeds );
}

/**
 * Every group, in display order.
 *
 * Ordered by the term meta rather than by name or ID, so the club controls the
 * running order from the admin. A group with no order set sorts last rather
 * than first, because an unset value is far more likely to be a new group than
 * a deliberate "put this at the top".
 *
 * @param bool $coaches_only Only groups ticked to show on the Coaches page.
 * @return array<int, WP_Term>
 */
function westshore_get_person_groups( $coaches_only = false ) {
	$terms = westshore_ordered_terms( 'westshore_group' );

	if ( ! $coaches_only ) {
		return $terms;
	}

	return array_values(
		array_filter(
			$terms,
			function ( $term ) {
				return (bool) get_term_meta( $term->term_id, WESTSHORE_GROUP_COACHES_META, true );
			}
		)
	);
}

/**
 * The settings a group carries.
 *
 * Registered through the shared term-settings API, which builds the add form,
 * the edit form and the save from this one array. Before that these were two
 * hand-written forms and a hand-written save, which is how the Coaches page
 * checkbox came to be clearable by a Quick Edit that never showed it. The
 * hidden marker that fixed it now lives in the API, so no future field can
 * reintroduce the bug.
 *
 * @return array<string, array<string, mixed>>
 */
function westshore_person_group_fields() {
	return array(
		'order'   => array(
			'label'       => __( 'Order', 'westshore' ),
			'type'        => 'number',
			'meta'        => WESTSHORE_GROUP_ORDER_META,
			'placeholder' => '',
			'help'        => __( 'Lower numbers come first. Leave it empty and the group sorts to the end.', 'westshore' ),
		),
		'coaches' => array(
			'label'        => __( 'Coaches page', 'westshore' ),
			'label_inline' => __( 'Show this group on the Coaches page', 'westshore' ),
			'type'         => 'checkbox',
			'meta'         => WESTSHORE_GROUP_COACHES_META,
			'help'         => __( 'Tick this for a team. Leave it clear for the board and for contact lists.', 'westshore' ),
		),
	);
}

/**
 * Register the group settings screens.
 */
function westshore_register_person_group_settings() {
	/**
	 * The settings a group carries.
	 *
	 * Filtered so season.php can add the training and games times without this
	 * file having to know about them. A group's training time is season data
	 * that happens to hang off a group, not something people and roles need.
	 *
	 * @param array<string, array<string, mixed>> $fields Field descriptions.
	 */
	$fields = apply_filters( 'westshore_person_group_fields', westshore_person_group_fields() );

	westshore_register_term_settings( 'westshore_group', 'westshore_group', $fields );
}
add_action( 'init', 'westshore_register_person_group_settings' );

/**
 * A person's roles, cleaned up and in order.
 *
 * Rows with no group are dropped: an empty slot in the meta box is not a role.
 *
 * @param int $post_id Person.
 * @return array<int, array{group:string, title:string, order:int}>
 */
function westshore_get_person_roles( $post_id ) {
	$roles = get_post_meta( $post_id, WESTSHORE_PERSON_ROLES_META, true );

	if ( ! is_array( $roles ) ) {
		return array();
	}

	$clean = array();

	foreach ( $roles as $role ) {
		if ( empty( $role['group'] ) ) {
			continue;
		}

		$clean[] = array(
			'group' => (string) $role['group'],
			'title' => isset( $role['title'] ) ? (string) $role['title'] : '',
			'order' => isset( $role['order'] ) ? (int) $role['order'] : 0,
		);
	}

	usort(
		$clean,
		function ( $a, $b ) {
			return $a['order'] <=> $b['order'];
		}
	);

	return $clean;
}

/**
 * Every role title already in use, for the suggestion list.
 *
 * Titles are free text on purpose: "Attack Coach" did not exist on this site
 * until the week this was written, and a fixed list would need a developer
 * every time the club invents a role. The datalist is the only guard against
 * "Head Coach" and "head coach" drifting apart. It suggests, it does not
 * restrict.
 *
 * @return array<int, string>
 */
function westshore_person_role_titles() {
	$people = get_posts(
		array(
			'post_type'        => 'westshore_person',
			'post_status'      => 'any',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'suppress_filters' => false,
		)
	);

	$titles = array();

	foreach ( $people as $id ) {
		foreach ( westshore_get_person_roles( $id ) as $role ) {
			if ( '' !== $role['title'] ) {
				$titles[ $role['title'] ] = true;
			}
		}
	}

	$titles = array_keys( $titles );
	sort( $titles );

	return $titles;
}

/**
 * The meta boxes on a person.
 */
function westshore_person_meta_boxes() {
	add_meta_box(
		'westshore-person-roles',
		__( 'Roles', 'westshore' ),
		'westshore_person_roles_box',
		'westshore_person',
		'normal',
		'high'
	);

	add_meta_box(
		'westshore-person-contact',
		__( 'Contact details', 'westshore' ),
		'westshore_person_contact_box',
		'westshore_person',
		'normal',
		'default'
	);
}
add_action( 'add_meta_boxes', 'westshore_person_meta_boxes' );

/**
 * The roles box.
 *
 * @param WP_Post $post The person being edited.
 */
function westshore_person_roles_box( $post ) {
	wp_nonce_field( 'westshore_person_save', 'westshore_person_nonce' );

	$roles  = westshore_get_person_roles( $post->ID );
	$groups = westshore_get_person_groups();
	$titles = westshore_person_role_titles();

	// The constant is where the box starts, not a ceiling. The save handler
	// rebuilds the whole roles array from what the form posts, so rendering a
	// fixed six rows for somebody the import gave seven roles silently dropped
	// the seventh the first time anybody opened and saved them. Always show at
	// least one empty row, so there is somewhere to add the next one.
	$slots = max( WESTSHORE_PERSON_ROLE_SLOTS, count( $roles ) + 1 );
	?>
	<p class="description">
		<?php esc_html_e( 'A person can hold more than one role. Leave a row empty if it is not needed. To add a team or a board section, go to People then Groups.', 'westshore' ); ?>
	</p>

	<datalist id="westshore-role-titles">
		<?php foreach ( $titles as $title ) : ?>
			<option value="<?php echo esc_attr( $title ); ?>"></option>
		<?php endforeach; ?>
	</datalist>

	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Group', 'westshore' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Role title', 'westshore' ); ?></th>
				<th scope="col" style="width:6em;"><?php esc_html_e( 'Order', 'westshore' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php for ( $i = 0; $i < $slots; $i++ ) : ?>
			<?php
			$row   = isset( $roles[ $i ] ) ? $roles[ $i ] : array(
				'group' => '',
				'title' => '',
				'order' => 0,
			);
			$field = 'westshore_person_roles[' . $i . ']';
			?>
			<tr>
				<td>
					<label class="screen-reader-text" for="westshore-role-group-<?php echo (int) $i; ?>">
						<?php esc_html_e( 'Group', 'westshore' ); ?>
					</label>
					<select id="westshore-role-group-<?php echo (int) $i; ?>" name="<?php echo esc_attr( $field ); ?>[group]">
						<option value=""><?php esc_html_e( '— none —', 'westshore' ); ?></option>
						<?php foreach ( $groups as $group ) : ?>
							<option value="<?php echo esc_attr( $group->slug ); ?>" <?php selected( $row['group'], $group->slug ); ?>>
								<?php echo esc_html( $group->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
				<td>
					<label class="screen-reader-text" for="westshore-role-title-<?php echo (int) $i; ?>">
						<?php esc_html_e( 'Role title', 'westshore' ); ?>
					</label>
					<input type="text" class="large-text" list="westshore-role-titles"
						id="westshore-role-title-<?php echo (int) $i; ?>"
						name="<?php echo esc_attr( $field ); ?>[title]"
						value="<?php echo esc_attr( $row['title'] ); ?>"
						placeholder="<?php esc_attr_e( 'Head Coach, Forwards, Director of Senior Mens', 'westshore' ); ?>" />
				</td>
				<td>
					<label class="screen-reader-text" for="westshore-role-order-<?php echo (int) $i; ?>">
						<?php esc_html_e( 'Order', 'westshore' ); ?>
					</label>
					<input type="number" class="small-text"
						id="westshore-role-order-<?php echo (int) $i; ?>"
						name="<?php echo esc_attr( $field ); ?>[order]"
						value="<?php echo esc_attr( (string) $row['order'] ); ?>" />
				</td>
			</tr>
		<?php endfor; ?>
		</tbody>
	</table>
	<?php
}

/**
 * The contact box.
 *
 * @param WP_Post $post The person being edited.
 */
function westshore_person_contact_box( $post ) {
	$email = get_post_meta( $post->ID, WESTSHORE_PERSON_EMAIL_META, true );
	$phone = get_post_meta( $post->ID, WESTSHORE_PERSON_PHONE_META, true );
	?>
	<p>
		<label for="westshore-person-email"><strong><?php esc_html_e( 'Email', 'westshore' ); ?></strong></label><br />
		<input type="email" id="westshore-person-email" name="westshore_person_email" class="large-text"
			value="<?php echo esc_attr( $email ); ?>" /><br />
		<span class="description"><?php esc_html_e( 'Optional. Leave it empty and no address is published for this person.', 'westshore' ); ?></span>
	</p>
	<p>
		<label for="westshore-person-phone"><strong><?php esc_html_e( 'Phone', 'westshore' ); ?></strong></label><br />
		<input type="text" id="westshore-person-phone" name="westshore_person_phone" class="large-text"
			value="<?php echo esc_attr( $phone ); ?>" placeholder="+1 (250) 555-1234" /><br />
		<span class="description"><?php esc_html_e( 'Optional. This appears on a public page, so only publish a number the person is happy to have public.', 'westshore' ); ?></span>
	</p>
	<?php
}

/**
 * Save a person.
 *
 * The group terms are set from the role rows rather than entered separately.
 * That mirroring is what lets a page ask for "everyone in u16-boys" with a
 * normal taxonomy query instead of a meta scan across every person, while the
 * titles and the ordering stay in the meta where they belong.
 *
 * @param int $post_id The person being saved.
 */
function westshore_person_save( $post_id ) {
	if ( ! westshore_can_save_post( $post_id, 'westshore_person_nonce', 'westshore_person_save' ) ) {
		return;
	}

	$email = isset( $_POST['westshore_person_email'] )
		? sanitize_email( wp_unslash( $_POST['westshore_person_email'] ) )
		: '';

	$phone = isset( $_POST['westshore_person_phone'] )
		? sanitize_text_field( wp_unslash( $_POST['westshore_person_phone'] ) )
		: '';

	update_post_meta( $post_id, WESTSHORE_PERSON_EMAIL_META, $email );
	update_post_meta( $post_id, WESTSHORE_PERSON_PHONE_META, $phone );

	$submitted = isset( $_POST['westshore_person_roles'] )
		? (array) wp_unslash( $_POST['westshore_person_roles'] )
		: array();

	$roles = array();
	$slugs = array();

	foreach ( $submitted as $row ) {
		$group = isset( $row['group'] ) ? sanitize_key( $row['group'] ) : '';

		if ( '' === $group ) {
			continue;
		}

		// A slug that no longer matches a term means the group was renamed or
		// deleted while this person was open in another tab. Drop the row
		// rather than writing a role pointing at nothing.
		if ( ! get_term_by( 'slug', $group, 'westshore_group' ) ) {
			continue;
		}

		$roles[] = array(
			'group' => $group,
			'title' => isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '',
			'order' => isset( $row['order'] ) ? (int) $row['order'] : 0,
		);

		$slugs[ $group ] = true;
	}

	update_post_meta( $post_id, WESTSHORE_PERSON_ROLES_META, $roles );
	wp_set_object_terms( $post_id, array_keys( $slugs ), 'westshore_group', false );
}
add_action( 'save_post_westshore_person', 'westshore_person_save' );

/**
 * Fill the roles column.
 *
 * @param int $post_id Person ID.
 */
function westshore_person_roles_column( $post_id ) {
	$lines = array();

	foreach ( westshore_get_person_roles( $post_id ) as $role ) {
		$term  = get_term_by( 'slug', $role['group'], 'westshore_group' );
		$where = $term ? $term->name : $role['group'];

		$lines[] = '' === $role['title']
			? esc_html( $where )
			: esc_html( $role['title'] . ', ' . $where );
	}

	echo $lines
		? wp_kses_post( implode( '<br />', $lines ) )
		: westshore_admin_missing( __( 'No role', 'westshore' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Fill the contact column.
 *
 * @param int $post_id Person ID.
 */
function westshore_person_contact_column( $post_id ) {
	$bits = array_filter(
		array(
			get_post_meta( $post_id, WESTSHORE_PERSON_EMAIL_META, true ),
			get_post_meta( $post_id, WESTSHORE_PERSON_PHONE_META, true ),
		)
	);

	echo $bits ? esc_html( implode( ' · ', $bits ) ) : '&mdash;';
}

/**
 * Roles and contact details in the people list.
 *
 * A list of names does not tell you who has no role or no way to reach them,
 * which is the thing somebody opens this screen to find.
 */
function westshore_register_person_columns() {
	westshore_register_admin_column( 'westshore_person', 'westshore_roles', __( 'Roles', 'westshore' ), 'westshore_person_roles_column' );
	westshore_register_admin_column( 'westshore_person', 'westshore_contact', __( 'Contact', 'westshore' ), 'westshore_person_contact_column' );
}
add_action( 'init', 'westshore_register_person_columns' );
