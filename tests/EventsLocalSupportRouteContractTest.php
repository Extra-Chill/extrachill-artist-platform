<?php

use PHPUnit\Framework\TestCase;

final class EventsLocalSupportRouteContractTest extends TestCase {
	public function test_sibling_events_artist_route_and_render_contract(): void {
		$events_root = (string) getenv( 'EXTRACHILL_EVENTS_WORKTREE' );
		if ( '' === $events_root || ! is_file( $events_root . '/vendor/bin/phpunit' ) ) {
			$this->markTestSkipped( 'Set EXTRACHILL_EVENTS_WORKTREE to run the cross-repository route contract.' );
		}

		foreach ( array( 'LocalSupportOrganizerEventsTest.php', 'LocalSupportRenderedRouteTest.php' ) as $suite ) {
			$output = array();
			$status = 0;
			exec(
				escapeshellarg( $events_root . '/vendor/bin/phpunit' ) . ' --configuration ' . escapeshellarg( $events_root . '/phpunit.xml.dist' ) . ' ' . escapeshellarg( $events_root . '/tests/' . $suite ),
				$output,
				$status
			);
			$this->assertSame( 0, $status, $suite . " failed:\n" . implode( "\n", $output ) );
		}
	}
}
