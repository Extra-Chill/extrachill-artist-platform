<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/inc/core/nav.php';

final class AvatarMenuContributionTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['ec_test'] = array(
			'current_blog_id' => 4,
			'blog_stack'      => array(),
			'blogs'           => array(
				4 => array(
					'posts' => array(),
				),
			),
		);
	}

	public function test_no_artist_identity_adds_no_destination(): void {
		$this->assertSame( array(), ec_artist_platform_avatar_menu_items( array(), 7 ) );
		$this->assertSame( array(), ec_artist_platform_avatar_menu_items( array(), 0 ) );
	}

	public function test_one_and_multiple_artist_identities_use_clear_labels(): void {
		$this->add_artist( 7, 42 );
		$items = ec_artist_platform_avatar_menu_items( array(), 7 );
		$this->assertSame( 'manage_artists', $items[0]['id'] );
		$this->assertSame( 'Manage Artist', $items[0]['label'] );
		$this->assertSame( 'https://artist.extrachill.com/manage-artist/', $items[0]['url'] );

		$this->add_artist( 7, 43 );
		$items = ec_artist_platform_avatar_menu_items( array(), 7 );
		$this->assertSame( 'Manage Artists', $items[0]['label'] );
	}

	public function test_existing_items_and_order_are_preserved(): void {
		$this->add_artist( 7, 42 );
		$existing = array(
			array(
				'id'       => 'manage_events',
				'label'    => 'Manage Events',
				'url'      => 'https://events.extrachill.com/manage/',
				'priority' => 40,
			),
		);

		$items = ec_artist_platform_avatar_menu_items( $existing, 7 );

		$this->assertSame( 'manage_events', $items[0]['id'] );
		$this->assertSame( 'manage_artists', $items[1]['id'] );
	}

	private function add_artist( int $user_id, int $artist_id ): void {
		$GLOBALS['ec_test']['blogs'][4]['posts'][ $artist_id ] = (object) array(
			'ID'          => $artist_id,
			'post_type'   => 'artist_profile',
			'post_status' => 'publish',
		);
		update_post_meta( $artist_id, '_artist_member_ids', array( $user_id ) );

		$artist_ids   = get_user_meta( $user_id, '_artist_profile_ids', true );
		$artist_ids   = is_array( $artist_ids ) ? $artist_ids : array();
		$artist_ids[] = $artist_id;
		update_user_meta( $user_id, '_artist_profile_ids', $artist_ids );
	}
}
