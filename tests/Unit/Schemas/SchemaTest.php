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
use Opis\JsonSchema\Validator;
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
use Wp\FastEndpoints\Helpers\WpError;
use Wp\FastEndpoints\Schemas\Schema;

beforeEach(function () {
    Monkey\setUp();
});

afterEach(function () {
    Monkey\tearDown();
    Mockery::close();
    vfsStream::setup();
});

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
    Filters\expectApplied('schema_is_to_parse')
        ->once()
        ->with(true, $schema);
    Filters\expectApplied('schema_params')
        ->once()
        ->with($user, Mockery::type(\WP_REST_Request::class), $schema);
    Filters\expectApplied('schema_validator')
        ->once()
        ->with(Mockery::type(Validator::class), Mockery::type(\WP_REST_Request::class), $schema);
    Filters\expectApplied('schema_is_valid')
        ->once()
        ->with(true, Mockery::type(ValidationResult::class), Mockery::type(\WP_REST_Request::class), $schema);
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
    $req = Mockery::mock('WP_REST_Request')
        ->shouldReceive('get_params')
        ->andReturn($user)
        ->getMock();
    Filters\expectApplied('schema_is_to_parse')
        ->once()
        ->with(true, $schema);
    Filters\expectApplied('schema_params')
        ->once()
        ->with($user, Mockery::type(\WP_REST_Request::class), $schema);
    Filters\expectApplied('schema_validator')
        ->once()
        ->with(Mockery::type(Validator::class), Mockery::type(\WP_REST_Request::class), $schema);
    Filters\expectApplied('schema_is_valid')
        ->once()
        ->with(false, Mockery::type(ValidationResult::class), Mockery::type(\WP_REST_Request::class), $schema);
    $result = $schema->validate($req);
    expect($result)
        ->toBeInstanceOf(WpError::class)
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
    Filters\expectApplied('schema_is_to_parse')
        ->once()
        ->with(true, $schema);
    Filters\expectApplied('schema_params')
        ->once()
        ->with($user, Mockery::type(\WP_REST_Request::class), $schema);
    Filters\expectApplied('schema_validator')
        ->once()
        ->with(Mockery::type(Validator::class), Mockery::type(\WP_REST_Request::class), $schema);
    $result = $schema->validate($req);
    expect($result)
        ->toBeInstanceOf(WpError::class)
        ->toHaveProperty('code', 500)
        ->toHaveProperty('message', 'Invalid request route schema type contains invalid json type: invalid')
        ->toHaveProperty('data', ['status' => 500]);
    $this->assertEquals(Filters\applied('schema_is_valid'), 0);
})->group('schema', 'validate');

test('Skip parsing schema', function () {
    $schema = new Schema(['test']);
    $req = Mockery::mock('WP_REST_Request');
    Filters\expectApplied('schema_is_to_parse')
        ->once()
        ->with(true, $schema)
        ->andReturn(false);
    $result = $schema->validate($req);
    expect($result)->toBeTrue();
    $this->assertEquals(Filters\applied('schema_params'), 0);
    $this->assertEquals(Filters\applied('schema_validator'), 0);
    $this->assertEquals(Filters\applied('schema_is_valid'), 0);
})->group('schema', 'validate');

test('Always rejects requests when no schema content is defined', function ($value) {
    Functions\when('esc_html__')->returnArg();
    $mockedSchema = Mockery::mock(Schema::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('getContents')
        ->andReturn($value)
        ->getMock();

    Helpers::setNonPublicClassProperty($mockedSchema, 'suffix', 'schema');
    $req = Mockery::mock('WP_REST_Request');
    Filters\expectApplied('schema_is_to_parse')
        ->once()
        ->with(true, $mockedSchema);
    $result = $mockedSchema->validate($req);
    expect($result)
        ->toBeInstanceOf(WpError::class)
        ->toHaveProperty('code', 422)
        ->toHaveProperty('message', 'Unprocessable request. Always fails')
        ->toHaveProperty('data', ['status' => 422]);
    $this->assertEquals(Filters\applied('schema_params'), 0);
    $this->assertEquals(Filters\applied('schema_validator'), 0);
    $this->assertEquals(Filters\applied('schema_is_valid'), 0);
})->with([false, null, [[]]])->group('schema', 'validate');
