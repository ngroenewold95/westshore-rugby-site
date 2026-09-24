<?php
/**
 * One Westshore menu, holding everything the club owns.
 *
 * Sponsors and People each had a top-level menu of their own, sitting in a
 * sidebar that also carries Posts, Media, Pages, Comments, Appearance, Plugins,
 * Users, Tools, Settings, Fluent Forms and Clone Stats. A volunteer who has
 * been shown where sponsors live once has no reason to guess that people are
 * somewhere else entirely, and Season would have been a third.
 *
 * They are submenus now, under one parent named after the club. Nothing about
 * the screens themselves changes and no URL moves: a post type's edit screen is
 * still edit.php?post_type=sponsor whatever menu points at it, so a bookmark or
 * a link in the admin guide still works.
 *
 * Doing it before the cutover rather than after is deliberate. Nobody has been
 * trained on the current arrangement and the admin guide has no screenshots in
 * it yet, so this is the cheapest this change will ever be.
 *
 * @package Westshore_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const WESTSHORE_ADMIN_MENU_SLUG = 'westshore';

/**
 * The parent menu, and the overview page it opens on.
 *
 * Position 21 is where Sponsors used to sit, just under Comments and above
 * Appearance, so the group lands where one of its members already was.
 *
 * edit_posts rather than manage_options: the people who do this work are
 * Editors, and an Administrator has the capability too.
 */
function westshore_admin_menu() {
	add_menu_page(
		__( 'Westshore', 'westshore' ),
		__( 'Westshore', 'westshore' ),
		'edit_posts',
		WESTSHORE_ADMIN_MENU_SLUG,
		'westshore_admin_overview',
		'dashicons-shield',
		21
	);
}
add_action( 'admin_menu', 'westshore_admin_menu' );

/**
 * The screens WordPress does not add for us, and the order they go in.
 *
 * Two things have to be done by hand once a post type sets show_in_menu to a
 * parent slug rather than true.
 *
 * The taxonomy screens vanish. wp-admin/menu.php only adds a post type's
 * taxonomy submenus when it is building that post type its own top-level menu,
 * so Tiers and Groups simply stopped appearing. Groups is not an advanced
 * screen: adding a team is adding a group, which is the thing that lets the
 * club put a new side on the Coaches page without a developer.
 *
 * And the parent needs a submenu entry of its own, or the overview page is
 * reachable only by clicking the parent and there is no row for it in the list.
 *
 * Priority 11, so every post type registered by wp-admin/menu.php is already in
 * place and the reorder below has everything to sort.
 */
function westshore_admin_menu_screens() {
	add_submenu_page(
		WESTSHORE_ADMIN_MENU_SLUG,
		__( 'Westshore', 'westshore' ),
		__( 'Overview', 'westshore' ),
		'edit_posts',
		WESTSHORE_ADMIN_MENU_SLUG,
		'westshore_admin_overview'
	);

	add_submenu_page(
		WESTSHORE_ADMIN_MENU_SLUG,
		__( 'Sponsor tiers', 'westshore' ),
		__( 'Tiers', 'westshore' ),
		'manage_categories',
		'edit-tags.php?taxonomy=sponsor_tier&post_type=sponsor'
	);

	add_submenu_page(
		WESTSHORE_ADMIN_MENU_SLUG,
		__( 'Groups', 'westshore' ),
		__( 'Groups', 'westshore' ),
		'manage_categories',
		'edit-tags.php?taxonomy=westshore_group&post_type=westshore_person'
	);
}
add_action( 'admin_menu', 'westshore_admin_menu_screens', 11 );

/**
 * Put the submenu in an order that reads like the job, not like registration.
 *
 * Each thing next to the list it is sorted into: sponsors then their tiers,
 * people then their groups. Left alone the order is whatever wp-admin/menu.php
 * happened to register first, which put Sponsors above the overview.
 *
 * Sorted by slug rather than by position, deliberately. The first version
 * renamed $submenu[0] on the assumption that WordPress puts the parent's own
 * entry there. It does not when a post type has already claimed the parent, so
 * that rename relabelled Sponsors as "Overview" and left the real overview
 * page with no row at all. Anything positional here is a guess about ordering
 * this file does not control.
 *
 * Priority 100, after everything else has registered.
 */
