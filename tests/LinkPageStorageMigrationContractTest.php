<?php
/**
 * Artist migration callback tests.
 *
 * @package ExtraChillArtistPlatform
 */

use PHPUnit\Framework\TestCase;

/** Multisite-capable test registry matching the Link Pages participant contract. */
final class EcArtistMigrationTestRegistry {
	/** @var array */
	private $participants = array();

	/** Register one complete participant. */
	public function register( $name, $contract_version, $callbacks, $priority ) {
		foreach ( array( 'claim_owner', 'plan', 'apply', 'validate', 'rollback' ) as $operation ) {
			if ( empty( $callbacks[ $operation ] ) || ! is_callable( $callbacks[ $operation ] ) ) {
				return new WP_Error( 'invalid_link_page_migration_participant', 'Every migration participant callback is required.' );
			}
		}
		$this->participants[ $name ] = compact( 'name', 'contract_version', 'callbacks', 'priority' );
		return true;
	}

	/** Return registered participants. */
	public function snapshot() {
		return array_values( $this->participants );
	}

	/** Reset isolated fixture state. */
	public function reset() {
		$this->participants = array();
	}
}

if ( ! function_exists( 'ec_link_page_migration_participant_registry' ) ) {
	/** Return the migration test registry. */
	function ec_link_page_migration_participant_registry() {
		static $registry = null;
		if ( null === $registry ) {
			$registry = new EcArtistMigrationTestRegistry();
		}
		return $registry;
	}
}

if ( ! function_exists( 'ec_register_link_page_migration_participant' ) ) {
	/** Register a fixture participant using the current core signature. */
	function ec_register_link_page_migration_participant( $name, $contract_version, $callbacks, $priority = 10 ) {
		return ec_link_page_migration_participant_registry()->register( $name, $contract_version, $callbacks, $priority );
	}
}

require_once dirname( __DIR__ ) . '/inc/link-pages/storage-migration.php';

/** Verify Artist behavior through the executable migration callbacks. */
final class LinkPageStorageMigrationContractTest extends TestCase {
	/** Prepare a nested multisite source fixture. */
	protected function setUp(): void {
		$this->resetRegistry( ec_link_page_owner_compatibility_registry(), 'providers' );
		$this->resetRegistry( ec_link_page_operation_provider_registry(), 'providers' );
		ec_link_page_migration_participant_registry()->reset();
		$GLOBALS['_wp_switched_stack'] = array( 1 );
		$GLOBALS['switched']           = true;
		$GLOBALS['ec_test']            = array(
			'current_blog_id' => 7,
			'blog_stack'      => array( 1 ),
			'current_user_id' => 9,
			'managed_artists' => array( 9 => array( 20 ) ),
			'thumbnails'      => array( 20 => 53 ),
			'blogs'           => array(
				1  => $this->emptyBlog(),
				4  => $this->emptyBlog(),
				7  => $this->emptyBlog(),
				13 => $this->emptyBlog(),
			),
		);
		extrachill_register_artist_profile_cpt();
		extrachill_register_artist_link_page_cpt();
		ec_register_link_page_owner_compatibility_provider( 'artist-platform', 'ec_artist_link_page_owner_compatibility_provider' );
		ec_register_link_page_operation_provider( 'artist-platform', 'ec_artist_link_page_operation_provider' );

		$this->addPost( 4, 20, 'artist_profile', 0 );
		$this->addPost( 4, 40, 'artist_link_page', 0 );
		$this->addPost( 4, 51, 'attachment', 40 );
		$this->addPost( 4, 52, 'attachment', 99 );
		$this->addPost( 4, 53, 'attachment', 40 );
		$GLOBALS['ec_test']['blogs'][4]['post_meta'][20] = array(
			'_extrch_link_page_id' => array( 40 ),
		);
		$GLOBALS['ec_test']['blogs'][4]['post_meta'][40] = array(
			EC_LINK_PAGE_OWNER_META_KEY          => array( 'post:4:artist_profile:20' ),
			'_associated_artist_profile_id'      => array( 20 ),
			'_link_page_profile_image_id'        => array( 51 ),
			'_link_page_background_image_id'     => array( 52 ),
		);
	}

	/** Remove fixture providers after each test. */
	protected function tearDown(): void {
		$this->resetRegistry( ec_link_page_owner_compatibility_registry(), 'providers' );
		$this->resetRegistry( ec_link_page_operation_provider_registry(), 'providers' );
		ec_link_page_migration_participant_registry()->reset();
	}

