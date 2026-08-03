<?php
/**
 * Typed owner references for Link Pages.
 *
 * @package ExtraChillArtistPlatform
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
 * Legacy artist associations are a read-only fallback.
 *
 * @param int $link_page_id Link Page post ID on the current blog.
 * @return array{kind:string,blog_id:int,subtype:string,object_id:int,reference:string}|WP_Error
 */
function ec_get_link_page_owner( $link_page_id ) {
	$link_page_id = absint( $link_page_id );
	if ( ! $link_page_id || 'artist_link_page' !== get_post_type( $link_page_id ) ) {
		return new WP_Error( 'invalid_link_page', 'The Link Page does not exist.' );
	}

	$stored = ec_get_stored_link_page_owner_references( $link_page_id );
	if ( count( $stored ) > 1 ) {
		return new WP_Error( 'duplicate_link_page_owner_references', 'The Link Page has duplicate stored owner references.' );
	}

	if ( empty( $stored ) ) {
		$artist_id = (int) get_post_meta( $link_page_id, '_associated_artist_profile_id', true );
		$blog_id   = function_exists( 'ec_get_blog_id' ) ? (int) ec_get_blog_id( 'artist' ) : get_current_blog_id();
		if ( ! $artist_id || ! $blog_id ) {
			return new WP_Error( 'link_page_owner_not_found', 'The Link Page has no owner association.' );
		}
		$reference = ec_format_link_page_owner_reference(
			array(
				'kind'      => 'post',
				'blog_id'   => $blog_id,
				'subtype'   => 'artist_profile',
				'object_id' => $artist_id,
			)
		);
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
 * @param int[]        $allowed_legacy_pages Known temporary legacy duplicates during replacement.
 * @return int|WP_Error Link Page ID, zero when absent, or a conflict error.
 */
function ec_get_link_page_id_for_owner( $owner, $allowed_legacy_pages = array() ) {
	$reference = ec_normalize_link_page_owner_reference( $owner );
	if ( is_wp_error( $reference ) ) {
		return $reference;
	}

	// Owner uniqueness requires an exact lookup on the canonical metadata value.
	// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	$link_page_ids = get_posts(
		array(
			'post_type'      => 'artist_link_page',
			'post_status'    => 'any',
			'meta_key'       => EC_LINK_PAGE_OWNER_META_KEY,
			'meta_value'     => $reference,
			'posts_per_page' => 2,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);
	// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	if ( count( $link_page_ids ) > 1 ) {
		$allowed_owner_pages = array_values( array_unique( array_map( 'absint', $allowed_legacy_pages ) ) );
		sort( $allowed_owner_pages, SORT_NUMERIC );
		$normalized_link_page_ids = array_values( array_unique( array_map( 'absint', $link_page_ids ) ) );
		sort( $normalized_link_page_ids, SORT_NUMERIC );
		if ( $allowed_owner_pages !== $normalized_link_page_ids ) {
			return new WP_Error( 'duplicate_link_pages_for_owner', 'Multiple Link Pages store the same owner reference.' );
		}
	}
	if ( ! empty( $link_page_ids ) ) {
		return (int) $link_page_ids[0];
	}

	$parsed         = ec_parse_link_page_owner_reference( $reference );
	$artist_blog_id = function_exists( 'ec_get_blog_id' ) ? (int) ec_get_blog_id( 'artist' ) : get_current_blog_id();
	if ( 'post' !== $parsed['kind'] || $artist_blog_id !== $parsed['blog_id'] || 'artist_profile' !== $parsed['subtype'] ) {
		return 0;
	}

	// The compatibility fallback must query the legacy reciprocal association.
	// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	$legacy_ids = get_posts(
		array(
			'post_type'      => 'artist_link_page',
			'post_status'    => 'any',
			'meta_key'       => '_associated_artist_profile_id',
			'meta_value'     => (string) $parsed['object_id'],
			'posts_per_page' => 2,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);
	// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	if ( count( $legacy_ids ) > 1 ) {
		$allowed_legacy_pages = array_values( array_unique( array_map( 'absint', $allowed_legacy_pages ) ) );
		sort( $allowed_legacy_pages, SORT_NUMERIC );
		$normalized_legacy_ids = array_values( array_unique( array_map( 'absint', $legacy_ids ) ) );
		sort( $normalized_legacy_ids, SORT_NUMERIC );
		if ( $allowed_legacy_pages !== $normalized_legacy_ids ) {
			return new WP_Error( 'duplicate_link_pages_for_owner', 'Multiple legacy Link Pages are associated with the same owner.' );
		}
	}

	return empty( $legacy_ids ) ? 0 : (int) $legacy_ids[0];
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
	if ( ! $link_page_id || 'artist_link_page' !== get_post_type( $link_page_id ) ) {
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

	$allowed_legacy_pages  = $replace_link_page_id ? array( $link_page_id, $replace_link_page_id ) : array();
	$existing_link_page_id = ec_get_link_page_id_for_owner( $reference, $allowed_legacy_pages );
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
		return new WP_Error( 'link_page_owner_assignment_failed', 'The Link Page owner could not be persisted.' );
	}

	$persisted_link_page_id = ec_get_link_page_id_for_owner( $reference, $allowed_legacy_pages );
	if ( is_wp_error( $persisted_link_page_id ) ) {
		delete_post_meta( $link_page_id, EC_LINK_PAGE_OWNER_META_KEY, $reference );
		if ( ! empty( ec_get_stored_link_page_owner_references( $link_page_id ) ) ) {
			return new WP_Error( 'link_page_owner_compensation_failed', 'A conflicting owner assignment could not be compensated safely.' );
		}
		return $persisted_link_page_id;
	}

	return true;
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
			'post_type'      => 'artist_link_page',
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
			continue;
		}
		++$result['updated'];
	}

	$result['next_offset'] = $offset + $result['processed'];
	return $result;
}
