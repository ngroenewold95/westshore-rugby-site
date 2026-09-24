<?php
/**
 * The parts sponsors and people were both carrying a copy of.
 *
 * Two content types had grown roughly 300 lines each of the same code: the same
 * term-order comparator, the same add and edit term forms, the same seed
 * writer, the same four-gate save check, the same admin column shape. A third
 * type would have copied all of it again, and copied the traps with it.
 *
 * Nothing here is new behaviour. Every function was lifted from whichever of
 * the two files had the better version of it, and the other one now calls this
 * instead. Where the two disagreed, the safer one won and the reason is in the
 * docblock.
 *
 * @package Westshore_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every term in a taxonomy, in the club's running order.
 *
 * Was westshore_get_person_groups() and westshore_get_sponsor_tier_terms(),
 * whose comparators were byte-identical apart from a comment. Same meta key for
 * both taxonomies on purpose, so there is one convention rather than two.
 *
 * A term with no order set sorts last, not first. An unset value is far more
 * likely to be a term somebody just added than a deliberate "put this at the
 * top", and the club can always type a number.
 *
 * The order is read once per term into a map before sorting rather than inside
 * the comparator, which called get_term_meta() O(n log n) times. Irrelevant at
 * seventeen groups, and the wrong shape to hand a third taxonomy.
 *
 * @param string $taxonomy Taxonomy name.
 * @return array<int, WP_Term>
 */
function westshore_ordered_terms( $taxonomy ) {
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		)
	);

	if ( is_wp_error( $terms ) ) {
		return array();
	}

	$order = array();

	foreach ( $terms as $term ) {
		$value = get_term_meta( $term->term_id, 'westshore_order', true );

		$order[ $term->term_id ] = '' === $value ? PHP_INT_MAX : (int) $value;
	}

	usort(
		$terms,
		function ( $a, $b ) use ( $order ) {
			if ( $order[ $a->term_id ] === $order[ $b->term_id ] ) {
				return strcmp( $a->name, $b->name );
			}

			return $order[ $a->term_id ] < $order[ $b->term_id ] ? -1 : 1;
		}
	);

	return $terms;
}

/**
 * A term by its slug, or by the name a volunteer would recognise.
 *
 * Was westshore_find_group(), which sponsors never had. The slug is what the
 * code uses and the name is what the admin shows, and nobody typing a shortcode
 * into a page should have to know the difference.
 *
 * Slug first, because it is the stable identifier and renaming a term does not
 * move it. That is the reason to prefer the slug in page content.
 *
 * @param string $taxonomy Taxonomy name.
 * @param string $value    Slug or display name.
 * @return WP_Term|null
 */
function westshore_find_term_loosely( $taxonomy, $value ) {
	$value = trim( (string) $value );

	if ( '' === $value ) {
		return null;
	}

	$term = get_term_by( 'slug', sanitize_title( $value ), $taxonomy );

	if ( $term instanceof WP_Term ) {
		return $term;
	}

	$term = get_term_by( 'name', $value, $taxonomy );

	return $term instanceof WP_Term ? $term : null;
}

/**
 * Create a taxonomy's starting terms, only where they are missing.
 *
 * Seed values only. Once a term exists the admin is the source of truth for its
 * name, its order and its settings, and this never looks at it again. That
 * matters because groups are meant to be renamed, reordered and removed: a
 * reactivation that reset them would quietly undo real work.
 *
 * @param string                                  $taxonomy Taxonomy name.
 * @param array<string, array<string, mixed>>     $seeds    Keyed by slug. Each entry
 *                                                          takes 'name', an optional
 *                                                          'description', and a 'meta'
 *                                                          array of term meta to write.
 * @return int How many terms were created.
 */
function westshore_seed_terms( $taxonomy, array $seeds ) {
	$created = 0;

	foreach ( $seeds as $slug => $seed ) {
		if ( get_term_by( 'slug', $slug, $taxonomy ) ) {
			continue;
		}

		$args = array( 'slug' => $slug );

		if ( isset( $seed['description'] ) ) {
			$args['description'] = $seed['description'];
		}

		$result = wp_insert_term( $seed['name'], $taxonomy, $args );

		if ( is_wp_error( $result ) ) {
			continue;
		}

		++$created;

		if ( empty( $seed['meta'] ) || ! is_array( $seed['meta'] ) ) {
			continue;
		}

		foreach ( $seed['meta'] as $key => $value ) {
			update_term_meta( $result['term_id'], $key, $value );
		}
	}

	return $created;
}

