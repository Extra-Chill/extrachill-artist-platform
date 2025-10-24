<?php
/**
 * Artist Platform Rewrite Rules
 * 
 * Centralized management of all URL rewrite rules, query variables, and routing
 * for the artist platform functionality including artist profiles and link pages.
 *
 * @package ExtraChillArtistPlatform
 * @since 1.0.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Get all excluded slugs dynamically
 *
 * @return array Array of slugs to exclude from artist link page rewrite rules
 */
function extrachill_get_excluded_slugs() {
    // Get all published WordPress pages dynamically
    $pages = get_posts(array(
        'post_type' => 'page',
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields' => 'ids'
    ));

    $page_slugs = array();
    foreach ($pages as $page_id) {
        $slug = get_post_field('post_name', $page_id);
        if ($slug) {
            $page_slugs[] = $slug;
        }
    }

    // Keep critical hardcoded exclusions for non-page URLs and plugin paths
    $static_exclusions = array(
        'manage-artist-profiles',  // Management interface
        'manage-link-page',        // Link page management
        'join',                    // Join flow redirect (handled separately)
        'wp-login',
        'wp-admin',
        'admin'
    );

    return array_merge($static_exclusions, $page_slugs);
}

/**
 * Add all artist platform rewrite rules
 *
 * Only adds rewrite rules on extrachill.link domain. Other domains rely on
 * template_include routing via extrachill_handle_artist_profile_routing().
 */
function extrachill_add_rewrite_rules() {
    // Only add rewrite rules for extrachill.link domain
    // Template routing handles all other domains via REQUEST_URI parsing
    $current_host = strtolower( $_SERVER['HTTP_HOST'] ?? '' );
    $is_link_domain = ( stripos( $current_host, 'extrachill.link' ) !== false );

    if ( ! $is_link_domain ) {
        // Still register the query var for consistency, but no rewrite rules
        add_rewrite_tag( '%artist_link_page%', '([^&]+)' );
        return;
    }

    // Artist link page rewrite rules - only on extrachill.link domain
    $excluded_slugs = extrachill_get_excluded_slugs();
    $excluded_pattern = '(?!' . implode('|', $excluded_slugs) . ')';
    add_rewrite_rule( '^' . $excluded_pattern . '([^/]+)/?$', 'index.php?artist_link_page=$matches[1]', 'top' );
    add_rewrite_tag( '%artist_link_page%', '([^&]+)' );
}

/**
 * Add custom query variables for artist platform
 * 
 * @param array $vars Existing query variables
 * @return array Modified query variables
 */
function extrachill_add_query_vars( $vars ) {
    $vars[] = 'artist_link_page';
    $vars[] = 'dev_view_link_page';
    $vars[] = 'artist_id';
    return $vars;
}

/**
 * Prevent canonical redirection for extrachill.link domain
 * 
 * This allows our custom template_include logic to handle routing for the link domain
 * without WordPress trying to redirect to "correct" URLs.
 *
 * @param string $redirect_url The URL WordPress intends to redirect to
 * @param string $requested_url The originally requested URL
 * @return string|false The redirect URL, or false to prevent redirection
 */
function extrachill_prevent_canonical_redirect_for_link_domain( $redirect_url, $requested_url ) {
    $current_host = strtolower( $_SERVER['SERVER_NAME'] ?? '' );
    if ( $current_host === 'extrachill.link' ) {
        return false; 
    }
    return $redirect_url;
}

/**
 * Handle template routing for extrachill.link domain
 * 
 * Consolidated routing for all extrachill.link domain requests including
 * root domain, admin interface, join redirects, and link page slugs.
 *
 * @param string $template The template WordPress wants to load
 * @return string The template to actually load
 */
