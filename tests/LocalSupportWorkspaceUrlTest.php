<?php

use PHPUnit\Framework\TestCase;

final class LocalSupportWorkspaceUrlTest extends TestCase {
	protected function setUp(): void {
		unset( $GLOBALS['ec_artist_binding_lock'], $GLOBALS['ec_artist_binding_failure'], $GLOBALS['ec_artist_binding_release_failure'] );
		$GLOBALS['ec_test'] = array(
			'current_blog_id' => 4,
			'blog_stack'      => array(),
			'current_user_id' => 7,
			'blogs'           => array(
				1 => array(
					'terms'     => array(
						142 => (object) array(
							'term_id'  => 142,
							'taxonomy' => 'artist',
							'slug'     => 'test-band',
						),
					),
					'term_meta' => array(
						142 => array( '_artist_profile_id' => 42 ),
					),
				),
				4 => array(
					'posts'     => array(
						42 => $this->artist( 42, 'test-band' ),
					),
					'post_meta' => array(
						42 => array(
							'_artist_term_id'    => 142,
							'_artist_member_ids' => array( 7 ),
						),
					),
				),
				7 => array(),
			),
			'user_meta'       => array(
				7 => array( '_artist_profile_ids' => array( 42 ) ),
			),
			'site_urls'       => array(
				'events' => 'https://events.example/subdirectory',
			),
		);
	}

	private function artist( int $id, string $slug ): object {
		return (object) array(
			'ID'          => $id,
			'post_type'   => 'artist_profile',
			'post_status' => 'publish',
			'post_name'   => $slug,
			'post_title'  => 'Test Band',
		);
	}

	public function test_exact_canonical_mapping_builds_registered_events_url(): void {
		$this->assertSame(
			'https://events.example/subdirectory/local-support/?artist_id=142',
			extrachill_artist_platform_local_support_workspace_url( 42, 7 )
		);
		$this->assertSame( 4, get_current_blog_id(), 'Cross-site reads must restore the Artist site.' );
	}

	public function test_missing_or_stale_reciprocal_bindings_fail_closed(): void {
		unset( $GLOBALS['ec_test']['blogs'][4]['post_meta'][42]['_artist_term_id'] );
		$this->assertSame( '', extrachill_artist_platform_local_support_workspace_url( 42, 7 ) );

		$GLOBALS['ec_test']['blogs'][4]['post_meta'][42]['_artist_term_id'] = 142;
		$GLOBALS['ec_test']['blogs'][1]['term_meta'][142]['_artist_profile_id'] = 99;
		$this->assertSame( '', extrachill_artist_platform_local_support_workspace_url( 42, 7 ) );
	}

	public function test_duplicate_binding_claims_fail_closed_in_both_directions(): void {
		$GLOBALS['ec_test']['blogs'][4]['post_meta'][42]['_artist_term_id'] = array( 142, 142 );
		$this->assertSame( '', extrachill_artist_platform_local_support_workspace_url( 42, 7 ) );

		$GLOBALS['ec_test']['blogs'][4]['post_meta'][42]['_artist_term_id'] = 142;
		$GLOBALS['ec_test']['blogs'][1]['terms'][143] = (object) array(
			'term_id'  => 143,
			'taxonomy' => 'artist',
			'slug'     => 'duplicate-band',
		);
		$GLOBALS['ec_test']['blogs'][1]['term_meta'][143] = array( '_artist_profile_id' => 42 );
		$this->assertSame( '', extrachill_artist_platform_local_support_workspace_url( 42, 7 ) );
	}

	public function test_duplicate_profile_claim_for_term_fails_closed(): void {
		$GLOBALS['ec_test']['blogs'][4]['posts'][43] = $this->artist( 43, 'duplicate-profile' );
		$GLOBALS['ec_test']['blogs'][4]['post_meta'][43] = array( '_artist_term_id' => 142 );
		$this->assertSame( '', extrachill_artist_platform_local_support_workspace_url( 42, 7 ) );
	}

	public function test_unavailable_events_site_or_invalid_url_fails_closed(): void {
		$GLOBALS['ec_test']['events_blog_unavailable'] = true;
		$this->assertSame( '', extrachill_artist_platform_local_support_workspace_url( 42, 7 ) );

		$GLOBALS['ec_test']['events_blog_unavailable'] = false;
		$GLOBALS['ec_test']['sites'][7]['archived'] = '1';
		$this->assertSame( '', extrachill_artist_platform_local_support_workspace_url( 42, 7 ) );

		$GLOBALS['ec_test']['sites'][7]['archived'] = '0';
		$GLOBALS['ec_test']['site_urls']['events']  = 'javascript:alert(1)';
		$this->assertSame( '', extrachill_artist_platform_local_support_workspace_url( 42, 7 ) );
	}

	public function test_unauthorized_or_unpublished_artist_fails_closed(): void {
		$GLOBALS['ec_test']['user_meta'][7]['_artist_profile_ids'] = array();
		$this->assertSame( '', extrachill_artist_platform_local_support_workspace_url( 42, 7 ) );

		$GLOBALS['ec_test']['user_meta'][7]['_artist_profile_ids'] = array( 42 );
		$GLOBALS['ec_test']['blogs'][4]['posts'][42]->post_status  = 'draft';
		$this->assertSame( '', extrachill_artist_platform_local_support_workspace_url( 42, 7 ) );
	}
}
