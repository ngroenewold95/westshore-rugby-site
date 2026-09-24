<?php
/**
 * The club's YouTube channel on the site: what is live, and what was streamed.
 *
 * The club streams its home games on @WestshoreRugby, and until now the site
 * said so in one text link on each senior page. On a Saturday afternoon that
 * link is the only way to find out whether anything is on.
 *
 * No API key. The YouTube Data API needs a Google Cloud project, the club's
 * Google account is locked until its mailboxes exist, and the channel sits
 * under a volunteer's own email. Two things the channel exposes for free do
 * the job instead:
 *
 * - The RSS feed carries the last fifteen uploads with id, title and date.
 *   Streams and their VODs are in it. So are the shorts, dropped on the
 *   #rugby tag the club puts in most of their titles, and for the rest on a
 *   probe of the /shorts/ID URL, which only answers 200 for a short.
 * - The /live URL serves the channel page when nothing is on and the watch
 *   page when a stream is live. The canonical link in the head says which, and
 *   the page data carries an isLiveNow flag. A stream that is scheduled but not
 *   started also resolves /live to a watch page, which is why both are checked.
 *
 * Both are cached in transients, and both fail closed: a fetch that goes wrong
 * reads as "nothing live" and "the last list we saw", never as an error on a
 * page. Same posture as instagram.php, for the same reason.
 *
 * The channel is named in three places: the two constants below and the
 * social-link URLs in the theme's header and footer parts.
 *
 * @package Westshore_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const WESTSHORE_YOUTUBE_CHANNEL = 'UCxp-W-2DkJjZkQrBXCdxe6w';
const WESTSHORE_YOUTUBE_HANDLE  = 'WestshoreRugby';

/**
 * How long each cache lives, in seconds.
 *
 * Three minutes for the live check is the longest a visitor arriving during a
 * game should wait to see the banner. Half an hour for the feed, because a new
 * VOD appearing thirty minutes late costs nobody anything. Five minutes after
 * a failed fetch before trying again, so a YouTube outage is a handful of
 * requests an hour and not one per page view.
 */
const WESTSHORE_YOUTUBE_LIVE_TTL  = 3 * MINUTE_IN_SECONDS;
const WESTSHORE_YOUTUBE_FEED_TTL  = 30 * MINUTE_IN_SECONDS;
const WESTSHORE_YOUTUBE_RETRY_TTL = 5 * MINUTE_IN_SECONDS;

/**
 * The request arguments both fetches share.
 *
 * A browser user agent, because YouTube answers the default WordPress one with
 * a consent page. A short timeout, because whoever loads the page first after
 * a cache expires is the one waiting for it.
 *
 * @return array<string, mixed>
 */
function westshore_youtube_request_args() {
	return array(
		'timeout'    => 6,
		'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36',
		'headers'    => array( 'Accept-Language' => 'en-CA,en;q=0.9' ),
	);
}

/**
 * The latest uploads, newest first, shorts removed.
 *
 * Served from the transient while it lives. When it has expired the feed is
 * fetched again; when that fails the previous good copy is served from an
 * option, and the failure is remembered for a few minutes so the next visitor
 * does not repeat the wait.
 *
 * @return array<int, array{id: string, title: string, published: int}>
 */