/**
 * Describe a taxonomy's settings once, and get both forms and the save.
 *
 * Generalised from the sponsor tier version, which already kept its field list
 * in one array so the two forms and the save could not drift. People had the
 * same two forms written out by hand twice.
 *
 * Each field takes:
 *   label        Shown to the volunteer.
 *   type         'text', 'number' or 'checkbox'.
 *   meta         Term meta key. Explicit rather than derived, because the
 *                existing keys are not uniform: order is 'westshore_order' and
 *                the coaches flag is 'westshore_on_coaches_page'.
 *   placeholder  Optional, text and number only.
 *   help         Optional line under the field.
 *   min          Optional floor on a number. A tier saved with 0 columns or a
 *                0px logo height renders invisible logos with no error, so the
 *                fields that feed CSS carry a floor.
 *   label_inline Optional. Checkbox only: the words next to the box.
 *
 * @param string                              $taxonomy Taxonomy name.
 * @param string                              $prefix   POST field prefix, e.g. 'westshore_tier'.
 * @param array<string, array<string, mixed>> $fields   Keyed by field key.
 * @param callable|null                       $reader   Optional. Given ( WP_Term, $key, $meta_key ),
 *                                                      returns the value to show on the edit form.
 *                                                      Defaults to the raw term meta. Sponsors pass
 *                                                      one so the form keeps showing the effective
 *                                                      value for a tier that predates these fields.
 */
function westshore_register_term_settings( $taxonomy, $prefix, array $fields, $reader = null ) {
	add_action(
		$taxonomy . '_add_form_fields',
		function () use ( $prefix, $fields ) {
			westshore_term_settings_marker( $prefix, false );

			foreach ( $fields as $key => $field ) {
				westshore_term_settings_field( $prefix, $key, $field, '', false );
			}
		}
	);

	add_action(
		$taxonomy . '_edit_form_fields',
		function ( $term ) use ( $prefix, $fields, $reader ) {
			westshore_term_settings_marker( $prefix, true );

			foreach ( $fields as $key => $field ) {
				$value = is_callable( $reader )
					? call_user_func( $reader, $term, $key, $field['meta'] )
					: get_term_meta( $term->term_id, $field['meta'], true );

				westshore_term_settings_field( $prefix, $key, $field, $value, true );
			}
		}
	);

	$save = function ( $term_id ) use ( $prefix, $fields ) {
		westshore_save_term_settings( $term_id, $prefix, $fields );
	};

	add_action( 'created_' . $taxonomy, $save );
	add_action( 'edited_' . $taxonomy, $save );
}

/**
 * The hidden marker that says this post came from one of the settings forms.
 *
 * Without it this was a real bug and it bit within the week of shipping. Quick
 * Edit renames a term without ever showing these fields, so nothing is posted,
 * and an unticked checkbox and an absent checkbox are identical in $_POST.
 * Renaming "Mens Division 1" from the list screen quietly took the whole team
 * off the Coaches page, with no error and nothing to see.
 *
 * Any term update that does not carry this marker leaves the settings alone.
 *
 * @param string $prefix POST field prefix.
 * @param bool   $table  True on the edit screen, which is a table.
 */
