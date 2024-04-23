<?php

/**
 * Holds tests for the Response class.
 *
 * @since 0.9.0
 *
 * @package wp-fastendpoints
 * @license MIT
 */

declare(strict_types=1);

namespace Tests\Wp\FastEndpoints\Unit\Schemas;

use Exception;
use Illuminate\Support\Facades\Route;
use Mockery;
use org\bovigo\vfs\vfsStream;
use Tests\Wp\FastEndpoints\Helpers\Helpers;
use TypeError;
use Wp\FastEndpoints\Endpoint;
use Wp\FastEndpoints\Router;
use Brain\Monkey;
use Brain\Monkey\Functions;

beforeEach(function () {
    Monkey\setUp();
});

afterEach(function () {
    Monkey\tearDown();
    Mockery::close();
    vfsStream::setup();
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
})->group('constructor');

test('Creating a Router instance with invalid parameters', function ($api, $version) {
    expect(function () use ($api, $version) {
        new Router($api, $version);
    })->toThrow(TypeError::class);
})->with([
    ['', []],
    [[], ''],
    [1, ''],
    ['', 1],
])->group('constructor');

// REST endpoints

test('Create a REST endpoint', function (string $method, string $api, string $version, string $route, ?array $args = [], $override = false) {
    $router = new Router($api, $version);
    $endpoint = $router->{strtolower($method)}($route, function ($request) {
        return "endpoint-success";
    }, $args, $override);
    expect($endpoint)->toBeInstanceOf(Endpoint::class)
        ->and(Helpers::getNonPublicClassProperty($endpoint, 'method'))->toBe($method)
        ->and(Helpers::getNonPublicClassProperty($endpoint, 'route'))->toBe($route)
        ->and(Helpers::getNonPublicClassProperty($endpoint, 'handler')(null))->toBe("endpoint-success")
        ->and(Helpers::getNonPublicClassProperty($endpoint, 'args'))->toBe($args)
        ->and(Helpers::getNonPublicClassProperty($endpoint, 'override'))->toBe($override)
        ->and(Helpers::getNonPublicClassProperty($router, 'endpoints'))->toMatchArray([$endpoint]);
})->with('http_methods')->with([
    ['my-api2', 'v2', 'my-custom-route2'],
    ['my-api3', 'v3', '/my-custom-route3', ['my-custom-arg' => 'hello'], true],
    ['my-api4', '/v4', 'my-custom-route4'],
    ['my-api5', '/v5', '/my-custom-route5'],
])->group('router');

// Sub routers

test('Include sub-routers', function () {
    $mainRouter = new Router('my-api', 'v1');
    $readyzEndpoint = $mainRouter->get('readyz', function ($request) {
        return "I am ready";
    });
    $usersRouter = new Router('users');
    $user123Endpoint = $usersRouter->get('123', function ($request) {
        return "User id: 123";
    });
    $currentUserEndpoint = $usersRouter->get('current-user', function ($request) {
        return "Current user";
    });
    $mainRouter->includeRouter($usersRouter);
    expect(Helpers::getNonPublicClassProperty($mainRouter, 'endpoints'))->toMatchArray([$readyzEndpoint])
        ->and(Helpers::getNonPublicClassProperty($usersRouter, 'endpoints'))->toMatchArray([$user123Endpoint, $currentUserEndpoint])
        ->and(Helpers::getNonPublicClassProperty($mainRouter, 'subRouters'))->toMatchArray([$usersRouter]);
})->group('includeRouter');

// Router namespace

// TODO: Check if apply filter has been called
test('Get router namespace', function (string $api, string $version) {
    $apiNamespace = 'my-api/v3';
    $router = new Router($api, $version);
    expect(Helpers::invokeNonPublicClassMethod($router, 'getNamespace'))->toBe($apiNamespace);
    $subRouter = new Router('users', 'v87');
    expect(Helpers::invokeNonPublicClassMethod($subRouter, 'getNamespace'))->toBe('users/v87');
    $router->includeRouter($subRouter);
    expect(Helpers::invokeNonPublicClassMethod($subRouter, 'getNamespace'))->toBe($apiNamespace);
    $subSubRouter = new Router('myself', 'v100');
    expect(Helpers::invokeNonPublicClassMethod($subSubRouter, 'getNamespace'))->toBe('myself/v100');
    $subRouter->includeRouter($subSubRouter);
    expect(Helpers::invokeNonPublicClassMethod($subSubRouter, 'getNamespace'))->toBe($apiNamespace);
})->with([
    ['my-api', 'v3'],
    ['my-api/', 'v3/'],
    ['/my-api/', '/v3'],
    ['/my-api/', '/v3/'],
])->group('getNamespace');

// Router REST path

// TODO: Check if apply filter has been called
test('Get router REST path', function (string $api, string $version) {
    $router = new Router($api, $version);
    expect(Helpers::invokeNonPublicClassMethod($router, 'getRestBase'))->toBe('');
    $subRouter = new Router('users', 'v87');
    expect(Helpers::invokeNonPublicClassMethod($subRouter, 'getRestBase'))->toBe('');
    $router->includeRouter($subRouter);
    expect(Helpers::invokeNonPublicClassMethod($subRouter, 'getRestBase'))->toBe('users/v87');
    $subSubRouter = new Router('myself', 'v100');
    expect(Helpers::invokeNonPublicClassMethod($subSubRouter, 'getRestBase'))->toBe('');
    $subRouter->includeRouter($subSubRouter);
    expect(Helpers::invokeNonPublicClassMethod($subSubRouter, 'getRestBase'))->toBe('myself/v100');
})->with([
    ['my-api', 'v3'],
    ['my-api/', 'v3/'],
    ['/my-api/', '/v3'],
    ['/my-api/', '/v3/'],
])->group('router', 'includeRouter');

// Schema dirs

test('Append invalid schema directories', function () {
    $invalidDir=true;
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_html')->returnArg();
    Functions\expect('wp_die')->andThrow(new Exception());
    $router = new Router('custom-api', 'v1');
    expect(Helpers::getNonPublicClassProperty($router, 'schemaDirs'))->toBe([])
        ->and(function () use ($invalidDir, $router) {
            $router->appendSchemaDir($invalidDir);
        })->toThrow(Exception::class)
        ->and(Helpers::getNonPublicClassProperty($router, 'schemaDirs'))->toBe([]);
})->with([true, false, null, '', [true], '/invalid'])->group('appendSchemaDir')->group('appendSchemaDir');

test('Append schema directories', function ($dir) {
    $router = new Router('custom-api', 'v1');
    expect(Helpers::getNonPublicClassProperty($router, 'schemaDirs'))->toBe([]);
    $router->appendSchemaDir($dir);
    expect(Helpers::getNonPublicClassProperty($router, 'schemaDirs'))->toBe([$dir]);
})->with([dirname(__FILE__), dirname(__FILE__) . '/../Schemas'])->group('appendSchemaDir');

// Register endpoints

// test('Append schema directories', function ($dir) {
//     $router = new Router('custom-api', 'v1');
//     expect(Helpers::getNonPublicClassProperty($router, 'schemaDirs'))->toBe([]);
//     $router->appendSchemaDir($dir);
//     expect(Helpers::getNonPublicClassProperty($router, 'schemaDirs'))->toBe([$dir]);
// })->with([dirname(__FILE__), dirname(__FILE__) . '/../Schemas'])->group('registerEndpoints');
