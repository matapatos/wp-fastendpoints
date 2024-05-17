<?php

/**
 * Holds tests for the SchemaMiddleware class.
 *
 * @since 0.9.0
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Tests\Unit\Schemas;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use Opis\JsonSchema\ValidationResult;
use org\bovigo\vfs\vfsStream;
use Wp\FastEndpoints\Helpers\WpError;
use Wp\FastEndpoints\Schemas\SchemaMiddleware;
use Wp\FastEndpoints\Schemas\SchemaResolver;
use Wp\FastEndpoints\Tests\Helpers\Helpers;
use Wp\FastEndpoints\Tests\Helpers\LoadSchema;

beforeEach(function () {
    Monkey\setUp();
});

afterEach(function () {
    Monkey\tearDown();
    vfsStream::setup();
});

// onRequest()

test('validate valid parameters', function ($loadSchemaFrom) {
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1.'/'.$path2;
    });
    $schema = 'https://www.wp-fastendpoints.com/Users/Get.json';
    $expectedContents = Helpers::loadSchema(\SCHEMAS_DIR.'Users/Get');
    $schemaResolver = new SchemaResolver();
    $schemaResolver->registerPrefix('https://www.wp-fastendpoints.com', \SCHEMAS_DIR);
    if ($loadSchemaFrom == LoadSchema::FromArray) {
        $schemaResolver->unregisterPrefix('https://www.wp-fastendpoints.com');
        $schema = $expectedContents;
    }
    $schema = new SchemaMiddleware($schema, $schemaResolver);
    $user = [
        'data' => [
            'user_email' => 'fake@wp-fastendpoints.com',
            'user_url' => 'https://www.wpfastendpoints.com/wp',
            'display_name' => 'André Gil',
        ],
    ];
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_params')
        ->andReturn($user);
    Filters\expectApplied('fastendpoints_schema_is_to_parse')
        ->once()
        ->with(true, $schema);
    Filters\expectApplied('fastendpoints_schema_params')
        ->once()
        ->with($user, Mockery::type(\WP_REST_Request::class), $schema);
    Filters\expectApplied('fastendpoints_schema_is_valid')
        ->once()
        ->with(true, Mockery::type(ValidationResult::class), Mockery::type(\WP_REST_Request::class), $schema);
    $result = $schema->onRequest($req);
    expect($result)->toBeNull();
})->with([
    LoadSchema::FromFile,
    LoadSchema::FromArray,
])->group('schema', 'onRequest');

test('validate invalid parameters', function ($loadSchemaFrom) {
    Functions\when('esc_html__')->returnArg();
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1.'/'.$path2;
    });
    $schema = 'https://www.wp-fastendpoints.com/Users/Get.json';
    $schemaResolver = new SchemaResolver();
    $expectedContents = Helpers::loadSchema(\SCHEMAS_DIR.'Users/Get');
    $schemaResolver->registerPrefix('https://www.wp-fastendpoints.com', \SCHEMAS_DIR);
    if ($loadSchemaFrom == LoadSchema::FromArray) {
        $schemaResolver->unregisterPrefix('https://www.wp-fastendpoints.com');
        $schema = $expectedContents;
    }
    $schema = new SchemaMiddleware($schema, $schemaResolver);
    $user = [
        'data' => [
            'user_email' => 'invalid-email',
        ],
    ];
    $req = Mockery::mock('WP_REST_Request')
        ->shouldReceive('get_params')
        ->andReturn($user)
        ->getMock();
    Filters\expectApplied('fastendpoints_schema_is_to_parse')
        ->once()
        ->with(true, $schema);
    Filters\expectApplied('fastendpoints_schema_params')
        ->once()
        ->with($user, Mockery::type(\WP_REST_Request::class), $schema);
    Filters\expectApplied('fastendpoints_schema_is_valid')
        ->once()
        ->with(false, Mockery::type(ValidationResult::class), Mockery::type(\WP_REST_Request::class), $schema);
    $result = $schema->onRequest($req);
    expect($result)
        ->toBeInstanceOf(WpError::class)
        ->toHaveProperty('code', 422)
        ->toHaveProperty('message', 'Unprocessable request')
        ->toHaveProperty('data', ['status' => 422, '/data/user_email' => ['The data must match the \'email\' format']]);
})->with([
    LoadSchema::FromFile,
    LoadSchema::FromArray,
])->group('schema', 'onRequest');

test('validate invalid schema', function () {
    Functions\when('esc_html__')->returnArg();
    $schema = new SchemaMiddleware(['type' => 'invalid']);
    $user = [
        'data' => [
            'user_email' => 'invalid-email',
        ],
    ];
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_params')
        ->andReturn($user);
    Filters\expectApplied('fastendpoints_schema_is_to_parse')
        ->once()
        ->with(true, $schema);
    Filters\expectApplied('fastendpoints_schema_params')
        ->once()
        ->with($user, Mockery::type(\WP_REST_Request::class), $schema);
    $result = $schema->onRequest($req);
    expect($result)
        ->toBeInstanceOf(WpError::class)
        ->toHaveProperty('code', 500)
        ->toHaveProperty('message', 'Invalid request route schema type contains invalid json type: invalid')
        ->toHaveProperty('data', ['status' => 500]);
    $this->assertEquals(Filters\applied('schema_is_valid'), 0);
})->group('schema', 'onRequest');

test('Skip parsing schema', function () {
    $schema = new SchemaMiddleware(['test']);
    $req = Mockery::mock('WP_REST_Request');
    Filters\expectApplied('fastendpoints_schema_is_to_parse')
        ->once()
        ->with(true, $schema)
        ->andReturn(false);
    $result = $schema->onRequest($req);
    expect($result)->toBeNull();
    $this->assertEquals(Filters\applied('fastendpoints_schema_params'), 0);
    $this->assertEquals(Filters\applied('fastendpoints_schema_is_valid'), 0);
})->group('schema', 'validate');

test('Always rejects requests when no schema content is defined', function ($value) {
    Functions\when('esc_html__')->returnArg();
    $mockedSchema = Mockery::mock(SchemaMiddleware::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('getSchema')
        ->andReturn($value)
        ->getMock();

    Helpers::setNonPublicClassProperty($mockedSchema, 'suffix', 'fastendpoints_schema');
    $req = Mockery::mock('WP_REST_Request');
    Filters\expectApplied('fastendpoints_schema_is_to_parse')
        ->once()
        ->with(true, $mockedSchema);
    $result = $mockedSchema->onRequest($req);
    expect($result)
        ->toBeInstanceOf(WpError::class)
        ->toHaveProperty('code', 422)
        ->toHaveProperty('message', 'Unprocessable request. Always fails')
        ->toHaveProperty('data', ['status' => 422]);
    $this->assertEquals(Filters\applied('schema_params'), 0);
    $this->assertEquals(Filters\applied('schema_validator'), 0);
    $this->assertEquals(Filters\applied('schema_is_valid'), 0);
})->with([false, null, [[]]])->group('schema', 'onRequest');

// getSchema()

test('Retrieving correct schema as a string', function (string $schema) {
    $schemaResolver = new SchemaResolver();
    $schemaResolver->registerPrefix('http://www.example.com', '/fake-dir');
    $middleware = new SchemaMiddleware($schema, $schemaResolver);
    $expectedSchema = $schema;
    if (! str_ends_with($schema, '.json')) {
        $expectedSchema .= '.json';
    }
    if (filter_var($schema, FILTER_VALIDATE_URL) === false) {
        $expectedSchema = 'http://www.example.com/'.ltrim($expectedSchema, '/');
    }
    expect($middleware->getSchema())->toBe($expectedSchema);
})->with(['Posts/Get', 'Posts/Update.json', '/Posts/Update.json',
    'file://Posts/Update.json', 'http://www.localhost.com:8080/Posts/Update',
    'http://www.example.com/Users/Get.json'])->group('schema', 'getSchema');

test('Retrieving correct schema as array', function (array $schema) {
    $middleware = new SchemaMiddleware($schema);
    expect($middleware->getSchema())->toBe($schema);
})->with([[[]], [['hello', 'another']]])->group('schema', 'getSchema');
