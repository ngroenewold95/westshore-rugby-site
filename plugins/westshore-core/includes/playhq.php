<?php
/**
 * Fixtures, results and league tables from Play HQ.
 *
 * BC Rugby runs its provincial competition on Play HQ, which has a public
 * read-only API. Until now the senior pages typed nothing about the schedule
 * and linked out to the club's Play HQ page instead, because a fixture typed
 * onto a page is wrong the first time BC Rugby moves a game. This reads the
 * schedule and the table from the source and renders them on the page.
 *
 * Three things about the API that a search does not turn up, all confirmed
 * against it on 2026-09-16:
 *
 * - The Canadian host is api.caprod.playhq.com, not api.playhq.com, and every
 *   request carries the tenant header for Rugby Canada. The key was issued by
 *   BC Rugby on 2026-09-15; Play HQ only issues one through the governing body.
 * - A team's fixture endpoint carries the results too. A played game has
 *   status FINAL and each competitor has an outcome and a score, so one call
 *   per team is the whole season, played and unplayed.
 * - The rows are not sorted, the competitor order is arbitrary so isHomeTeam
 *   is the only home or away signal, and a team can have a stray exhibition
 *   game from another grade mixed in. All three are handled below.
 *
 * The key and the team IDs live in the season option behind Westshore >
 * Season, the same as every other fact that changes each August: BC Rugby
 * re-registers the teams every season and every ID changes with them. The key
 * is never in this repo.
 *
 * Fetches are cached in transients and fail closed, the way youtube.php does:
 * a fetch that goes wrong serves the last good copy, and with nothing to serve
 * the shortcode prints nothing rather than an error. Anything cached goes
 * through wp_encode_emoji() first, because the options table on this host is
 * utf8mb3 and a four-byte character makes the whole write fail silently.
 *
 * @package Westshore_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const WESTSHORE_PLAYHQ_HOST   = 'https://api.caprod.playhq.com';
const WESTSHORE_PLAYHQ_TENANT = 'rca';

/**
 * The club on Play HQ. Public: it is the last segment of the club's page URL.
 */
const WESTSHORE_PLAYHQ_ORG = 'cdcd78ac-a5de-4322-8369-2b6ce62fc662';

/**
 * How long each cache lives, in seconds.
 *
 * An hour for fixtures and the ladder: results go in on a Saturday evening,
 * and a score arriving an hour late costs nobody anything. Five minutes after
 * a failed fetch before trying again. A day for the club's team list, which
 * only the Season screen reads and which changes once a year.
 */
const WESTSHORE_PLAYHQ_TTL       = HOUR_IN_SECONDS;
const WESTSHORE_PLAYHQ_RETRY_TTL = 5 * MINUTE_IN_SECONDS;
const WESTSHORE_PLAYHQ_TEAMS_TTL = DAY_IN_SECONDS;

/**
 * How many team rows the Season screen shows.
 *
 * The club has fourteen sides registered this season, five senior and nine
 * junior, and the junior grades are not in Play HQ yet. Room for all of them
 * and a spare, so the day BC Rugby loads a junior grade is a page edit.
 */
const WESTSHORE_PLAYHQ_TEAM_SLOTS = 16;

/**
 * The stored key, or an empty string.
 *
 * @return string
 */
function westshore_playhq_key() {
	return (string) westshore_season_settings()['playhq_key'];
}

/**
 * The configured teams, slug => { slug, name, team_id, page_id }.
 *
 * @return array<string, array{slug: string, name: string, team_id: string, page_id: int}>
 */
function westshore_playhq_teams() {
	$teams = array();

	foreach ( (array) westshore_season_settings()['playhq_teams'] as $team ) {
		if ( empty( $team['slug'] ) || empty( $team['team_id'] ) ) {
			continue;
		}

		$teams[ $team['slug'] ] = $team;
	}

	return $teams;
}

/**
 * One configured team by the slug a page names, or null.
 *
 * @param string $slug The slug shown on the Season screen.
 * @return array{slug: string, name: string, team_id: string}|null
 */
function westshore_playhq_team( $slug ) {
	$teams = westshore_playhq_teams();
	$slug  = sanitize_title( $slug );

	if ( isset( $teams[ $slug ] ) ) {
		return $teams[ $slug ];
	}

	// The failure worth being loud about: a page names a team that is not on
	// the Season screen, so the page silently loses its fixtures.
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			sprintf( '[westshore_fixtures] no Play HQ team with the slug "%s".', $slug )
		);
	}

	return null;
}

/**
 * One GET against the API, decoded, or null when it cannot be trusted.
 *
 * Null rather than an empty array on failure, because an empty list is a real
 * answer (a team with no games yet) and must not be confused with a fetch that
 * never arrived. A short timeout, because whoever loads the page first after
 * a cache expires is the one waiting for it.
 *
 * @param string $path Path and query, from the host root.
 * @return array<string, mixed>|null
 */
