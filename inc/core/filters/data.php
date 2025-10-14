<?php
/**
 * Centralized data functions for artist profiles, link pages, and following system.
 * ec_get_link_page_data() serves as single source of truth for all link page data access.
 */

function ec_get_forum_for_artist( $artist_id ) {
    if ( ! $artist_id || get_post_type( $artist_id ) !== 'artist_profile' ) {
        return false;
    }

    $forum_id = get_post_meta( $artist_id, '_artist_forum_id', true );
    return $forum_id ? (int) $forum_id : false;
}

function ec_is_user_artist_member( $user_id = null, $artist_id = null ) {
    if ( ! $user_id ) {
        $user_id = get_current_user_id();
    }

    if ( ! $user_id || ! $artist_id ) {
        return false;
    }

    $user_artist_ids = ec_get_user_owned_artists( $user_id );
    return in_array( (int) $artist_id, $user_artist_ids );
}

function ec_get_user_followed_artists( $user_id = null ) {
    if ( ! $user_id ) {
        $user_id = get_current_user_id();
    }

    if ( ! $user_id ) {
        return array();
    }

    $followed_ids = get_user_meta( $user_id, '_followed_artist_profile_ids', true );
    if ( ! is_array( $followed_ids ) ) {
        return array();
    }

    return array_map( 'intval', $followed_ids );
}

function ec_is_user_following_artist( $user_id = null, $artist_id = null ) {
    if ( ! $user_id ) {
        $user_id = get_current_user_id();
    }

    if ( ! $user_id || ! $artist_id ) {
        return false;
    }

    $followed_artists = ec_get_user_followed_artists( $user_id );
    return in_array( (int) $artist_id, $followed_artists );
}

function ec_get_link_page_for_artist( $artist_id ) {
    if ( ! $artist_id || get_post_type( $artist_id ) !== 'artist_profile' ) {
        return false;
    }

    $link_pages = get_posts( array(
        'post_type' => 'artist_link_page',
        'meta_key' => '_associated_artist_profile_id',
        'meta_value' => (string) $artist_id,
        'posts_per_page' => 1,
        'fields' => 'ids'
    ) );

    return ! empty( $link_pages ) ? (int) $link_pages[0] : false;
}

function ec_get_user_artist_profiles( $user_id = null ) {
    if ( ! $user_id ) {
        $user_id = get_current_user_id();
    }

    if ( ! $user_id ) {
        return array();
    }

    $artist_ids = ec_get_user_owned_artists( $user_id );
    if ( empty( $artist_ids ) ) {
        return array();
    }

    return get_posts( array(
        'post_type' => 'artist_profile',
        'post__in' => $artist_ids,
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ) );
}

function ec_get_artist_subscribers( $artist_id, $args = array() ) {
    global $wpdb;

    if ( ! $artist_id ) {
        return array();
    }

    $defaults = array(
        'per_page' => 20,
        'page' => 1,
        'include_exported' => false
    );
    $args = wp_parse_args( $args, $defaults );

    $table_name = $wpdb->prefix . 'artist_subscribers';
    $offset = ( $args['page'] - 1 ) * $args['per_page'];

    $where_clause = $wpdb->prepare( "WHERE artist_profile_id = %d", $artist_id );
    if ( ! $args['include_exported'] ) {
        $where_clause .= " AND (exported = 0 OR exported IS NULL)";
    }

    $sql = $wpdb->prepare(
        "SELECT * FROM {$table_name} {$where_clause} ORDER BY subscription_date DESC LIMIT %d OFFSET %d",
        $args['per_page'],
        $offset
    );

    return $wpdb->get_results( $sql );
}

/**
 * Single source of truth for link page data (CSS vars, links, socials, settings).
 * Supports live preview overrides and comprehensive data validation.
 */