function westshore_term_settings_marker( $prefix, $table ) {
	$input = sprintf(
		'<input type="hidden" name="%s_fields" value="1" />',
		esc_attr( $prefix )
	);

	echo $table
		? '<tr style="display:none;"><td>' . $input . '</td></tr>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		: $input; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * One settings field, on whichever of the two forms is asking.
 *
 * The add screen is divs and the edit screen is table rows. That is WordPress's
 * shape, not a choice.
 *
 * @param string               $prefix POST field prefix.
 * @param string               $key    Field key.
 * @param array<string, mixed> $field  Field description.
 * @param mixed                $value  Current value.
 * @param bool                 $table  True on the edit screen.
 */
function westshore_term_settings_field( $prefix, $key, $field, $value, $table ) {
	$id   = esc_attr( str_replace( '_', '-', $prefix . '-' . $key ) );
	$name = esc_attr( $prefix . '_' . $key );
	$help = isset( $field['help'] ) ? $field['help'] : '';

	if ( 'checkbox' === $field['type'] ) {
		$control = sprintf(
			'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
			$id,
			$name,
			checked( (bool) $value, true, false ),
			esc_html( isset( $field['label_inline'] ) ? $field['label_inline'] : $field['label'] )
		);

		$label = '';
	} else {
		$control = sprintf(
			'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" placeholder="%5$s"%6$s%7$s />',
			esc_attr( $field['type'] ),
			$id,
			$name,
			esc_attr( (string) $value ),
			esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' ),
			isset( $field['min'] ) ? ' min="' . esc_attr( (string) $field['min'] ) . '"' : '',
			$table && 'text' === $field['type'] ? ' class="large-text"' : ''
		);

		$label = sprintf( '<label for="%s">%s</label>', $id, esc_html( $field['label'] ) );
	}

	if ( $table ) {
		printf(
			'<tr class="form-field"><th scope="row">%s</th><td>%s%s</td></tr>',
			'' === $label ? esc_html( $field['label'] ) : $label, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$control, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$help ? '<p class="description">' . esc_html( $help ) . '</p>' : ''
		);

		return;
	}

	printf(
		'<div class="form-field">%s%s%s</div>',
		$label, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$control, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$help ? '<p>' . esc_html( $help ) . '</p>' : ''
	);
}

/**
 * Write the settings back.
 *
 * WordPress has already run its own nonce check by the time the created_ and
 * edited_ hooks fire, so this checks the capability, checks the marker, and
 * takes the values.
 *
 * An emptied text or number field deletes its meta rather than storing an empty
 * string, so the term falls back to its default instead of rendering with
 * nothing in it. A checkbox writes 0 or 1 either way, because for a checkbox
 * "off" is a real answer.
 *
 * @param int                                 $term_id Term.
 * @param string                              $prefix  POST field prefix.
 * @param array<string, array<string, mixed>> $fields  Field descriptions.
 */
function westshore_save_term_settings( $term_id, $prefix, array $fields ) {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! isset( $_POST[ $prefix . '_fields' ] ) ) {
		return;
	}

	foreach ( $fields as $key => $field ) {
		$name = $prefix . '_' . $key;

		if ( 'checkbox' === $field['type'] ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_term_meta( $term_id, $field['meta'], isset( $_POST[ $name ] ) ? 1 : 0 );
			continue;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST[ $name ] ) ) {
			continue;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$value = sanitize_text_field( wp_unslash( $_POST[ $name ] ) );

		if ( '' === $value ) {
			delete_term_meta( $term_id, $field['meta'] );
			continue;
		}

		if ( 'number' === $field['type'] ) {
			$value = (int) $value;

			if ( isset( $field['min'] ) && $value < (int) $field['min'] ) {
				$value = (int) $field['min'];
			}
		}

		update_term_meta( $term_id, $field['meta'], $value );
	}
}

/**
 * The four gates every save_post handler was opening by hand.
 *
 * Nonce present, nonce valid, not an autosave, and the user may edit this post.
 * All four, in that order, in both files, byte for byte.
 *
 * @param int    $post_id     Post being saved.
 * @param string $nonce_field The nonce input's name.
 * @param string $action      The nonce action.
 * @return bool True when it is safe to write.
 */
function westshore_can_save_post( $post_id, $nonce_field, $action ) {
	if ( ! isset( $_POST[ $nonce_field ] ) ) {
		return false;
	}

	if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ $nonce_field ] ) ), $action ) ) {
		return false;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return false;
	}

	return (bool) current_user_can( 'edit_post', $post_id );
}

/**
 * Add one column to a post type's list screen.
 *
 * A list of names is useless for spotting the one with the wrong image or the
 * one with no role, which is why both types grew a column. Same shape twice.
 *
 * @param string   $post_type    Post type.
 * @param string   $key          Column key.
 * @param string   $label        Column heading.
 * @param callable $callback     Given a post ID, echoes the cell.
 * @param bool     $before_title Put it left of the title rather than after it.
 */
