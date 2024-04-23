<?php

/**
 * Holds tests for the Schema class.
 *
 * @since 0.9.0
 *
 * @package wp-fastendpoints
 * @license MIT
 */

declare(strict_types=1);

namespace Tests\Wp\FastEndpoints\Unit\Schemas;

use Exception;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\ValidationResult;
use TypeError;
use Mockery;
use org\bovigo\vfs\vfsStream;
use Illuminate\Support\Str;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;

use Tests\Wp\FastEndpoints\Helpers\Helpers;
use Tests\Wp\FastEndpoints\Helpers\FileSystemCache;
use Tests\Wp\FastEndpoints\Helpers\LoadSchema;
use Wp\FastEndpoints\Schemas\Schema;

beforeEach(function () {
    Monkey\setUp();
});

afterEach(function () {
    Monkey\tearDown();
    Mockery::close();
    vfsStream::setup();
});

// getContents()

test('getContents retrieves correct schema', function ($loadSchemaFrom) {
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1 . '/' . $path2;
    });
    $schema = 'Users/Get';
    $expectedContents = Helpers::loadSchema(\SCHEMAS_DIR . $schema);
    if ($loadSchemaFrom == LoadSchema::FromArray) {
        $schema = $expectedContents;
    }
    $schema = new Schema($schema);
    Filters\expectApplied('schema_contents')
        ->with($expectedContents, $schema)
        ->once();
    $schema->appendSchemaDir(\SCHEMAS_DIR);
    $contents = $schema->getContents();
    expect($contents)->toEqual($expectedContents);
})->with([
    LoadSchema::FromFile,
    LoadSchema::FromArray,
])->group('schema', 'getContents');

test('Trying to read an invalid schema', function ($loadSchemaFrom) {
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1 . '/' . $path2;
    });
    $schema = 'Users/Get';
    $expectedContents = Helpers::loadSchema(\SCHEMAS_DIR . $schema);
    if ($loadSchemaFrom == LoadSchema::FromArray) {
        $schema = $expectedContents;
    }
    $schema = new Schema($schema);
    Filters\expectApplied('schema_contents')
        ->with($expectedContents, $schema)
        ->once();
    $schema->appendSchemaDir(\SCHEMAS_DIR);
    $contents = $schema->getContents();
    expect($contents)->toEqual($expectedContents);
})->with([
    LoadSchema::FromFile,
    LoadSchema::FromArray,
])->group('schema', 'getContents');

// validate()

test('validate valid parameters', function ($loadSchemaFrom) {
    $schema = 'Users/Get';
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1 . '/' . $path2;
    });
    $expectedContents = Helpers::loadSchema(\SCHEMAS_DIR . $schema);
    if ($loadSchemaFrom == LoadSchema::FromArray) {
        $schema = $expectedContents;
    }
    $schema = new Schema($schema);
    $schema->appendSchemaDir(\SCHEMAS_DIR);
    $user = [
        'data' => [
            'user_email' => 'fake@wp-fastendpoints.com',
            "user_url" => "https://www.wpfastendpoints.com/wp",
            "display_name" => "André Gil",
        ],
    ];
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_params')
        ->andReturn($user);
    $result = $schema->validate($req);
    expect($result)->toBeTrue();
})->with([
    LoadSchema::FromFile,
    LoadSchema::FromArray,
])->group('schema', 'validate');

test('validate invalid parameters', function ($loadSchemaFrom) {
    $schema = 'Users/Get';
    Functions\when('esc_html__')->returnArg();
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1 . '/' . $path2;
    });
    $expectedContents = Helpers::loadSchema(\SCHEMAS_DIR . $schema);
    if ($loadSchemaFrom == LoadSchema::FromArray) {
        $schema = $expectedContents;
    }
    $schema = new Schema($schema);
    $schema->appendSchemaDir(\SCHEMAS_DIR);
    $user = [
        'data' => [
            'user_email' => 'invalid-email',
        ],
    ];
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_params')
        ->andReturn($user);
    $result = $schema->validate($req);
    expect($result)
        ->toBeInstanceOf(\WP_Error::class)
        ->toHaveProperty('code', 422)
        ->toHaveProperty('message', 'Unprocessable request')
        ->toHaveProperty('data', ['status' => 422, '/data/user_email' => ['The data must match the \'email\' format']]);
})->with([
    LoadSchema::FromFile,
    LoadSchema::FromArray,
])->group('schema', 'validate');

test('validate invalid schema', function () {
    Functions\when('esc_html__')->returnArg();
    $schema = new Schema(["type" => "invalid"]);
    $schema->appendSchemaDir(\SCHEMAS_DIR);
    $user = [
        'data' => [
            'user_email' => 'invalid-email',
        ],
    ];
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_params')
        ->andReturn($user);
    $result = $schema->validate($req);
    expect($result)
        ->toBeInstanceOf(\WP_Error::class)
        ->toHaveProperty('code', 500)
        ->toHaveProperty('message', 'Invalid request route schema type contains invalid json type: invalid')
        ->toHaveProperty('data', ['status' => 500]);
})->group('schema', 'validate');
