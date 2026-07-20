<?php

use PHPUnit\Framework\TestCase;

final class ArtistShowsStableBindingTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['ec_test'] = array(
			'current_blog_id'    => 4,
			'blog_stack'         => array(),
			'cross_site_requests' => array(),
			'blogs'              => array(
				1 => array(
					'terms'     => array(),
					'term_meta' => array(),
				),
				4 => array(),
			),
		);
	}

	private function addMainTerm( $term_id, $events_term_id = 0 ) {
		$GLOBALS['ec_test']['blogs'][1]['terms'][ $term_id ] = (object) array(
			'term_id'  => $term_id,
			'taxonomy' => 'artist',
			'slug'     => 'main-artist-' . $term_id,
		);
		if ( $events_term_id > 0 ) {
			$GLOBALS['ec_test']['blogs'][1]['term_meta'][ $term_id ]['_events_artist_term_id'] = $events_term_id;
		}
	}

	public function test_missing_mapping_fails_closed_when_slug_discovery_returns_no_stable_id(): void {
		$this->addMainTerm( 201 );

		$this->assertSame(
			array( 'term_slug' => '', 'upcoming' => array(), 'past' => array() ),
			ec_artist_shows_gather( 201 )
		);
		$request = $GLOBALS['ec_test']['cross_site_requests'][0][3]['query']['input'];
		$this->assertSame( 'main-artist-201', $request['term_slug'] );
		$this->assertArrayNotHasKey( 'term_id', $request );
	}

	public function test_events_join_uses_and_verifies_the_stable_events_term_id(): void {
		$this->addMainTerm( 202, 502 );
		$GLOBALS['ec_test']['cross_site_result'] = array(
			'term_id'   => 502,
			'term_slug' => 'events-side-renamed-artist',
			'found'     => true,
			'upcoming'  => array( array( 'event_id' => 9 ) ),
			'past'      => array(),
		);

		$result  = ec_artist_shows_gather( 202 );
		$request = $GLOBALS['ec_test']['cross_site_requests'][0][3]['query']['input'];

		$this->assertSame( 502, $request['term_id'] );
		$this->assertArrayNotHasKey( 'term_slug', $request );
		$this->assertSame( 'events-side-renamed-artist', $result['term_slug'] );
		$this->assertSame( 9, $result['upcoming'][0]['event_id'] );
	}

	public function test_events_join_rejects_a_mismatched_returned_term_id(): void {
		$this->addMainTerm( 203, 503 );
		$GLOBALS['ec_test']['cross_site_result'] = array(
			'term_id'   => 999,
			'term_slug' => 'wrong-artist',
			'found'     => true,
			'upcoming'  => array( array( 'event_id' => 10 ) ),
			'past'      => array(),
		);

		$this->assertSame(
			array( 'term_slug' => '', 'upcoming' => array(), 'past' => array() ),
			ec_artist_shows_gather( 203 )
		);
	}

	public function test_slug_discovery_is_persisted_then_used_as_the_stable_events_join(): void {
		$this->addMainTerm( 204 );
		$GLOBALS['ec_test']['cross_site_results'] = array(
			array(
				'term_id'   => 504,
				'term_slug' => 'main-artist-204',
				'found'     => true,
				'upcoming'  => array(),
				'past'      => array(),
			),
			array(
				'term_id'   => 504,
				'term_slug' => 'events-artist-renamed-after-binding',
				'found'     => true,
				'upcoming'  => array( array( 'event_id' => 11 ) ),
				'past'      => array(),
			),
		);

		$result            = ec_artist_shows_gather( 204 );
		$discovery_request = $GLOBALS['ec_test']['cross_site_requests'][0][3]['query']['input'];
		$stable_request    = $GLOBALS['ec_test']['cross_site_requests'][1][3]['query']['input'];

		$this->assertSame( 'main-artist-204', $discovery_request['term_slug'] );
		$this->assertSame( 504, $stable_request['term_id'] );
		$this->assertArrayNotHasKey( 'term_slug', $stable_request );
		$this->assertSame( 504, $GLOBALS['ec_test']['blogs'][1]['term_meta'][204]['_events_artist_term_id'] );
		$this->assertSame( 'events-artist-renamed-after-binding', $result['term_slug'] );
		$this->assertSame( 11, $result['upcoming'][0]['event_id'] );
	}
}