function extrachill_handle_link_domain_routing( $template ) {
    // Skip if in development mode
    if ( defined( 'EXTRCH_LINKPAGE_DEV' ) && EXTRCH_LINKPAGE_DEV ) {
        return $template;
    }

    $current_host = strtolower( $_SERVER['HTTP_HOST'] ?? '' );
    $is_link_page_domain = ( stripos( $current_host, 'extrachill.link' ) !== false );

    // Only handle extrachill.link domain requests
    if ( ! $is_link_page_domain ) {
        return $template;
    }

    global $wp_query;
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $request_path = trim( parse_url( $request_uri, PHP_URL_PATH ), '/' );

    // Handle join redirect
    if ( $request_path === 'join' ) {
        $redirect_url = 'https://artist.extrachill.com/login/?from_join=true';
        if ( ! headers_sent() ) {
            wp_redirect( esc_url_raw( $redirect_url ), 301 );
            exit;
        }
        return $template;
    }

    // Handle manage-link-page requests (admin interface)
    if ( $request_path === 'manage-link-page' ) {
        $manage_page = get_page_by_path( 'manage-link-page' );
        if ( $manage_page ) {
            $wp_query->posts = array( $manage_page );
            $wp_query->post_count = 1;
            $wp_query->found_posts = 1;
            $wp_query->max_num_pages = 1;
            $wp_query->is_page = true;
            $wp_query->is_singular = true;
            $wp_query->is_404 = false;
            $wp_query->query_vars['pagename'] = 'manage-link-page';
            $wp_query->queried_object_id = $manage_page->ID;
            $wp_query->queried_object = $manage_page;

            $template_to_load = EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/link-pages/management/templates/manage-link-page.php';
            if ( file_exists( $template_to_load ) ) {
                return $template_to_load;
            }
        } else {
            status_header( 404 );
            return get_404_template();
        }
    }

    // Handle root domain request or extra-chill request
    $is_root_or_extra_chill = ( empty( $request_path ) || $request_path === 'extra-chill' );
    if ( $is_root_or_extra_chill ) {
        // Handle extra-chill redirect to root
        if ( $request_path === 'extra-chill' ) {
            if ( ! headers_sent() ) {
                wp_redirect( esc_url_raw( 'https://extrachill.link/' ), 301 );
                exit;
            }
        }

        // Look for default extra-chill link page
        $default_slug = 'extra-chill';
        $default_link_pages = get_posts( array(
            'name'           => $default_slug,
            'post_type'      => 'artist_link_page',
            'post_status'    => 'publish',
            'numberposts'    => 1,
            'fields'         => 'ids',
        ) );

        if ( $default_link_pages ) {
            $default_link_page_id = $default_link_pages[0];
            $default_link_page = get_post( $default_link_page_id );

            $wp_query->posts = array( $default_link_page );
            $wp_query->post_count = 1;
            $wp_query->found_posts = 1;
            $wp_query->max_num_pages = 1;
            $wp_query->is_single = true;
            $wp_query->is_singular = true;
            $wp_query->is_404 = false;
            $wp_query->query_vars['name'] = $default_slug;
            $wp_query->query_vars['post_type'] = 'artist_link_page';
            $wp_query->queried_object_id = $default_link_page_id;
            $wp_query->queried_object = $default_link_page;

            $template_to_load = EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/link-pages/live/templates/single-artist_link_page.php';
            if ( file_exists( $template_to_load ) ) {
                return $template_to_load;
            }
        } else {
            status_header( 404 );
            return get_404_template();
        }
    }

    // Handle specific link page slug requests
    $link_pages = get_posts( array(
        'name'           => $request_path,
        'post_type'      => 'artist_link_page',
        'post_status'    => 'publish',
        'numberposts'    => 1,
        'fields'         => 'ids'
    ) );

    if ( ! empty( $link_pages ) ) {
        $link_page_id = $link_pages[0];
        $link_page = get_post( $link_page_id );
        
        $wp_query->posts = array( $link_page );
        $wp_query->post_count = 1;
        $wp_query->found_posts = 1;
        $wp_query->max_num_pages = 1;
        $wp_query->is_single = true;
        $wp_query->is_singular = true;
        $wp_query->is_404 = false;
        $wp_query->query_vars['name'] = $request_path;
        $wp_query->query_vars['post_type'] = 'artist_link_page';
        $wp_query->queried_object_id = $link_page_id;
        $wp_query->queried_object = $link_page;

        $template_to_load = EXTRACHILL_ARTIST_PLATFORM_PLUGIN_DIR . 'inc/link-pages/live/templates/single-artist_link_page.php';
        if ( file_exists( $template_to_load ) ) {
            return $template_to_load;
        }
    } else {
        // No link page found - redirect to root
        if ( ! headers_sent() ) {
            wp_redirect( esc_url_raw( 'https://extrachill.link/' ), 301 );
            exit;
        }
    }

    return $template;
}

/**
 * Handle template routing for artist profiles on artist.extrachill.com
 *
 * Provides top-level /{slug} URLs for artist profiles when NOT on extrachill.link domain.
 * Domain-aware routing prevents conflicts with link page routing on extrachill.link.
 *
 * @param string $template The template WordPress wants to load
 * @return string The template to actually load
 */
function extrachill_handle_artist_profile_routing( $template ) {
    $current_host = strtolower( $_SERVER['HTTP_HOST'] ?? '' );

    // Only handle requests that are NOT on extrachill.link
    if ( stripos( $current_host, 'extrachill.link' ) !== false ) {
        return $template;
    }

    // Skip if already handling a specific query
    if ( is_admin() || is_post_type_archive() || is_search() ) {
        return $template;
    }

    global $wp_query;
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $request_path = trim( parse_url( $request_uri, PHP_URL_PATH ), '/' );

    // Skip empty paths and known WordPress paths
    if ( empty( $request_path ) ||
         in_array( $request_path, ['wp-login', 'wp-admin', 'admin', 'artists'], true ) ) {
        return $template;
    }

    // Query for artist profile by slug
    $artist_profiles = get_posts( array(
        'name'           => $request_path,
        'post_type'      => 'artist_profile',
        'post_status'    => 'publish',
        'numberposts'    => 1,
        'fields'         => 'ids',
    ) );

    if ( ! empty( $artist_profiles ) ) {
        $artist_id = $artist_profiles[0];
        $artist_post = get_post( $artist_id );

        // Set up wp_query for artist profile
        $wp_query->posts = array( $artist_post );
        $wp_query->post_count = 1;
        $wp_query->found_posts = 1;
        $wp_query->max_num_pages = 1;
        $wp_query->is_single = true;
        $wp_query->is_singular = true;
        $wp_query->is_404 = false;
        $wp_query->query_vars['name'] = $request_path;
        $wp_query->query_vars['post_type'] = 'artist_profile';
        $wp_query->queried_object_id = $artist_id;
        $wp_query->queried_object = $artist_post;

        // Load artist profile template
        return locate_template( array( 'single-artist_profile.php', 'single.php' ) );
    }

    return $template;
}

