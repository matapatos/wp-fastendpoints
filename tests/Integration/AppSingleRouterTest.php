<?php

/**
 * Holds tests for registering a single FastEndpoints router
 *
 * @since 1.0.0
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Tests\Integration;

use Wp\FastEndpoints\Tests\Helpers\Helpers;
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
    $router = Helpers::getRouter('PostsRouter.php');
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
            '/my-posts/v1',
            '/my-posts/v1/(?P<ID>[\\d]+)',
        ])
        ->and($routes['/my-posts/v1/(?P<ID>[\\d]+)'])
        ->toBeArray()
        ->toHaveCount(3);
})->group('single');

test('Retrieving a post by id', function () {
    $userId = $this::factory()->user->create();
    $postId = $this::factory()->post->create(['post_author' => $userId]);
    wp_set_current_user($userId);
    $response = $this->server->dispatch(
        new \WP_REST_Request('GET', "/my-posts/v1/{$postId}")
    );
    expect($response->get_status())->toBe(200);
    $data = $response->get_data();
    expect($data)
        ->toHaveProperty('ID', $postId)
        ->toHaveProperty('post_author', $userId);
})->group('single');

test('Trying to retrieve a post without permissions', function () {
    $postId = $this::factory()->post->create();
    $response = $this->server->dispatch(
        new \WP_REST_Request('GET', "/my-posts/v1/{$postId}")
    );
    $data = (object) $response->get_data();
    expect($response->get_status())->toBe(403)
        ->and($data)
        ->toHaveProperty('code', 403)
        ->toHaveProperty('message', 'Not enough permissions')
        ->toHaveProperty('data', ['status' => 403]);
})->group('single');

test('Updating a post', function () {
    $userId = $this::factory()->user->create();
    $user = get_user_by('id', $userId);
    $user->add_cap('edit_published_posts');
    $postId = $this::factory()->post->create(['post_author' => $userId]);
    wp_set_current_user($userId);
    $request = new \WP_REST_Request('POST', "/my-posts/v1/{$postId}");
    $request->set_header('content-type', 'application/json');
    $request->set_param('post_title', 'My testing message');
    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(200);
    $data = $response->get_data();
    expect($data)
        ->toHaveProperty('ID', $postId)
        ->toHaveProperty('post_title', 'My testing message');
})->group('single');

test('Trying to update a post without permissions', function () {
    $allUserIds = $this::factory()->user->create_many(2);
    $firstUser = get_user_by('id', $allUserIds[0]);
    $firstUser->add_cap('edit_published_posts');
    $postId = $this::factory()->post->create(['post_author' => $allUserIds[1]]);
    wp_set_current_user($allUserIds[1]);
    $request = new \WP_REST_Request('POST', "/my-posts/v1/{$postId}");
    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(403);
    $data = (object) $response->get_data();
    expect($data)
        ->toHaveProperty('code', 403)
        ->toHaveProperty('message', 'Not enough permissions')
        ->toHaveProperty('data', ['status' => 403]);
})->group('single');

test('Deleting a post', function () {
    $userId = $this::factory()->user->create();
    $user = get_user_by('id', $userId);
    $user->add_cap('delete_published_posts');
    $postId = $this::factory()->post->create(['post_author' => $userId]);
    wp_set_current_user($userId);
    $request = new \WP_REST_Request('DELETE', "/my-posts/v1/{$postId}");
    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(200);
    $data = $response->get_data();
    expect($data)->toBe('Post deleted with success');
})->group('single');

test('Trying to delete a post without permissions', function () {
    $allUserIds = $this::factory()->user->create_many(2);
    $user = get_user_by('id', $allUserIds[0]);
    $user->add_cap('delete_published_posts');
    $postId = $this::factory()->post->create(['post_author' => $allUserIds[1]]);
    wp_set_current_user($allUserIds[0]);
    $request = new \WP_REST_Request('DELETE', "/my-posts/v1/{$postId}");
    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(403);
    $data = (object) $response->get_data();
    expect($data)
        ->toHaveProperty('code', 403)
        ->toHaveProperty('message', 'Not enough permissions')
        ->toHaveProperty('data', ['status' => 403]);
})->group('single');
