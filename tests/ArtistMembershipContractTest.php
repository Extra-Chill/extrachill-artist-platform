<?php

use PHPUnit\Framework\TestCase;

final class ArtistMembershipContractTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['ec_test'] = array(
			'current_blog_id' => 1,
			'blog_stack'      => array(),
			'blogs'           => array(
				4 => array(
					'posts' => array(
						20 => (object) array(
							'ID'          => 20,
							'post_type'   => 'artist_profile',
							'post_status' => 'publish',
						),
						21 => (object) array(
							'ID'          => 21,
							'post_type'   => 'artist_profile',
							'post_status' => 'private',
						),
						22 => (object) array(
							'ID'          => 22,
							'post_type'   => 'post',
							'post_status' => 'publish',
						),
					),
				),
			),
		);
	}

	public function test_add_and_remove_are_idempotent_and_keep_both_sides_consistent(): void {
		$this->assertTrue( ec_add_artist_membership( 7, 20 ) );
		$this->assertTrue( ec_add_artist_membership( 7, 20 ) );
		$this->assertSame( array( 20 ), get_user_meta( 7, '_artist_profile_ids', true ) );

		switch_to_blog( 4 );
		$this->assertSame( array( 7 ), get_post_meta( 20, '_artist_member_ids', true ) );
		restore_current_blog();

		$this->assertTrue( ec_remove_artist_membership( 7, 20 ) );
		$this->assertTrue( ec_remove_artist_membership( 7, 20 ) );
		$this->assertSame( array(), get_user_meta( 7, '_artist_profile_ids', true ) );
	}

	public function test_add_rejects_unpublished_wrong_type_and_deleted_targets(): void {
		$this->assertFalse( ec_add_artist_membership( 7, 21 ) );
		$this->assertFalse( ec_add_artist_membership( 7, 22 ) );
		$this->assertFalse( ec_add_artist_membership( 7, 999 ) );
		$this->assertSame( '', get_user_meta( 7, '_artist_profile_ids', true ) );
	}

	public function test_partial_add_failure_is_truthful_and_retry_reconciles_it(): void {
		$GLOBALS['ec_test']['fail_user_meta_update'] = true;
		$this->assertFalse( ec_add_artist_membership( 7, 20 ) );
		$this->assertSame( array(), ec_get_linked_members( 20 ) );

		$this->assertTrue( ec_add_artist_membership( 7, 20 ) );
		$this->assertSame( array( 7 ), array_map( static fn( $user ) => $user->ID, ec_get_linked_members( 20 ) ) );
	}

	public function test_partial_remove_failure_is_truthful_and_retry_reconciles_it(): void {
		$this->assertTrue( ec_add_artist_membership( 7, 20 ) );
		$GLOBALS['ec_test']['fail_post_meta_update'] = true;
		$this->assertFalse( ec_remove_artist_membership( 7, 20 ) );
		$this->assertSame( array(), ec_get_linked_members( 20 ) );

		$this->assertTrue( ec_remove_artist_membership( 7, 20 ) );
		switch_to_blog( 4 );
		$this->assertSame( array(), get_post_meta( 20, '_artist_member_ids', true ) );
		restore_current_blog();
	}

	public function test_reverse_roster_requires_reciprocal_membership_and_valid_artist(): void {
		$GLOBALS['ec_test']['user_meta'][7]['_artist_profile_ids'] = array( 20 );
		$GLOBALS['ec_test']['user_meta'][8]['_artist_profile_ids'] = array();
		$GLOBALS['ec_test']['blogs'][4]['post_meta'][20]['_artist_member_ids'] = array( 7, 8 );
		$GLOBALS['ec_test']['blogs'][4]['post_meta'][21]['_artist_member_ids'] = array( 7 );
		$GLOBALS['ec_test']['blogs'][4]['post_meta'][22]['_artist_member_ids'] = array( 7 );

		$this->assertSame( array( 7 ), array_map( static fn( $user ) => $user->ID, ec_get_linked_members( 20 ) ) );
		$this->assertSame( array(), ec_get_linked_members( 21 ) );
		$this->assertSame( array(), ec_get_linked_members( 22 ) );
		$this->assertSame( array(), ec_get_linked_members( 999 ) );
	}

	public function test_admin_handlers_report_partial_write_failures(): void {
		$GLOBALS['ec_test']['capabilities']['manage_network_options'] = true;
		$GLOBALS['ec_test']['fail_user_meta_update']                  = true;
		$result = extrachill_artist_platform_ability_admin_link_artist_relationship( array( 'user_id' => 7, 'artist_id' => 20 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'relationship_update_failed', $result->get_error_code() );

		$GLOBALS['ec_test']['fail_post_meta_update'] = true;
		$result = extrachill_artist_platform_ability_admin_unlink_artist_relationship( array( 'user_id' => 7, 'artist_id' => 20 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'relationship_update_failed', $result->get_error_code() );
	}
}
