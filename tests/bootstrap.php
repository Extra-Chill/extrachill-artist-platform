<?php

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	private $code;
	private $message;

	public function __construct( $code, $message ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}
}

function __( $text ) {
	return $text;
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

$GLOBALS['ec_test'] = array();

function sanitize_text_field( $value ) {
	return trim( $value );
}

function absint( $value ) {
	return abs( (int) $value );
}

function get_post_type( $post_id ) {
	return $GLOBALS['ec_test']['posts'][ $post_id ]->post_type ?? '';
}

function get_post_meta( $post_id, $key = '', $single = false ) {
	$meta = $GLOBALS['ec_test']['meta'][ $post_id ] ?? array();
	if ( $key === '' ) {
		return $meta;
	}
	$value = $meta[ $key ] ?? ( $single ? '' : array() );
	return $single && is_array( $value ) ? ( $value[0] ?? '' ) : $value;
}

function maybe_unserialize( $value ) {
	return $value;
}

function get_post_thumbnail_id( $post_id ) {
	return $GLOBALS['ec_test']['thumbnails'][ $post_id ] ?? 0;
}

function get_the_title( $post_id ) {
	return $GLOBALS['ec_test']['posts'][ $post_id ]->post_title ?? '';
}

function get_post_field( $field, $post_id ) {
	return $GLOBALS['ec_test']['posts'][ $post_id ]->{$field} ?? '';
}

function get_permalink( $post_id ) {
	return 'https://artist.example/artists/' . get_post_field( 'post_name', $post_id ) . '/';
}

function get_post( $post_id ) {
	return $GLOBALS['ec_test']['posts'][ $post_id ] ?? null;
}

function get_current_user_id() {
	return $GLOBALS['ec_test']['current_user_id'] ?? 0;
}

function get_user_meta( $user_id, $key, $single = false ) {
	$value = $GLOBALS['ec_test']['user_meta'][ $user_id ][ $key ] ?? ( $single ? '' : array() );
	return $value;
}

function ec_get_blog_id( $site ) {
	return 'artist' === $site ? ( $GLOBALS['ec_test']['artist_blog_id'] ?? 7 ) : null;
}

function switch_to_blog( $blog_id ) {
	$GLOBALS['ec_test']['switched_to'][] = $blog_id;
}

function restore_current_blog() {
	$GLOBALS['ec_test']['restore_count'] = ( $GLOBALS['ec_test']['restore_count'] ?? 0 ) + 1;
}

function get_current_blog_id() {
	return $GLOBALS['ec_test']['current_blog_id'] ?? 1;
}

function user_can( $user_id, $capability ) {
	return ! empty( $GLOBALS['ec_test']['user_caps'][ $user_id ][ $capability ] );
}

function get_posts( $args ) {
	$GLOBALS['ec_test']['get_posts_args'][] = $args;
	return $GLOBALS['ec_test']['get_posts_result'] ?? array();
}

function wp_get_attachment_url( $attachment_id ) {
	return 'https://artist.example/media/' . $attachment_id . '.jpg';
}

function get_the_post_thumbnail_url( $post_id, $size ) {
	$attachment_id = get_post_thumbnail_id( $post_id );
	return $attachment_id ? wp_get_attachment_url( $attachment_id ) : false;
}

function ec_get_artist_relationships_for_admin( $view, $search ) {
	$GLOBALS['ec_test']['list'] = array( $view, $search );
	return $GLOBALS['ec_test']['list_result'] ?? array();
}

function ec_get_orphaned_artist_relationships() {
	return $GLOBALS['ec_test']['orphan_result'] ?? array();
}

function get_userdata( $user_id ) {
	return empty( $GLOBALS['ec_test']['missing_user'] ) ? (object) array( 'ID' => $user_id ) : false;
}

function ec_add_artist_membership( $user_id, $artist_id ) {
	$GLOBALS['ec_test']['added'] = array( $user_id, $artist_id );
	return $GLOBALS['ec_test']['add_result'] ?? true;
}

function ec_remove_artist_membership( $user_id, $artist_id ) {
	$GLOBALS['ec_test']['removed'] = array( $user_id, $artist_id );
	return true;
}

function wp_unslash( $value ) {
	if ( is_string( $value ) ) {
		return stripslashes( $value );
	}

	return $value;
}

function sanitize_hex_color( $color ) {
	if ( '' === $color ) {
		return '';
	}

	if ( preg_match( '|^#([A-Fa-f0-9]{3}){1,2}$|', $color ) ) {
		return $color;
	}

	return null;
}

require_once dirname( __DIR__ ) . '/inc/abilities/handlers/admin-list-artist-relationships.php';
require_once dirname( __DIR__ ) . '/inc/abilities/handlers/admin-link-artist-relationship.php';
require_once dirname( __DIR__ ) . '/inc/abilities/handlers/admin-unlink-artist-relationship.php';
require_once dirname( __DIR__ ) . '/inc/abilities/handlers/admin-list-orphan-artist-relationships.php';
require_once dirname( __DIR__ ) . '/inc/abilities/handlers/admin-cleanup-artist-relationships.php';
require_once dirname( __DIR__ ) . '/inc/artist-profiles/memberships.php';
require_once dirname( __DIR__ ) . '/inc/core/filters/data.php';
require_once dirname( __DIR__ ) . '/inc/abilities/handlers/artist-get.php';
require_once dirname( __DIR__ ) . '/inc/abilities/helpers.php';
