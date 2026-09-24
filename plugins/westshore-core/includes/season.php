<?php
/**
 * The things that change every season, in one place.
 *
 * Three sets of facts were typed onto pages and went stale there. Training days
 * and times and the ground were in thirteen or more places across five page
 * files. The season label was in eight files in four different formats. The
 * registration prices were five cards on one page, and the Play HQ URL, which
 * carries a per-season club id, was on five.
 *
 * This has already been wrong on the live site. The front page had juniors
 * training on Wednesdays while the Junior Rugby page said Tuesdays and
 * Thursdays; Wednesday appeared nowhere else on the site at all.
 *
 * Two homes, because it is two kinds of fact:
 *
 * - Training and games times are term meta on the group, because the group is
 *   already the thing a page names. A page asking for U16 Boys' training time
 *   reads the same way as a page asking for U16 Boys' coaches.
 * - Everything else is one option behind one settings screen, because it is
 *   club-wide rather than per team. Five fee rows that all change together once
 *   a year do not earn a post type, and one screen is one place to look each
 *   August.
 *
 * The shortcodes return bare strings on purpose. The same facts are published
 * as paragraph pairs on the senior pages, as coloured columns on Juniors, as a
 * table on Minis and as a facts band on the front page. A shortcode that
 * rendered a whole panel would have replaced four layouts with one; these drop
 * into the markup that is already there.
 *
 * @package Westshore_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const WESTSHORE_SEASON_OPTION = 'westshore_season';

/**
 * The season settings, with every key present.
 *
 * @return array<string, mixed>
 */
function westshore_season_settings() {
	$stored = get_option( WESTSHORE_SEASON_OPTION, array() );

	if ( ! is_array( $stored ) ) {
		$stored = array();
	}

	return array_merge(
		array(
			'start_year'     => 0,
			'register_url'   => '',
			'ground'         => '',
			'fees'           => array(),
			'playhq_key'     => '',
			'playhq_teams'   => array(),
		),
		$stored
	);
}

/**
 * The season written the way a given page writes it.
 *
 * The start year is stored, not the label, so the four formats already
 * published stay as they are. The Registration page and the Board page say
 * 2026/2027, the front page button and the Teams page say 2026/27, and the
 * Juniors coach heading says a hyphenated short form. Storing one string would
 * have forced all of them into one shape, which is a change to published
 * wording made for the convenience of the code.
 *
 * @param string $format 'long', 'short' or 'hyphen'.
 * @return string
 */
function westshore_season_label( $format = 'long' ) {
	$year = (int) westshore_season_settings()['start_year'];

	if ( $year < 1 ) {
		return '';
	}

	$next = $year + 1;

	if ( 'short' === $format ) {
		return sprintf( '%d/%02d', $year, $next % 100 );
	}

	if ( 'hyphen' === $format ) {
		return sprintf( '%d-%02d', $year, $next % 100 );
	}

	return sprintf( '%d/%d', $year, $next );
}

/**
 * Training and games times, as two more settings on a group.
 *
 * Added through the same declarative field API the order and Coaches-page
 * settings use, so they appear on the group add and edit screens with no extra
 * form code and with the hidden-marker guard already in place.
 *
 * @param array<string, array<string, mixed>> $fields Existing group fields.
 * @return array<string, array<string, mixed>>
 */
function westshore_season_group_fields( $fields ) {
	$fields['training'] = array(
		'label'       => __( 'Training', 'westshore' ),
		'type'        => 'text',
		'meta'        => 'westshore_training',
		'placeholder' => __( 'Tuesdays and Thursdays, 6:30pm to 8:00pm', 'westshore' ),
		'help'        => __( 'The days and the time, written the way it should read on the page. Every page that publishes this team\'s training time reads it from here.', 'westshore' ),
	);

	$fields['games'] = array(
		'label'       => __( 'Games', 'westshore' ),
		'type'        => 'text',
		'meta'        => 'westshore_games',
		'placeholder' => __( 'Saturdays through the BC season', 'westshore' ),
		'help'        => __( 'Leave it empty if this team publishes no game day.', 'westshore' ),
	);

	return $fields;
}
add_filter( 'westshore_person_group_fields', 'westshore_season_group_fields' );

