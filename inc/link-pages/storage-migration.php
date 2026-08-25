<?php
/**
 * Artist-owned validation and attachment semantics for Link Page migration.
 *
 * @package ExtraChillArtistPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Build a deterministic, read-only Artist migration projection.
 *
 * @param array $context Migration context.
 */
function ec_artist_link_page_migration_plan( $context ) {
	$source_blog_id = (int) $context['source_blog_id'];
	$artist_blog_id = function_exists( 'ec_get_blog_id' ) ? (int) ec_get_blog_id( 'artist' ) : $source_blog_id;
	$rows           = array();
	$attachments    = array();
	$source_before  = get_current_blog_id();
	if ( get_current_blog_id() !== $source_blog_id && ! switch_to_blog( $source_blog_id ) ) {
		return new WP_Error( 'artist_link_page_migration_context_failed', 'The source Link Page context is unavailable.' );
	}
	try {
		foreach ( $context['link_page_ids'] as $link_page_id ) {
			$link_page_id = (int) $link_page_id;
			$artist_id    = (int) get_post_meta( $link_page_id, '_associated_artist_profile_id', true );
			if ( ! $artist_id ) {
				return new WP_Error( 'artist_link_page_migration_missing_owner', 'A Link Page has no legacy associated profile.', array( 'link_page_id' => $link_page_id ) );
			}
			$owner = ec_get_link_page_owner( $link_page_id );
			if ( is_wp_error( $owner ) || 'post' !== $owner['kind'] || $artist_blog_id !== (int) $owner['blog_id'] || 'artist_profile' !== $owner['subtype'] || $artist_id !== (int) $owner['object_id'] ) {
				return new WP_Error( 'artist_link_page_migration_owner_mismatch', 'Legacy and canonical Link Page ownership disagree.', array( 'link_page_id' => $link_page_id ) );
			}
			$legacy_profile_image = (int) get_post_meta( $link_page_id, '_link_page_profile_image_id', true );
			if ( $legacy_profile_image ) {
				$attachments[] = $legacy_profile_image; }
			$background_image = (int) get_post_meta( $link_page_id, '_link_page_background_image_id', true );
			if ( $background_image ) {
				$attachments[] = $background_image; }
			$switched = get_current_blog_id() !== $artist_blog_id;
			if ( $switched && ! switch_to_blog( $artist_blog_id ) ) {
				return new WP_Error( 'artist_link_page_migration_context_failed', 'The profile context is unavailable.' ); }
			try {
				$profile = get_post( $artist_id );
				if ( ! $profile || 'artist_profile' !== $profile->post_type || (int) get_post_meta( $artist_id, '_extrch_link_page_id', true ) !== $link_page_id ) {
					return new WP_Error(
						'artist_link_page_migration_reciprocal_mismatch',
						'An owner profile has a stale or ambiguous reciprocal pointer.',
						array(
							'link_page_id' => $link_page_id,
							'profile_id'   => $artist_id,
						)
					);
				}
				$profile_image = (int) get_post_thumbnail_id( $artist_id );
				if ( $profile_image ) {
					$attachments[] = $profile_image; }
			} finally {
				if ( $switched ) {
					restore_current_blog(); }
			}
			$projection = ec_read_link_page( $link_page_id );
			if ( is_wp_error( $projection ) || (int) ( $projection['link_page_id'] ?? 0 ) !== $link_page_id ) {
				return new WP_Error( 'artist_link_page_migration_projection_mismatch', 'The current Link Page operation projection is not exact.', array( 'link_page_id' => $link_page_id ) );
			}
			$rows[] = array(
				'link_page_id'               => $link_page_id,
				'profile_id'                 => $artist_id,
				'owner_reference'            => $owner['reference'],
				'reciprocal_link_page_id'    => $link_page_id,
				'profile_image_id'           => $profile_image,
				'legacy_profile_image_id'    => $legacy_profile_image,
				'background_image_id'        => $background_image,
				'profile_remains_on_blog_id' => $artist_blog_id,
			);
		}
	} finally {
		while ( get_current_blog_id() !== $source_before && ! empty( $GLOBALS['_wp_switched_stack'] ) ) {
			restore_current_blog(); }
	}
	usort(
		$rows,
		static function ( $a, $b ) {
			return $a['link_page_id'] <=> $b['link_page_id'];
		}
	);
	$attachments = array_values( array_unique( array_filter( array_map( 'absint', $attachments ) ) ) );
	sort( $attachments, SORT_NUMERIC );
	$data                = array(
		'profiles'             => $rows,
		'attachment_ids'       => $attachments,
		'attachment_semantics' => array_map(
			static function ( $id ) {
				return array(
					'attachment_id'      => $id,
					'destination_parent' => 0,
					'reason'             => 'owner-profile-media-parent-remap',
				);
			},
			$attachments
		),
		'qr_storage'           => 'generated-on-demand-no-persisted-attachment',
	);
	$data['fingerprint'] = hash( 'sha256', wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	return $data;
}

/**
 * Artist owns no destination mutations; exact IDs keep reciprocal pointers valid.
 *
 * @param array $context Migration context.
 */
function ec_artist_link_page_migration_apply( $context ) {
	unset( $context );
	return true;
}

/**
 * Re-run the exact owner projection after core copy.
 *
 * @param array $context Migration context.
 */
function ec_artist_link_page_migration_validate( $context ) {
	$plan = ec_artist_link_page_migration_plan( $context );
	return is_wp_error( $plan ) ? $plan : true;
}

/**
 * Artist never mutates owner profiles during migration.
 *
 * @param array $context Migration context.
 */
function ec_artist_link_page_migration_rollback( $context ) {
	unset( $context );
	return true;
}

/** Register the Artist-owned migration extension. */
function ec_artist_register_link_page_migration_adapter() {
	if ( ! function_exists( 'ec_register_link_page_migration_participant' ) ) {
		return true;
	}
	return ec_register_link_page_migration_participant(
		'artist-platform',
		array(
			'plan'     => 'ec_artist_link_page_migration_plan',
			'apply'    => 'ec_artist_link_page_migration_apply',
			'validate' => 'ec_artist_link_page_migration_validate',
			'rollback' => 'ec_artist_link_page_migration_rollback',
		)
	);
}