/**
 * Initialize all rewrite rules and routing
 */
function extrachill_init_rewrite_rules() {
    extrachill_add_rewrite_rules();
}

/**
 * Redirect direct CPT access to extrachill.link domain
 * 
 * Redirects direct access to 'artist_link_page' CPT posts (via their WordPress permalinks)
 * to their canonical URL on the extrachill.link domain.
 */
function extrachill_redirect_artist_link_page_cpt_to_custom_domain() {
    $is_dev_mode = ( defined( 'EXTRCH_LINKPAGE_DEV' ) && EXTRCH_LINKPAGE_DEV );
    $is_extrachill_link_host = ( strpos( strtolower( $_SERVER['HTTP_HOST'] ?? '' ), 'extrachill.link' ) !== false );

    if ( is_singular( 'artist_link_page' ) ) {
        $current_link_page_post = get_queried_object();
        if ( $current_link_page_post && $current_link_page_post->ID ) {
            $link_page_id = $current_link_page_post->ID;

            // Use centralized data source for redirect settings (single source of truth)
            $artist_id = apply_filters('ec_get_artist_id', $link_page_id);
            $data = ec_get_link_page_data( $artist_id, $link_page_id );
            $temp_redirect_enabled = $data['settings']['redirect_enabled'] ?? false;
            $target_redirect_url = $data['settings']['redirect_target_url'] ?? '';
            
            if ( $temp_redirect_enabled ) {
                if ( ! empty( $target_redirect_url ) && filter_var( $target_redirect_url, FILTER_VALIDATE_URL ) ) {
                    if ( ! headers_sent() ) {
                        wp_redirect( esc_url_raw( $target_redirect_url ), 302 );
                        exit;
                    }
                }
            }
            if ( ! $is_dev_mode && ! $is_extrachill_link_host ) {
                // Use already retrieved artist_id (avoid duplicate query)
                if ( $artist_id ) {
                    $artist_profile_post = get_post( $artist_id );
                    if ( $artist_profile_post && ! empty( $artist_profile_post->post_name ) ) {
                        $artist_slug = $artist_profile_post->post_name;
                        $target_url_path = '/' . $artist_slug . '/';
                        $target_url = 'https://extrachill.link' . $target_url_path;
                        if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
                            $target_url .= '?' . $_SERVER['QUERY_STRING'];
                        }
                        if ( ! headers_sent() ) {
                            wp_safe_redirect( esc_url_raw( $target_url ), 301 );
                            exit;
                        }
                    }
                }
            }
        }
    }
}

/**
 * Redirects direct forum access to associated artist profile page.
 * Ensures artist profile is the single source of truth for forum content.
 */
function extrachill_redirect_artist_forum_to_profile() {
    // Only redirect on single forum pages
    if (!function_exists('bbp_is_single_forum') || !bbp_is_single_forum()) {
        return;
    }
    
    $forum_id = bbp_get_forum_id();
    if (empty($forum_id)) {
        return;
    }
    
    // Check if this is an artist forum
    $is_artist_forum = get_post_meta($forum_id, '_is_artist_profile_forum', true);
    if (!$is_artist_forum) {
        return;
    }
    
    // Get associated artist profile ID
    $artist_profile_id = apply_filters('ec_get_artist_id', $forum_id);
    if (empty($artist_profile_id)) {
        return;
    }
    
    // Validate artist profile exists and is published
    $artist_post = get_post($artist_profile_id);
    if (!$artist_post || $artist_post->post_status !== 'publish') {
        return;
    }
    
    // Redirect to artist profile (301 for SEO)
    $artist_url = get_permalink($artist_profile_id);
    if ($artist_url) {
        wp_redirect(esc_url_raw($artist_url), 301);
        exit;
    }
}



// Hook into WordPress
add_action( 'init', 'extrachill_init_rewrite_rules', 25 );
add_filter( 'query_vars', 'extrachill_add_query_vars' );
add_filter( 'redirect_canonical', 'extrachill_prevent_canonical_redirect_for_link_domain', 10, 2 );
add_filter( 'template_include', 'extrachill_handle_artist_profile_routing', 5 );
add_filter( 'template_include', 'extrachill_handle_link_domain_routing' );
add_action( 'template_redirect', 'extrachill_redirect_artist_link_page_cpt_to_custom_domain' );
add_action( 'template_redirect', 'extrachill_redirect_artist_forum_to_profile', 10 );