/**
 * One of a group's season fields, by group slug or display name.
 *
 * @param string $group Group slug or name.
 * @param string $key   'training' or 'games'.
 * @return string
 */
function westshore_group_season_value( $group, $key ) {
	$term = westshore_find_term_loosely( 'westshore_group', $group );

	if ( ! $term ) {
		// The failure worth being loud about: somebody renamed a group's slug
		// and a page still asks for the old one, so the page silently loses a
		// training time and reads as though the team does not train.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				sprintf( '[westshore_%s] no group with the slug "%s".', $key, $group )
			);
		}

		return '';
	}

	return (string) get_term_meta( $term->term_id, 'westshore_' . $key, true );
}

/**
 * The card grid of registration brackets and prices.
 *
 * The one shortcode here that renders markup rather than a string, because the
 * brackets are a list that changes: the club can drop a bracket or add one and
 * the row count follows. It emits the same block classes the page already used,
 * which the theme already styles, so no CSS comes with it.
 *
 * Final HTML rather than block comments, because shortcodes run after the block
 * parser has been and gone. That means the layout classes WordPress adds while
 * rendering a real block have to be written out here, and one of them matters:
 * is-layout-flow zeroes the default margins on a column's children and puts a
 * uniform gap between them instead. Without it the paragraphs inside a fee card
 * fall back to their own margins and the card spacing changes. The wrapper's
 * flex classes are not copied, because the theme gives .westshore-cards its own
 * grid and its own gap, so they would be inert.
 *
 * @return string
 */
function westshore_fees_shortcode() {
	$fees = westshore_season_settings()['fees'];

	if ( ! $fees ) {
		// Nothing configured means the settings screen has not been filled in.
		// An empty row of cards would read as a layout fault, so print nothing.
		return '';
	}

	$cards = '';

	foreach ( $fees as $fee ) {
		if ( '' === trim( (string) $fee['bracket'] ) ) {
			continue;
		}

		$cards .= '<div class="wp-block-column westshore-card is-layout-flow wp-block-column-is-layout-flow">'
			. '<h3 class="wp-block-heading has-large-font-size">'
			. esc_html( $fee['bracket'] ) . '</h3>'
			. "\n\n\n\n" . '<p class="has-x-large-font-size">' . esc_html( $fee['price'] ) . '</p>';

		if ( '' !== trim( (string) $fee['note'] ) ) {
			$cards .= "\n\n\n\n" . '<p class="has-small-font-size">' . esc_html( $fee['note'] ) . '</p>';
		}

		$cards .= '</div>';
	}

	if ( '' === $cards ) {
		return '';
	}

	return '<div class="wp-block-columns westshore-cards">' . $cards . '</div>';
}
add_shortcode( 'westshore_fees', 'westshore_fees_shortcode' );

/**
 * The season label. [westshore_season format="short"]
 *
 * @param array<string, string>|string $atts Attributes.
 * @return string
 */
function westshore_season_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'format' => 'long' ), $atts, 'westshore_season' );

	return esc_html( westshore_season_label( $atts['format'] ) );
}
add_shortcode( 'westshore_season', 'westshore_season_shortcode' );

/**
 * A team's training time. [westshore_training group="u16-boys"]
 *
 * @param array<string, string>|string $atts Attributes.
 * @return string
 */
function westshore_training_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'group' => '' ), $atts, 'westshore_training' );

	return esc_html( westshore_group_season_value( $atts['group'], 'training' ) );
}
add_shortcode( 'westshore_training', 'westshore_training_shortcode' );

/**
 * A team's game day. [westshore_games group="juniors"]
 *
 * @param array<string, string>|string $atts Attributes.
 * @return string
 */
function westshore_games_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'group' => '' ), $atts, 'westshore_games' );

	return esc_html( westshore_group_season_value( $atts['group'], 'games' ) );
}
add_shortcode( 'westshore_games', 'westshore_games_shortcode' );