function westshore_playhq_request( $path ) {
	$key = westshore_playhq_key();

	if ( '' === $key ) {
		return null;
	}

	$response = wp_remote_get(
		WESTSHORE_PLAYHQ_HOST . $path,
		array(
			'timeout' => 6,
			'headers' => array(
				'x-api-key'    => $key,
				'x-phq-tenant' => WESTSHORE_PLAYHQ_TENANT,
				'Accept'       => 'application/json',
			),
		)
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	return is_array( $data ) ? $data : null;
}

/**
 * Every page of a cursor-paginated list, or null if any page failed.
 *
 * @param string $path Path, with any query already on it.
 * @return array<int, mixed>|null
 */
function westshore_playhq_request_all( $path ) {
	$rows   = array();
	$cursor = '';

	for ( $page = 0; $page < 20; $page++ ) {
		$sep  = false === strpos( $path, '?' ) ? '?' : '&';
		$data = westshore_playhq_request( $path . ( '' !== $cursor ? $sep . 'cursor=' . rawurlencode( $cursor ) : '' ) );

		if ( null === $data || ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
			return null;
		}

		$rows = array_merge( $rows, $data['data'] );

		if ( empty( $data['metadata']['hasMore'] ) || empty( $data['metadata']['nextCursor'] ) ) {
			return $rows;
		}

		$cursor = (string) $data['metadata']['nextCursor'];
	}

	return $rows;
}

/**
 * Serve a value from its transient, else fetch it, else serve the last good.
 *
 * The one cache shape every fetch here uses. A failed fetch is remembered for
 * a few minutes so the next visitor does not repeat the wait, and with a last
 * good copy to fall back on that is all a Play HQ outage costs.
 *
 * @param string   $name    Cache name, one word plus an id.
 * @param callable $fetch   Returns the value, or null when it cannot be trusted.
 * @param int      $ttl     Seconds a good value lives.
 * @return mixed The value, or an empty array with nothing to serve.
 */
function westshore_playhq_cached( $name, $fetch, $ttl ) {
	$transient = 'westshore_playhq_' . $name;
	$cached    = get_transient( $transient );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	$last = get_option( 'westshore_playhq_last', array() );
	$last = is_array( $last ) ? $last : array();

	if ( 'failed' === $cached ) {
		return isset( $last[ $name ] ) ? $last[ $name ] : array();
	}

	$value = $fetch();

	if ( null === $value ) {
		set_transient( $transient, 'failed', isset( $last[ $name ] ) ? WESTSHORE_PLAYHQ_RETRY_TTL : MINUTE_IN_SECONDS );

		return isset( $last[ $name ] ) ? $last[ $name ] : array();
	}

	set_transient( $transient, $value, $ttl );

	$last[ $name ] = $value;
	update_option( 'westshore_playhq_last', $last, false );

	return $value;
}

/**
 * A team's season: every game, played and unplayed, sorted by date.
 *
 * Each row is reduced to what the renderers need, seen from Westshore's side:
 * who the opponent is, whether it is home, what the score was. A row whose
 * competitors do not include the team asked for is dropped, which has not
 * happened but would otherwise render as "Westshore v Westshore".
 *
 * @param string $team_id Play HQ team id.
 * @return array<int, array<string, mixed>>
 */
function westshore_playhq_fixture( $team_id ) {
	return westshore_playhq_cached(
		'fixture_' . $team_id,
		static function () use ( $team_id ) {
			$rows = westshore_playhq_request_all( '/v1/teams/' . rawurlencode( $team_id ) . '/fixture' );

			if ( null === $rows ) {
				return null;
			}

			$games = array();

			foreach ( $rows as $row ) {
				$game = westshore_playhq_normalise_game( $row, $team_id );

				if ( $game ) {
					$games[] = $game;
				}
			}

			usort(
				$games,
				static function ( $a, $b ) {
					return strcmp( $a['date'] . $a['time'], $b['date'] . $b['time'] );
				}
			);

			return $games;
		},
		WESTSHORE_PLAYHQ_TTL
	);
}

/**
 * One API game row as one flat row from Westshore's side, or null.
 *
 * @param array<string, mixed> $row     A game from the fixture endpoint.
 * @param string               $team_id Which competitor is us.
 * @return array<string, mixed>|null
 */
function westshore_playhq_normalise_game( $row, $team_id ) {
	$us   = null;
	$them = null;

	foreach ( (array) ( $row['competitors'] ?? array() ) as $competitor ) {
		if ( ( $competitor['id'] ?? '' ) === $team_id ) {
			$us = $competitor;
		} else {
			$them = $competitor;
		}
	}

	if ( ! $us || ! $them || empty( $row['schedule']['date'] ) ) {
		return null;
	}

	$status = strtoupper( (string) ( $row['status'] ?? '' ) );

	return array(
		'id'         => (string) ( $row['id'] ?? '' ),
		'status'     => $status,
		'date'       => (string) $row['schedule']['date'],
		'time'       => (string) ( $row['schedule']['time'] ?? '' ),
		'tz'         => (string) ( $row['schedule']['timezone'] ?? 'America/Vancouver' ),
		'round'      => wp_encode_emoji( (string) ( $row['round']['name'] ?? '' ) ),
		'grade_id'   => (string) ( $row['grade']['id'] ?? '' ),
		'grade_name' => wp_encode_emoji( (string) ( $row['grade']['name'] ?? '' ) ),
		'grade_url'  => (string) ( $row['grade']['url'] ?? '' ),
		'home'       => ! empty( $us['isHomeTeam'] ),
		'us_name'    => wp_encode_emoji( (string) ( $us['name'] ?? '' ) ),
		'opponent'   => wp_encode_emoji( (string) ( $them['name'] ?? '' ) ),
		'venue'      => wp_encode_emoji( (string) ( $row['venue']['name'] ?? '' ) ),
		'us_score'   => isset( $us['scoreTotal'] ) ? (int) $us['scoreTotal'] : null,
		'them_score' => isset( $them['scoreTotal'] ) ? (int) $them['scoreTotal'] : null,
		'outcome'    => strtoupper( (string) ( $us['outcome'] ?? '' ) ),
		'url'        => (string) ( $row['url'] ?? '' ),
	);
}

/**
 * The grade a team's ladder should come from.
 *
 * The grade of the game nearest today that is not an exhibition, falling back
 * to the most common grade in the list. Mens Division 1 has a pre-season
 * exhibition game filed under "Exhibition Games" in the middle of its season,
 * and the ladder for that would be nonsense.
 *
 * @param array<int, array<string, mixed>> $games From westshore_playhq_fixture().
 * @return array{id: string, name: string, url: string}|null
 */
function westshore_playhq_grade_for( $games ) {
	$today   = wp_date( 'Y-m-d' );
	$nearest = null;
	$gap     = PHP_INT_MAX;
	$counts  = array();

	foreach ( $games as $game ) {
		if ( '' === $game['grade_id'] ) {
			continue;
		}

		$counts[ $game['grade_id'] ] = ( $counts[ $game['grade_id'] ] ?? 0 ) + 1;

		if ( westshore_playhq_is_exhibition( $game ) ) {
			continue;
		}

		$distance = abs( strtotime( $game['date'] ) - strtotime( $today ) );

		if ( $distance < $gap ) {
			$gap     = $distance;
			$nearest = $game;
		}
	}

	if ( ! $nearest && $counts ) {
		arsort( $counts );
		$id = (string) array_key_first( $counts );

		foreach ( $games as $game ) {
			if ( $game['grade_id'] === $id ) {
				$nearest = $game;
				break;
			}
		}
	}

	if ( ! $nearest ) {
		return null;
	}

	return array(
		'id'   => $nearest['grade_id'],
		'name' => $nearest['grade_name'],
		'url'  => $nearest['grade_url'],
	);
}

/**
 * A grade's ladder: the columns, and one row per team.
 *
 * The API sends a header list and each standing as a bare list of values in
 * header order. Here each row's values are keyed by the header key, so a
 * renderer asks for "won" rather than for column four, and BC Rugby adding a
 * column changes nothing.
 *
 * @param string $grade_id Play HQ grade id.
 * @return array{headers: array<string, string>, standings: array<int, array{team_id: string, team: string, values: array<string, mixed>}>}|array{}
 */
function westshore_playhq_ladder( $grade_id ) {
	return westshore_playhq_cached(
		'ladder_' . $grade_id,
		static function () use ( $grade_id ) {
			$data = westshore_playhq_request( '/v2/grades/' . rawurlencode( $grade_id ) . '/ladder' );

			if ( null === $data || empty( $data['ladders'][0]['headers'] ) || ! isset( $data['ladders'][0]['standings'] ) ) {
				return null;
			}

			$ladder  = $data['ladders'][0];
			$keys    = array();
			$headers = array();

			foreach ( $ladder['headers'] as $header ) {
				$keys[]                    = (string) $header['key'];
				$headers[ $header['key'] ] = (string) ( $header['shortName'] ?? $header['name'] ?? $header['key'] );
			}

			$standings = array();

			foreach ( $ladder['standings'] as $standing ) {
				$values = array();

				foreach ( $keys as $i => $key ) {
					$values[ $key ] = $standing['values'][ $i ] ?? null;
				}

				$standings[] = array(
					'team_id' => (string) ( $standing['team']['id'] ?? '' ),
					'team'    => wp_encode_emoji( (string) ( $standing['team']['name'] ?? '' ) ),
					'values'  => $values,
				);
			}

			return array(
				'headers'   => $headers,
				'standings' => $standings,
			);
		},
		WESTSHORE_PLAYHQ_TTL
	);
}

/**
 * Every team Play HQ lists for the club in its active season.
 *
 * For the Season screen only. The season's team list is 290 rows over three
 * pages, which nobody is going to page through by hand each August; this
 * filters it to the club and shows name and id to copy across.
 *
 * @return array<int, array{id: string, name: string, grade: string}>
 */
function westshore_playhq_club_teams() {
	return westshore_playhq_cached(
		'club_teams',
		static function () {
			$seasons = westshore_playhq_request( '/v1/organisations/' . WESTSHORE_PLAYHQ_ORG . '/seasons' );

			if ( null === $seasons || empty( $seasons['data'] ) ) {
				return null;
			}

			$season = null;

			foreach ( $seasons['data'] as $candidate ) {
				if ( 'ACTIVE' === strtoupper( (string) ( $candidate['status'] ?? '' ) ) ) {
					$season = $candidate;
					break;
				}
			}

			if ( ! $season ) {
				$season = $seasons['data'][0];
			}

			$rows = westshore_playhq_request_all( '/v1/seasons/' . rawurlencode( (string) $season['id'] ) . '/teams' );

			if ( null === $rows ) {
				return null;
			}

			$teams = array();

			foreach ( $rows as $row ) {
				if ( ( $row['club']['id'] ?? '' ) !== WESTSHORE_PLAYHQ_ORG ) {
					continue;
				}

				$teams[] = array(
					'id'    => (string) $row['id'],
					'name'  => wp_encode_emoji( (string) ( $row['name'] ?? '' ) ),
					'grade' => wp_encode_emoji( (string) ( $row['grade']['name'] ?? '' ) ),
				);
			}

			usort(
				$teams,
				static function ( $a, $b ) {
					return strnatcasecmp( $a['name'], $b['name'] );
				}
			);

			return $teams;
		},
		WESTSHORE_PLAYHQ_TEAMS_TTL
	);
}

// ------------------------------------------------------------------ wording.

/**
 * The game's date and kickoff as a point in time, in the game's own zone.
 *
 * Play HQ sends the date, the clock time and the zone name separately. The
 * zone is the ground's, which for a BC competition is always Vancouver, but
 * it is read rather than assumed.
 *
 * @param array<string, mixed> $game A fixture row.
 * @return DateTimeImmutable|null
 */
function westshore_playhq_datetime( $game ) {
	try {
		$zone = new DateTimeZone( $game['tz'] );
	} catch ( Exception $e ) {
		$zone = wp_timezone();
	}

	$when = DateTimeImmutable::createFromFormat(
		'Y-m-d H:i:s',
		$game['date'] . ' ' . ( '' !== $game['time'] ? $game['time'] : '12:00:00' ),
		$zone
	);

	return $when instanceof DateTimeImmutable ? $when : null;
}

/**
 * "Saturday 19 September, 2:30pm", or the day alone.
 *
 * @param array<string, mixed> $game      A fixture row.
 * @param bool                 $with_time Whether to append the kickoff.
 * @return string
 */
function westshore_playhq_when( $game, $with_time = true ) {
	$when = westshore_playhq_datetime( $game );

	if ( ! $when ) {
		return $game['date'];
	}

	$day = wp_date( 'l j F', $when->getTimestamp(), $when->getTimezone() );

	if ( ! $with_time || '' === $game['time'] ) {
		return $day;
	}

	return $day . ', ' . westshore_playhq_kickoff( $game );
}

/**
 * The kickoff on its own, "2:30pm" or "2pm", or an empty string.
 *
 * @param array<string, mixed> $game A fixture row.
 * @return string
 */
function westshore_playhq_kickoff( $game ) {
	$when = westshore_playhq_datetime( $game );

	if ( ! $when || '' === $game['time'] ) {
		return '';
	}

	return str_replace( ':00', '', wp_date( 'g:ia', $when->getTimestamp(), $when->getTimezone() ) );
}

/**
 * "Won 38-36", "Lost 28-45", "Drew 20-20", "Result to come", or the kickoff.
 *
 * @param array<string, mixed> $game A fixture row.
 * @return string
 */
function westshore_playhq_result( $game ) {
	if ( 'FINAL' === $game['status'] && null !== $game['us_score'] && null !== $game['them_score'] ) {
		$score = $game['us_score'] . '-' . $game['them_score'];

		if ( 'WON' === $game['outcome'] || $game['us_score'] > $game['them_score'] ) {
			/* translators: %s: a score, ours first */
			return sprintf( __( 'Won %s', 'westshore' ), $score );
		}

		if ( 'LOST' === $game['outcome'] || $game['us_score'] < $game['them_score'] ) {
			/* translators: %s: a score, ours first */
			return sprintf( __( 'Lost %s', 'westshore' ), $score );
		}

		/* translators: %s: a score, ours first */
		return sprintf( __( 'Drew %s', 'westshore' ), $score );
	}

	if ( in_array( $game['status'], array( 'FINAL', 'PENDING' ), true ) ) {
		return __( 'Result to come', 'westshore' );
	}

	return westshore_playhq_kickoff( $game );
}

/**
 * Whether a game is still to be played.
 *
 * @param array<string, mixed> $game A fixture row.
 * @return bool
 */
function westshore_playhq_is_upcoming( $game ) {
	return ! in_array( $game['status'], array( 'FINAL', 'PENDING' ), true )
		&& $game['date'] >= wp_date( 'Y-m-d' );
}

/**
 * Whether a game is played, with or without a score entered yet.
 *
 * @param array<string, mixed> $game A fixture row.
 * @return bool
 */
function westshore_playhq_is_played( $game ) {
	return in_array( $game['status'], array( 'FINAL', 'PENDING' ), true );
}

/**
 * Whether a game is filed under an exhibition grade rather than the season.
 *
 * A pre-season friendly is a game too, but it is not what "next game" or
 * "next home game" means to a visitor, and its opponent can be anything.
 *
 * @param array<string, mixed> $game A fixture row.
 * @return bool
 */
function westshore_playhq_is_exhibition( $game ) {
	return false !== stripos( $game['grade_name'], 'exhibition' );
}

/**
 * The most recently played game, or null.
 *
 * @param array<int, array<string, mixed>> $games Sorted fixture rows.
 * @return array<string, mixed>|null
 */
function westshore_playhq_last_played( $games ) {
	$last = null;

	foreach ( $games as $game ) {
		if ( westshore_playhq_is_played( $game ) ) {
			$last = $game;
		}
	}

	return $last;
}

/**
 * The next game to be played, competition first, or null when none is left.
 *
 * An exhibition is only the answer when it is the only thing left, and the
 * renderers say so by naming its grade.
 *
 * @param array<int, array<string, mixed>> $games Sorted fixture rows.
 * @return array<string, mixed>|null
 */
function westshore_playhq_next_game( $games ) {
	$fallback = null;

	foreach ( $games as $game ) {
		if ( ! westshore_playhq_is_upcoming( $game ) ) {
			continue;
		}

		if ( ! westshore_playhq_is_exhibition( $game ) ) {
			return $game;
		}

		if ( ! $fallback ) {
			$fallback = $game;
		}
	}

	return $fallback;
}

/**
 * "Sat 12 Sep", for a card that has no room for the long form.
 *
 * @param array<string, mixed> $game      A fixture row.
 * @param bool                 $with_time Whether to append the kickoff.
 * @return string
 */
function westshore_playhq_when_short( $game, $with_time = false ) {
	$when = westshore_playhq_datetime( $game );

	if ( ! $when ) {
		return $game['date'];
	}

	$day = wp_date( 'D j M', $when->getTimestamp(), $when->getTimezone() );

	if ( ! $with_time || '' === $game['time'] ) {
		return $day;
	}

	return $day . ', ' . westshore_playhq_kickoff( $game );
}

/**
 * 1st, 2nd, 3rd, 4th, 11th, 12th, 13th, 21st.
 *
 * @param int $n A position.
 * @return string
 */
function westshore_playhq_ordinal( $n ) {
	$n = (int) $n;

	if ( $n % 100 >= 11 && $n % 100 <= 13 ) {
		return $n . 'th';
	}

	switch ( $n % 10 ) {
		case 1:
			return $n . 'st';
		case 2:
			return $n . 'nd';
		case 3:
			return $n . 'rd';
	}

	return $n . 'th';
}

/**
 * Where a team stands in its grade, as numbers, or null.
 *
 * @param string                                $team_id Our team id.
 * @param array<string, mixed>|array{}          $ladder  From westshore_playhq_ladder().
 * @return array{position: int, teams: int, played: int|null, points: int|null}|null
 */
function westshore_playhq_standing( $team_id, $ladder ) {
	if ( empty( $ladder['standings'] ) ) {
		return null;
	}

	foreach ( $ladder['standings'] as $i => $row ) {
		if ( $row['team_id'] === $team_id ) {
			return array(
				'position' => $i + 1,
				'teams'    => count( $ladder['standings'] ),
				'played'   => isset( $row['values']['played'] ) ? (int) $row['values']['played'] : null,
				'points'   => isset( $row['values']['competitionPoints'] ) ? (int) $row['values']['competitionPoints'] : null,
			);
		}
	}

	return null;
}

/**
 * Won, lost, drawn or pending, for a played game's colour and wording.
 *
 * @param array<string, mixed> $game A played fixture row.
 * @return string
 */
function westshore_playhq_outcome_class( $game ) {
	if ( 'FINAL' !== $game['status'] || null === $game['us_score'] || null === $game['them_score'] ) {
		return 'pending';
	}

	if ( 'WON' === $game['outcome'] || $game['us_score'] > $game['them_score'] ) {
		return 'won';
	}

	if ( 'LOST' === $game['outcome'] || $game['us_score'] < $game['them_score'] ) {
		return 'lost';
	}

	return 'drawn';
}

/**
 * "Sat 19 Sep, 2:30pm · at Capilano · Klahanie Park", one line for a card.
 *
 * The ground only when it is not ours, see westshore_playhq_ground().
 *
 * @param array<string, mixed> $game An upcoming fixture row.
 * @return string
 */
function westshore_playhq_next_short( $game ) {
	$line   = westshore_playhq_when_short( $game, true ) . ' · ' . westshore_playhq_versus( $game );
	$ground = westshore_playhq_ground( $game );

	if ( '' !== $ground ) {
		$line .= ' · ' . $ground;
	}

	if ( westshore_playhq_is_exhibition( $game ) ) {
		$line .= ' (' . $game['grade_name'] . ')';
	}

	return $line;
}

/**
 * A closed disclosure in the theme's accordion shape.
 *
 * The classes are the theme's, which styles the arrow and the bar; the
 * plugin's stylesheet carries a plain fallback under its own class for a
 * theme that does not.
 *
 * @param string $summary      The bar text.
 * @param string $body         HTML inside.
 * @param string $class        The plugin's own class for the element.
 * @param string $summary_html Escaped HTML appended inside the bar, for a second line.
 * @return string
 */
function westshore_playhq_fold( $summary, $body, $class, $summary_html = '' ) {
	return '<details class="wp-block-details westshore-details wsr-fold ' . esc_attr( $class ) . '">'
		. '<summary>' . esc_html( $summary ) . $summary_html . '</summary>'
		. $body
		. '</details>';
}

/**
 * The opponent without the grade suffix Play HQ appends to every team name.
 *
 * "Capilano Men's Premier" is "Capilano" on a page headed Mens Premier. The
 * suffix is whatever the opponent's name and our own Play HQ name share at
 * the end, word for word, and then a trailing Men's or Women's. When the two
 * share nothing the rest is left whole, so "JBAA Premier Reserves" stays.
 *
 * @param string $opponent The opponent's full name.
 * @param string $ours     Our team's full name in the same grade.
 * @return string
 */
function westshore_playhq_short_name( $opponent, $ours ) {
	$a = explode( ' ', trim( $opponent ) );
	$b = explode( ' ', trim( $ours ) );

	// Some junior sides carry a code after the grade, "Salish Sea Warriors
	// RFC U18 Girls U18G", which is nothing the grade has not said and stops
	// the suffix loop below before it starts. Ours never has one.
	if ( count( $a ) > 2 && preg_match( '/^U\d{1,2}[A-Z]?$/i', end( $a ) ) && ! preg_match( '/^U\d{1,2}$/i', prev( $a ) ) ) {
		array_pop( $a );
	}

	while ( count( $a ) > 1 && count( $b ) > 1 && strcasecmp( end( $a ), end( $b ) ) === 0 ) {
		array_pop( $a );
		array_pop( $b );
	}

	// What is left often ends in the sex of the side, "RichLions Women's" once
	// "Division 2" has gone, which the page heading already says.
	while ( count( $a ) > 1 && in_array( strtolower( end( $a ) ), array( "men's", "women's", 'men', 'women', 'mens', 'womens' ), true ) ) {
		array_pop( $a );
	}

	return implode( ' ', $a );
}

/**
 * "vs Capilano" for a home game, "at Capilano" for an away one.
 *
 * The one place home or away is put into words. Nathan's call, 2026-09-17:
 * the word Home or Away after the opponent was the thing a supporter never
 * says, and "vs" and "at" say it in the opponent's name. Every renderer
 * goes through here, so a change of wording is one edit.
 *
 * @param array<string, mixed> $game A fixture row.
 * @return string
 */
function westshore_playhq_versus( $game ) {
	$opponent = westshore_playhq_short_name( $game['opponent'], $game['us_name'] );

	return $game['home']
		/* translators: %s: opponent, a home game */
		? sprintf( __( 'vs %s', 'westshore' ), $opponent )
		/* translators: %s: opponent, an away game */
		: sprintf( __( 'at %s', 'westshore' ), $opponent );
}

/**
 * The ground a game is at, or an empty string when it is our own.
 *
 * A home game at Juan de Fuca needs no ground named, the facts band and the
 * training box already say where that is. A home game moved to a neutral
 * field, or any away game, does. Our own ground is whatever the Season
 * screen says it is, "Juan de Fuca Rugby Field, Colwood", and Play HQ names
 * the same place more briefly, "Juan de Fuca", so the test is whether the
 * season's name starts with Play HQ's. Nothing is hard-coded: a club that
 * moves grounds edits the Season screen and this follows.
 *
 * @param array<string, mixed> $game A fixture row.
 * @return string
 */
function westshore_playhq_ground( $game ) {
	$venue = trim( (string) $game['venue'] );

	if ( '' === $venue ) {
		return '';
	}

	$ours = trim( (string) westshore_season_settings()['ground'] );

	if ( '' !== $ours && 0 === stripos( $ours, $venue ) ) {
		return '';
	}

	return $venue;
}

/**
 * The scoreline strip: last result, next game, table position.
 *
 * Three panels at the content width, one column on a phone. The result panel
 * carries a coloured left rule, green for a win and the crest red for a loss,
 * the one place on the site green appears (design-direction.md says why).
 *
 * @param array<string, mixed>|null                                        $last     Last played game.
 * @param array<string, mixed>|null                                        $next     Next game.
 * @param array{position: int, teams: int, played: int|null, points: int|null}|null $standing Where we stand.
 * @return string
 */
function westshore_playhq_strip( $last, $next, $standing ) {
	// A pre-season strip would say the first game twice, once as "season
	// opens" and once as "next game", so before the first result the next
	// panel carries the opening on its own and the strip is two panels.
	$out = '<div class="wsr-scoreline' . ( $last ? '' : ' wsr-scoreline--no-result' ) . ( $standing ? '' : ' wsr-scoreline--no-table' ) . '">';

	// The result panel.
	if ( $last ) {
		$class  = westshore_playhq_outcome_class( $last );
		$versus = westshore_playhq_versus( $last );
		$words  = array(
			'won'     => __( 'Won', 'westshore' ),
			'lost'    => __( 'Lost', 'westshore' ),
			'drawn'   => __( 'Drew', 'westshore' ),
			'pending' => __( 'Played', 'westshore' ),
		);

		$out .= '<div class="wsr-scoreline__panel wsr-scoreline__panel--result wsr-scoreline__panel--' . esc_attr( $class ) . '">'
			. '<p class="wsr-scoreline__label">' . esc_html__( 'Last result', 'westshore' ) . '</p>';

		if ( 'pending' === $class ) {
			$out .= '<p class="wsr-scoreline__opponent">' . esc_html( $versus ) . '</p>'
				. '<p class="wsr-scoreline__line"><strong>' . esc_html( $words[ $class ] ) . '</strong></p>'
				. '<p class="wsr-scoreline__meta">' . esc_html( westshore_playhq_when_short( $last ) . ' · ' . __( 'Result to come', 'westshore' ) ) . '</p>';
		} else {
			$out .= '<p class="wsr-scoreline__score">' . (int) $last['us_score'] . ' <span>-</span> ' . (int) $last['them_score'] . '</p>'
				. '<p class="wsr-scoreline__line"><strong>' . esc_html( $words[ $class ] ) . '</strong> ' . esc_html( $versus ) . '</p>'
				. '<p class="wsr-scoreline__meta">' . esc_html( westshore_playhq_when_short( $last ) . ( '' !== $last['round'] ? ' · ' . $last['round'] : '' ) ) . '</p>';
		}

		$out .= '</div>';
	}

	// The next panel.
	$out .= '<div class="wsr-scoreline__panel wsr-scoreline__panel--next">'
		. '<p class="wsr-scoreline__label">' . esc_html( $last || ! $next ? __( 'Next game', 'westshore' ) : __( 'Season opens', 'westshore' ) ) . '</p>';

	if ( $next ) {
		$meta = array_filter( array( westshore_playhq_ground( $next ), westshore_playhq_is_exhibition( $next ) ? $next['grade_name'] : $next['round'] ) );

		$out .= '<p class="wsr-scoreline__opponent">' . esc_html( westshore_playhq_versus( $next ) ) . '</p>'
			. '<p class="wsr-scoreline__line">' . esc_html( westshore_playhq_when_short( $next, true ) ) . '</p>'
			. '<p class="wsr-scoreline__meta">' . esc_html( implode( ' · ', $meta ) ) . '</p>';
	} else {
		$out .= '<p class="wsr-scoreline__opponent">' . esc_html__( 'Season finished', 'westshore' ) . '</p>';
	}

	$out .= '</div>';

	// The table panel.
	if ( $standing ) {
		$out .= '<div class="wsr-scoreline__panel wsr-scoreline__panel--table">'
			. '<p class="wsr-scoreline__label">' . esc_html__( 'Table', 'westshore' ) . '</p>';

		if ( null !== $standing['played'] && 0 === $standing['played'] ) {
			// Before a ball is kicked the order is alphabetical, and "7th" reads
			// as bottom of the table rather than as the season not started.
			$out .= '<p class="wsr-scoreline__position wsr-scoreline__position--none">&#8212;</p>'
				/* translators: %d: number of teams */
				. '<p class="wsr-scoreline__meta">' . esc_html( sprintf( __( '%d teams, not started', 'westshore' ), $standing['teams'] ) ) . '</p>';
		} else {
			$meta = sprintf( /* translators: %d: number of teams */ __( 'of %d', 'westshore' ), $standing['teams'] );

			if ( null !== $standing['points'] ) {
				/* translators: %d: competition points */
				$meta .= ' · ' . sprintf( __( '%d pts', 'westshore' ), $standing['points'] );
			}

			$out .= '<p class="wsr-scoreline__position">' . esc_html( westshore_playhq_ordinal( $standing['position'] ) ) . '</p>'
				. '<p class="wsr-scoreline__meta">' . esc_html( $meta ) . '</p>';
		}

		$out .= '</div>';
	}

	return $out . '</div>';
}

// --------------------------------------------------------------- shortcodes.

/**
 * [westshore_fixtures team="mens-premier" count="0" ladder="yes" fold="no"]
 *
 * The team's season as one table: date, round, opponent, where, and the score
 * or the kickoff. The next game to be played is marked. count="N" keeps only
 * the next N unplayed games, for a page that wants a short list. ladder="yes"
 * prints the league table's fold beside the season's, so a team page needs
 * one shortcode per side rather than this and [westshore_ladder]. fold="yes"
 * puts the whole block behind one closed bar that names the side and its next
 * game, for a page with more sides than a visitor wants to scroll past: the
 * Juniors page has six, and a parent opens the one they came for.
 *
 * Final HTML rather than block markup, for the reason the fees shortcode in
 * season.php gives: a shortcode runs after the block parser. The theme's own
 * table rules handle the overflow on a phone.
 *
 * @param array<string, string>|string $atts Attributes.
 * @return string
 */
function westshore_fixtures_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'team'   => '',
			'count'  => 0,
			'ladder' => '',
			'fold'   => '',
		),
		$atts,
		'westshore_fixtures'
	);

	$team = westshore_playhq_team( $atts['team'] );

	if ( ! $team ) {
		return westshore_playhq_placeholder( __( 'Fixtures', 'westshore' ), $atts['team'] );
	}

	$games = westshore_playhq_fixture( $team['team_id'] );

	if ( ! $games ) {
		return westshore_playhq_placeholder( __( 'Fixtures', 'westshore' ), $team['name'] );
	}

	$grade = westshore_playhq_grade_for( $games );
	$count = max( 0, (int) $atts['count'] );

	if ( $count > 0 ) {
		$games = array_slice( array_values( array_filter( $games, 'westshore_playhq_is_upcoming' ) ), 0, $count );
	}

	westshore_lazy_style( 'westshore-fixtures', 'fixtures.css' );

	$next_marked = false;
	$rows        = '';

	foreach ( $games as $game ) {
		$classes = array();

		if ( ! $next_marked && westshore_playhq_is_upcoming( $game ) ) {
			$classes[]   = 'wsr-fixtures__next';
			$next_marked = true;
		}

		if ( $grade && $game['grade_id'] !== $grade['id'] ) {
			$classes[] = 'wsr-fixtures__other';
		}

		$round = $game['round'];

		// A game from another grade says so in place of its round number, which
		// would otherwise read as a round of this season.
		if ( $grade && $game['grade_id'] !== $grade['id'] ) {
			$round = $game['grade_name'];
		}

		// The full table names every ground, ours included: this is where
		// someone checks which Saturdays are at Juan de Fuca. The vs or at
		// in the opponent column carries home or away on a phone, where the
		// ground column is hidden.
		$rows .= '<tr' . ( $classes ? ' class="' . esc_attr( implode( ' ', $classes ) ) . '"' : '' ) . '>'
			. '<td class="wsr-fixtures__date">' . esc_html( westshore_playhq_when( $game, false ) ) . '</td>'
			. '<td class="wsr-fixtures__round">' . esc_html( $round ) . '</td>'
			. '<td class="wsr-fixtures__opponent">' . esc_html( westshore_playhq_versus( $game ) ) . '</td>'
			. '<td class="wsr-fixtures__venue">' . esc_html( $game['venue'] ) . '</td>'
			. '<td class="wsr-fixtures__result' . ( westshore_playhq_is_upcoming( $game ) ? ' wsr-fixtures__kickoff' : '' ) . '">' . esc_html( westshore_playhq_result( $game ) ) . '</td>'
			. '</tr>';
	}

	// The side's name alone. The grade name used to sit under it as an
	// eyebrow, "Senior Men Premier" under "Mens Premier" under a page headed
	// "Senior Mens", and Nathan read it as the same thing three times over
	// (2026-09-17). It names the Play HQ link below instead.
	$folded = in_array( strtolower( (string) $atts['fold'] ), array( 'yes', '1', 'true' ), true );
	$out    = $folded ? '' : '<h3 class="wp-block-heading has-medium-font-size wsr-fixtures__title">' . esc_html( $team['name'] ) . '</h3>';

	// The strip a visitor came for, always visible: the last score in big
	// figures, the next game beside it, and the table position as a number.
	// The whole season is a fold below it: two teams a page at eighteen rows
	// each was more page than anyone wanted to scroll.
	$ladder = $grade ? westshore_playhq_ladder( $grade['id'] ) : array();

	$out .= westshore_playhq_strip(
		westshore_playhq_last_played( $games ),
		westshore_playhq_next_game( $games ),
		westshore_playhq_standing( $team['team_id'], $ladder )
	);

	$table = '<table class="wsr-fixtures__table"><thead><tr>'
		. '<th scope="col">' . esc_html__( 'Date', 'westshore' ) . '</th>'
		. '<th scope="col" class="wsr-fixtures__round">' . esc_html__( 'Round', 'westshore' ) . '</th>'
		. '<th scope="col">' . esc_html__( 'Opponent', 'westshore' ) . '</th>'
		. '<th scope="col" class="wsr-fixtures__venue">' . esc_html__( 'Ground', 'westshore' ) . '</th>'
		. '<th scope="col">' . esc_html__( 'Result', 'westshore' ) . '</th>'
		. '</tr></thead><tbody>' . $rows . '</tbody></table>';

	if ( $grade && '' !== $grade['url'] ) {
		$table .= '<p class="wsr-fixtures__link has-small-font-size"><a href="' . esc_url( $grade['url'] ) . '" target="_blank" rel="noreferrer noopener">'
			. esc_html( '' !== $grade['name']
				/* translators: %s: grade name */
				? sprintf( __( '%s on Play HQ', 'westshore' ), $grade['name'] )
				: __( 'Game centre on Play HQ', 'westshore' ) )
			. '</a></p>';
	}

	$folds = westshore_playhq_fold(
		/* translators: %d: number of games */
		sprintf( _n( 'Full season, %d game', 'Full season, %d games', count( $games ), 'westshore' ), count( $games ) ),
		$table,
		'wsr-fixtures__more'
	);

	// ladder="yes" puts the league table's fold beside the season's, one row
	// of two under the strip, instead of a second box under the first. The
	// two folds are one grid, so they have to come from one shortcode.
	if ( $grade && in_array( strtolower( (string) $atts['ladder'] ), array( 'yes', '1', 'true' ), true ) ) {
		$folds .= westshore_playhq_ladder_fold( $team, $grade, $ladder );
		$folds  = '<div class="wsr-scoreline__more">' . $folds . '</div>';
	}

	// fold="yes": the whole block behind one closed bar. The bar is the side's
	// name and its next game, so six closed bars still answer "when do we
	// play" without a click, and there is no h3 because the bar names the
	// side. The next-game panel inside repeats the bar, which is the price
	// of the strip being one shape everywhere.
	if ( $folded ) {
		// Day, kickoff and opponent on the bar, the ground as a second muted
		// line under it. For a junior side the ground is the thing a parent
		// needs on the Sunday, and "Salish Sea Warriors" is not a place the way
		// Brockton Oval is (Nathan, 2026-09-18). Ours is named too: the strip
		// inside leaves it out, but the bar is read closed.
		$next  = westshore_playhq_next_game( $games );
		$label = $team['name'] . ' · ' . ( $next ? westshore_playhq_when_short( $next, true ) . ' · ' . westshore_playhq_versus( $next ) : __( 'Season finished', 'westshore' ) );
		$where = $next && '' !== $next['venue'] ? '<span class="wsr-fixtures__fold-where">' . esc_html( $next['venue'] ) . '</span>' : '';

		return '<div class="wsr-fixtures wsr-fixtures--folded">'
			. westshore_playhq_fold( '', $out . $folds, 'wsr-fixtures__fold', '<span class="wsr-fixtures__fold-label">' . esc_html( $label ) . '</span>' . $where )
			. '</div>';
	}

	return '<div class="wsr-fixtures">' . $out . $folds . '</div>';
}
add_shortcode( 'westshore_fixtures', 'westshore_fixtures_shortcode' );

