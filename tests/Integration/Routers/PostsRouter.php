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

// Fetches a single post
$router->get('(?P<ID>[\d]+)', function (string $ID) {
    return get_post($ID);
})
    ->returns('Posts/Get')
    ->hasCap('read');

// Updates a post
$router->post('(?P<ID>[\d]+)', function (\WP_REST_Request $request, $ID) {
    $payload = $request->get_params();
    $error = wp_update_post($payload, true);

    return is_wp_error($error) ? $error : get_post($ID);
})
    ->schema('Posts/Update')
    ->returns('Posts/Get')
    ->hasCap('edit_post', '<ID>');

// Deletes a post
$router->delete('(?P<ID>[\d]+)', function (string $ID) {
    $result = wp_delete_post($ID);
    if ($result === false or $result === null) {
        return new WpError(500, 'Unable to delete post');
    }

    return esc_html__('Post deleted with success');
})
    ->hasCap('delete_post', '<ID>');

return $router;