function westshore_admin_menu_order() {
	global $submenu;

	if ( empty( $submenu[ WESTSHORE_ADMIN_MENU_SLUG ] ) ) {
		return;
	}

	$order = array(
		WESTSHORE_ADMIN_MENU_SLUG,
		'edit.php?post_type=sponsor',
		'edit-tags.php?taxonomy=sponsor_tier&post_type=sponsor',
		'edit.php?post_type=westshore_person',
		'edit-tags.php?taxonomy=westshore_group&post_type=westshore_person',
		'westshore-season',
	);

	$order = array_flip( $order );

	usort(
		$submenu[ WESTSHORE_ADMIN_MENU_SLUG ],
		function ( $a, $b ) use ( $order ) {
			// Anything this list does not name keeps to the end rather than
			// being dropped, so a screen added later still appears.
			$rank_a = isset( $order[ $a[2] ] ) ? $order[ $a[2] ] : PHP_INT_MAX;
			$rank_b = isset( $order[ $b[2] ] ) ? $order[ $b[2] ] : PHP_INT_MAX;

			return $rank_a <=> $rank_b;
		}
	);
}
add_action( 'admin_menu', 'westshore_admin_menu_order', 100 );

/**
 * The overview page.
 *
 * Six jobs, in the order the admin guide covers them, each one a link to the
 * screen that does it. It exists because a parent menu has to open on
 * something, and a page that says what the club can actually do here is worth
 * more than landing on whichever submenu happened to be first.
 *
 * Deliberately not a dashboard. No counts, no charts, nothing that needs
 * maintaining or that can go stale and make the admin look broken.
 */
function westshore_admin_overview() {
	$jobs = array(
		array(
			'title' => __( 'Sponsors', 'westshore' ),
			'body'  => __( 'Add a sponsor, change a logo, move somebody between tiers. Upload a logo, pick a tier, publish.', 'westshore' ),
			'url'   => 'edit.php?post_type=sponsor',
			'link'  => __( 'Open sponsors', 'westshore' ),
		),
		array(
			'title' => __( 'People, coaches and contacts', 'westshore' ),
			'body'  => __( 'Everybody the site names, entered once. A person can hold several roles, so changing an email is one edit and every page that shows them follows.', 'westshore' ),
			'url'   => 'edit.php?post_type=westshore_person',
			'link'  => __( 'Open people', 'westshore' ),
		),
		array(
			'title' => __( 'Groups', 'westshore' ),
			'body'  => __( 'The teams and lists people are sorted into. Add a team, set its running order, and tick whether it belongs on the Coaches page.', 'westshore' ),
			'url'   => 'edit-tags.php?taxonomy=westshore_group&post_type=westshore_person',
			'link'  => __( 'Open groups', 'westshore' ),
		),
		array(
			'title' => __( 'News', 'westshore' ),
			'body'  => __( 'Club news posts. These are ordinary WordPress posts and they show on the News page.', 'westshore' ),
			'url'   => 'edit.php',
			'link'  => __( 'Open posts', 'westshore' ),
		),
		array(
			'title' => __( 'Pages and menus', 'westshore' ),
			'body'  => __( 'The pages themselves. Most are built from locked patterns, so the words are editable and the layout cannot be taken apart by accident.', 'westshore' ),
			'url'   => 'edit.php?post_type=page',
			'link'  => __( 'Open pages', 'westshore' ),
		),
		array(
			'title' => __( 'Forms', 'westshore' ),
			'body'  => __( 'Registration and contact forms, and the submissions people have sent.', 'westshore' ),
			'url'   => 'admin.php?page=fluent_forms',
			'link'  => __( 'Open forms', 'westshore' ),
		),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Westshore', 'westshore' ); ?></h1>
		<p class="description" style="max-width:46em;font-size:14px;">
			<?php esc_html_e( 'Everything the club owns on the website. Each of these is a job somebody does once or twice a season.', 'westshore' ); ?>
		</p>

		<div style="display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));max-width:1100px;margin-top:20px;">
			<?php foreach ( $jobs as $job ) : ?>
				<div class="card" style="margin:0;padding:16px 18px;max-width:none;">
					<h2 style="margin-top:0;font-size:15px;"><?php echo esc_html( $job['title'] ); ?></h2>
					<p style="margin-bottom:14px;"><?php echo esc_html( $job['body'] ); ?></p>
					<a class="button" href="<?php echo esc_url( admin_url( $job['url'] ) ); ?>">
						<?php echo esc_html( $job['link'] ); ?>
					</a>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}
