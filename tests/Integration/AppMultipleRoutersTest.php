<?php

/**
 * Holds tests for registering multiple FastEndpoints router's
 *
 * @since 1.0.0
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Wp\FastEndpoints\Helpers\Helpers;
use Wp\FastEndpoints\Router;
use Yoast\WPTestUtils\WPIntegration\TestCase;

if (! Helpers::isIntegrationTest()) {
    return;
}

/*
 * We need to provide the base test class to every integration test.
 * This will enable us to use all the WordPress test goodies, such as
 * factories and proper test cleanup.
 */
uses(TestCase::class);

beforeEach(function () {
    parent::setUp();

    // Set up a REST server instance.
    global $wp_rest_server;

    $this->server = $wp_rest_server = new \WP_REST_Server();
    $postsRouter = Helpers::getRouter('PostsRouter.php');
    $actionsRouter = Helpers::getRouter('ActionsRouter.php');
    $router = new Router('my-api', 'v1');
    $router->includeRouter($postsRouter);
    $router->includeRouter($actionsRouter);
    $router->register();
    do_action('rest_api_init', $this->server);
});

afterEach(function () {
    global $wp_rest_server;
    $wp_rest_server = null;

    parent::tearDown();
});

test('REST API endpoints registered', function () {
    $routes = $this->server->get_routes();

    expect($routes)
        ->toBeArray()
        ->toHaveKeys([
            '/my-api/v1',
            '/my-api/v1/my-posts/v1/(?P<post_id>[\\d]+)',
            '/my-api/v1/my-actions/v2/middleware/on-request/(?P<action>\w+)',
            '/my-api/v1/my-actions/v2/middleware/on-response/(?P<action>\w+)',
            '/my-api/v1/my-actions/v2/permission/(?P<action>\w+)',
        ])
        ->and($routes['/my-api/v1/my-posts/v1/(?P<post_id>[\\d]+)'])
        ->toBeArray()
        ->toHaveCount(3)
        ->and($routes['/my-api/v1/my-actions/v2/middleware/on-request/(?P<action>\w+)'])
        ->toBeArray()
        ->toHaveCount(1)
        ->and($routes['/my-api/v1/my-actions/v2/middleware/on-response/(?P<action>\w+)'])
        ->toBeArray()
        ->toHaveCount(1)
        ->and($routes['/my-api/v1/my-actions/v2/permission/(?P<action>\w+)'])
        ->toBeArray()
        ->toHaveCount(1);
})->group('multiple');

test('Retrieving a post by id', function () {
    $userId = $this::factory()->user->create();
    $postId = $this::factory()->post->create(['post_author' => $userId]);
    wp_set_current_user($userId);
    $response = $this->server->dispatch(
        new \WP_REST_Request('GET', "/my-api/v1/my-posts/v1/{$postId}")
    );
    expect($response->get_status())->toBe(200);
})->group('multiple');

test('Updating a post', function () {
    $userId = $this::factory()->user->create();
    $postId = $this::factory()->post->create(['post_author' => $userId]);
    wp_set_current_user($userId);
    $request = new \WP_REST_Request('POST', "/my-api/v1/my-posts/v1/{$postId}");
    $request->set_header('content-type', 'application/json');
    $request->set_param('post_title', 'My testing message');
    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(200);
})->group('multiple');

test('Deleting a post', function () {
    $userId = $this::factory()->user->create();
    $postId = $this::factory()->post->create(['post_author' => $userId]);
    wp_set_current_user($userId);
    $request = new \WP_REST_Request('DELETE', "/my-api/v1/my-posts/v1/{$postId}");
    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(200);
})->group('multiple');

test('Trigger error in a middleware', function (string $middlewareType, string $errorMessage) {
    $request = new \WP_REST_Request('GET', "/my-api/v1/my-actions/v2/middleware/{$middlewareType}/error");
    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(469)
        ->and((object) $response->get_data())
        ->toHaveProperty('code', 469)
        ->toHaveProperty('message', $errorMessage)
        ->toHaveProperty('data', ['status' => 469]);
})->with([
    ['on-request', 'Triggered error action before handling request'],
    ['on-response', 'Triggered error action before sending response']])->group('multiple');

test('Trigger success in a middleware', function (string $middlewareType) {
    $request = new \WP_REST_Request('GET', "/my-api/v1/my-actions/v2/middleware/{$middlewareType}/success");
    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(200);
})->with(['on-request', 'on-response'])->group('multiple');

test('Trigger no permissions in permission callback', function () {
    $request = new \WP_REST_Request('GET', '/my-api/v1/my-actions/v2/permission/notAllowed');
    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(403)
        ->and((object) $response->get_data())
        ->toHaveProperty('code', 403)
        ->toHaveProperty('message', 'Failed permission callback check')
        ->toHaveProperty('data', ['status' => 403]);
})->group('multiple');

test('Trigger has permissions in permission callback', function () {
    $request = new \WP_REST_Request('GET', '/my-api/v1/my-actions/v2/permission/allowed');
    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(200);
})->group('multiple');