/**
 * [westshore_ladder team="mens-premier"]
 *
 * The league table of the grade the team plays in, Westshore's row marked.
 * Six columns, asked for by key so the order BC Rugby sends them in does not
 * matter: played, won, drawn, lost, points difference, competition points.
 *
 * @param array<string, string>|string $atts Attributes.
 * @return string
 */
function westshore_ladder_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'team' => '' ), $atts, 'westshore_ladder' );
	$team = westshore_playhq_team( $atts['team'] );

	if ( ! $team ) {
		return westshore_playhq_placeholder( __( 'Ladder', 'westshore' ), $atts['team'] );
	}

	$games = westshore_playhq_fixture( $team['team_id'] );
	$grade = $games ? westshore_playhq_grade_for( $games ) : null;

	if ( ! $grade ) {
		return westshore_playhq_placeholder( __( 'Ladder', 'westshore' ), $team['name'] );
	}

	$ladder = westshore_playhq_ladder( $grade['id'] );

	if ( empty( $ladder['standings'] ) ) {
		return westshore_playhq_placeholder( __( 'Ladder', 'westshore' ), $team['name'] );
	}

	westshore_lazy_style( 'westshore-fixtures', 'fixtures.css' );

	// The position itself is on the strip in the fixtures block above; this
	// block is the full table, folded.
	return '<div class="wsr-ladder">' . westshore_playhq_ladder_fold( $team, $grade, $ladder ) . '</div>';
}
add_shortcode( 'westshore_ladder', 'westshore_ladder_shortcode' );

