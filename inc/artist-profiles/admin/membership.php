<?php
/**
 * Canonical artist membership relationship primitives.
 *
 * @package ExtraChillArtistPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds an artist profile ID to both sides of the membership relationship.
 *
 * A false result may leave one side written. Reciprocal readers reject that
 * partial state, and retrying this idempotent operation reconciles it.
 *
 * @param int $user_id   The ID of the user.
 * @param int $artist_id The ID of the artist_profile post.
 * @return bool True when both sides contain the relationship, false on failure.
 */
function ec_add_artist_membership( $user_id, $artist_id ) {
	$user_id   = absint( $user_id );
	$artist_id = absint( $artist_id );

	if ( ! $user_id || ! $artist_id || ! get_userdata( $user_id ) ) {
		return false;
	}

	$artist_blog_id = function_exists( 'ec_get_blog_id' ) ? ec_get_blog_id( 'artist' ) : null;
	if ( ! $artist_blog_id ) {
		return false;
	}

	switch_to_blog( $artist_blog_id );
	try {
		if ( 'artist_profile' !== get_post_type( $artist_id ) || 'publish' !== get_post_status( $artist_id ) ) {
			return false;
		}

		$member_ids = ec_normalize_artist_relationship_ids( get_post_meta( $artist_id, '_artist_member_ids', true ) );
		if ( ! in_array( $user_id, $member_ids, true ) ) {
			$member_ids[] = $user_id;
			if ( ! update_post_meta( $artist_id, '_artist_member_ids', $member_ids ) ) {
				return false;
			}
		}
	} finally {
		restore_current_blog();
	}

	$artist_ids = ec_normalize_artist_relationship_ids( get_user_meta( $user_id, '_artist_profile_ids', true ) );
	if ( ! in_array( $artist_id, $artist_ids, true ) ) {
		$artist_ids[] = $artist_id;
		if ( ! update_user_meta( $user_id, '_artist_profile_ids', $artist_ids ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Removes an artist membership from both relationship records.
 *
 * Both writes are attempted. A false result is safe to retry and reciprocal
 * readers reject either possible one-sided state in the meantime.
 *
 * @param int $user_id   The ID of the user.
 * @param int $artist_id The ID of the artist_profile post.
 * @return bool True on success, false on failure.
 */
function ec_remove_artist_membership( $user_id, $artist_id ) {
	$user_id   = absint( $user_id );
	$artist_id = absint( $artist_id );

	if ( ! $user_id || ! $artist_id ) {
		return false;
	}

	$user_updated = true;
	$artist_ids   = ec_normalize_artist_relationship_ids( get_user_meta( $user_id, '_artist_profile_ids', true ) );
	if ( in_array( $artist_id, $artist_ids, true ) ) {
		$artist_ids   = array_values( array_diff( $artist_ids, array( $artist_id ) ) );
		$user_updated = (bool) update_user_meta( $user_id, '_artist_profile_ids', $artist_ids );
	}

	$artist_blog_id = function_exists( 'ec_get_blog_id' ) ? ec_get_blog_id( 'artist' ) : null;
	if ( ! $artist_blog_id ) {
		return false;
	}

	$artist_updated = true;
	switch_to_blog( $artist_blog_id );
	try {
		$member_ids = ec_normalize_artist_relationship_ids( get_post_meta( $artist_id, '_artist_member_ids', true ) );
		if ( in_array( $user_id, $member_ids, true ) ) {
			$member_ids     = array_values( array_diff( $member_ids, array( $user_id ) ) );
			$artist_updated = (bool) update_post_meta( $artist_id, '_artist_member_ids', $member_ids );
		}
	} finally {
		restore_current_blog();
	}

	return $user_updated && $artist_updated;
}

/**
 * Normalize IDs stored in either side of the artist membership relationship.
 *
 * @param mixed $ids Stored relationship value.
 * @return int[] Unique positive IDs.
 */
function ec_normalize_artist_relationship_ids( $ids ) {
	if ( ! is_array( $ids ) ) {
		return array();
	}

	return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
}

/**
 * Gets reciprocally linked users from the maintained artist-side roster.
 *
 * @param int $artist_profile_id The ID of the artist profile CPT.
 * @return array Array of WP_User objects.
 */
function ec_get_linked_members( $artist_profile_id ) {
	$artist_profile_id = absint( $artist_profile_id );
	$artist_blog_id    = function_exists( 'ec_get_blog_id' ) ? ec_get_blog_id( 'artist' ) : null;
	if ( ! $artist_profile_id || ! $artist_blog_id ) {
		return array();
	}

	switch_to_blog( $artist_blog_id );
	try {
		if ( 'artist_profile' !== get_post_type( $artist_profile_id ) || 'publish' !== get_post_status( $artist_profile_id ) ) {
			return array();
		}
		$member_ids = ec_normalize_artist_relationship_ids( get_post_meta( $artist_profile_id, '_artist_member_ids', true ) );
	} finally {
		restore_current_blog();
	}

	$members = array();
	foreach ( $member_ids as $user_id ) {
		$user       = get_userdata( $user_id );
		$artist_ids = ec_normalize_artist_relationship_ids( get_user_meta( $user_id, '_artist_profile_ids', true ) );
		if ( $user && in_array( $artist_profile_id, $artist_ids, true ) ) {
			$members[] = $user;
		}
	}

	return $members;
}
