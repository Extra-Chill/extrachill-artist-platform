<?php
/**
 * Link page footer with a canonical artist profile continuation.
 */

defined( 'ABSPATH' ) || exit;

$args = wp_parse_args(
	$args ?? array(),
	array(
		'artist_profile' => null,
		'powered_by'     => true,
	)
);

$artist_profile = $args['artist_profile'];
$profile_url    = '';
$profile_label  = '';

if (
	is_object( $artist_profile )
	&& ! empty( $artist_profile->ID )
	&& 'artist_profile' === ( $artist_profile->post_type ?? '' )
	&& 'publish' === ( $artist_profile->post_status ?? '' )
) {
	$artist_name = get_the_title( $artist_profile->ID );
	$profile_url = get_permalink( $artist_profile->ID );

	if ( $artist_name && $profile_url ) {
		/* translators: %s: artist name. */
		$profile_label = sprintf( __( 'View %s on Extra Chill', 'extrachill-artist-platform' ), $artist_name );
	}
}

if ( ! $profile_label && ! $args['powered_by'] ) {
	return;
}

$powered_by_url = $args['powered_by']
	? ec_get_site_url( 'main' ) . '/power/?utm_source=linkpage&utm_medium=footer&utm_campaign=power'
	: '';
?>
<footer class="extrch-link-page-footer">
	<?php if ( $profile_label ) : ?>
		<a
			class="extrch-link-page-profile-continuation"
			href="<?php echo esc_url( $profile_url ); ?>"
			rel="noopener"
		><span class="extrch-link-page-link-text"><?php echo esc_html( $profile_label ); ?></span></a>
	<?php endif; ?>
	<?php if ( $args['powered_by'] ) : ?>
		<div class="extrch-link-page-powered">
			<a href="<?php echo esc_url( $powered_by_url ); ?>" rel="noopener">Powered by Extra Chill</a>
		</div>
	<?php endif; ?>
</footer>