/**
 * The league table as a fold: the summary line and the table inside it.
 *
 * Shared by [westshore_ladder] and by [westshore_fixtures ladder="yes"],
 * which prints it beside the season fold. Empty when the grade has no
 * standings yet, so the fixtures block never shows an empty box.
 *
 * @param array<string, mixed> $team   The configured team.
 * @param array<string, mixed> $grade  The grade the ladder belongs to.
 * @param array<string, mixed> $ladder The ladder as westshore_playhq_ladder() returns it.
 * @return string
 */
function westshore_playhq_ladder_fold( $team, $grade, $ladder ) {
	if ( empty( $ladder['standings'] ) ) {
		return '';
	}

	$columns = array(
		'played'            => __( 'P', 'westshore' ),
		'won'               => __( 'W', 'westshore' ),
		'drawn'             => __( 'D', 'westshore' ),
		'lost'              => __( 'L', 'westshore' ),
		'pointsDifference'  => __( 'PD', 'westshore' ),
		'competitionPoints' => __( 'Pts', 'westshore' ),
	);

	// Only the columns this ladder actually has. A grade with no points
	// difference column would otherwise show a column of dashes.
	$columns = array_intersect_key( $columns, $ladder['headers'] );

	$head = '<th scope="col" class="wsr-ladder__pos"><span class="screen-reader-text">' . esc_html__( 'Position', 'westshore' ) . '</span></th>'
		. '<th scope="col" class="wsr-ladder__team">' . esc_html__( 'Team', 'westshore' ) . '</th>';

	foreach ( $columns as $key => $label ) {
		$head .= '<th scope="col" class="wsr-ladder__num" title="' . esc_attr( $ladder['headers'][ $key ] ) . '">' . esc_html( $label ) . '</th>';
	}

	// Every name in a grade ends the same way, "Men's Premier" eleven times
	// over, and the caption already says so. Ours is the reference the others
	// are shortened against, and it shortens to the club's own name.
	$ours  = '';
	$other = '';

	foreach ( $ladder['standings'] as $standing ) {
		if ( $standing['team_id'] === $team['team_id'] ) {
			$ours = $standing['team'];
		} elseif ( '' === $other ) {
			$other = $standing['team'];
		}
	}

	$rows     = '';
	$position = 0;

	foreach ( $ladder['standings'] as $standing ) {
		++$position;
		$us   = $standing['team_id'] === $team['team_id'];
		$name = $standing['team'];

		if ( '' !== $ours && '' !== $other ) {
			$name = westshore_playhq_short_name( $standing['team'], $us ? $other : $ours );
		}

		$rows .= '<tr' . ( $us ? ' class="wsr-ladder__us"' : '' ) . '>'
			. '<td class="wsr-ladder__pos">' . (int) $position . '</td>'
			. '<td class="wsr-ladder__team">' . esc_html( $name ) . '</td>';

		foreach ( $columns as $key => $label ) {
			$value = $standing['values'][ $key ];
			$rows .= '<td class="wsr-ladder__num">' . ( null === $value ? '' : esc_html( (string) $value ) ) . '</td>';
		}

		$rows .= '</tr>';
	}

	// The bar says "League table" and nothing more: it sits under the side's
	// name, and the grade name on it and again as a caption inside was the
	// same heading three times once the fold was open (Nathan, 2026-09-17).
	// The caption keeps the grade for a screen reader, which lands on the
	// table without the heading above it.
	return westshore_playhq_fold(
		__( 'League table', 'westshore' ),
		'<table class="wsr-ladder__table"><caption class="screen-reader-text">' . esc_html( $grade['name'] ) . '</caption>'
		. '<thead><tr>' . $head . '</tr></thead><tbody>' . $rows . '</tbody></table>',
		'wsr-ladder__more'
	);
}

