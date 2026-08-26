<?php
/**
 * Artist Manager doorway to the Events-owned Local Support workspace.
 *
 * @package ExtraChillArtistPlatform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalize raw binding metadata without hiding duplicate rows.
 *
 * @param mixed $claims Raw metadata values.
 * @return int[] Positive IDs, preserving duplicates.
 */
function extrachill_artist_platform_normalize_binding_claims( $claims ) {
	$claims = is_array( $claims ) ? $claims : array( $claims );
	return array_values( array_filter( array_map( 'absint', $claims ) ) );
}

/**
 * Resolve the Events organizer workspace for one exactly managed Artist.
 *
 * The canonical profile/term pair is read under the existing binding lock.
 * Missing, stale, or duplicate claims fail closed rather than allowing a
 * client-selected identity to cross the site boundary.
 *
 * @param int $artist_id Artist profile post ID.
 * @param int $actor_id  Network user ID. Defaults to the current user.
 * @return string Absolute Events workspace URL, or an empty string.
 */
function extrachill_artist_platform_local_support_workspace_url( $artist_id, $actor_id = 0 ) {
	$artist_id = absint( $artist_id );
	$actor_id  = $actor_id ? absint( $actor_id ) : get_current_user_id();
	if (
		$artist_id < 1
		|| $actor_id < 1
		|| ! function_exists( 'ec_get_blog_id' )
		|| ! function_exists( 'ec_get_site_url' )
		|| ! function_exists( 'ec_user_can_manage_artist_object' )
		|| ! function_exists( 'ec_acquire_artist_binding_lock' )
	) {
		return '';
	}

	$artist_blog_id = absint( ec_get_blog_id( 'artist' ) );
	$main_blog_id   = absint( ec_get_blog_id( 'main' ) );
	$events_blog_id = absint( ec_get_blog_id( 'events' ) );
	$events_site    = $events_blog_id ? get_site( $events_blog_id ) : null;
	if (
		$artist_blog_id < 1
		|| $main_blog_id < 1
		|| ! $events_site
		|| ! empty( $events_site->deleted )
		|| ! empty( $events_site->archived )
		|| ! empty( $events_site->spam )
	) {
		return '';
	}

	switch_to_blog( $artist_blog_id );
	try {
		$profile    = get_post( $artist_id );
		$authorized = $profile
			&& 'artist_profile' === $profile->post_type
			&& 'publish' === $profile->post_status
			&& ec_user_can_manage_artist_object( $actor_id, $artist_id );
	} finally {
		restore_current_blog();
	}
	if ( ! $authorized ) {
		return '';
	}

	$lock = ec_acquire_artist_binding_lock();
	if ( is_wp_error( $lock ) ) {
		return '';
	}

	$term_id = 0;
	try {
		switch_to_blog( $artist_blog_id );
		try {
			$profile_claims = extrachill_artist_platform_normalize_binding_claims( get_post_meta( $artist_id, '_artist_term_id', false ) );
			$profile_ids    = 1 === count( $profile_claims ) ? get_posts(
				array(
					'post_type'   => 'artist_profile',
					'post_status' => 'any',
					'meta_key'    => '_artist_term_id',
					'meta_value'  => (string) $profile_claims[0],
					'fields'      => 'ids',
					'numberposts' => 2,
					'orderby'     => 'ID',
					'order'       => 'ASC',
				)
			) : array();
		} finally {
			restore_current_blog();
		}

		if ( 1 !== count( $profile_claims ) || array( $artist_id ) !== array_map( 'intval', $profile_ids ) ) {
			return '';
		}
		$term_id = $profile_claims[0];

		switch_to_blog( $main_blog_id );
		try {
			$term        = get_term( $term_id, 'artist' );
			$term_claims = extrachill_artist_platform_normalize_binding_claims( get_term_meta( $term_id, '_artist_profile_id', false ) );
			$term_ids    = get_terms(
				array(
					'taxonomy'   => 'artist',
					'hide_empty' => false,
					'fields'     => 'ids',
					'number'     => 2,
					'orderby'    => 'term_id',
					'order'      => 'ASC',
					'meta_query' => array(
						array(
							'key'     => '_artist_profile_id',
							'value'   => $artist_id,
							'compare' => '=',
							'type'    => 'NUMERIC',
						),
					),
				)
			);
		} finally {
			restore_current_blog();
		}

		if (
			! $term
			|| is_wp_error( $term )
			|| 'artist' !== $term->taxonomy
			|| 1 !== count( $term_claims )
			|| $artist_id !== $term_claims[0]
			|| is_wp_error( $term_ids )
			|| array( $term_id ) !== array_map( 'intval', $term_ids )
		) {
			return '';
		}
	} finally {
		$released = ec_finish_artist_binding_lock( $lock );
	}

	if ( ! $released || $term_id < 1 ) {
		return '';
	}

	$events_url = esc_url_raw( (string) ec_get_site_url( 'events' ) );
	if ( '' === $events_url ) {
		return '';
	}

	return esc_url_raw( trailingslashit( $events_url ) . 'local-support/?artist_id=' . rawurlencode( (string) $term_id ) );
}