function westshore_youtube_feed() {
	$cached = get_transient( 'westshore_youtube_feed' );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	if ( 'failed' === $cached ) {
		return westshore_youtube_feed_last_good();
	}

	// The feed endpoint answers a 404 or a 500 to roughly every other
	// request, from this host and from a home connection alike, checked
	// 2026-09-14 across eighty tries, the failures independent of each other
	// and of the edge IP. It is 18 KB, so ask eight times before giving up:
	// about one refresh in a hundred still fails, and that one serves the
	// last good copy.
	$entries = null;

	for ( $attempt = 0; $attempt < 8 && null === $entries; $attempt++ ) {
		$response = wp_remote_get(
			'https://www.youtube.com/feeds/videos.xml?channel_id=' . WESTSHORE_YOUTUBE_CHANNEL,
			westshore_youtube_request_args()
		);

		$entries = westshore_youtube_parse_feed( $response );
	}

	if ( null === $entries ) {
		$last = westshore_youtube_feed_last_good();

		// With a last good copy to serve, wait the full retry interval. With
		// nothing at all, a fresh site, try again after a minute: the section
		// is empty until this succeeds once.
		set_transient( 'westshore_youtube_feed', 'failed', $last ? WESTSHORE_YOUTUBE_RETRY_TTL : MINUTE_IN_SECONDS );

		return $last;
	}

	set_transient( 'westshore_youtube_feed', $entries, WESTSHORE_YOUTUBE_FEED_TTL );
	update_option( 'westshore_youtube_feed_last', $entries, false );

	return $entries;
}

/**
 * The last feed that parsed, or nothing.
 *
 * @return array<int, array{id: string, title: string, published: int}>
 */
function westshore_youtube_feed_last_good() {
	$last = get_option( 'westshore_youtube_feed_last', array() );

	return is_array( $last ) ? $last : array();
}

/**
 * Turn the feed response into entries, or null when it cannot be trusted.
 *
 * Null rather than an empty array on failure, because an empty array is a
 * real answer (a channel with nothing on it) and must not be confused with a
 * fetch that never arrived.
 *
 * @param array<string, mixed>|WP_Error $response From wp_remote_get.
 * @return array<int, array{id: string, title: string, published: int}>|null
 */
function westshore_youtube_parse_feed( $response ) {
	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$body = wp_remote_retrieve_body( $response );

	if ( '' === $body ) {
		return null;
	}

	$previous = libxml_use_internal_errors( true );
	$xml      = simplexml_load_string( $body );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	if ( false === $xml || ! isset( $xml->entry ) ) {
		return null;
	}

	$entries = array();

	foreach ( $xml->entry as $entry ) {
		$yt    = $entry->children( 'yt', true );
		$id    = isset( $yt->videoId ) ? (string) $yt->videoId : '';
		$title = trim( (string) $entry->title );

		if ( '' === $id || '' === $title ) {
			continue;
		}

		// The club tags most of its shorts #rugby in the title, which saves a
		// probe. The ones it does not tag are caught below.
		if ( false !== strpos( $title, '#' ) ) {
			continue;
		}

		$entries[] = array(
			'id'        => $id,
			// The options table on this host is utf8mb3, and a title with an
			// emoji in it ("D'Shawn Bacon 🕺") made the whole write fail with
			// no error. Encoded to entities the way core stores post content.
			'title'     => wp_encode_emoji( $title ),
			'published' => (int) strtotime( (string) $entry->published ),
		);
	}

	$entries = westshore_youtube_without_shorts( $entries );

	usort(
		$entries,
		static function ( $a, $b ) {
			return $b['published'] <=> $a['published'];
		}
	);

	return $entries;
}

/**
 * Drop the shorts the title did not give away.
 *
 * The feed carries no duration and no format, but YouTube itself knows: the
 * /shorts/ID URL answers 200 for a short and a 303 to /watch for anything
 * else. One HEAD request per video the first time it is seen, and the answer
 * is kept in an option, so a refresh costs one probe a week in season and
 * none out of it. An answer that is neither is not kept, so it is asked
 * again next time rather than guessed.
 *
 * @param array<int, array{id: string, title: string, published: int}> $entries Feed entries.
 * @return array<int, array{id: string, title: string, published: int}>
 */