/**
 * [westshore_next_home_game layout="line"]
 *
 * The soonest home game across every configured team. layout="line" is one
 * paragraph in the facts band's shape; layout="panel" is a filled navy panel
 * for the top of a results section, the same ground as the scoreline's next
 * game panel, with the ground and the stream link under it. Either way the
 * shortcode prints its own wrapper rather than a bare string: with no home
 * game to show, the whole thing has to vanish, and a bare string would leave
 * a label sitting over nothing.
 *
 * @param array<string, string>|string $atts Attributes.
 * @return string
 */
function westshore_next_home_game_shortcode( $atts ) {
	$atts      = shortcode_atts( array( 'layout' => 'line' ), $atts, 'westshore_next_home_game' );
	$best      = null;
	$best_team = null;

	foreach ( westshore_playhq_teams() as $team ) {
		foreach ( westshore_playhq_fixture( $team['team_id'] ) as $game ) {
			// Competition games only. A pre-season exhibition is a home game too,
			// but "v Ebb Tide Open Mixed Rising Tide Over 30s" is not the line
			// the front page is for.
			if ( ! $game['home'] || ! westshore_playhq_is_upcoming( $game ) || westshore_playhq_is_exhibition( $game ) ) {
				continue;
			}

			if ( ! $best || strcmp( $game['date'] . $game['time'], $best['date'] . $best['time'] ) < 0 ) {
				$best      = $game;
				$best_team = $team;
			}
		}
	}

	if ( ! $best ) {
		return '';
	}

	/* translators: 1: our team, 2: "vs Capilano" */
	$who    = sprintf( __( '%1$s %2$s', 'westshore' ), $best_team['name'], westshore_playhq_versus( $best ) );
	$ground = westshore_playhq_ground( $best );

	if ( 'panel' !== $atts['layout'] ) {
		$line = westshore_playhq_when( $best ) . ', ' . $who;

		// A home game somewhere other than our ground is the one case the
		// line has to say where.
		if ( '' !== $ground ) {
			$line .= ', ' . $ground;
		}

		return '<p><strong>' . esc_html__( 'Next home game', 'westshore' ) . '</strong>' . esc_html( $line ) . '</p>';
	}

	westshore_lazy_style( 'westshore-fixtures', 'fixtures.css' );

	// Our own ground is named here even though the line form leaves it out:
	// the panel stands on its own at the top of a section, with no facts
	// band beside it to say where home is.
	$meta = '<span>' . esc_html( '' !== $ground ? $ground : westshore_season_settings()['ground'] ) . '</span>';

	if ( function_exists( 'westshore_youtube_channel_url' ) ) {
		$meta .= ' <span aria-hidden="true">·</span> <a href="' . esc_url( westshore_youtube_channel_url() ) . '" target="_blank" rel="noreferrer noopener">'
			. esc_html__( 'Streamed live on YouTube', 'westshore' ) . '</a>';
	}

	return '<div class="wsr-nexthome">'
		. '<p class="wsr-nexthome__label">' . esc_html__( 'Next home game', 'westshore' ) . '</p>'
		. '<p class="wsr-nexthome__when">' . esc_html( westshore_playhq_when( $best ) ) . '</p>'
		. '<p class="wsr-nexthome__who">' . esc_html( $who ) . '</p>'
		. '<p class="wsr-nexthome__meta">' . $meta . '</p>'
		. '</div>';
}
add_shortcode( 'westshore_next_home_game', 'westshore_next_home_game_shortcode' );

