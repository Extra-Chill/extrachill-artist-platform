<?php

use PHPUnit\Framework\TestCase;

final class ArtistMembershipsTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['ec_test'] = array(
			'posts'     => array(),
			'meta'      => array(),
			'user_meta' => array(),
		);
	}

	public function test_valid_bidirectional_membership_resolves(): void {
		$this->add_artist( 10, 'publish', array( 4 ) );
		$this->set_user_artists( 4, array( 10 ) );

		$this->assertSame( array( 10 ), ec_get_artists_for_user( 4 ) );
	}

	public function test_user_side_only_stale_id_is_excluded(): void {
		$this->add_artist( 10, 'publish', array() );
		$this->set_user_artists( 4, array( 10 ) );

		$this->assertSame( array(), ec_get_artists_for_user( 4 ) );
	}

	public function test_artist_side_only_stale_id_does_not_create_membership(): void {
		$this->add_artist( 10, 'publish', array( 4 ) );
		$this->set_user_artists( 4, array() );

		$this->assertSame( array(), ec_get_artists_for_user( 4 ) );
	}

	public function test_published_wrong_post_type_is_excluded(): void {
		$GLOBALS['ec_test']['posts'][ 10 ] = (object) array(
			'ID'          => 10,
			'post_type'   => 'post',
			'post_status' => 'publish',
		);
		$GLOBALS['ec_test']['meta'][ 10 ]['_artist_member_ids'] = array( array( 4 ) );
		$this->set_user_artists( 4, array( 10 ) );

		$this->assertSame( array(), ec_get_artists_for_user( 4 ) );
	}

	public function test_deleted_and_private_profiles_are_excluded(): void {
		$this->add_artist( 10, 'private', array( 4 ) );
		$this->set_user_artists( 4, array( 10, 11 ) );

		$this->assertSame( array(), ec_get_artists_for_user( 4 ) );
	}

	public function test_multiple_memberships_are_preserved(): void {
		$this->add_artist( 10, 'publish', array( 4 ) );
		$this->add_artist( 11, 'publish', array( 4, 7 ) );
		$this->set_user_artists( 4, array( 10, 11 ) );

		$this->assertSame( array( 10, 11 ), ec_get_artists_for_user( 4 ) );
	}

	public function test_relationship_resolves_after_both_sides_are_repaired(): void {
		$this->add_artist( 10, 'publish', array() );
		$this->set_user_artists( 4, array( 10 ) );
		$this->assertSame( array(), ec_get_artists_for_user( 4 ) );

		$GLOBALS['ec_test']['meta'][ 10 ]['_artist_member_ids'] = array( array( '4' ) );

		$this->assertSame( array( 10 ), ec_get_artists_for_user( 4 ) );
	}

	public function test_admin_override_preserves_published_artist_query_contract(): void {
		$GLOBALS['ec_test']['user_caps'][ 4 ]['manage_options'] = true;
		$GLOBALS['ec_test']['get_posts_result']                 = array( 10, 11 );

		$this->assertSame( array( 10, 11 ), ec_get_artists_for_user( 4, true ) );
		$this->assertSame(
			array(
				'post_type'   => 'artist_profile',
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
			),
			$GLOBALS['ec_test']['get_posts_args'][ 0 ]
		);
	}

	private function add_artist( int $artist_id, string $status, array $member_ids ): void {
		$GLOBALS['ec_test']['posts'][ $artist_id ] = (object) array(
			'ID'          => $artist_id,
			'post_type'   => 'artist_profile',
			'post_status' => $status,
		);
		$GLOBALS['ec_test']['meta'][ $artist_id ]['_artist_member_ids'] = array( $member_ids );
	}

	private function set_user_artists( int $user_id, array $artist_ids ): void {
		$GLOBALS['ec_test']['user_meta'][ $user_id ]['_artist_profile_ids'] = $artist_ids;
	}
}
