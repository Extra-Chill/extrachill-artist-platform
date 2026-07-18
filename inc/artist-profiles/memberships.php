<?php
/**
 * Canonical Artist Membership Reads
 *
 * @package ExtraChillArtistPlatform
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'ec_get_artists_for_user' ) ) {
	/**
	 * Gets published artist profiles canonically linked to a user.
	 *
	 * @param int|null $user_id       User ID. Defaults to the current user.
	 * @param bool     $admin_override Whether administrators may access all artists.
	 * @return int[] Published artist profile IDs.
	 */
	function ec_get_artists_for_user( $user_id = null, $admin_override = false ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}

		$artist_blog_id = function_exists( 'ec_get_blog_id' ) ? ec_get_blog_id( 'artist' ) : null;
		if ( ! $artist_blog_id ) {
			return array();
		}

		switch_to_blog( $artist_blog_id );
		try {
			if ( $admin_override && user_can( $user_id, 'manage_options' ) ) {
				return get_posts(
					array(
						'post_type'   => 'artist_profile',
						'post_status' => 'publish',
						'numberposts' => -1,
						'fields'      => 'ids',
					)
				);
			}

			$user_artist_ids = get_user_meta( $user_id, '_artist_profile_ids', true );
			if ( ! is_array( $user_artist_ids ) ) {
				return array();
			}

			$artist_ids = array();
			foreach ( $user_artist_ids as $artist_id ) {
				$artist_id = absint( $artist_id );
				$artist    = $artist_id ? get_post( $artist_id ) : null;

				if ( ! $artist || 'artist_profile' !== $artist->post_type || 'publish' !== $artist->post_status ) {
					continue;
				}

				$member_ids = get_post_meta( $artist_id, '_artist_member_ids', true );
				if ( ! is_array( $member_ids ) || ! in_array( $user_id, array_map( 'absint', $member_ids ), true ) ) {
					continue;
				}

				$artist_ids[] = $artist_id;
			}

			return array_values( array_unique( $artist_ids ) );
		} finally {
			restore_current_blog();
		}
	}
}