	/** Registration is versioned, complete, and owner claiming is mandatory. */
	public function test_registration_claim_plan_validate_apply_and_rollback_callbacks_execute(): void {
		$incomplete = array_fill_keys( array( 'plan', 'apply', 'validate', 'rollback' ), '__return_true' );
		$this->assertMigrationError( 'invalid_link_page_migration_participant', ec_register_link_page_migration_participant( 'incomplete', '1', $incomplete ) );
		$this->assertTrue( ec_artist_register_link_page_migration_adapter() );
		$participants = ec_link_page_migration_participant_registry()->snapshot();
		$this->assertCount( 1, $participants );
		$this->assertSame( 'artist-platform', $participants[0]['name'] );
		$this->assertSame( '1', $participants[0]['contract_version'] );
		$this->assertSame( array( 'claim_owner', 'plan', 'apply', 'validate', 'rollback' ), array_keys( $participants[0]['callbacks'] ) );

		$callbacks = $participants[0]['callbacks'];
		$this->assertTrue( call_user_func( $callbacks['claim_owner'], $this->claimContext() ) );
		$wrong_owner                  = $this->claimContext();
		$wrong_owner['owner']['kind'] = 'term';
		$this->assertFalse( call_user_func( $callbacks['claim_owner'], $wrong_owner ) );
		$plan = call_user_func( $callbacks['plan'], $this->migrationContext() );
		$this->assertIsArray( $plan );
		$this->assertTrue( call_user_func( $callbacks['validate'], $this->migrationContext() ) );
		$this->assertTrue( call_user_func( $callbacks['apply'], $this->migrationContext() ) );
		$this->assertTrue( call_user_func( $callbacks['rollback'], $this->migrationContext() ) );
		$this->assertContextRestored();
	}

	/** Plan enumerates every real attachment source without mutating either side. */
	public function test_plan_preserves_ids_parents_source_owners_and_exact_source_state(): void {
		$source_before      = $GLOBALS['ec_test']['blogs'][4];
		$destination_before = $GLOBALS['ec_test']['blogs'][13];
		$plan               = ec_artist_link_page_migration_plan( $this->migrationContext() );

		$this->assertIsArray( $plan );
		$this->assertSame( array( 51, 52, 53 ), $plan['attachment_ids'] );
		$this->assertSame(
			array(
				array( 'attachment_id' => 51, 'destination_parent' => 40, 'owner_reference' => 'post:4:artist_profile:20', 'reason' => 'migrated-link-page-parent-preserved' ),
				array( 'attachment_id' => 52, 'destination_parent' => 0, 'owner_reference' => 'post:4:artist_profile:20', 'reason' => 'external-owner-parent-remapped' ),
				array( 'attachment_id' => 53, 'destination_parent' => 40, 'owner_reference' => 'post:4:artist_profile:20', 'reason' => 'migrated-link-page-parent-preserved' ),
			),
			$plan['attachment_semantics']
		);
		$this->assertSame( 'generated-on-demand-no-persisted-attachment', $plan['qr_storage'] );
		$this->assertSame( 20, $plan['profiles'][0]['profile_id'] );
		$this->assertSame( 40, $plan['profiles'][0]['link_page_id'] );
		$this->assertSame( 40, $plan['profiles'][0]['reciprocal_link_page_id'] );
		$this->assertSame( 4, $plan['profiles'][0]['profile_remains_on_blog_id'] );
		$this->assertSame( 51, $plan['profiles'][0]['legacy_profile_image_id'] );
		$this->assertSame( 52, $plan['profiles'][0]['background_image_id'] );
		$this->assertSame( 53, $plan['profiles'][0]['profile_image_id'] );
		$this->assertSame( $source_before, $GLOBALS['ec_test']['blogs'][4], 'Source profiles, posts, and every metadata row must remain byte-for-byte equivalent.' );
		$this->assertSame( $destination_before, $GLOBALS['ec_test']['blogs'][13], 'The participant never writes destination posts or reciprocal metadata.' );
		$this->assertTrue( ec_artist_link_page_migration_validate( $this->migrationContext() ) );
		$this->assertSame( $source_before, $GLOBALS['ec_test']['blogs'][4] );
		$this->assertSame( $destination_before, $GLOBALS['ec_test']['blogs'][13] );
		$this->assertContextRestored();
	}

	/** Claiming and planning reject a non-Artist source blog. */
	public function test_wrong_source_blog_is_rejected(): void {
		$claim                   = $this->claimContext();
		$claim['source_blog_id'] = 7;
		$this->assertFalse( ec_artist_link_page_migration_claim_owner( $claim ) );
		$context                   = $this->migrationContext();
		$context['source_blog_id'] = 7;
		$this->assertMigrationError( 'artist_link_page_migration_source_mismatch', ec_artist_link_page_migration_plan( $context ) );
		$this->assertContextRestored();
	}