function westshore_register_admin_column( $post_type, $key, $label, $callback, $before_title = false ) {
	add_filter(
		'manage_' . $post_type . '_posts_columns',
		function ( $columns ) use ( $key, $label, $before_title ) {
			if ( ! $before_title ) {
				$columns[ $key ] = $label;

				return $columns;
			}

			$new = array();

			foreach ( $columns as $existing => $existing_label ) {
				if ( 'title' === $existing ) {
					$new[ $key ] = $label;
				}

				$new[ $existing ] = $existing_label;
			}

			return $new;
		}
	);

	add_action(
		'manage_' . $post_type . '_posts_custom_column',
		function ( $column, $post_id ) use ( $key, $callback ) {
			if ( $key === $column ) {
				call_user_func( $callback, $post_id );
			}
		},
		10,
		2
	);
}

/**
 * The red "this one is missing something" span both list screens use.
 *
 * @param string $text What is missing.
 * @return string
 */
function westshore_admin_missing( $text ) {
	return '<span style="color:#b32d2e;">' . esc_html( $text ) . '</span>';
}

/**
 * Enqueue one of the plugin's stylesheets, the first time something needs it.
 *
 * Sponsors registered on wp_enqueue_scripts and then enqueued inside the
 * renderer; people skipped the registration and enqueued lazily behind a
 * wp_style_is() guard. Two conventions for one job. The people one wins: a page
 * with no sponsors and no people loads neither file.
 *
 * @param string $handle Style handle.
 * @param string $file   File name inside assets/.
 */
function westshore_lazy_style( $handle, $file ) {
	if ( wp_style_is( $handle, 'enqueued' ) ) {
		return;
	}

	wp_enqueue_style(
		$handle,
		WESTSHORE_CORE_URL . 'assets/' . $file,
		array(),
		WESTSHORE_CORE_VERSION
	);
}

/**
 * Turn an image URL into an attachment ID.
 *
 * There were two of these, in import-people.php and import-sponsors.php, with
 * different bodies and the same name. Both are global functions in files loaded
 * by wp eval-file, so running them in one process was a fatal redeclare, and
 * the cutover only avoided it by running them as separate calls. The weaker of
 * the two was the one the people import depended on.
 *
 * This is the sponsors version, which is the one that works. Two things stand
 * between markup and the attachment row and both have to be undone:
 *
 * 1. Content points at resized files, foo-1024x1024.png. attachment_url_to_postid
 *    only matches the original.
 * 2. WordPress renames anything over the big-image threshold on upload, so the
 *    original on disk is foo-scaled.png, not foo.png. Seven of the eleven
 *    sponsor logos are in that state.
 *
 * Matching is on the exact file name, never a partial one. A LIKE on
 * "Red-Arrow" would happily return the 2017 "Red-Arrow-Brewing.jpg" and put the
 * wrong logo on the page, which is worse than reporting no match.
 *
 * @param string $url Image URL.
 * @return int Attachment ID, or 0.
 */
function westshore_attachment_from_url( $url ) {
	$id = attachment_url_to_postid( $url );

	if ( $id ) {
		return $id;
	}

	// Strip the -WxH size suffix.
	$stripped = preg_replace( '/-\d+x\d+(\.[a-zA-Z0-9]+)$/', '$1', $url );
	$stripped = $stripped ? $stripped : $url;

	$id = attachment_url_to_postid( $stripped );

	if ( $id ) {
		return $id;
	}

	$file = basename( wp_parse_url( $stripped, PHP_URL_PATH ) );
	$ext  = pathinfo( $file, PATHINFO_EXTENSION );
	$stem = pathinfo( $file, PATHINFO_FILENAME );

	$candidates = array( $file );

	if ( $ext && ! str_ends_with( $stem, '-scaled' ) ) {
		$candidates[] = $stem . '-scaled.' . $ext;
	}

	global $wpdb;

	foreach ( $candidates as $candidate ) {
		// Anchored on the full file name after the last slash, so a partial
		// name cannot match a different sponsor's logo.
		$id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				 WHERE meta_key = '_wp_attached_file'
				   AND ( meta_value = %s OR meta_value LIKE %s )
				 LIMIT 1",
				$candidate,
				'%/' . $wpdb->esc_like( $candidate )
			)
		);

		if ( $id ) {
			return $id;
		}
	}

	return 0;
}