/**
 * The ground. [westshore_ground]
 *
 * The name only. The street address is published on exactly two pages, in two
 * deliberately different shapes, and it has not changed since the club moved in
 * 2015. A setting that controlled neither of them would be the sponsor tier
 * "Columns" box all over again: a control in the admin that does nothing.
 *
 * @return string
 */
function westshore_ground_shortcode() {
	return esc_html( westshore_season_settings()['ground'] );
}
add_shortcode( 'westshore_ground', 'westshore_ground_shortcode' );

/**
 * The Play HQ registration link, for an href.
 *
 * It carries a per-season club id, so it is a season setting rather than a
 * fixed address, and it was typed onto five pages.
 *
 * @return string
 */
function westshore_register_url_shortcode() {
	return esc_url( westshore_season_settings()['register_url'] );
}
add_shortcode( 'westshore_register_url', 'westshore_register_url_shortcode' );

/**
 * How many fee rows the settings screen shows.
 *
 * Fixed rows rather than an "add another" button, the same choice the person
 * roles box makes and for the same reason: it works with no build step and no
 * JavaScript. One more than the club currently uses, so there is always an
 * empty row to fill in.
 */
const WESTSHORE_SEASON_FEE_SLOTS = 7;

/**
 * The Season screen, under the Westshore menu.
 *
 * edit_posts rather than manage_options, matching the rest of the menu. The
 * people who change a price at the start of a season are the people who run the
 * site, and sending them to an administrator to do it is how a page ends up
 * saying "Pricing Coming Soon" through a registration window.
 */
function westshore_season_menu() {
	add_submenu_page(
		WESTSHORE_ADMIN_MENU_SLUG,
		__( 'Season', 'westshore' ),
		__( 'Season', 'westshore' ),
		'edit_posts',
		'westshore-season',
		'westshore_season_screen'
	);
}
add_action( 'admin_menu', 'westshore_season_menu', 11 );

/**
 * A Play HQ id as typed, kept to the characters a UUID has.
 *
 * Both the key and the team ids are UUIDs. Somebody pasting one from an email
 * brings a space or a full stop with it often enough to be worth stripping,
 * and anything else is not an id.
 *
 * @param string $value What was typed.
 * @return string
 */
function westshore_season_uuid( $value ) {
	return strtolower( preg_replace( '/[^0-9a-fA-F-]/', '', (string) $value ) );
}

/**
 * Drop every cached Play HQ answer.
 *
 * The transients expire on their own within the hour; this is for the moment
 * a volunteer saves a corrected team id and reloads the page to check it.
 */
function westshore_season_forget_playhq() {
	global $wpdb;

	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_westshore_playhq_%' OR option_name LIKE '_transient_timeout_westshore_playhq_%'"
	);
	delete_option( 'westshore_playhq_last' );
}

/**
 * Save the season settings.
 *
 * Hand-rolled rather than the Settings API because the fee rows are a repeater,
 * and options.php would need its capability filtered down from manage_options
 * anyway. Nonce, capability, then take the values.
 */