function westshore_youtube_without_shorts( $entries ) {
	$stored = get_option( 'westshore_youtube_shorts', array() );
	$known  = is_array( $stored ) ? $stored : array();
	$seen   = array();
	$kept   = array();

	foreach ( $entries as $entry ) {
		$id = $entry['id'];

		if ( ! isset( $known[ $id ] ) ) {
			$response = wp_remote_head(
				'https://www.youtube.com/shorts/' . rawurlencode( $id ),
				array_merge( westshore_youtube_request_args(), array( 'redirection' => 0 ) )
			);

			$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

			if ( 200 === $code ) {
				$known[ $id ] = true;
			} elseif ( $code >= 300 && $code < 400 ) {
				$known[ $id ] = false;
			}
		}

		if ( isset( $known[ $id ] ) ) {
			$seen[ $id ] = $known[ $id ];
		}

		if ( empty( $known[ $id ] ) ) {
			$kept[] = $entry;
		}
	}

	// Only the videos in the current feed are worth remembering.
	if ( $seen !== $stored ) {
		update_option( 'westshore_youtube_shorts', $seen, false );
	}

	return $kept;
}

/**
 * The stream that is live right now, or false.
 *
 * Fetched from the /live URL and cached for a few minutes. A lock transient
 * covers the fetch itself, so two page views arriving together after an
 * expiry do not both go to YouTube; the second reads as not live for a
 * minute, which is the cheaper mistake.
 *
 * The whole page is read, 1.1 MB when nothing is on. The canonical link
 * sits three quarters of the way in, after the inline scripts, so capping
 * the read would only lose it.
 *
 * @return array{id: string, title: string}|false
 */
function westshore_youtube_live() {
	$cached = get_transient( 'westshore_youtube_live' );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	if ( 'off' === $cached ) {
		return false;
	}

	if ( get_transient( 'westshore_youtube_live_lock' ) ) {
		return false;
	}

	set_transient( 'westshore_youtube_live_lock', 1, MINUTE_IN_SECONDS );

	$response = wp_remote_get(
		'https://www.youtube.com/channel/' . WESTSHORE_YOUTUBE_CHANNEL . '/live',
		westshore_youtube_request_args()
	);

	$live = westshore_youtube_parse_live( $response );

	delete_transient( 'westshore_youtube_live_lock' );

	if ( null === $live ) {
		set_transient( 'westshore_youtube_live', 'off', WESTSHORE_YOUTUBE_RETRY_TTL );

		return false;
	}

	set_transient( 'westshore_youtube_live', $live ? $live : 'off', WESTSHORE_YOUTUBE_LIVE_TTL );

	return $live;
}

/**
 * Read the /live page: a stream, false for nothing on, null for no answer.
 *
 * @param array<string, mixed>|WP_Error $response From wp_remote_get.
 * @return array{id: string, title: string}|false|null
 */
