<?php
/**
 * Typed owner references for Link Pages.
 *
 * @package ExtraChillLinkPages
 */

defined( 'ABSPATH' ) || exit;

const EC_LINK_PAGE_OWNER_META_KEY = '_ec_link_page_owner_reference';

/**
 * Parse an opaque owner reference without resolving its object.
 *
 * @param string $reference Owner reference in kind:blog:subtype:id form.
 * @return array{kind:string,blog_id:int,subtype:string,object_id:int,reference:string}|WP_Error
 */
function ec_parse_link_page_owner_reference( $reference ) {
	if ( ! is_string( $reference ) || 1 !== preg_match( '/^(post|term):([1-9][0-9]*):([a-z0-9_-]+):([1-9][0-9]*)$/', $reference, $matches ) ) {
		return new WP_Error( 'invalid_link_page_owner_reference', 'The Link Page owner reference is malformed.' );
	}

	return array(
		'kind'      => $matches[1],
		'blog_id'   => (int) $matches[2],
		'subtype'   => $matches[3],
		'object_id' => (int) $matches[4],
		'reference' => $reference,
	);
}

/**
 * Format owner fields as an opaque owner reference.
 *
 * @param array{kind:mixed,blog_id:mixed,subtype:mixed,object_id:mixed} $owner Owner fields.
 * @return string|WP_Error
 */
function ec_format_link_page_owner_reference( $owner ) {
	if ( ! is_array( $owner ) ) {
		return new WP_Error( 'invalid_link_page_owner', 'Link Page owner fields must be an array.' );
	}

	$kind      = isset( $owner['kind'] ) && is_string( $owner['kind'] ) ? $owner['kind'] : '';
	$blog_id   = isset( $owner['blog_id'] ) ? absint( $owner['blog_id'] ) : 0;
	$subtype   = isset( $owner['subtype'] ) && is_string( $owner['subtype'] ) ? $owner['subtype'] : '';
	$object_id = isset( $owner['object_id'] ) ? absint( $owner['object_id'] ) : 0;
	$reference = sprintf( '%s:%d:%s:%d', $kind, $blog_id, $subtype, $object_id );
	$parsed    = ec_parse_link_page_owner_reference( $reference );

	return is_wp_error( $parsed ) ? $parsed : $reference;
}

/**
 * Normalize and validate an owner reference against its WordPress object.
 *
 * @param string|array $owner Owner reference or fields.
 * @return string|WP_Error
 */
function ec_normalize_link_page_owner_reference( $owner ) {
	$reference = is_array( $owner ) ? ec_format_link_page_owner_reference( $owner ) : $owner;
	if ( is_wp_error( $reference ) ) {
		return $reference;
	}

	$parsed = ec_parse_link_page_owner_reference( $reference );
	if ( is_wp_error( $parsed ) ) {
		return $parsed;
	}

	$site = get_site( $parsed['blog_id'] );
	if ( ! $site || ! empty( $site->deleted ) || ! empty( $site->archived ) || ! empty( $site->spam ) ) {
		return new WP_Error( 'invalid_link_page_owner_blog', 'The Link Page owner blog is unavailable.' );
	}

	$did_switch = get_current_blog_id() !== $parsed['blog_id'];
	if ( $did_switch ) {
		switch_to_blog( $parsed['blog_id'] );
	}

	try {
		if ( 'post' === $parsed['kind'] ) {
			if ( ! post_type_exists( $parsed['subtype'] ) || get_post_type( $parsed['object_id'] ) !== $parsed['subtype'] ) {
				return new WP_Error( 'invalid_link_page_owner_object', 'The Link Page post owner does not match its declared type.' );
			}
		} elseif ( ! taxonomy_exists( $parsed['subtype'] ) || ! get_term( $parsed['object_id'], $parsed['subtype'] ) ) {
			return new WP_Error( 'invalid_link_page_owner_object', 'The Link Page term owner does not match its declared taxonomy.' );
		}
	} finally {
		if ( $did_switch ) {
			restore_current_blog();
		}
	}

	return ec_format_link_page_owner_reference( $parsed );
}

/**
 * Return all stored owner values without treating duplicate rows as valid.
 *
 * @param int $link_page_id Link Page post ID.
 * @return array
 */
function ec_get_stored_link_page_owner_references( $link_page_id ) {
	$values = get_post_meta( $link_page_id, EC_LINK_PAGE_OWNER_META_KEY, false );
	if ( ! is_array( $values ) ) {
		$values = array( $values );
	}

	return array_values( $values );
}