function ec_get_link_page_data( $artist_id, $link_page_id = null, $overrides = array() ) {
    if ( ! $artist_id ) {
        return array();
    }

    if ( ! $link_page_id ) {
        $link_page_id = ec_get_link_page_for_artist( $artist_id );
    }

    if ( ! $link_page_id ) {
        return array();
    }

    $all_meta = get_post_meta( $link_page_id );

    $data = array(
        'artist_id' => (int) $artist_id,
        'link_page_id' => (int) $link_page_id,
        'css_vars' => array(),
        'links' => array(),
        'socials' => array(),
        'settings' => array(
            'link_expiration_enabled' => false,
            'weekly_notifications_enabled' => false,
            'redirect_enabled' => false,
            'redirect_target_url' => '',
            'youtube_embed_enabled' => true,
            'meta_pixel_id' => '',
            'google_tag_id' => '',
            'google_tag_manager_id' => '',
            'subscribe_display_mode' => 'icon_modal',
            'subscribe_description' => '',
            'social_icons_position' => 'above',
            'profile_image_shape' => 'circle',
            'profile_image_id' => '',
            'overlay_enabled' => true
        )
    );
    $css_vars_raw = array();
    if ( isset( $all_meta['_link_page_custom_css_vars'][0] ) ) {
        $css_vars_raw = maybe_unserialize( $all_meta['_link_page_custom_css_vars'][0] );
        if ( ! is_array( $css_vars_raw ) ) {
            $css_vars_raw = array();
        }
    }

    $default_css_vars = ec_get_link_page_defaults_for( 'styles' );
    $data['css_vars'] = array_merge( $default_css_vars, $css_vars_raw );

    $data['css_vars']['--link-page-button-hover-text-color'] = $data['css_vars']['--link-page-link-text-color'];

    $data['raw_font_values'] = array(
        'title_font' => $data['css_vars']['--link-page-title-font-family'] ?? '',
        'body_font' => $data['css_vars']['--link-page-body-font-family'] ?? ''
    );

    if ( class_exists( 'ExtraChillArtistPlatform_Fonts' ) ) {
        $font_manager = ExtraChillArtistPlatform_Fonts::instance();
        $data['css_vars'] = $font_manager->process_font_css_vars( $data['css_vars'] );
    }

    if ( isset( $all_meta['_link_page_links'][0] ) ) {
        $links_raw = maybe_unserialize( $all_meta['_link_page_links'][0] );
        if ( is_array( $links_raw ) ) {
            $data['links'] = $links_raw;
        }
    }

    $artist_social_links = get_post_meta( $artist_id, '_artist_profile_social_links', true );
    $artist_social_links = maybe_unserialize( $artist_social_links );
    if ( ! is_array( $artist_social_links ) || empty( $artist_social_links ) ) {
        $social_json = get_post_meta( $artist_id, '_artist_social_links_json', true );
        if ( ! empty( $social_json ) ) {
            $artist_social_links = json_decode( $social_json, true );
        }
    }
    if ( is_array( $artist_social_links ) ) {
        $data['socials'] = $artist_social_links;
    }
    $data['settings']['link_expiration_enabled'] = isset( $all_meta['_link_expiration_enabled'][0] ) && $all_meta['_link_expiration_enabled'][0] === '1';
    $data['settings']['weekly_notifications_enabled'] = isset( $all_meta['_link_page_enable_weekly_notifications'][0] ) && $all_meta['_link_page_enable_weekly_notifications'][0] === '1';
    $data['settings']['redirect_enabled'] = isset( $all_meta['_link_page_redirect_enabled'][0] ) && $all_meta['_link_page_redirect_enabled'][0] === '1';
    $data['settings']['redirect_target_url'] = $all_meta['_link_page_redirect_target_url'][0] ?? '';
    $data['settings']['youtube_embed_enabled'] = ! isset( $all_meta['_enable_youtube_inline_embed'][0] ) || $all_meta['_enable_youtube_inline_embed'][0] !== '0';
    $data['settings']['meta_pixel_id'] = $all_meta['_link_page_meta_pixel_id'][0] ?? '';
    $data['settings']['google_tag_id'] = $all_meta['_link_page_google_tag_id'][0] ?? '';
    $data['settings']['google_tag_manager_id'] = $all_meta['_link_page_google_tag_manager_id'][0] ?? '';
    $data['settings']['subscribe_display_mode'] = $all_meta['_link_page_subscribe_display_mode'][0] ?? 'icon_modal';
    $data['settings']['subscribe_description'] = $all_meta['_link_page_subscribe_description'][0] ?? '';
    $data['settings']['social_icons_position'] = $all_meta['_link_page_social_icons_position'][0] ?? 'above';
    $data['settings']['profile_image_shape'] = $all_meta['_link_page_profile_img_shape'][0] ?? 'circle';
    $data['settings']['profile_image_id'] = get_post_thumbnail_id( $artist_id ) ?: '';
    $data['settings']['background_image_id'] = $all_meta['_link_page_background_image_id'][0] ?? '';

    if ( isset( $data['css_vars']['overlay'] ) ) {
        $data['settings']['overlay_enabled'] = $data['css_vars']['overlay'] === '1';
    }

    $display_data = array(
        'display_title' => (isset($overrides['artist_profile_title']) && $overrides['artist_profile_title'] !== '') ? $overrides['artist_profile_title'] : ($artist_id ? get_the_title($artist_id) : ''),
        'bio' => (isset($overrides['link_page_bio_text']) && $overrides['link_page_bio_text'] !== '') ? $overrides['link_page_bio_text'] : ($artist_id ? get_post($artist_id)->post_content : ''),
        'profile_img_url' => (isset($overrides['profile_img_url']) && $overrides['profile_img_url'] !== '') ? $overrides['profile_img_url'] : ($artist_id ? (get_the_post_thumbnail_url($artist_id, 'large') ?: '') : ''),
        'social_links' => isset($overrides['social_links']) ? $overrides['social_links'] : $data['socials'],
        'socials' => $data['socials'],
        'link_sections' => isset($data['links'][0]['links']) || empty($data['links']) ? $data['links'] : array(array('section_title' => '', 'links' => $data['links'])),
        'css_vars' => $data['css_vars'],
        'background_type' => $data['css_vars']['--link-page-background-type'] ?? 'color',
        'background_style' => '',
        'powered_by' => true,
        'artist_id' => $artist_id,
        'link_page_id' => $link_page_id,
        'profile_img_shape' => $data['settings']['profile_image_shape'],
        '_link_page_social_icons_position' => $data['settings']['social_icons_position'],
        '_link_page_subscribe_display_mode' => $data['settings']['subscribe_display_mode'],
        '_link_page_subscribe_description' => $data['settings']['subscribe_description'],
        '_actual_link_page_id_for_template' => $link_page_id,
        'artist_profile' => $artist_id ? get_post($artist_id) : null,
        'settings' => $data['settings'],
        'links' => $data['links'],
        'raw_font_values' => $data['raw_font_values'],
        'background_image_id' => $data['settings']['background_image_id'],
        'background_image_url' => !empty($data['settings']['background_image_id']) ? wp_get_attachment_url($data['settings']['background_image_id']) : '',
    );
    if (isset($overrides['artist_profile_social_links_json'])) {
        $social_decoded = json_decode($overrides['artist_profile_social_links_json'], true);
        if (is_array($social_decoded)) {
            $display_data['social_links'] = $social_decoded;
            $display_data['socials'] = $social_decoded;
        }
    }

    if (isset($overrides['link_page_links_json'])) {
        $links_decoded = json_decode($overrides['link_page_links_json'], true);
        if (is_array($links_decoded)) {
            $display_data['links'] = $links_decoded;
            $display_data['link_sections'] = isset($links_decoded[0]['links']) || empty($links_decoded) ? $links_decoded : array(array('section_title' => '', 'links' => $links_decoded));
        }
    }

    if (isset($overrides['css_vars'])) {
        $display_data['css_vars'] = is_array($overrides['css_vars']) ? $overrides['css_vars'] : array();
    }

    return apply_filters( 'extrch_get_link_page_data', $display_data, $artist_id, $link_page_id, $overrides );
}

