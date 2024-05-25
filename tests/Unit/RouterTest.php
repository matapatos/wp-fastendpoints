<?php

/**
 * Holds tests for the ResponseMiddleware class.
 *
 * @since 0.9.0
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Tests\Unit\Schemas;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Exception;
use Mockery;
use TypeError;
use Wp\FastEndpoints\Endpoint;
use Wp\FastEndpoints\Router;
use Wp\FastEndpoints\Tests\Helpers\Helpers;

beforeEach(function () {
    Monkey\setUp();
});

afterEach(function () {
    Monkey\tearDown();
});

dataset('http_methods', [
    'GET',
    'POST',
    'PUT',
    'PATCH',
    'DELETE',
]);

// Constructor

test('Creating Router instance', function () {
    $router = new Router();
    expect($router)->toBeInstanceOf(Router::class);
    $router = new Router('my-api', 'v45');
    expect($router)->toBeInstanceOf(Router::class);
})->group('router', 'constructor');

test('Creating a Router instance with invalid parameters', function ($api, $version) {
    expect(function () use ($api, $version) {
        new Router($api, $version);
    })->toThrow(TypeError::class);
})->with([
    ['', []],
    [[], ''],
    [1, ''],
    ['', 1],
])->group('router', 'constructor');

// REST endpoints

test('Create a REST endpoint', function (string $method, string $api, string $version, string $route, ?array $args = [], $override = false) {
    $router = new Router($api, $version);
    $endpoint = $router->{strtolower($method)}($route, function ($request) {
        return 'endpoint-success';
    }, $args, $override);
    expect($endpoint)->toBeInstanceOf(Endpoint::class)
        ->and(Helpers::getNonPublicClassProperty($endpoint, 'method'))->toBe($method)
        ->and(Helpers::getNonPublicClassProperty($endpoint, 'route'))->toBe($route)
        ->and(Helpers::getNonPublicClassProperty($endpoint, 'handler')(null))->toBe('endpoint-success')
        ->and(Helpers::getNonPublicClassProperty($endpoint, 'args'))->toBe($args)
        ->and(Helpers::getNonPublicClassProperty($endpoint, 'override'))->toBe($override)
        ->and(Helpers::getNonPublicClassProperty($router, 'endpoints'))->toMatchArray([$endpoint]);
})->with('http_methods')->with([
    ['my-api2', 'v2', 'my-custom-route2'],
    ['my-api3', 'v3', '/my-custom-route3', ['my-custom-arg' => 'hello'], true],
    ['my-api4', '/v4', 'my-custom-route4'],
    ['my-api5', '/v5', '/my-custom-route5'],
])->group('router', 'endpoints');

// Sub routers

test('Include sub-routers', function () {
    $mainRouter = new Router('my-api', 'v1');
    $readyzEndpoint = $mainRouter->get('readyz', function ($request) {
        return 'I am ready';
    });
    $usersRouter = new Router('users');
    $user123Endpoint = $usersRouter->get('123', function ($request) {
        return 'User id: 123';
    });
    $currentUserEndpoint = $usersRouter->get('current-user', function ($request) {
        return 'Current user';
    });
    $mainRouter->includeRouter($usersRouter);
    expect(Helpers::getNonPublicClassProperty($mainRouter, 'endpoints'))->toMatchArray([$readyzEndpoint])
        ->and(Helpers::getNonPublicClassProperty($usersRouter, 'endpoints'))->toMatchArray([$user123Endpoint, $currentUserEndpoint])
        ->and(Helpers::getNonPublicClassProperty($mainRouter, 'subRouters'))->toMatchArray([$usersRouter]);
})->group('router', 'includeRouter');

// Router namespace

test('Get router namespace', function (string $api, string $version) {
    $apiNamespace = 'my-api/v3';
    $router = new Router($api, $version);
    expect(Helpers::invokeNonPublicClassMethod($router, 'getNamespace'))->toBe($apiNamespace);
    $this->assertSame(Filters\applied('fastendpoints_router_namespace'), 1);
    $subRouter = new Router('users', 'v87');
    expect(Helpers::invokeNonPublicClassMethod($subRouter, 'getNamespace'))->toBe('users/v87');
    $this->assertSame(Filters\applied('fastendpoints_router_namespace'), 2);
    $router->includeRouter($subRouter);
    expect(Helpers::invokeNonPublicClassMethod($subRouter, 'getNamespace'))->toBe($apiNamespace);
    $this->assertSame(Filters\applied('fastendpoints_router_namespace'), 2);
    $subSubRouter = new Router('myself', 'v100');
    expect(Helpers::invokeNonPublicClassMethod($subSubRouter, 'getNamespace'))->toBe('myself/v100');
    $this->assertSame(Filters\applied('fastendpoints_router_namespace'), 3);
    $subRouter->includeRouter($subSubRouter);
    expect(Helpers::invokeNonPublicClassMethod($subSubRouter, 'getNamespace'))->toBe($apiNamespace);
    $this->assertSame(Filters\applied('fastendpoints_router_namespace'), 3);
})->with([
    ['my-api', 'v3'],
    ['my-api/', 'v3/'],
    ['/my-api/', '/v3'],
    ['/my-api/', '/v3/'],
])->group('router', 'getNamespace');

// Router REST path

test('Get router REST path', function (string $api, string $version) {
    $router = new Router($api, $version);
    expect(Helpers::invokeNonPublicClassMethod($router, 'getRestBase'))->toBe('');
    $this->assertSame(Filters\applied('fastendpoints_router_rest_base'), 0);
    $subRouter = new Router('users', 'v87');
    expect(Helpers::invokeNonPublicClassMethod($subRouter, 'getRestBase'))->toBe('');
    $this->assertSame(Filters\applied('fastendpoints_router_rest_base'), 0);
    $router->includeRouter($subRouter);
    expect(Helpers::invokeNonPublicClassMethod($subRouter, 'getRestBase'))->toBe('users/v87');
    $this->assertSame(Filters\applied('fastendpoints_router_rest_base'), 1);
    $subSubRouter = new Router('myself', 'v100');
    expect(Helpers::invokeNonPublicClassMethod($subSubRouter, 'getRestBase'))->toBe('');
    $this->assertSame(Filters\applied('fastendpoints_router_rest_base'), 1);
    $subRouter->includeRouter($subSubRouter);
    expect(Helpers::invokeNonPublicClassMethod($subSubRouter, 'getRestBase'))->toBe('myself/v100');
    $this->assertSame(Filters\applied('fastendpoints_router_rest_base'), 2);
})->with([
    ['my-api', 'v3'],
    ['my-api/', 'v3/'],
    ['/my-api/', '/v3'],
    ['/my-api/', '/v3/'],
])->group('router', 'getRestBase');

// SchemaMiddleware dirs

test('Append invalid schema directories', function ($invalidDir, $errorMessage) {
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_html')->returnArg();
    Functions\when('wp_die')->alias(function ($msg) {
        throw new Exception($msg);
    });
    $router = new Router('custom-api', 'v1');
    expect(Helpers::getNonPublicClassProperty($router, 'schemaDirs'))->toBe([])
        ->and(function () use ($invalidDir, $router) {
            $router->appendSchemaDir($invalidDir, 'https://www.wp-fastendpoints.com');
        })->toThrow(Exception::class, $errorMessage)
        ->and(Helpers::getNonPublicClassProperty($router, 'schemaDirs'))->toBe([]);
})->with([
    ['', 'Invalid schema directory'],
    ['/invalid', 'Invalid or not found schema directory: /invalid'],
    [__FILE__, 'Invalid or not found schema directory: '.__FILE__],
])->group('router', 'appendSchemaDir');

test('Append schema directories', function ($dir) {
    $router = new Router('custom-api', 'v1');
    expect(Helpers::getNonPublicClassProperty($router, 'schemaDirs'))->toBe([]);
    $router->appendSchemaDir($dir, 'https://www.wp-fastendpoints.com');
    expect(Helpers::getNonPublicClassProperty($router, 'schemaDirs'))->toBe(['https://www.wp-fastendpoints.com' => $dir]);
})->with([dirname(__FILE__), dirname(__FILE__).'/../Schemas'])->group('router', 'appendSchemaDir');

// Register endpoints

test('Register endpoints', function () {
    $endpointMock1 = Mockery::mock(Endpoint::class)
        ->expects()
        ->register('custom-api/v3', '')
        ->getMock();
    $endpointMock2 = Mockery::mock(Endpoint::class)
        ->expects()
        ->register('custom-api/v3', '')
        ->getMock();

    $router = new Router('custom-api', 'v3');
    Helpers::setNonPublicClassProperty($router, 'schemaDirs', ['fake-prefix' => 'tests-schema-dir']);
    Helpers::setNonPublicClassProperty($router, 'endpoints', [$endpointMock1, $endpointMock2]);
    expect(Helpers::getNonPublicClassProperty($router, 'registered'))->toBeFalse();
    $router->registerEndpoints();
    expect(Helpers::getNonPublicClassProperty($router, 'registered'))->toBeTrue();
})->group('router', 'registerEndpoints');

// Register router

test('Skipping registering a router via hook', function () {
    Filters\expectApplied('fastendpoints_is_to_register')
        ->once()
        ->with(true, Mockery::type(Router::class))
        ->andReturn(false);

    $router = new Router();
    $router->register();
    $this->assertSame(Filters\applied('fastendpoints_is_to_register'), 1);
    $this->assertSame(Actions\did('fastendpoints_before_register'), 0);
    $this->assertSame(Actions\did('fastendpoints_after_register'), 0);
    $this->assertFalse(has_action('rest_api_init', [$router, 'registerEndpoints']));
})->group('router', 'register');

test('Register router with invalid base namespace', function () {
    Functions\when('esc_html__')->returnArg();
    Functions\when('wp_die')->alias(function ($msg) {
        throw new Exception($msg);
    });
    $router = new Router('');
    expect(function () use ($router) {
        $router->register();
    })->toThrow(Exception::class, 'No api namespace specified in the parent router');

    $this->assertSame(Filters\applied('fastendpoints_is_to_register'), 1);
    $this->assertSame(Actions\did('fastendpoints_before_register'), 0);
    $this->assertSame(Actions\did('fastendpoints_after_register'), 0);
    $this->assertFalse(has_action('rest_api_init', [$router, 'registerEndpoints']));
})->group('router', 'register');

test('Register router with invalid version', function () {
    Functions\when('esc_html__')->returnArg();
    Functions\when('wp_die')->alias(function ($msg) {
        throw new Exception($msg);
    });
    $router = new Router();
    expect(function () use ($router) {
        $router->register();
    })->toThrow(Exception::class, 'No api version specified in the parent router');

    $this->assertSame(Filters\applied('fastendpoints_is_to_register'), 1);
    $this->assertSame(Actions\did('fastendpoints_before_register'), 0);
    $this->assertSame(Actions\did('fastendpoints_after_register'), 0);
    $this->assertFalse(has_action('rest_api_init', [$router, 'registerEndpoints']));
})->group('router', 'register');

test('Trying to register a sub-router first', function () {
    Functions\when('esc_html__')->returnArg();
    Functions\when('wp_die')->alias(function ($msg) {
        throw new Exception($msg);
    });
    $router = new Router('api', 'v1');
    $subRouter = new Router('sub-api');
    $router->includeRouter($subRouter);
    expect(function () use ($subRouter) {
        $subRouter->register();
    })->toThrow(Exception::class, 'You are trying to build a sub-router before building the parent router. \
					Call the build() function on the parent router only!');

    $this->assertSame(Filters\applied('fastendpoints_is_to_register'), 1);
    $this->assertSame(Actions\did('fastendpoints_before_register'), 0);
    $this->assertSame(Actions\did('fastendpoints_after_register'), 0);
    $this->assertFalse(has_action('rest_api_init', [$router, 'registerEndpoints']));
})->group('router', 'register');

test('Register single router', function () {
    Actions\expectAdded('rest_api_init')
        ->with('Wp\FastEndpoints\Router->registerEndpoints()')
        ->times(1);
    $router = new Router('api', 'v1');
    $router->register();

    $this->assertSame(Filters\applied('fastendpoints_is_to_register'), 1);
    $this->assertSame(Actions\did('fastendpoints_before_register'), 1);
    $this->assertSame(Actions\did('fastendpoints_after_register'), 1);
})->group('router', 'register');

test('Register router with sub-routers mocks', function () {
    Actions\expectAdded('rest_api_init')
        ->with('Wp\FastEndpoints\Router->registerEndpoints()')
        ->times(1);
    $router = new Router('api', 'v1');
    $subRouter1 = Mockery::mock(Router::class)
        ->expects()
        ->appendSchemaDir('/test-dir', 'fake-uri-prefix')
        ->getMock()
        ->expects()
        ->register()
        ->getMock();
    $subRouter2 = Mockery::mock(Router::class)
        ->expects()
        ->appendSchemaDir('/test-dir', 'fake-uri-prefix')
        ->getMock()
        ->expects()
        ->register()
        ->getMock();
    Helpers::setNonPublicClassProperty($router, 'subRouters', [$subRouter1, $subRouter2]);
    Helpers::setNonPublicClassProperty($router, 'schemaDirs', ['fake-uri-prefix' => '/test-dir']);
    $router->register();

    $this->assertSame(Filters\applied('fastendpoints_is_to_register'), 1);
    $this->assertSame(Actions\did('fastendpoints_before_register'), 1);
    $this->assertSame(Actions\did('fastendpoints_after_register'), 1);
})->group('router', 'register');

test('Register router with sub-routers', function () {
    Functions\when('esc_html__')->returnArg();
    Functions\when('wp_die')->alias(function ($msg) {
        throw new Exception($msg);
    });
    Actions\expectAdded('rest_api_init')
        ->with('Wp\FastEndpoints\Router->registerEndpoints()')
        ->times(3);
    $router = new Router('api', 'v1');
    $firstSubRouter = new Router('first');
    $secondSubRouter = new Router('second');
    $router->includeRouter($firstSubRouter);
    $router->includeRouter($secondSubRouter);
    $router->register();

    $this->assertSame(Filters\applied('fastendpoints_is_to_register'), 3);
    $this->assertSame(Actions\did('fastendpoints_before_register'), 1);
    $this->assertSame(Actions\did('fastendpoints_after_register'), 1);
})->group('router', 'register');
