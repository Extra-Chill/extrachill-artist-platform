<?php

use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults ) {
		return array_merge( $defaults, $args );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $value ) {
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $value ) {
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'ec_get_site_url' ) ) {
	function ec_get_site_url( $site ) {
		return 'main' === $site ? 'https://extrachill.com' : '';
	}
}

final class LinkPageProfileContinuationTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['ec_test'] = array(
			'posts'      => array(),
			'permalinks' => array(),
		);
	}

	private function render_continuation( $artist_profile, bool $powered_by = true ): string {
		$args = array(
			'artist_profile' => $artist_profile,
			'powered_by'     => $powered_by,
		);

		ob_start();
		require dirname( __DIR__ ) . '/inc/link-pages/live/templates/components/profile-continuation.php';
		return ob_get_clean();
	}

	private function add_artist( string $status = 'publish', string $title = 'The Chill Band' ): object {
		$artist = (object) array(
			'ID'          => 127,
			'post_type'   => 'artist_profile',
			'post_status' => $status,
			'post_title'  => $title,
			'post_name'   => 'the-chill-band',
		);

		$GLOBALS['ec_test']['posts'][ 127 ] = $artist;
		return $artist;
	}

	public function test_published_relationship_renders_canonical_profile_before_secondary_branding(): void {
		$html = $this->render_continuation( $this->add_artist() );

		$this->assertStringContainsString( 'View The Chill Band on Extra Chill', $html );
		$this->assertStringContainsString( 'https://artist.example/artists/the-chill-band/', $html );
		$this->assertLessThan(
			strpos( $html, 'Powered by Extra Chill' ),
			strpos( $html, 'View The Chill Band on Extra Chill' )
		);
	}

	public function test_unpublished_relationship_keeps_only_secondary_branding(): void {
		$html = $this->render_continuation( $this->add_artist( 'draft' ) );

		$this->assertStringNotContainsString( 'extrch-link-page-profile-continuation', $html );
		$this->assertStringContainsString( 'Powered by Extra Chill', $html );
	}

	public function test_missing_relationship_keeps_only_secondary_branding(): void {
		$html = $this->render_continuation( null );

		$this->assertStringNotContainsString( 'extrch-link-page-profile-continuation', $html );
		$this->assertStringContainsString( 'Powered by Extra Chill', $html );
	}

	public function test_custom_domain_permalink_and_artist_name_are_escaped(): void {
		$artist = $this->add_artist( 'publish', 'A & B <script>alert("x")</script>' );
		$GLOBALS['ec_test']['permalinks'][ 127 ] = 'https://artist.custom.example/a-and-b/?from=link&page=footer';

		$html = $this->render_continuation( $artist );

		$this->assertStringContainsString(
			'https://artist.custom.example/a-and-b/?from=link&amp;page=footer',
			$html
		);
		$this->assertStringContainsString(
			'View A &amp; B &lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; on Extra Chill',
			$html
		);
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_profile_continuation_has_semantic_footer_and_descriptive_accessible_name(): void {
		$html = $this->render_continuation( $this->add_artist() );
		$document = new DOMDocument();
		$use_internal_errors = libxml_use_internal_errors( true );
		$document->loadHTML( '<!DOCTYPE html><html><body>' . $html . '</body></html>' );
		libxml_clear_errors();
		libxml_use_internal_errors( $use_internal_errors );
		$xpath = new DOMXPath( $document );
		$link = $xpath->query(
			'//footer[contains(concat(" ", normalize-space(@class), " "), " extrch-link-page-footer ")]/a[contains(concat(" ", normalize-space(@class), " "), " extrch-link-page-profile-continuation ")]'
		)->item( 0 );

		$this->assertNotNull( $link );
		$this->assertSame( 'View The Chill Band on Extra Chill', trim( $link->textContent ) );
		$this->assertSame( 'noopener', $link->getAttribute( 'rel' ) );
	}

	public function test_footer_remains_outside_curated_link_section_rendering(): void {
		$template = file_get_contents( dirname( __DIR__ ) . '/inc/link-pages/live/templates/extrch-link-page-template.php' );

		$this->assertGreaterThan(
			strpos( $template, 'foreach ($link_sections as $section)' ),
			strpos( $template, "'profile-continuation'" )
		);
	}

	public function test_footer_has_mobile_safe_wrapping_and_touch_target_styles(): void {
		$styles = file_get_contents( dirname( __DIR__ ) . '/assets/css/extrch-links.css' );

		$this->assertMatchesRegularExpression(
			'/\\.extrch-link-page-profile-continuation\\s*\\{[^}]*min-height:\\s*44px;[^}]*overflow-wrap:\\s*anywhere;/s',
			$styles
		);
		$this->assertMatchesRegularExpression(
			'/@media \\(max-width:\s*600px\\)[^{]*\\{.*\\.extrch-link-page-footer\\s*\\{/s',
			$styles
		);
	}

	public function test_footer_links_keep_delegated_click_text_tracking(): void {
		$html = $this->render_continuation( $this->add_artist() );
		$script = file_get_contents( dirname( __DIR__ ) . '/inc/link-pages/live/assets/js/link-page-public-tracking.js' );

		$this->assertStringContainsString( 'class="extrch-link-page-link-text"', $html );
		$this->assertStringContainsString( "event.target.closest('a')", $script );
		$this->assertStringContainsString( 'destination_url: linkElement.href', $script );
	}
}
