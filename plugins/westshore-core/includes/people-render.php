<?php
/**
 * People: the two shortcodes that put them on a page.
 *
 * [westshore_coaches]                          the whole Coaches page
 * [westshore_people group="board"]             one group, as cards
 * [westshore_people group="u16-boys" layout="line"]   one group, on one line
 *
 * Follows sponsors-render.php: plain strings, one stylesheet enqueued only when
 * a shortcode actually runs, and nothing rendered at all when there is nothing
 * to show.
 *
 * @package Westshore_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load the stylesheet, once, and only on a page that uses it.
 *
 * Registered rather than enqueued at load time so a page with no people on it
 * does not carry the CSS.
 */
function westshore_people_styles() {
	westshore_lazy_style( 'westshore-people', 'people.css' );
}

/**
 * Everyone in a group, in the order their role rows give.
 *
 * The taxonomy finds them, the role meta orders them. Sorting has to happen
 * here rather than in the query because the order is per role: somebody who is
 * first on the board is not necessarily first on a team.
 *
 * @param string $slug Group slug.
 * @return array<int, array{post:WP_Post, title:string, order:int}>
 */
function westshore_get_people_in_group( $slug ) {
	$people = get_posts(
		array(
			'post_type'        => 'westshore_person',
			'post_status'      => 'publish',
			'numberposts'      => -1,
			'suppress_filters' => false,
			'tax_query'        => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'westshore_group',
					'field'    => 'slug',
					'terms'    => $slug,
				),
			),
		)
	);

	$rows = array();

	foreach ( $people as $person ) {
		foreach ( westshore_get_person_roles( $person->ID ) as $role ) {
			if ( $role['group'] !== $slug ) {
				continue;
			}

			$rows[] = array(
				'post'  => $person,
				'title' => $role['title'],
				'order' => $role['order'],
			);
		}
	}

	usort(
		$rows,
		function ( $a, $b ) {
			if ( $a['order'] === $b['order'] ) {
				return strcmp( $a['post']->post_title, $b['post']->post_title );
			}

			return $a['order'] <=> $b['order'];
		}
	);

	return $rows;
}

/**
 * Join names the way the club writes them: commas, and an ampersand last.
 *
 * "Noa Molia, Amy Camicioli & Hailey Sampson", which is exactly how the Juniors
 * page has always written it. Matching the existing house style rather than
 * inventing one means the switch to this system changes nothing visible.
 *
 * @param array<int, string> $parts Names, already escaped.
 * @return string
 */
function westshore_join_names( $parts ) {
	$count = count( $parts );

	if ( 0 === $count ) {
		return '';
	}

	if ( 1 === $count ) {
		return $parts[0];
	}

	$last = array_pop( $parts );

	return implode( ', ', $parts ) . ' &amp; ' . $last;
}

/**
 * One group as a line: the group name, then the people.
 *
 * Role titles are shown in brackets only where a group actually uses them. A
 * junior team whose coaches have no titles reads as a plain list of names, the
 * way it does today; the mens side, which has a head coach and three
 * specialists, reads with them.
 *
 * @param WP_Term $group Group.
 * @return string
 */
function westshore_render_group_line( $group, $heading = true ) {
	$rows = westshore_get_people_in_group( $group->slug );

	if ( ! $rows ) {
		return '';
	}

	$names = array();

	foreach ( $rows as $row ) {
		$name = esc_html( $row['post']->post_title );

		if ( '' !== $row['title'] ) {
			$name .= ' <span class="wsr-person-role">' . esc_html( $row['title'] ) . '</span>';
		}

		$names[] = $name;
	}

	// heading="no" used to be silently ignored here while it worked on the other
	// two layouts, so the attribute was lying about what it did.
	$label = $heading
		? '<strong>' . esc_html( $group->name ) . '</strong> '
		: '';

	return '<p class="wsr-people-line">' . $label . westshore_join_names( $names ) . '</p>';
}

