<?php
/**
 * Inline Subscribe Form for Artist Link Page
 * Used in extrch-link-page-template.php when display mode is 'inline_form'.
 *
 * Assumes $artist_id is set by the including template.
 */

defined( 'ABSPATH' ) || exit;

// Ensure $artist_id is available
$current_artist_id = apply_filters('ec_get_artist_id', isset($artist_id) ? compact('artist_id') : []);

if (empty($current_artist_id)) {
    // Don't render the form if artist ID is missing
    return;
}

// Create a nonce for the AJAX form submission
$subscribe_nonce = wp_create_nonce( 'extrch_subscribe_nonce' );

$artist_name = isset($artist_name) ? $artist_name : (isset($data['display_title']) ? $data['display_title'] : '');
?>

<div class="extrch-link-page-subscribe-inline-form-container">
    <h3 class="extrch-subscribe-header">
        Subscribe<?php if (!empty($artist_name)) echo ' to ' . esc_html($artist_name); ?>
    </h3>
    <p><?php 
    $subscribe_description = isset($data['_link_page_subscribe_description']) && $data['_link_page_subscribe_description'] !== '' ? $data['_link_page_subscribe_description'] : sprintf(__('Enter your email address to receive occasional news and updates from %s.', 'extrachill-artist-platform'), $artist_name);
    echo esc_html($subscribe_description);
    ?></p>

    <form id="extrch-subscribe-form-inline" class="extrch-subscribe-form">
        <input type="hidden" name="action" value="extrch_link_page_subscribe">
        <input type="hidden" name="artist_id" value="<?php echo esc_attr($current_artist_id); ?>">
        <input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr($subscribe_nonce); ?>">

        <div class="form-group">
            <input type="email" name="subscriber_email" id="subscriber_email_inline" placeholder="<?php esc_attr_e('Your email address', 'extrachill-artist-platform'); ?>" required aria-label="<?php esc_attr_e('Email Address', 'extrachill-artist-platform'); ?>">
        </div>

        <button type="submit" class="button-1 button-medium"><?php esc_html_e('Subscribe', 'extrachill-artist-platform'); ?></button>

        <div class="extrch-form-message" aria-live="polite"></div> <?php // For success/error messages ?>
    </form>
</div>

<?php
// The JavaScript for handling form submission
// will be in a separate file (link-page-subscribe.js)
?> 