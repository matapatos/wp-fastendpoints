<?php

/**
 * Holds an example of FastEndpoints router
 *
 * @since 1.0.0
 *
 * @license MIT
 */

declare(strict_types=1);

use Tests\Wp\FastEndpoints\Integration\Routers\Middlewares\OnRequestErrorActionMiddleware;
use Tests\Wp\FastEndpoints\Integration\Routers\Middlewares\OnResponseErrorActionMiddleware;
use Wp\FastEndpoints\Helpers\WpError;
use Wp\FastEndpoints\Router;

$router = new Router('my-actions', 'v2');
$router->appendSchemaDir(\SCHEMAS_DIR);

// Triggers onRequest middleware
$router->get('/middleware/on-request/(?P<action>\w+)', function (\WP_REST_Request $request): bool {
    return true;
})
    ->middleware(new OnRequestErrorActionMiddleware());

// Triggers onResponse middleware
$router->get('/middleware/on-response/(?P<action>\w+)', function (\WP_REST_Request $request): bool {
    return true;
})
    ->middleware(new OnResponseErrorActionMiddleware());

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