/**
 * One group as cards: photo, role, name, email, phone.
 *
 * The Board page shape. Contact details are only printed when they exist, so a
 * person with no published email does not leave an empty line behind.
 *
 * $fields exists because a person's details are not equally public everywhere.
 * The registrar's phone number has always been on the senior team pages and his
 * email has not, and rendering the whole entry there would have published an
 * address on two pages that never carried it. Narrowing what is shown is a
 * decision the page makes, not the person entry.
 *
 * @param WP_Term            $group   Group.
 * @param bool               $heading Print the group name above the cards.
 * @param array<int, string> $fields  Which contact details to print.
 * @return string
 */
function westshore_render_group_cards( $group, $heading = true, $fields = array( 'email', 'phone' ) ) {
	$rows = westshore_get_people_in_group( $group->slug );

	if ( ! $rows ) {
		return '';
	}

	$out = '';

	if ( $heading ) {
		$out .= '<h2 class="wsr-people-heading">' . esc_html( $group->name ) . '</h2>';
	}

	return $out . '<div class="wsr-people wsr-people--cards">'
		. westshore_render_person_cards( $rows, $fields ) . '</div>';
}

/**
 * The cards themselves, without the wrapper or the heading.
 *
 * Its own function because the roster below renders the same card for the half
 * of a group that can actually be reached, and two copies of this loop would
 * drift the first time one of them was changed.
 *
 * @param array<int, array{post:WP_Post, title:string, order:int}> $rows   People with their role in this group.
 * @param array<int, string>                                      $fields Which contact details to print.
 * @return string
 */
function westshore_render_person_cards( $rows, $fields ) {
	$cards = '';

	foreach ( $rows as $row ) {
		$person = $row['post'];
		$email  = get_post_meta( $person->ID, WESTSHORE_PERSON_EMAIL_META, true );
		$phone  = get_post_meta( $person->ID, WESTSHORE_PERSON_PHONE_META, true );

		$card = '<div class="wsr-person">';

		if ( has_post_thumbnail( $person->ID ) ) {
			$card .= '<div class="wsr-person-photo">'
				. get_the_post_thumbnail( $person->ID, 'westshore-person', array( 'alt' => esc_attr( $person->post_title ) ) )
				. '</div>';
		}

		$card .= '<div class="wsr-person-text">';

		if ( '' !== $row['title'] ) {
			$card .= '<div class="wsr-person-title">' . esc_html( $row['title'] ) . '</div>';
		}

		$card .= '<div class="wsr-person-name">' . esc_html( $person->post_title ) . '</div>';

		if ( $email && in_array( 'email', $fields, true ) ) {
			$card .= '<div class="wsr-person-contact"><a href="' . esc_url( 'mailto:' . $email ) . '">'
				. esc_html( $email ) . '</a></div>';
		}

		if ( $phone && in_array( 'phone', $fields, true ) ) {
			// A tel: link, so the number is tappable on the touchline. The
			// stored string keeps its spaces and brackets for reading; the href
			// is digits and a leading plus, which is all a dialler wants.
			$dial  = preg_replace( '/(?!^\+)[^0-9]/', '', $phone );
			$card .= '<div class="wsr-person-contact"><a href="' . esc_attr( 'tel:' . $dial ) . '">'
				. esc_html( $phone ) . '</a></div>';
		}

		$card .= '</div></div>';

		$cards .= $card;
	}

	return $cards;
}

/**
 * One group as a roster: everybody, in role order, one compact row each.
 *
 * The board is fifteen people, and as full cards that ran 2,095px on a phone.
 * The first version of this split them, cards for the eight who publish an
 * email and a list for the other seven, which halved it and then put the
 * President, the Vice President, the Secretary and the Treasurer below the
 * junior directors, because none of the four publishes an address. A board page
 * that opens on the people who happen to have an email is worse than a long
 * one.
 *
 * So it is one list in the order the club set, the photographs kept, the row
 * about half the height of a card, and two columns from 520px. An email appears
 * on the row where there is one and the row is silent where there is not, which
 * is the honest version of the same thing.
 *
 * @param WP_Term            $group   Group.
 * @param bool               $heading Print the group name above the roster.
 * @param array<int, string> $fields  Which contact details to print.
 * @return string
 */