	/** Missing source attachments fail even when the same ID exists elsewhere. */
	public function test_missing_and_wrong_blog_attachments_are_rejected_from_source_context(): void {
		unset( $GLOBALS['ec_test']['blogs'][4]['posts'][51] );
		$this->assertMigrationError( 'artist_link_page_migration_attachment_source_mismatch', ec_artist_link_page_migration_plan( $this->migrationContext() ) );
		$this->addPost( 13, 51, 'attachment', 40 );
		$this->assertMigrationError( 'artist_link_page_migration_attachment_source_mismatch', ec_artist_link_page_migration_plan( $this->migrationContext() ) );
		$this->assertContextRestored();
	}

	/** Canonical ownership must agree with the legacy profile association. */
	public function test_canonical_owner_divergence_is_rejected(): void {
		$this->addPost( 4, 21, 'artist_profile', 0 );
		$GLOBALS['ec_test']['blogs'][4]['post_meta'][40][ EC_LINK_PAGE_OWNER_META_KEY ] = array( 'post:4:artist_profile:21' );
		$this->assertMigrationError( 'artist_link_page_migration_owner_mismatch', ec_artist_link_page_migration_plan( $this->migrationContext() ) );
	}

	/** Duplicate reciprocal rows are never collapsed into a valid pointer. */
	public function test_duplicate_reciprocal_meta_rows_are_rejected(): void {
		$GLOBALS['ec_test']['blogs'][4]['post_meta'][20]['_extrch_link_page_id'] = array( 40, 40 );
		$this->assertMigrationError( 'artist_link_page_migration_reciprocal_mismatch', ec_artist_link_page_migration_plan( $this->migrationContext() ) );
	}

	/** Another owner profile cannot point to the same Link Page. */
	public function test_competing_profile_pointer_is_rejected(): void {
		$this->addPost( 4, 21, 'artist_profile', 0 );
		$GLOBALS['ec_test']['blogs'][4]['post_meta'][21]['_extrch_link_page_id'] = array( 40 );
		$this->assertMigrationError( 'artist_link_page_migration_reciprocal_mismatch', ec_artist_link_page_migration_plan( $this->migrationContext() ) );
	}

	/** The owner profile pointer must identify the exact migrating Link Page ID. */
	public function test_stale_reciprocal_pointer_is_rejected(): void {
		$GLOBALS['ec_test']['blogs'][4]['post_meta'][20]['_extrch_link_page_id'] = array( 41 );
		$this->assertMigrationError( 'artist_link_page_migration_reciprocal_mismatch', ec_artist_link_page_migration_plan( $this->migrationContext() ) );
	}

	/** Return an empty blog fixture. */
	private function emptyBlog(): array {
		return array( 'terms' => array(), 'term_meta' => array(), 'posts' => array(), 'post_meta' => array() );
	}

	/** Add a post to one site in the multisite fixture. */
	private function addPost( $blog_id, $post_id, $post_type, $parent ): void {
		$GLOBALS['ec_test']['blogs'][ $blog_id ]['posts'][ $post_id ] = (object) array(
			'ID'          => $post_id,
			'post_type'   => $post_type,
			'post_status' => 'publish',
			'post_title'  => 'Fixture ' . $post_id,
			'post_name'   => 'fixture-' . $post_id,
			'post_parent' => $parent,
		);
	}

	/** Build the current owner-claim context. */
	private function claimContext(): array {
		return array(
			'source_blog_id' => 4,
			'link_page_id'   => 40,
			'owner'          => array(
				'kind'      => 'post',
				'blog_id'   => 4,
				'subtype'   => 'artist_profile',
				'object_id' => 20,
			),
		);
	}

	/** Build the participant plan/validate context supplied by Link Pages. */
	private function migrationContext(): array {
		return array(
			'mode'                => 'readiness',
			'source_blog_id'      => 4,
			'destination_blog_id' => 13,
			'link_page_ids'       => array( 40 ),
			'attachment_map'      => array(),
			'fingerprint'         => '',
			'journal_id'          => '',
			'journal_record'      => null,
		);
	}

	/** Assert a migration error code. */
	private function assertMigrationError( $code, $result ): void {
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( $code, $result->get_error_code() );
	}

	/** Assert exact restoration of the pre-existing nested switch stack. */
	private function assertContextRestored(): void {
		$this->assertSame( 7, get_current_blog_id() );
		$this->assertSame( array( 1 ), $GLOBALS['_wp_switched_stack'] );
		$this->assertSame( array( 1 ), $GLOBALS['ec_test']['blog_stack'] );
		$this->assertTrue( $GLOBALS['switched'] );
	}

	/** Reset an append-only runtime registry for test isolation. */
	private function resetRegistry( $registry, $property_name ): void {
		$reflection = new ReflectionObject( $registry );
		$property   = $reflection->getProperty( $property_name );
		$property->setAccessible( true );
		$property->setValue( $registry, array() );
	}
}
