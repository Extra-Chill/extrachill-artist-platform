<?php
/**
 * Dev-only PHPStan stubs for cross-boundary APIs this plugin calls but does not own.
 *
 * The Extra Chill theme and the external Extra Chill Link Pages runtime provide
 * these functions at runtime. They are declared here so static analysis can
 * resolve their signatures; this file is never loaded at runtime.
 */

/**
 * Queue an admin/front-end notice via the theme notice system.
 *
 * @param string              $message Notice text.
 * @param string              $type    Notice type slug (info|success|warning|error).
 * @param array<string,mixed> $args    Optional notice arguments.
 * @return void
 */
function extrachill_set_notice( string $message, string $type = 'info', array $args = array() ): void {}

/**
 * Render the theme breadcrumb trail.
 *
 * @return void
 */
function extrachill_breadcrumbs(): void {}

/**
 * Render pagination for a query or prebuilt pagination data.
 *
 * @param mixed  $query_or_data WP_Query instance, pagination data array, or null.
 * @param string $context       Pagination render context.
 * @param string $item_label    Label describing the paginated item.
 * @return void
 */
function extrachill_pagination( $query_or_data = null, $context = 'default', $item_label = 'post' ): void {}

/**
 * Prepare a link page public projection for rendering (external runtime API).
 *
 * @param mixed $projection Public projection data.
 * @param mixed $data       Link page persistence data.
 * @return mixed Prepared projection, or WP_Error on failure.
 */
function ec_prepare_link_page_public_render( $projection, $data ) {}

/**
 * Render the public link page head markup (external runtime API).
 *
 * @param mixed $link_page_id Link page post ID.
 * @param mixed $data         Link page persistence data.
 * @param mixed $projection   Prepared public projection.
 * @return void
 */
function ec_render_link_page_public_head( $link_page_id, $data, $projection ): void {}