function westshore_render_group_roster( $group, $heading = true, $fields = array( 'email', 'phone' ) ) {
	$rows = westshore_get_people_in_group( $group->slug );

	if ( ! $rows ) {
		return '';
	}

	$out = '';

	if ( $heading ) {
		$out .= '<h2 class="wsr-people-heading">' . esc_html( $group->name ) . '</h2>';
	}

	return $out . '<div class="wsr-people wsr-people--roster">'
		. westshore_render_person_cards( $rows, $fields ) . '</div>';
}

/**
 * A group, by its slug or by the name a volunteer would recognise.
 *
 * The slug is what the code uses and the name is what the admin shows, and
 * nobody typing a shortcode into a page should have to know the difference.
 * group="U16 Boys" and group="u16-boys" both work.
 *
 * Slug first, because it is the stable identifier and a rename does not move
 * it. Renaming a group therefore does not break a page that used the slug,
 * which is the reason to prefer it.
 *
 * @param string $value Slug or display name.
 * @return WP_Term|null
 */
function westshore_find_group( $value ) {
	return westshore_find_term_loosely( 'westshore_group', $value );
}

/**
 * [westshore_coaches]
 *
 * Every group ticked "show on the Coaches page", in order. No group list here
 * on purpose: adding a team is ticking a box in the admin, not editing a page
 * or this file. That is the difference between a system the club can run and
 * one that needs a developer every season.
 *
 * @return string
 */
function westshore_coaches_shortcode() {
	$out = '';

	foreach ( westshore_get_person_groups( true ) as $group ) {
		$out .= westshore_render_group_line( $group );
	}

	if ( '' === $out ) {
		// Nobody assigned to any coaching group means the import has not run.
		// An empty page section would read as a layout bug, so print nothing.
		return '';
	}

	westshore_people_styles();

	return '<div class="wsr-people wsr-people--lines">' . $out . '</div>';
}
add_shortcode( 'westshore_coaches', 'westshore_coaches_shortcode' );

/**
 * [westshore_people group="board" layout="cards"]
 *
 * group takes either the slug or the name shown in the admin, so
 * group="U16 Boys" works as well as group="u16-boys". layout is "cards" or
 * "line". heading="no" drops the group name, for a page that already has its
 * own heading above the shortcode. fields narrows which contact details are
 * printed: fields="phone" for a page that should show a number and not an
 * address.
 *
 * A group slug that does not exist is the one failure worth being loud about:
 * it happens when somebody renames a group in the admin and a page still names
 * the old slug, and the page then silently shows nothing. It logs, so the
 * reason is findable, and still prints nothing to the visitor.
 *
 * @param array<string, string>|string $atts Shortcode attributes.
 * @return string
 */
function westshore_people_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'group'   => '',
			'layout'  => 'cards',
			'heading' => 'yes',
			'fields'  => 'email,phone',
		),
		$atts,
		'westshore_people'
	);

	$group = westshore_find_group( $atts['group'] );

	if ( ! $group ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				sprintf(
					'[westshore_people] no group with the slug "%s". The group was probably renamed; this page still asks for the old one.',
					$atts['group']
				)
			);
		}

		return '';
	}

	$fields = array_filter( array_map( 'trim', explode( ',', $atts['fields'] ) ) );

	if ( 'roster' === $atts['layout'] ) {
		$out = westshore_render_group_roster( $group, 'no' !== $atts['heading'], $fields );
	} elseif ( 'line' === $atts['layout'] ) {
		$out = westshore_render_group_line( $group, 'no' !== $atts['heading'] );
	} else {
		$out = westshore_render_group_cards( $group, 'no' !== $atts['heading'], $fields );
	}

	if ( '' === $out ) {
		return '';
	}

	westshore_people_styles();

	return 'line' === $atts['layout']
		? '<div class="wsr-people wsr-people--lines">' . $out . '</div>'
		: $out;
}
add_shortcode( 'westshore_people', 'westshore_people_shortcode' );