/**
 * Resolve the normalized owner fields for a Link Page.
 *
 * @param int $link_page_id Link Page post ID on the current blog.
 * @return array{kind:string,blog_id:int,subtype:string,object_id:int,reference:string}|WP_Error
 */
function ec_get_link_page_owner( $link_page_id ) {
	$link_page_id = absint( $link_page_id );
	if ( ! $link_page_id || EC_LINK_PAGE_POST_TYPE !== get_post_type( $link_page_id ) ) {
		return new WP_Error( 'invalid_link_page', 'The Link Page does not exist.' );
	}

	$stored = ec_get_stored_link_page_owner_references( $link_page_id );
	if ( count( $stored ) > 1 ) {
		return new WP_Error( 'duplicate_link_page_owner_references', 'The Link Page has duplicate stored owner references.' );
	}

	if ( empty( $stored ) ) {
		$reference = apply_filters( 'ec_link_page_legacy_owner_reference', '', $link_page_id );
		if ( ! is_string( $reference ) || '' === $reference ) {
			return new WP_Error( 'link_page_owner_not_found', 'The Link Page has no owner association.' );
		}
	} else {
		$reference = $stored[0];
	}

	$normalized = ec_normalize_link_page_owner_reference( $reference );
	if ( is_wp_error( $normalized ) ) {
		return $normalized;
	}

	return ec_parse_link_page_owner_reference( $normalized );
}

/**
 * Find the unique Link Page assigned to an owner on the current blog.
 *
 * @param string|array $owner                 Owner reference or fields.
 * @param int[]        $allowed_link_pages Known temporary duplicates during replacement.
 * @return int|WP_Error Link Page ID, zero when absent, or a conflict error.
 */
function ec_get_link_page_id_for_owner( $owner, $allowed_link_pages = array() ) {
	$reference = ec_normalize_link_page_owner_reference( $owner );
	if ( is_wp_error( $reference ) ) {
		return $reference;
	}

	// Owner uniqueness requires an exact lookup on the canonical metadata value.
	// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	$link_page_ids = get_posts(
		array(
			'post_type'      => EC_LINK_PAGE_POST_TYPE,
			'post_status'    => 'any',
			'meta_key'       => EC_LINK_PAGE_OWNER_META_KEY,
			'meta_value'     => $reference,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);
	// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	$compatibility_ids = apply_filters( 'ec_link_page_legacy_owner_candidates', array(), $reference );
	if ( ! is_array( $compatibility_ids ) ) {
		return new WP_Error( 'invalid_link_page_owner_candidates', 'A Link Page owner compatibility provider returned invalid candidates.' );
	}
	$candidate_ids      = array_values( array_unique( array_map( 'absint', array_merge( $link_page_ids, $compatibility_ids ) ) ) );
	$allowed_link_pages = array_values( array_unique( array_map( 'absint', $allowed_link_pages ) ) );
	$candidate_ids      = array_values( array_filter( $candidate_ids ) );
	$allowed_link_pages = array_values( array_filter( $allowed_link_pages ) );
	sort( $candidate_ids, SORT_NUMERIC );
	sort( $allowed_link_pages, SORT_NUMERIC );
	if ( count( $candidate_ids ) > 1 && $allowed_link_pages !== $candidate_ids ) {
		return new WP_Error( 'duplicate_link_pages_for_owner', 'Multiple Link Pages resolve to the same owner.' );
	}

	return empty( $candidate_ids ) ? 0 : (int) $candidate_ids[0];
}

/**
 * Assign the unique normalized owner of a Link Page.
 *
 * @param int          $link_page_id          Link Page post ID.
 * @param string|array $owner                 Owner reference or fields.
 * @param int          $replace_link_page_id  Existing page intentionally being replaced.
 * @return true|WP_Error
 */
