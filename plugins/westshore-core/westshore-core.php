<?php
/**
 * Plugin Name: Westshore Core
 * Description: Content types the club owns: sponsors, people and coaches, and anything else that must outlive a theme change. Kept in a plugin on purpose, so switching themes never takes the club's data with it.
 * Version: 0.18.6
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: Westshore Rugby Football Club
 * License: GPL-2.0-or-later
 * Text Domain: westshore
 *
 * @package Westshore_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WESTSHORE_CORE_VERSION', '0.18.6' );
define( 'WESTSHORE_CORE_FILE', __FILE__ );
define( 'WESTSHORE_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'WESTSHORE_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once WESTSHORE_CORE_DIR . 'includes/base.php';
require_once WESTSHORE_CORE_DIR . 'includes/admin-menu.php';
require_once WESTSHORE_CORE_DIR . 'includes/sponsors.php';
require_once WESTSHORE_CORE_DIR . 'includes/sponsors-render.php';
require_once WESTSHORE_CORE_DIR . 'includes/people.php';
require_once WESTSHORE_CORE_DIR . 'includes/people-render.php';
require_once WESTSHORE_CORE_DIR . 'includes/season.php';
require_once WESTSHORE_CORE_DIR . 'includes/instagram.php';
require_once WESTSHORE_CORE_DIR . 'includes/youtube.php';
require_once WESTSHORE_CORE_DIR . 'includes/playhq.php';
require_once WESTSHORE_CORE_DIR . 'includes/redirects.php';

/**
 * Seed the sponsor tiers and the people groups on activation.
 *
 * Terms are created only if missing, so reactivating never duplicates them and
 * never overwrites anything the club has edited. That matters more for groups
 * than for tiers: groups are meant to be renamed, reordered and removed from
 * the admin, so an activation that reset them would undo real work.
 */
function westshore_core_activate() {
	westshore_register_sponsor_post_type();
	westshore_register_sponsor_tier_taxonomy();
	westshore_seed_sponsor_tiers();
	westshore_register_person_post_type();
	westshore_register_person_group_taxonomy();
	westshore_seed_person_groups();
	update_option( 'westshore_core_version', WESTSHORE_CORE_VERSION, false );
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'westshore_core_activate' );

/**
 * Run the seeds again when the plugin has been updated in place.
 *
 * Seeding used to happen only on activation, which is right the first time and
 * wrong every time after. A release that adds a group or a tier would never
 * have shown it on a site where the plugin was already active, and the failure
 * is a silently empty render rather than an error: exactly the shape of bug
 * this codebase keeps finding.
 *
 * Safe to run on every update because the seed writers skip any term that
 * already exists, so nothing the club has renamed, reordered or unticked is
 * touched. Only genuinely new terms appear.
 *
 * The stored version is what decides, not the plugin file, so a downgrade
 * followed by an upgrade seeds once rather than twice.
 */
function westshore_core_maybe_upgrade() {
	$stored = get_option( 'westshore_core_version' );

	if ( WESTSHORE_CORE_VERSION === $stored ) {
		return;
	}

	westshore_seed_sponsor_tiers();
	westshore_seed_person_groups();

	update_option( 'westshore_core_version', WESTSHORE_CORE_VERSION, false );
}
add_action( 'init', 'westshore_core_maybe_upgrade', 20 );

/**
 * Tidy rewrite rules on deactivation. Content is left alone.
 */
function westshore_core_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'westshore_core_deactivate' );
