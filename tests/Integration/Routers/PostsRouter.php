<?php

/**
 * Holds an example of FastEndpoints router
 *
 * @since 1.0.0
 *
 * @license MIT
 */

declare(strict_types=1);

use Wp\FastEndpoints\Helpers\WpError;
use Wp\FastEndpoints\Router;

$router = new Router('my-posts', 'v1');
$router->appendSchemaDir(\SCHEMAS_DIR);

// Fetch post a single post
$router->get('(?P<post_id>[\d]+)', function (\WP_REST_Request $request) {
    $postId = $request->get_param('post_id');

    return get_post($postId);
})
    ->returns('Posts/Get')
    ->hasCap('read');

// Updating a post
$router->post('(?P<post_id>[\d]+)', function (\WP_REST_Request $request) {
    $payload = $request->get_params();
    $payload['ID'] = $request->get_param('post_id');

    $postId = wp_update_post($payload);
    if (is_wp_error($postId)) {
        return $postId;
    }

    return get_post($postId);
})
    ->schema('Posts/Update')
    ->hasCap('read');

// Deleting a post
$router->delete('(?P<post_id>[\d]+)', function (\WP_REST_Request $request) {
    $result = wp_delete_post($request->get_param('post_id'));
    if ($result === false or $result === null) {
        return new WpError(500, 'Unable to delete post');
    }

    return esc_html__('Post deleted with success');
})
    ->hasCap('read');