/**
 * [westshore_scoreboard teams="all"]
 *
 * One compact card per side: where it stands, the last result, the next
 * game, and a link to its page. The front page names the four senior sides;
 * the Teams page asks for "all", which is every side on the Season screen
 * with a competition game to its name, so a junior grade appearing on Play
 * HQ shows up there on its own.
 *
 * The theme's card classes, the way the fees and streams shortcodes use them,
 * with is-layout-flow written out for the reason season.php gives.
 *
 * @param array<string, string>|string $atts Attributes.
 * @return string
 */
function westshore_scoreboard_shortcode( $atts ) {
	$atts  = shortcode_atts( array( 'teams' => 'all' ), $atts, 'westshore_scoreboard' );
	$teams = westshore_playhq_teams();

	if ( 'all' !== trim( $atts['teams'] ) ) {
		$wanted = array_filter( array_map( 'sanitize_title', explode( ',', $atts['teams'] ) ) );
		$teams  = array_intersect_key( $teams, array_flip( $wanted ) );
	}

	$cards = '';

	foreach ( $teams as $team ) {
		$games = westshore_playhq_fixture( $team['team_id'] );

		if ( ! $games || ! array_filter( $games, static function ( $game ) {
			return ! westshore_playhq_is_exhibition( $game );
		} ) ) {
			continue;
		}

		$grade  = westshore_playhq_grade_for( $games );
		$ladder = $grade ? westshore_playhq_ladder( $grade['id'] ) : array();
		$last   = westshore_playhq_last_played( $games );
		$next   = westshore_playhq_next_game( $games );

		$standing = westshore_playhq_standing( $team['team_id'], $ladder );
		$pos      = '';

		if ( $standing && ( null === $standing['played'] || $standing['played'] > 0 ) ) {
			/* translators: 1: ordinal position, 2: number of teams */
			$pos = sprintf( __( '%1$s of %2$d', 'westshore' ), westshore_playhq_ordinal( $standing['position'] ), $standing['teams'] );
		} elseif ( $standing ) {
			/* translators: %d: number of teams */
			$pos = sprintf( __( '%d teams', 'westshore' ), $standing['teams'] );
		}

		// A navy header that bleeds to the card edge, the way the page bands do.
		$card = '<div class="wsr-score__head"><span class="wsr-score__team">' . esc_html( $team['name'] ) . '</span>'
			. ( '' !== $pos ? '<span class="wsr-score__pos">' . esc_html( $pos ) . '</span>' : '' ) . '</div>'
			. '<div class="wsr-score__body">';

		if ( $last ) {
			$class  = westshore_playhq_outcome_class( $last );
			$versus = westshore_playhq_versus( $last );

			$card .= '<p class="wsr-score__meta">' . esc_html( sprintf( /* translators: %s: date */ __( 'Last result · %s', 'westshore' ), westshore_playhq_when_short( $last ) ) ) . '</p>';

			if ( 'pending' === $class ) {
				$card .= '<p class="wsr-score__score wsr-score__score--pending">' . esc_html( sprintf( /* translators: %s: "vs Capilano" or "at Capilano" */ __( '%s, result to come', 'westshore' ), $versus ) ) . '</p>';
			} else {
				$letters = array(
					'won'   => _x( 'W', 'win, one letter', 'westshore' ),
					'lost'  => _x( 'L', 'loss, one letter', 'westshore' ),
					'drawn' => _x( 'D', 'draw, one letter', 'westshore' ),
				);

				$card .= '<p class="wsr-score__score"><span class="wsr-score__letter wsr-score__letter--' . esc_attr( $class ) . '">' . esc_html( $letters[ $class ] ) . '</span> '
					. (int) $last['us_score'] . ' - ' . (int) $last['them_score']
					. ' <span class="wsr-score__opp">' . esc_html( $versus ) . '</span></p>';
			}

			$card .= '<p class="wsr-score__next"><span>' . esc_html__( 'Next', 'westshore' ) . '</span> '
				. esc_html( $next ? westshore_playhq_next_short( $next ) : __( 'Season finished', 'westshore' ) ) . '</p>';
		} elseif ( $next ) {
			$ground = westshore_playhq_ground( $next );

			$card .= '<p class="wsr-score__meta">' . esc_html__( 'Season opens', 'westshore' ) . '</p>'
				. '<p class="wsr-score__score wsr-score__score--opens">' . esc_html( westshore_playhq_versus( $next ) ) . '</p>'
				. '<p class="wsr-score__next"><span>' . esc_html( westshore_playhq_when_short( $next, true ) . ( '' !== $ground ? ' · ' . $ground : '' ) ) . '</span></p>';
		}

		if ( ! empty( $team['page_id'] ) ) {
			$link = get_permalink( (int) $team['page_id'] );

			if ( $link ) {
				$card .= '<p class="westshore-card__link"><a href="' . esc_url( $link . '#fixtures' ) . '">' . esc_html__( 'Season and table', 'westshore' ) . '</a></p>';
			}
		}

		$card .= '</div>';

		$cards .= '<div class="wp-block-column westshore-card westshore-card--link wsr-score is-layout-flow wp-block-column-is-layout-flow">' . $card . '</div>';
	}

	if ( '' === $cards ) {
		return westshore_playhq_placeholder( __( 'Scoreboard', 'westshore' ), $atts['teams'] );
	}

	westshore_lazy_style( 'westshore-fixtures', 'fixtures.css' );

	return '<div class="wsr-scoreboard"><div class="wp-block-columns westshore-cards">' . $cards . '</div></div>';
}
add_shortcode( 'westshore_scoreboard', 'westshore_scoreboard_shortcode' );

