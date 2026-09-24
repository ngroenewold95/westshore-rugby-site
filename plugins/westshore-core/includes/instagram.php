<?php
/**
 * The Instagram feed, wrapped so a half-configured plugin cannot shout at
 * visitors.
 *
 * Smash Balloon prints its own errors into the page. "Error: No feed with the
 * ID 1 found" carries a line saying it is only visible to administrators, and
 * it is not: an anonymous request gets the whole block. On the front page of a
 * rugby club that reads as a broken website.
 *
 * So the front page carries [westshore_instagram] rather than the plugin's own
 * shortcode. If the feed is connected it renders exactly what the plugin
 * renders. If it is not, or the plugin is deactivated, it renders nothing and
 * the section around it still stands on its Follow button.
 *
 * @package Westshore_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is there an Instagram account connected?
 *
 * Checked against the sources table rather than the settings option, because
 * the plugin has carried connected accounts in more than one place across
 * versions and the table is what the feed builder actually reads.
 *
 * @return bool
 */
function westshore_instagram_is_connected() {
	global $wpdb;

	$table = $wpdb->prefix . 'sbi_sources';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- no API for this, and the
	// alternative is loading the plugin's builder classes on every page view.
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

	if ( $exists !== $table ) {
		return false;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) > 0;
}

/**
 * [westshore_instagram]
 *
 * @param array<string, string>|string $atts Shortcode attributes.
 * @return string
 */
function westshore_instagram_shortcode( $atts ) {
	$atts = shortcode_atts(
		array( 'feed' => '1' ),
		$atts,
		'westshore_instagram'
	);

	if ( ! shortcode_exists( 'instagram-feed' ) || ! westshore_instagram_is_connected() ) {
		return westshore_instagram_placeholder();
	}

	// Only the feed number goes through. The free plugin strips every other
	// att for a feed-builder feed (class-sb-instagram-settings.php,
	// filter_atts_for_legacy), so the header, Load More, Follow button and
	// post count are set on the feed itself by a setup script.
	return do_shortcode( sprintf( '[instagram-feed feed=%d]', (int) $atts['feed'] ) );
}

/**
 * What stands in for the feed while nobody has connected the account.
 *
 * Nothing at all on production: a visitor should never see scaffolding. On
 * staging it is a plain panel saying the slot is wired and waiting, because
 * otherwise the only way to tell the feed is there is to read the page source,
 * and a section that renders as empty space looks like work that was not done.
 *
 * @return string
 */
function westshore_instagram_placeholder() {
	if ( 'production' === wp_get_environment_type() ) {
		return '';
	}

	// The plugin has one stylesheet and this panel borrows the empty-tier look
	// from it, so it has to be asked for: nothing else on the front page may
	// have rendered a sponsor block yet.
	wp_enqueue_style( 'westshore-sponsors' );

	return '<div class="wsr-insta-placeholder"><p><strong>' .
		esc_html__( 'Instagram feed: wired up, not connected yet.', 'westshore' ) .
		'</strong></p><p>' .
		esc_html__( 'The posts appear here once somebody signs in to the club Instagram account once, in Instagram Feed in the admin. This panel is on staging only and never renders on the live site.', 'westshore' ) .
		'</p></div>';
}
add_shortcode( 'westshore_instagram', 'westshore_instagram_shortcode' );
