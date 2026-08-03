<?php
/**
 * Artist compatibility adapter for Link Page owner references.
 *
 * @package ExtraChillArtistPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve a legacy artist association as a normalized owner reference.
 *
 * @param string $reference    Existing fallback reference.
 * @param int    $link_page_id Link Page post ID.
 * @return string
 */
function ec_artist_link_page_legacy_owner_reference( $reference, $link_page_id ) {
	if ( '' !== $reference ) {
		return $reference;
	}

	$artist_id = (int) get_post_meta( $link_page_id, '_associated_artist_profile_id', true );
	$blog_id   = function_exists( 'ec_get_blog_id' ) ? (int) ec_get_blog_id( 'artist' ) : get_current_blog_id();
	if ( ! $artist_id || ! $blog_id ) {
		return '';
	}

	$reference = ec_format_link_page_owner_reference(
		array(
			'kind'      => 'post',
			'blog_id'   => $blog_id,
			'subtype'   => 'artist_profile',
			'object_id' => $artist_id,
		)
	);

	return is_wp_error( $reference ) ? '' : $reference;
}
add_filter( 'ec_link_page_legacy_owner_reference', 'ec_artist_link_page_legacy_owner_reference', 10, 2 );

/**
 * Find all Link Pages carrying the matching legacy artist association.
 *
 * @param int[]  $link_page_ids Existing compatibility candidates.
 * @param string $reference     Normalized owner reference.
 * @return int[]|WP_Error
 */
function ec_artist_link_page_legacy_owner_candidates( $link_page_ids, $reference ) {
	if ( is_wp_error( $link_page_ids ) ) {
		return $link_page_ids;
	}
	if ( ! is_array( $link_page_ids ) ) {
		return new WP_Error( 'invalid_link_page_owner_candidates', 'A Link Page owner compatibility provider returned an invalid candidate accumulator.' );
	}

	$owner = ec_parse_link_page_owner_reference( $reference );
	if ( is_wp_error( $owner ) ) {
		return $link_page_ids;
	}

	$artist_blog_id = function_exists( 'ec_get_blog_id' ) ? (int) ec_get_blog_id( 'artist' ) : get_current_blog_id();
	if ( 'post' !== $owner['kind'] || $artist_blog_id !== $owner['blog_id'] || 'artist_profile' !== $owner['subtype'] ) {
		return $link_page_ids;
	}
	foreach ( $link_page_ids as $link_page_id ) {
		if ( ! is_int( $link_page_id ) || $link_page_id <= 0 || EC_LINK_PAGE_POST_TYPE !== get_post_type( $link_page_id ) ) {
			continue;
		}
		$legacy_artist_id = (int) get_post_meta( $link_page_id, '_associated_artist_profile_id', true );
		if ( $legacy_artist_id && $legacy_artist_id !== $owner['object_id'] ) {
			return new WP_Error(
				'link_page_owner_divergence',
				'The Link Page canonical owner conflicts with its legacy artist association.',
				array(
					'link_page_id' => $link_page_id,
					'retryable'    => false,
				)
			);
		}
	}

	// Compatibility lookup requires the legacy reciprocal metadata value.
	// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	$legacy_ids = get_posts(
		array(
			'post_type'      => 'artist_link_page',
			'post_status'    => 'any',
			'meta_key'       => '_associated_artist_profile_id',
			'meta_value'     => (string) $owner['object_id'],
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);
	// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value

	foreach ( $legacy_ids as $legacy_id ) {
		$stored = ec_get_stored_link_page_owner_references( $legacy_id );
		if ( count( $stored ) > 1 ) {
			return new WP_Error( 'duplicate_link_page_owner_references', 'A legacy artist Link Page has duplicate canonical owner references.' );
		}
		if ( ! empty( $stored ) ) {
			$canonical = ec_normalize_link_page_owner_reference( $stored[0] );
			if ( is_wp_error( $canonical ) ) {
				return $canonical;
			}
			if ( $canonical !== $reference ) {
				return new WP_Error(
					'link_page_owner_divergence',
					'The Link Page canonical owner conflicts with its legacy artist association.',
					array(
						'link_page_id' => (int) $legacy_id,
						'retryable'    => false,
					)
				);
			}
		}
		$link_page_ids[] = (int) $legacy_id;
	}

	return $link_page_ids;
}
add_filter( 'ec_link_page_legacy_owner_candidates', 'ec_artist_link_page_legacy_owner_candidates', 10, 2 );