/**
 * What stands in when there is nothing to show.
 *
 * Nothing on production, the same as the streams and Instagram wrappers. On
 * staging a plain panel naming what was asked for, so an empty section reads
 * as "not fetched" and not as work never done.
 *
 * @param string $what 'Fixtures' or 'Ladder'.
 * @param string $team The team asked for.
 * @return string
 */
function westshore_playhq_placeholder( $what, $team ) {
	if ( 'production' === wp_get_environment_type() ) {
		return '';
	}

	wp_enqueue_style( 'westshore-sponsors' );

	$reason = '' === westshore_playhq_key()
		? __( 'No Play HQ key is saved under Westshore > Season.', 'westshore' )
		: __( 'Either the team is not on the Season screen, or Play HQ has no games for it yet, or the fetch failed and there is no earlier copy to show.', 'westshore' );

	return '<div class="wsr-insta-placeholder"><p><strong>' .
		/* translators: 1: Fixtures or Ladder, 2: the team asked for */
		esc_html( sprintf( __( '%1$s for "%2$s": nothing to show.', 'westshore' ), $what, $team ) ) .
		'</strong></p><p>' . esc_html( $reason ) . ' ' .
		esc_html__( 'This panel is on staging only and never renders on the live site.', 'westshore' ) .
		'</p></div>';
}