function ec_generate_css_variables_style_block( $css_vars, $element_id = 'link-page-custom-vars' ) {
    if ( empty( $css_vars ) || ! is_array( $css_vars ) ) {
        return '';
    }

    $output = '<style id="' . esc_attr( $element_id ) . '">:root {';
    foreach ( $css_vars as $key => $value ) {
        if ( $value !== null && $value !== false ) {
            $output .= esc_html( $key ) . ':' . $value . ';';
        }
    }
    $output .= '}</style>';

    return $output;
}

function ec_render_single_link( $link_data, $args = array() ) {
    $template_args = array_merge( $args, $link_data );
    return ec_render_template( 'single-link', $template_args );
}

function ec_render_link_section( $section_data, $args = array() ) {
    $template_args = array_merge( $args, $section_data );
    return ec_render_template( 'link-section', $template_args );
}

function ec_render_social_icon( $social_data, $social_manager = null ) {
    $template_args = array(
        'social_data' => $social_data,
        'social_manager' => $social_manager
    );
    return ec_render_template( 'social-icon', $template_args );
}

function ec_render_social_icons_container( $social_links, $position = 'above', $social_manager = null ) {
    $template_args = array(
        'social_links' => $social_links,
        'position' => $position,
        'social_manager' => $social_manager
    );
    return ec_render_template( 'social-icons-container', $template_args );
}