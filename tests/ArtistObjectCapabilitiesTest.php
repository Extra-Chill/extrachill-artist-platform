<?php

use PHPUnit\Framework\TestCase;

final class ArtistObjectCapabilitiesTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['ec_test'] = array(
			'current_blog_id' => 4,
			'blog_stack'      => array(),
			'blogs'           => array(
				4 => array(
					'posts'     => array(
						20 => (object) array(
							'ID'          => 20,
							'post_type'   => 'artist_profile',
							'post_status' => 'publish',
						),
						30 => (object) array(
							'ID'          => 30,
							'post_type'   => 'revision',
							'post_status' => 'inherit',
							'post_parent' => 20,
						),
						40 => (object) array(
							'ID'          => 40,
							'post_type'   => 'post',
							'post_status' => 'publish',
						),
					),
					'post_meta' => array(
						20 => array( '_artist_member_ids' => array( 7 ) ),
					),
				),
			),
			'user_meta' => array(
				7 => array( '_artist_profile_ids' => array( 20 ) ),
				8 => array( '_artist_profile_ids' => array( 20 ) ),
			),
			'mapped_caps' => array(
				'delete_post' => array(
					20 => array( 'delete_others_artist_profiles', 'delete_published_artist_profiles' ),
				),
			),
		);
	}

	public function test_reciprocal_member_receives_mapped_primitives_for_core_rest_crud(): void {
		$user = (object) array( 'ID' => 7 );
		$crud = array(
			'read_post'    => array( 'read' ),
			'edit_post'    => array( 'edit_others_artist_profiles', 'edit_published_artist_profiles' ),
			'publish_post' => array( 'publish_artist_profiles' ),
			'delete_post'  => array( 'delete_others_artist_profiles', 'delete_published_artist_profiles' ),
		);

		foreach ( $crud as $requested => $required ) {
			$allcaps = ec_filter_user_capabilities( array(), $required, array( $requested, 7, 20 ), $user );
			foreach ( $required as $primitive ) {
				$this->assertTrue( $allcaps[ $primitive ], $requested . ' did not grant ' . $primitive );
			}
		}
	}

	public function test_artist_profiles_register_isolated_caps_and_revision_support(): void {
		extrachill_register_artist_profile_cpt();
		$args = $GLOBALS['ec_test']['registered_post_types']['artist_profile'];

		$this->assertSame( array( 'artist_profile', 'artist_profiles' ), $args['capability_type'] );
		$this->assertTrue( $args['map_meta_cap'] );
		$this->assertTrue( $args['show_in_rest'] );
		$this->assertContains( 'revisions', $args['supports'] );
	}

	public function test_one_sided_membership_and_generic_editor_caps_do_not_grant_access(): void {
		$editor_caps = array(
			'edit_posts'           => true,
			'edit_others_posts'    => true,
			'edit_published_posts' => true,
		);
		$required    = array( 'edit_others_artist_profiles', 'edit_published_artist_profiles' );
		$allcaps     = ec_filter_user_capabilities( $editor_caps, $required, array( 'edit_post', 8, 20 ), (object) array( 'ID' => 8 ) );

		$this->assertArrayNotHasKey( 'edit_others_artist_profiles', $allcaps );
		$this->assertArrayNotHasKey( 'edit_published_artist_profiles', $allcaps );

		$create_caps = ec_filter_user_capabilities( $editor_caps, array( 'edit_artist_profiles' ), array( 'edit_artist_profiles', 8 ), (object) array( 'ID' => 8 ) );
		$this->assertArrayNotHasKey( 'edit_artist_profiles', $create_caps );
	}

	public function test_administrator_receives_object_primitives(): void {
		$GLOBALS['ec_test']['user_capabilities'][99]['manage_options'] = true;
		$allcaps = ec_filter_user_capabilities(
			array( 'manage_options' => true ),
			array( 'edit_others_artist_profiles', 'edit_published_artist_profiles' ),
			array( 'edit_post', 99, 20 ),
			(object) array( 'ID' => 99 )
		);

		$this->assertTrue( $allcaps['edit_others_artist_profiles'] );
		$this->assertTrue( $allcaps['edit_published_artist_profiles'] );
	}

	public function test_administrator_receives_objectless_core_rest_collection_and_create_caps(): void {
		$GLOBALS['ec_test']['user_capabilities'][99]['manage_options'] = true;
		$user = (object) array( 'ID' => 99 );

		$create_caps = ec_filter_user_capabilities( array(), array( 'edit_artist_profiles' ), array( 'edit_artist_profiles', 99 ), $user );
		$this->assertTrue( $create_caps['edit_artist_profiles'] );

		$publish_caps = ec_filter_user_capabilities( array(), array( 'publish_artist_profiles' ), array( 'publish_artist_profiles', 99 ), $user );
		$this->assertTrue( $publish_caps['publish_artist_profiles'] );
	}

	public function test_revisions_and_autosaves_resolve_to_the_artist_parent(): void {
		$this->assertSame( 20, ec_get_artist_id_for_owned_object( 30 ) );
		$this->assertSame( 20, ec_get_artist_id_for_owned_object( 20 ) );

		$mapped = ec_map_artist_object_capabilities( array( 'do_not_allow' ), 'delete_post', 7, array( 30 ) );
		$this->assertSame( array( 'delete_others_artist_profiles', 'delete_published_artist_profiles' ), $mapped );

		$allcaps = ec_filter_user_capabilities( array(), $mapped, array( 'delete_post', 7, 30 ), (object) array( 'ID' => 7 ) );
		$this->assertTrue( $allcaps['delete_others_artist_profiles'] );
		$this->assertTrue( $allcaps['delete_published_artist_profiles'] );
	}

	public function test_non_artist_objects_are_untouched(): void {
		$allcaps = array( 'edit_posts' => true );
		$this->assertSame(
			$allcaps,
			ec_filter_user_capabilities( $allcaps, array( 'edit_others_posts' ), array( 'edit_post', 7, 40 ), (object) array( 'ID' => 7 ) )
		);
		$this->assertSame(
			array( 'do_not_allow' ),
			ec_map_artist_object_capabilities( array( 'do_not_allow' ), 'delete_post', 7, array( 40 ) )
		);
	}
}