function westshore_youtube_parse_live( $response ) {
	if ( is_wp_error( $response ) ) {
		return null;
	}

	$body = wp_remote_retrieve_body( $response );

	// An empty body or a page with no canonical link is a consent wall or an
	// error page, not an answer.
	if ( '' === $body || ! preg_match( '#<link rel="canonical" href="https://www\.youtube\.com/([^"]+)"#', $body, $canonical ) ) {
		return null;
	}

	if ( ! preg_match( '#^watch\?v=([A-Za-z0-9_-]{11})#', $canonical[1], $video ) ) {
		return false;
	}

	if ( false === strpos( $body, '"isLiveNow":true' ) ) {
		return false;
	}

	$title = '';

	if ( preg_match( '#<meta name="title" content="([^"]*)"#', $body, $meta ) ) {
		$title = html_entity_decode( $meta[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	return array(
		'id'    => $video[1],
		'title' => $title,
	);
}

/**
 * [westshore_streams count="3" layout="cards"]
 *
 * A live banner when a stream is on, then the latest streams: three cards on
 * the senior pages, or one wide entry on the front page with layout="feature".
 * Ends with a button to the channel's streams tab either way.
 *
 * Final HTML rather than block comments, and is-layout-flow written onto each
 * card, for the reason the fees shortcode in season.php gives: a shortcode
 * runs after the block parser, so the layout classes WordPress adds to a real
 * column are absent unless they are written here. The card classes are the
 * theme's own, so the cards bring no stylesheet; only the banner and the
 * feature layout have rules of their own.
 *
 * @param array<string, string>|string $atts Attributes.
 * @return string
 */
function westshore_streams_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'count'  => 3,
			'layout' => 'cards',
		),
		$atts,
		'westshore_streams'
	);

	$live  = westshore_youtube_live();
	$feed  = westshore_youtube_feed();
	$count = 'feature' === $atts['layout'] ? 1 : max( 1, (int) $atts['count'] );

	// The stream that is live is also the newest thing in the feed once
	// YouTube lists it, and showing it twice reads as a mistake.
	if ( $live ) {
		$feed = array_values(
			array_filter(
				$feed,
				static function ( $entry ) use ( $live ) {
					return $entry['id'] !== $live['id'];
				}
			)
		);
	}

	$feed = array_slice( $feed, 0, $count );

	if ( ! $live && ! $feed ) {
		return westshore_streams_placeholder();
	}

	westshore_lazy_style( 'westshore-youtube', 'youtube.css' );

	$out = '<div class="wsr-streams">';

	if ( $live ) {
		$out .= westshore_streams_live_banner( $live );
	}

	// The front page's one entry stands in for the banner, not beside it: when
	// a game is on, the game is the section.
	if ( $feed && 'feature' === $atts['layout'] && ! $live ) {
		$out .= westshore_streams_feature( $feed[0] );
	} elseif ( $feed && 'feature' !== $atts['layout'] ) {
		$out .= westshore_streams_cards( $feed );
	}

	$out .= '<div class="wp-block-buttons is-layout-flex wp-block-buttons-is-layout-flex"><div class="wp-block-button">'
		. '<a class="wp-block-button__link wp-element-button" href="' . esc_url( westshore_youtube_channel_url( 'streams' ) ) . '" target="_blank" rel="noreferrer noopener">'
		. esc_html__( 'All streams on YouTube', 'westshore' ) . '</a></div></div>';

	return $out . '</div>';
}
add_shortcode( 'westshore_streams', 'westshore_streams_shortcode' );

/**
 * A URL on the channel.
 *
 * @param string $tab '' for the channel home, or 'streams', 'videos'.
 * @return string
 */
function westshore_youtube_channel_url( $tab = '' ) {
	$url = 'https://www.youtube.com/@' . WESTSHORE_YOUTUBE_HANDLE;

	return '' === $tab ? $url : $url . '/' . $tab;
}

/**
 * The banner for a stream that is on now: marker, title, player, link.
 *
 * The player is the privacy-enhanced embed, which sets no cookie until the
 * visitor presses play. It is lazy so a visitor who never scrolls to it never
 * loads it.
 *
 * @param array{id: string, title: string} $live The live stream.
 * @return string
 */
function westshore_streams_live_banner( $live ) {
	$title = '' !== $live['title'] ? $live['title'] : __( 'Westshore RFC, live', 'westshore' );

	return '<div class="wsr-live">'
		. '<p class="wsr-live__marker"><span class="wsr-live__dot" aria-hidden="true"></span>' . esc_html__( 'Live now', 'westshore' ) . '</p>'
		. '<h3 class="wsr-live__title">' . esc_html( $title ) . '</h3>'
		. '<div class="wsr-live__player"><iframe src="' . esc_url( 'https://www.youtube-nocookie.com/embed/' . $live['id'] ) . '"'
		. ' title="' . esc_attr( $title ) . '" loading="lazy" allow="accelerometer; encrypted-media; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>'
		. '<p class="wsr-live__link"><a href="' . esc_url( westshore_youtube_watch_url( $live['id'] ) ) . '" target="_blank" rel="noreferrer noopener">'
		. esc_html__( 'Watch on YouTube', 'westshore' ) . '</a></p>'
		. '</div>';
}

/**
 * The card grid of recent streams, in the theme's card classes.
 *
 * @param array<int, array{id: string, title: string, published: int}> $entries Feed entries.
 * @return string
 */