function westshore_season_save() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'You are not allowed to change the season settings.', 'westshore' ) );
	}

	check_admin_referer( 'westshore_season_save' );

	$fees = array();

	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$rows = isset( $_POST['westshore_fees'] ) ? (array) wp_unslash( $_POST['westshore_fees'] ) : array();

	foreach ( $rows as $row ) {
		$bracket = isset( $row['bracket'] ) ? sanitize_text_field( $row['bracket'] ) : '';

		// A row with no bracket name is an empty slot, not a fee.
		if ( '' === trim( $bracket ) ) {
			continue;
		}

		$fees[] = array(
			'bracket' => $bracket,
			'price'   => isset( $row['price'] ) ? sanitize_text_field( $row['price'] ) : '',
			'note'    => isset( $row['note'] ) ? sanitize_text_field( $row['note'] ) : '',
		);
	}

	$teams = array();

	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$rows = isset( $_POST['westshore_playhq_teams'] ) ? (array) wp_unslash( $_POST['westshore_playhq_teams'] ) : array();

	foreach ( $rows as $row ) {
		$name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';

		// A row with no name is an empty slot, not a team.
		if ( '' === trim( $name ) ) {
			continue;
		}

		$teams[] = array(
			// The slug is what a page types, so it is fixed here at save time
			// rather than derived on every read, and shown back on the screen.
			'slug'    => sanitize_title( $name ),
			'name'    => $name,
			'team_id' => isset( $row['team_id'] ) ? westshore_season_uuid( $row['team_id'] ) : '',
			// The page a scoreboard card links to, the side's own team page.
			'page_id' => isset( $row['page_id'] ) ? (int) $row['page_id'] : 0,
		);
	}

	$settings = array(
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		'start_year'     => isset( $_POST['westshore_start_year'] ) ? (int) $_POST['westshore_start_year'] : 0,
		'register_url'   => isset( $_POST['westshore_register_url'] ) ? esc_url_raw( wp_unslash( $_POST['westshore_register_url'] ) ) : '',
		'ground'         => isset( $_POST['westshore_ground'] ) ? sanitize_text_field( wp_unslash( $_POST['westshore_ground'] ) ) : '',
		'playhq_key'     => isset( $_POST['westshore_playhq_key'] ) ? westshore_season_uuid( wp_unslash( $_POST['westshore_playhq_key'] ) ) : '',
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		'fees'           => $fees,
		'playhq_teams'   => $teams,
	);

	// A new key or a changed team id makes every cached answer stale.
	$before = westshore_season_settings();

	if ( $before['playhq_key'] !== $settings['playhq_key'] || $before['playhq_teams'] !== $settings['playhq_teams'] ) {
		westshore_season_forget_playhq();
	}

	update_option( WESTSHORE_SEASON_OPTION, $settings );

	wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=westshore-season' ) ) );
	exit;
}
add_action( 'admin_post_westshore_season_save', 'westshore_season_save' );

/**
 * Render the Season screen.
 */