function ec_assign_link_page_owner( $link_page_id, $owner, $replace_link_page_id = 0 ) {
	$link_page_id = absint( $link_page_id );
	if ( ! $link_page_id || EC_LINK_PAGE_POST_TYPE !== get_post_type( $link_page_id ) ) {
		return new WP_Error( 'invalid_link_page', 'The Link Page does not exist.' );
	}

	$reference = ec_normalize_link_page_owner_reference( $owner );
	if ( is_wp_error( $reference ) ) {
		return $reference;
	}

	$stored = ec_get_stored_link_page_owner_references( $link_page_id );
	if ( count( $stored ) > 1 ) {
		return new WP_Error( 'duplicate_link_page_owner_references', 'The Link Page has duplicate stored owner references.' );
	}
	if ( ! empty( $stored ) && $stored[0] !== $reference ) {
		return new WP_Error( 'link_page_owner_conflict', 'The Link Page is already assigned to another owner.' );
	}

	$allowed_link_pages    = $replace_link_page_id ? array( $link_page_id, $replace_link_page_id ) : array();
	$existing_link_page_id = ec_get_link_page_id_for_owner( $reference, $allowed_link_pages );
	if ( is_wp_error( $existing_link_page_id ) ) {
		return $existing_link_page_id;
	}
	if ( $existing_link_page_id && $link_page_id !== $existing_link_page_id && absint( $replace_link_page_id ) !== $existing_link_page_id ) {
		return new WP_Error( 'link_page_owner_conflict', 'The owner is already assigned to another Link Page.' );
	}
	if ( 1 === count( $stored ) && $stored[0] === $reference ) {
		return true;
	}

	update_post_meta( $link_page_id, EC_LINK_PAGE_OWNER_META_KEY, $reference );
	$stored = ec_get_stored_link_page_owner_references( $link_page_id );
	if ( 1 !== count( $stored ) || $reference !== $stored[0] ) {
		return ec_compensate_link_page_owner_assignment(
			$link_page_id,
			$reference,
			new WP_Error( 'link_page_owner_assignment_failed', 'The Link Page owner could not be persisted.' )
		);
	}

	$persisted_link_page_id = ec_get_link_page_id_for_owner( $reference, $allowed_link_pages );
	if ( is_wp_error( $persisted_link_page_id ) ) {
		return ec_compensate_link_page_owner_assignment( $link_page_id, $reference, $persisted_link_page_id );
	}

	return true;
}

/**
 * Remove and verify canonical metadata written by a failed assignment.
 *
 * @param int      $link_page_id Link Page post ID.
 * @param string   $reference    Attempted owner reference.
 * @param WP_Error $error        Assignment error to return after compensation.
 * @return WP_Error
 */
function ec_compensate_link_page_owner_assignment( $link_page_id, $reference, $error ) {
	delete_post_meta( $link_page_id, EC_LINK_PAGE_OWNER_META_KEY, $reference );
	$remaining = ec_get_stored_link_page_owner_references( $link_page_id );
	if ( in_array( $reference, $remaining, true ) ) {
		return new WP_Error(
			'link_page_owner_compensation_failed',
			'A failed owner assignment could not be compensated. Manual reconciliation is required.',
			array( 'retryable' => false )
		);
	}

	return $error;
}

/**
 * Backfill a bounded page of legacy owner associations.
 *
 * @param int $limit  Number of Link Pages to inspect, capped at 500.
 * @param int $offset Query offset for resumable CLI use.
 * @return array{processed:int,updated:int,skipped:int,errors:array,next_offset:int}
 */
function ec_backfill_link_page_owner_references( $limit = 100, $offset = 0 ) {
	$limit         = min( 500, max( 1, absint( $limit ) ) );
	$offset        = absint( $offset );
	$link_page_ids = get_posts(
		array(
			'post_type'      => EC_LINK_PAGE_POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);
	$result        = array(
		'processed'   => 0,
		'updated'     => 0,
		'skipped'     => 0,
		'errors'      => array(),
		'next_offset' => $offset,
	);

	foreach ( $link_page_ids as $link_page_id ) {
		++$result['processed'];
		$stored = ec_get_stored_link_page_owner_references( $link_page_id );
		if ( ! empty( $stored ) ) {
			$owner = ec_get_link_page_owner( $link_page_id );
			if ( is_wp_error( $owner ) ) {
				$result['errors'][ (int) $link_page_id ] = $owner->get_error_code();
				continue;
			}
			++$result['skipped'];
			continue;
		}

		$owner = ec_get_link_page_owner( $link_page_id );
		if ( is_wp_error( $owner ) ) {
			$result['errors'][ (int) $link_page_id ] = $owner->get_error_code();
			continue;
		}

		$assigned = ec_assign_link_page_owner( $link_page_id, $owner );
		if ( is_wp_error( $assigned ) ) {
			$result['errors'][ (int) $link_page_id ] = $assigned->get_error_code();
			if ( 'link_page_owner_compensation_failed' === $assigned->get_error_code() ) {
				break;
			}
			continue;
		}
		++$result['updated'];
	}

	$result['next_offset'] = $offset + $result['processed'];
	return $result;
}