function westshore_streams_cards( $entries ) {
	$cards = '';

	foreach ( $entries as $entry ) {
		$cards .= '<div class="wp-block-column westshore-card is-layout-flow wp-block-column-is-layout-flow">'
			. westshore_streams_thumbnail( $entry )
			. '<h3 class="wp-block-heading has-medium-font-size">' . esc_html( $entry['title'] ) . '</h3>'
			. '<p class="has-small-font-size">' . esc_html( westshore_streams_date( $entry ) ) . '</p>'
			. '<p class="westshore-card__link"><a href="' . esc_url( westshore_youtube_watch_url( $entry['id'] ) ) . '" target="_blank" rel="noreferrer noopener">'
			. esc_html__( 'Watch', 'westshore' ) . '</a></p>'
			. '</div>';
	}

	return '<div class="wp-block-columns westshore-cards">' . $cards . '</div>';
}

/**
 * One stream, thumbnail beside the text, for the front page.
 *
 * @param array{id: string, title: string, published: int} $entry Feed entry.
 * @return string
 */
function westshore_streams_feature( $entry ) {
	return '<div class="wsr-feature">'
		. westshore_streams_thumbnail( $entry )
		. '<div class="wsr-feature__text">'
		. '<p class="wsr-feature__eyebrow">' . esc_html__( 'Latest stream', 'westshore' ) . '</p>'
		. '<h3 class="wsr-feature__title">' . esc_html( $entry['title'] ) . '</h3>'
		. '<p class="wsr-feature__date">' . esc_html( westshore_streams_date( $entry ) ) . '</p>'
		. '<p class="wsr-feature__link"><a href="' . esc_url( westshore_youtube_watch_url( $entry['id'] ) ) . '" target="_blank" rel="noreferrer noopener">'
		. esc_html__( 'Watch on YouTube', 'westshore' ) . '</a></p>'
		. '</div></div>';
}

/**
 * A stream's thumbnail, linked to the video.
 *
 * Empty alt on purpose: the title is the next thing on the card, and reading
 * it twice helps nobody.
 *
 * @param array{id: string, title: string, published: int} $entry Feed entry.
 * @return string
 */
function westshore_streams_thumbnail( $entry ) {
	return '<figure class="westshore-card__image"><a href="' . esc_url( westshore_youtube_watch_url( $entry['id'] ) ) . '" target="_blank" rel="noreferrer noopener" tabindex="-1" aria-hidden="true">'
		. '<img src="' . esc_url( 'https://i.ytimg.com/vi/' . $entry['id'] . '/hqdefault.jpg' ) . '" alt="" width="480" height="360" loading="lazy" decoding="async" />'
		. '</a></figure>';
}

/**
 * "Streamed 13 September 2026", in the site's date format.
 *
 * @param array{id: string, title: string, published: int} $entry Feed entry.
 * @return string
 */
function westshore_streams_date( $entry ) {
	if ( $entry['published'] < 1 ) {
		return '';
	}

	/* translators: %s: a date */
	return sprintf( __( 'Streamed %s', 'westshore' ), date_i18n( get_option( 'date_format' ), $entry['published'] ) );
}

/**
 * A watch URL.
 *
 * @param string $id Video ID.
 * @return string
 */
function westshore_youtube_watch_url( $id ) {
	return 'https://www.youtube.com/watch?v=' . rawurlencode( $id );
}

/**
 * What stands in when there is nothing to show at all.
 *
 * Nothing on production, the same as the Instagram wrapper. On staging a
 * plain panel, so an empty section reads as "the feed did not arrive" and
 * not as work that was never done.
 *
 * @return string
 */
function westshore_streams_placeholder() {
	if ( 'production' === wp_get_environment_type() ) {
		return '';
	}

	wp_enqueue_style( 'westshore-sponsors' );

	return '<div class="wsr-insta-placeholder"><p><strong>' .
		esc_html__( 'YouTube streams: wired up, nothing fetched yet.', 'westshore' ) .
		'</strong></p><p>' .
		esc_html__( 'The latest streams appear here once the channel feed can be read from this server. This panel is on staging only and never renders on the live site.', 'westshore' ) .
		'</p></div>';
}
