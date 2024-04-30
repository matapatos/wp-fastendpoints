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

$router = new Router('my-actions', 'v2');
$router->appendSchemaDir(\SCHEMAS_DIR);

$triggerErrorActionMiddleware = function (\WP_REST_Request $request) {
    $action = $request->get_param('action');
    if ($action !== 'error') {
        return true;
    }

    return new WpError(469, 'Triggered error action');
};

// Triggers middleware
$router->get('/middleware/(?P<action>\w+)', function (\WP_REST_Request $request): bool {
    return true;
})
    ->middleware($triggerErrorActionMiddleware);

$triggerPermissionCallback = function (\WP_REST_Request $request) {
    $action = $request->get_param('action');
    if ($action !== 'allowed') {
        return new WpError(403, 'Failed permission callback check');
    }

    return true;
};

// Triggers permission callable
$router->get('/permission/(?P<action>\w+)', function (\WP_REST_Request $request): bool {
    return true;
})
    ->permission($triggerPermissionCallback);
