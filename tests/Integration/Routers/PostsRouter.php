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

// Fetches a single post
$router->get('(?P<postId>[\d]+)', function (string $postId) {
    return get_post($postId);
})
    ->returns('Posts/Get')
    ->hasCap('read');

// Updates a post
$router->post('(?P<postId>[\d]+)', function (string $postId, \WP_REST_Request $request) {
    $payload = $request->get_params();
    $payload['ID'] = $postId;

    $postId = wp_update_post($payload);
    if (is_wp_error($postId)) {
        return $postId;
    }

    return get_post($postId);
})
    ->schema('Posts/Update')
    ->returns('Posts/Get')
    ->hasCap('edit_post', '{postId}');

// Deletes a post
$router->delete('(?P<postId>[\d]+)', function (string $postId) {
    $result = wp_delete_post($postId);
    if ($result === false or $result === null) {
        return new WpError(500, 'Unable to delete post');
    }

    return esc_html__('Post deleted with success');
})
    ->hasCap('delete_post', '{postId}');