function westshore_season_screen() {
	$settings   = westshore_season_settings();
	$fees       = $settings['fees'];
	$slots      = max( WESTSHORE_SEASON_FEE_SLOTS, count( $fees ) + 1 );
	$teams      = array_values( (array) $settings['playhq_teams'] );
	$team_slots = max( WESTSHORE_PLAYHQ_TEAM_SLOTS, count( $teams ) + 1 );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Season', 'westshore' ); ?></h1>

		<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved. Every page that shows these has changed with them.', 'westshore' ); ?></p></div>
		<?php endif; ?>

		<p class="description" style="max-width:46em;font-size:14px;">
			<?php esc_html_e( 'The facts that change once a year. Everything here is published on several pages, and changing it here changes all of them.', 'westshore' ); ?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="westshore_season_save" />
			<?php wp_nonce_field( 'westshore_season_save' ); ?>

			<h2><?php esc_html_e( 'The season', 'westshore' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="westshore-start-year"><?php esc_html_e( 'Season start year', 'westshore' ); ?></label></th>
					<td>
						<input type="number" id="westshore-start-year" name="westshore_start_year" min="2000" max="2100"
							value="<?php echo esc_attr( (string) $settings['start_year'] ); ?>" />
						<p class="description">
							<?php
							printf(
								/* translators: 1: long form, 2: short form */
								esc_html__( 'Just the first year. Pages write it as %1$s or %2$s depending on where it sits, so both stay right.', 'westshore' ),
								'<code>' . esc_html( westshore_season_label( 'long' ) ) . '</code>',
								'<code>' . esc_html( westshore_season_label( 'short' ) ) . '</code>'
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="westshore-register-url"><?php esc_html_e( 'Registration link', 'westshore' ); ?></label></th>
					<td>
						<input type="url" id="westshore-register-url" name="westshore_register_url" class="large-text"
							value="<?php echo esc_attr( $settings['register_url'] ); ?>" />
						<p class="description"><?php esc_html_e( 'The Play HQ address. It carries a code that changes with the season, and five pages link to it.', 'westshore' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'The ground', 'westshore' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="westshore-ground"><?php esc_html_e( 'Name', 'westshore' ); ?></label></th>
					<td><input type="text" id="westshore-ground" name="westshore_ground" class="large-text"
						value="<?php echo esc_attr( $settings['ground'] ); ?>" /></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Play HQ', 'westshore' ); ?></h2>
			<p class="description" style="max-width:46em;">
				<?php esc_html_e( 'The senior pages read their fixtures, results and league tables straight from Play HQ, and the front page reads the next home game. Nothing here is typed onto a page.', 'westshore' ); ?>
			</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="westshore-playhq-key"><?php esc_html_e( 'API key', 'westshore' ); ?></label></th>
					<td>
						<input type="text" id="westshore-playhq-key" name="westshore_playhq_key" class="large-text code" autocomplete="off" spellcheck="false"
							value="<?php echo esc_attr( $settings['playhq_key'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Issued by BC Rugby, who ask Play HQ for it on the club\'s behalf. It reads public fixtures only. If it ever stops working, the club asks BC Rugby for a new one.', 'westshore' ); ?></p>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Teams', 'westshore' ); ?></h3>
			<p class="description" style="max-width:46em;">
				<?php esc_html_e( 'One row per side. The name is what the page prints above the table. The slug is what a page types to ask for it, as in [westshore_fixtures team="mens-premier"]. The page is where a scoreboard card sends a reader for the full season, normally the side\'s own team page. BC Rugby registers the teams again every season and every ID changes, so this table is part of the August job; the list underneath shows the current IDs to copy from.', 'westshore' ); ?>
			</p>
			<table class="widefat striped" style="max-width:1000px;">
				<thead>
					<tr>
						<th scope="col" style="width:16em;"><?php esc_html_e( 'Name', 'westshore' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Play HQ team ID', 'westshore' ); ?></th>
						<th scope="col" style="width:12em;"><?php esc_html_e( 'Page', 'westshore' ); ?></th>
						<th scope="col" style="width:11em;"><?php esc_html_e( 'Slug for pages', 'westshore' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php for ( $i = 0; $i < $team_slots; $i++ ) : ?>
					<?php
					$row = array_merge(
						array(
							'slug'    => '',
							'name'    => '',
							'team_id' => '',
							'page_id' => 0,
						),
						isset( $teams[ $i ] ) ? $teams[ $i ] : array()
					);
					?>
					<tr>
						<td>
							<label class="screen-reader-text" for="westshore-team-name-<?php echo (int) $i; ?>"><?php esc_html_e( 'Name', 'westshore' ); ?></label>
							<input type="text" id="westshore-team-name-<?php echo (int) $i; ?>" class="large-text"
								name="westshore_playhq_teams[<?php echo (int) $i; ?>][name]"
								value="<?php echo esc_attr( $row['name'] ); ?>" />
						</td>
						<td>
							<label class="screen-reader-text" for="westshore-team-id-<?php echo (int) $i; ?>"><?php esc_html_e( 'Play HQ team ID', 'westshore' ); ?></label>
							<input type="text" id="westshore-team-id-<?php echo (int) $i; ?>" class="large-text code" spellcheck="false"
								name="westshore_playhq_teams[<?php echo (int) $i; ?>][team_id]"
								value="<?php echo esc_attr( $row['team_id'] ); ?>" />
						</td>
						<td>
							<label class="screen-reader-text" for="westshore-team-page-<?php echo (int) $i; ?>"><?php esc_html_e( 'Page', 'westshore' ); ?></label>
							<?php
							wp_dropdown_pages(
								array(
									'id'                => 'westshore-team-page-' . $i,
									'name'              => 'westshore_playhq_teams[' . $i . '][page_id]',
									'selected'          => (int) $row['page_id'],
									'show_option_none'  => __( 'No link', 'westshore' ),
									'option_none_value' => 0,
									'class'             => 'westshore-team-page',
								)
							);
							?>
						</td>
						<td><code><?php echo esc_html( $row['slug'] ); ?></code></td>
					</tr>
				<?php endfor; ?>
				</tbody>
			</table>

			<?php if ( '' !== $settings['playhq_key'] ) : ?>
				<?php $listed = function_exists( 'westshore_playhq_club_teams' ) ? westshore_playhq_club_teams() : array(); ?>
				<h4><?php esc_html_e( 'What Play HQ lists for Westshore this season', 'westshore' ); ?></h4>
				<?php if ( $listed ) : ?>
					<table class="widefat" style="max-width:1000px;">
						<thead>
							<tr>
								<th scope="col" style="width:16em;"><?php esc_html_e( 'Play HQ name', 'westshore' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Team ID', 'westshore' ); ?></th>
								<th scope="col" style="width:12em;"><?php esc_html_e( 'Grade', 'westshore' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $listed as $team ) : ?>
							<tr>
								<td><?php echo esc_html( $team['name'] ); ?></td>
								<td><code><?php echo esc_html( $team['id'] ); ?></code></td>
								<td><?php echo '' !== $team['grade'] ? esc_html( $team['grade'] ) : '<em>' . esc_html__( 'none yet', 'westshore' ) . '</em>'; ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<p class="description"><?php esc_html_e( 'A team with no grade has no games to show yet. Read from Play HQ once a day.', 'westshore' ); ?></p>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'Play HQ did not answer with the key above. Check it, or try again in a few minutes.', 'westshore' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Registration dues', 'westshore' ); ?></h2>
			<p class="description" style="max-width:46em;">
				<?php esc_html_e( 'One row per bracket, in the order they should appear on the Registration page. Clear the bracket name to remove a row.', 'westshore' ); ?>
			</p>
			<table class="widefat striped" style="max-width:1000px;">
				<thead>
					<tr>
						<th scope="col" style="width:16em;"><?php esc_html_e( 'Bracket', 'westshore' ); ?></th>
						<th scope="col" style="width:9em;"><?php esc_html_e( 'Price', 'westshore' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Small line underneath', 'westshore' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php for ( $i = 0; $i < $slots; $i++ ) : ?>
					<?php
					$row = isset( $fees[ $i ] ) ? $fees[ $i ] : array(
						'bracket' => '',
						'price'   => '',
						'note'    => '',
					);
					?>
					<tr>
						<td>
							<label class="screen-reader-text" for="westshore-fee-bracket-<?php echo (int) $i; ?>"><?php esc_html_e( 'Bracket', 'westshore' ); ?></label>
							<input type="text" id="westshore-fee-bracket-<?php echo (int) $i; ?>" class="large-text"
								name="westshore_fees[<?php echo (int) $i; ?>][bracket]"
								value="<?php echo esc_attr( $row['bracket'] ); ?>" />
						</td>
						<td>
							<label class="screen-reader-text" for="westshore-fee-price-<?php echo (int) $i; ?>"><?php esc_html_e( 'Price', 'westshore' ); ?></label>
							<input type="text" id="westshore-fee-price-<?php echo (int) $i; ?>" class="large-text"
								name="westshore_fees[<?php echo (int) $i; ?>][price]"
								value="<?php echo esc_attr( $row['price'] ); ?>" placeholder="$0.00" />
						</td>
						<td>
							<label class="screen-reader-text" for="westshore-fee-note-<?php echo (int) $i; ?>"><?php esc_html_e( 'Small line underneath', 'westshore' ); ?></label>
							<input type="text" id="westshore-fee-note-<?php echo (int) $i; ?>" class="large-text"
								name="westshore_fees[<?php echo (int) $i; ?>][note]"
								value="<?php echo esc_attr( $row['note'] ); ?>" />
						</td>
					</tr>
				<?php endfor; ?>
				</tbody>
			</table>

			<?php submit_button(); ?>
		</form>

		<h2><?php esc_html_e( 'Training times', 'westshore' ); ?></h2>
		<p class="description" style="max-width:46em;">
			<?php
			printf(
				/* translators: %s: link to the groups screen */
				esc_html__( 'Each team\'s training and game times live on the team itself, under %s, because they are different for every side.', 'westshore' ),
				'<a href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=westshore_group&post_type=westshore_person' ) ) . '">' . esc_html__( 'Groups', 'westshore' ) . '</a>'
			);
			?>
		</p>
	</div>
	<?php
}
