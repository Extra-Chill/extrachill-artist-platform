<?php

use PHPUnit\Framework\TestCase;

final class LinkPageOwnerReferenceTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['ec_test'] = array(
			'current_blog_id' => 4,
			'blog_stack'      => array(),
			'blogs'           => array(
				4 => array( 'terms' => array(), 'term_meta' => array(), 'posts' => array(), 'post_meta' => array() ),
				7 => array( 'terms' => array(), 'term_meta' => array(), 'posts' => array(), 'post_meta' => array() ),
			),
		);
		extrachill_register_artist_profile_cpt();
		extrachill_register_artist_link_page_cpt();
		$this->addPost( 4, 20, 'artist_profile', 'test-artist' );
		$this->addTerm( 7, 30, 'place' );
	}

	private function addPost( $blog_id, $post_id, $post_type, $slug ): void {
		$GLOBALS['ec_test']['blogs'][ $blog_id ]['posts'][ $post_id ] = (object) array(
			'ID'          => $post_id,
			'post_type'   => $post_type,
			'post_status' => 'publish',
			'post_title'  => ucwords( str_replace( '-', ' ', $slug ) ),
			'post_name'   => $slug,
		);
	}

	private function addTerm( $blog_id, $term_id, $taxonomy ): void {
		$GLOBALS['ec_test']['blogs'][ $blog_id ]['terms'][ $term_id ] = (object) array(
			'term_id'  => $term_id,
			'taxonomy' => $taxonomy,
			'slug'     => 'object-' . $term_id,
		);
	}

	private function postOwner( $object_id = 20 ): array {
		return array(
			'kind'      => 'post',
			'blog_id'   => 4,
			'subtype'   => 'artist_profile',
			'object_id' => $object_id,
		);
	}

	public function test_post_and_term_references_parse_format_and_normalize_round_trip(): void {
		$post_reference = ec_format_link_page_owner_reference( $this->postOwner() );
		$term_reference = ec_format_link_page_owner_reference(
			array( 'kind' => 'term', 'blog_id' => 7, 'subtype' => 'place', 'object_id' => 30 )
		);

		$this->assertSame( 'post:4:artist_profile:20', $post_reference );
		$this->assertSame( $post_reference, ec_normalize_link_page_owner_reference( $post_reference ) );
		$this->assertSame( $this->postOwner() + array( 'reference' => $post_reference ), ec_parse_link_page_owner_reference( $post_reference ) );
		$this->assertSame( 'term:7:place:30', $term_reference );
		$this->assertSame( $term_reference, ec_normalize_link_page_owner_reference( $term_reference ) );
		$this->assertSame( 4, get_current_blog_id() );
		$this->assertSame( array(), $GLOBALS['ec_test']['blog_stack'] );
	}

	/**
	 * @dataProvider invalidReferenceProvider
	 */
	public function test_malformed_and_invalid_owner_references_fail( $reference, $error_code ): void {
		$result = ec_normalize_link_page_owner_reference( $reference );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( $error_code, $result->get_error_code() );
		$this->assertSame( 4, get_current_blog_id() );
	}

	public function invalidReferenceProvider(): array {
		return array(
			'malformed'        => array( 'post/4/artist_profile/20', 'invalid_link_page_owner_reference' ),
			'invalid kind'     => array( 'user:4:subscriber:20', 'invalid_link_page_owner_reference' ),
			'invalid blog'     => array( 'post:99:artist_profile:20', 'invalid_link_page_owner_blog' ),
			'invalid subtype'  => array( 'post:4:event:20', 'invalid_link_page_owner_object' ),
			'invalid taxonomy' => array( 'term:7:venue:30', 'invalid_link_page_owner_object' ),
			'invalid object'   => array( 'post:4:artist_profile:999', 'invalid_link_page_owner_object' ),
			'zero object'      => array( 'term:7:place:0', 'invalid_link_page_owner_reference' ),
		);
	}

	public function test_legacy_artist_fallback_does_not_write_during_read(): void {
		$this->addPost( 4, 40, 'artist_link_page', 'test-artist' );
		$GLOBALS['ec_test']['blogs'][4]['post_meta'][40]['_associated_artist_profile_id'] = 20;

		$owner = ec_get_link_page_owner( 40 );

		$this->assertSame( 'post:4:artist_profile:20', $owner['reference'] );
		$this->assertArrayNotHasKey( EC_LINK_PAGE_OWNER_META_KEY, $GLOBALS['ec_test']['blogs'][4]['post_meta'][40] );
		$this->assertArrayNotHasKey( 'post_meta_update_calls', $GLOBALS['ec_test'] );
	}

	public function test_artist_creation_dual_writes_without_changing_id_or_slug(): void {
		$link_page_id = ec_create_link_page( 20 );

		$this->assertSame( 21, $link_page_id );
		$this->assertSame( 'test-artist', get_post_field( 'post_name', $link_page_id ) );
		$this->assertSame( 20, (int) get_post_meta( $link_page_id, '_associated_artist_profile_id', true ) );
		$this->assertSame( 21, (int) get_post_meta( 20, '_extrch_link_page_id', true ) );
		$this->assertSame( 'post:4:artist_profile:20', get_post_meta( $link_page_id, EC_LINK_PAGE_OWNER_META_KEY, true ) );
	}

	public function test_owner_conflict_and_duplicate_references_fail_deterministically(): void {
		$this->addPost( 4, 40, 'artist_link_page', 'first' );
		$this->addPost( 4, 41, 'artist_link_page', 'second' );
		$this->addPost( 4, 42, 'artist_link_page', 'third' );
		$GLOBALS['ec_test']['blogs'][4]['post_meta'][40][ EC_LINK_PAGE_OWNER_META_KEY ] = 'post:4:artist_profile:20';

		$conflict = ec_assign_link_page_owner( 41, $this->postOwner() );
		$this->assertSame( 'link_page_owner_conflict', $conflict->get_error_code() );

		$GLOBALS['ec_test']['blogs'][4]['post_meta'][41][ EC_LINK_PAGE_OWNER_META_KEY ] = 'post:4:artist_profile:20';
		$duplicate_pages = ec_get_link_page_id_for_owner( $this->postOwner() );
		$this->assertSame( 'duplicate_link_pages_for_owner', $duplicate_pages->get_error_code() );

		$GLOBALS['ec_test']['blogs'][4]['post_meta'][42][ EC_LINK_PAGE_OWNER_META_KEY ] = array(
			'post:4:artist_profile:20',
			'post:4:artist_profile:20',
		);
		$duplicate_rows = ec_get_link_page_owner( 42 );
		$this->assertSame( 'duplicate_link_page_owner_references', $duplicate_rows->get_error_code() );
	}

	public function test_malformed_stored_reference_does_not_fall_back_to_legacy_owner(): void {
		$this->addPost( 4, 40, 'artist_link_page', 'test-artist' );
		$GLOBALS['ec_test']['blogs'][4]['post_meta'][40]['_associated_artist_profile_id'] = 20;
		$GLOBALS['ec_test']['blogs'][4]['post_meta'][40][ EC_LINK_PAGE_OWNER_META_KEY ]    = 'broken';

		$owner = ec_get_link_page_owner( 40 );

		$this->assertSame( 'invalid_link_page_owner_reference', $owner->get_error_code() );
	}

	public function test_conflict_created_during_assignment_is_compensated(): void {
		$this->addPost( 4, 40, 'artist_link_page', 'first' );
		$this->addPost( 4, 41, 'artist_link_page', 'second' );
		$GLOBALS['ec_test']['after_post_meta_update'] = static function () {
			$GLOBALS['ec_test']['blogs'][4]['post_meta'][41][ EC_LINK_PAGE_OWNER_META_KEY ] = 'post:4:artist_profile:20';
		};

		$result = ec_assign_link_page_owner( 40, $this->postOwner() );

		$this->assertSame( 'duplicate_link_pages_for_owner', $result->get_error_code() );
		$this->assertArrayNotHasKey( EC_LINK_PAGE_OWNER_META_KEY, $GLOBALS['ec_test']['blogs'][4]['post_meta'][40] );
	}

	public function test_cross_blog_post_and_term_validation_always_restores_context(): void {
		$this->addPost( 7, 50, 'event', 'event-owner' );

		$this->assertSame( 'post:7:event:50', ec_normalize_link_page_owner_reference( 'post:7:event:50' ) );
		$this->assertSame( 'term:7:place:30', ec_normalize_link_page_owner_reference( 'term:7:place:30' ) );
		$this->assertSame( 4, get_current_blog_id() );
		$this->assertSame( array(), $GLOBALS['ec_test']['blog_stack'] );
	}

	public function test_creation_rolls_back_when_owner_assignment_fails(): void {
		$GLOBALS['ec_test']['fail_post_meta_update_keys'][ EC_LINK_PAGE_OWNER_META_KEY ] = 1;

		$result = ec_create_link_page( 20 );

		$this->assertSame( 'link_page_owner_assignment_failed', $result->get_error_code() );
		$this->assertSame( array( 20 ), array_keys( $GLOBALS['ec_test']['blogs'][4]['posts'] ) );
		$this->assertEmpty( get_post_meta( 20, '_extrch_link_page_id', true ) );
	}

	public function test_backfill_is_bounded_and_idempotent(): void {
		$this->addPost( 4, 40, 'artist_link_page', 'test-artist' );
		$GLOBALS['ec_test']['blogs'][4]['post_meta'][40]['_associated_artist_profile_id'] = 20;

		$first  = ec_backfill_link_page_owner_references( 1, 0 );
		$second = ec_backfill_link_page_owner_references( 1, 0 );

		$this->assertSame( array( 'processed' => 1, 'updated' => 1, 'skipped' => 0, 'errors' => array(), 'next_offset' => 1 ), $first );
		$this->assertSame( array( 'processed' => 1, 'updated' => 0, 'skipped' => 1, 'errors' => array(), 'next_offset' => 1 ), $second );
	}
}
