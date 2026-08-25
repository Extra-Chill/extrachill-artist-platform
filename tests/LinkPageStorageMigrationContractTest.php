<?php
/**
 * Artist migration hardening contracts.
 *
 * @package ExtraChillArtistPlatform
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/inc/link-pages/storage-migration.php';


/** Verify mandatory owner and attachment migration semantics. */
final class LinkPageStorageMigrationContractTest extends TestCase {
	/** Owner claims are source-affine. */
	public function test_owner_claim_requires_exact_source_affinity(): void {
		$context = array(
			'source_blog_id' => 4,
			'owner'          => array(
				'kind'    => 'post',
				'subtype' => 'artist_profile',
				'blog_id' => 4,
			),
		);
		$this->assertTrue( ec_artist_link_page_migration_claim_owner( $context ) );
		$context['source_blog_id'] = 7;
		$this->assertFalse( ec_artist_link_page_migration_claim_owner( $context ) );
	}

	/** Actual compatibility keys and parent semantics remain explicit. */
	public function test_actual_binding_and_attachment_contracts_are_explicit(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/inc/link-pages/storage-migration.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local source contract fixture.
		foreach ( array( '_associated_artist_profile_id', '_extrch_link_page_id', '_link_page_profile_image_id', '_link_page_background_image_id', 'get_post_thumbnail_id', 'migrated-link-page-parent-preserved', 'external-owner-parent-remapped', 'count( $reciprocal_rows )', '$other_profiles' ) as $needle ) {
			$this->assertStringContainsString( $needle, $source );
		}
		$this->assertStringContainsString( "'artist-platform',\n\t\t'1'", $source );
	}
}
