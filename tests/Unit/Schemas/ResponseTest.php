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
use TypeError;
use Mockery;
use org\bovigo\vfs\vfsStream;
use Illuminate\Support\Str;
use Opis\JsonSchema\Helper;

use Tests\Wp\FastEndpoints\Helpers\Helpers;
use Tests\Wp\FastEndpoints\Helpers\FileSystemCache;
use Tests\Wp\FastEndpoints\Helpers\Faker;

use Wp\FastEndpoints\Schemas\Response;

abstract class LoadSchema
{
    const FromFile = 0;
    const FromArray = 1;
}

afterEach(function () {
    Mockery::close();
    vfsStream::setup();
});

// Constructor

test('Creating Response instance with $schema as a string', function () {
    expect(new Response('User/Get'))->toBeInstanceOf(Response::class);
});

test('Creating Response instance with $schema as an array', function () {
    expect(new Response([]))->toBeInstanceOf(Response::class);
});

test('Creating Response instance with an invalid $schema type', function ($value) {
    expect(fn() => new Response($value))->toThrow(TypeError::class);
})->with([1, 1.67, true, false]);

// getSuffix()

test('Checking correct Response suffix', function () {
    $response = new Response([]);
    $suffix = Helpers::invokeNonPublicClassMethod($response, 'getSuffix');
    expect($suffix)->toBe('response');
});

// appendSchemaDir()

test('Passing invalid schema directories to appendSchemaDir()', function (...$invalidDirectories) {
    $response = new Response([]);
    expect(fn() => Helpers::invokeNonPublicClassMethod($response, 'appendSchemaDir', $invalidDirectories))->toThrow(TypeError::class);
})->with([
    125, 62.5, 'fakedirectory', 'fake/dir/ups', '',
    ['', ''], ['fake', '/fake/ups'], [1,2],
]);

test('Passing both valid and invalid schema directories to appendSchemaDir()', function (...$invalidDirectories) {
    $response = new Response([]);
    $cache = new FileSystemCache();
    $invalidDirectories[0] = $cache->touchDirectory($invalidDirectories[0]);

    expect(fn() => Helpers::invokeNonPublicClassMethod($response, 'appendSchemaDir', $invalidDirectories))->toThrow(TypeError::class);
})->with([
    ['valid', 'invalid'], ['fake', 'fake/ups'], ['yup', 'true', 'yes'],
]);

test('Passing a valid schema directories to appendSchemaDir()', function (...$validDirectories) {
    $cache = new FileSystemCache();
    $validDirectories = $cache->touchDirectories($validDirectories);

    $response = new Response([]);
    $schemaDirs = Helpers::getNonPublicClassProperty($response, 'schemaDirs');
    expect($schemaDirs)
        ->toBeArray()
        ->toBeEmpty();
    Helpers::invokeNonPublicClassMethod($response, 'appendSchemaDir', $validDirectories);
    $schemaDirs = Helpers::getNonPublicClassProperty($response, 'schemaDirs');
    expect($schemaDirs)
        ->toBeArray()
        ->toHaveCount(count($validDirectories))
        ->toEqual($validDirectories);
})->with([
    'Schemas', 'Others/Schemas', 'Random/Another/Schemas',
    ['Hey', 'Dude'], ['Great/Man', 'Yes/ItWorks'],
]);

// getValidSchemaFilepath()

test('Trying to retrieve a json schema filepath without providing a filename', function () {
    $response = new Response([]);
    expect(fn() => Helpers::invokeNonPublicClassMethod($response, 'getValidSchemaFilepath'))
        ->toThrow(Exception::class);
});

test('Trying to retrieve a json schema filepath of a file that doesn\'t exists', function () {
    $response = new Response('random.json');
    expect(fn() => Helpers::invokeNonPublicClassMethod($response, 'getValidSchemaFilepath'))
        ->toThrow(Exception::class);
});

test('Retrieving a json schema filepath when providing a full filepath', function () {
    $cache = new FileSystemCache();
    $schemaFullpath = $cache->store('schema.json', '{}');
    $response = new Response($schemaFullpath);
    expect(Helpers::invokeNonPublicClassMethod($response, 'getValidSchemaFilepath'))
        ->toBe($schemaFullpath);
});

test('Retrieving a json schema filepath when providing a relative filepath', function (string $schemaRelativePath) {
    $cache = new FileSystemCache();
    $schemaFullpath = $cache->store(Str::finish($schemaRelativePath, '.json'), '{}');
    $response = new Response($schemaRelativePath);
    Helpers::setNonPublicClassProperty($response, 'schemaDirs', [$cache->getRootDir()]);
    expect(Helpers::invokeNonPublicClassMethod($response, 'getValidSchemaFilepath'))
        ->toBe($schemaFullpath);
})->with(['schema', 'schema.json']);

// returns()

test('returns() matches expected return value - Basic', function ($loadSchemaFrom, $value) {
    $schemaName = Str::ucfirst(Str::lower(gettype($value)));
    $schema = 'Basics/' . $schemaName;
    if ($loadSchemaFrom == LoadSchema::FromArray) {
        $schema = Helpers::loadSchema(\SCHEMAS_DIR . $schema);
    }
    $response = new Response($schema);
    $response->appendSchemaDir(\SCHEMAS_DIR);
    // Create WP_REST_Request mock
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_route')
        ->andReturn('value');
    // Validate response
    $data = $response->returns($req, $value);
    expect($data)->toEqual($value);
})->with([
    [LoadSchema::FromFile, 0.674],
    [LoadSchema::FromFile, 255],
    [LoadSchema::FromFile, true],
    [LoadSchema::FromFile, null],
    [LoadSchema::FromFile, "this is a string"],
    [LoadSchema::FromFile, [1,2,3,4,5]],
    [LoadSchema::FromFile, (object) [
        "stringVal" => "hello",
        "intVal"    => 1,
        "arrayVal"  => [1,2,3],
        "doubleVal" => 0.82,
        "boolVal"   => false,
    ]],
    [LoadSchema::FromArray, 0.674],
    [LoadSchema::FromArray, 255],
    [LoadSchema::FromArray, true],
    [LoadSchema::FromArray, null],
    [LoadSchema::FromArray, "this is a string"],
    [LoadSchema::FromArray, [1,2,3,4,5]],
    [LoadSchema::FromArray, (object) [
        "stringVal" => "hello",
        "intVal"    => 1,
        "arrayVal"  => [1,2,3],
        "doubleVal" => 0.82,
        "boolVal"   => false,
    ]],
]);

test('Ignoring additional properties in returns()', function ($loadSchemaFrom) {
    $schema = 'Users/Get';
    if ($loadSchemaFrom == LoadSchema::FromArray) {
        $schema = Helpers::loadSchema(\SCHEMAS_DIR . $schema);
    }
    $response = new Response($schema, true);
    $response->appendSchemaDir(\SCHEMAS_DIR);
    $user = Faker::getWpUser();
    // Create WP_REST_Request mock
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_route')
        ->andReturn('user');
    // Validate response
    $data = $response->returns($req, $user);
    expect($data)->toEqual(Helper::toJSON([
        "data" => [
            "user_email" => "fake@wpfastendpoints.com",
            "user_url" => "https://www.wpfastendpoints.com/wp",
            "display_name" => "André Gil",
        ]
    ]));
})->with([
    LoadSchema::FromFile,
    LoadSchema::FromArray,
]);

test('Keeps additional properties in returns()', function ($loadSchemaFrom) {
    $schema = 'Users/Get';
    if ($loadSchemaFrom == LoadSchema::FromArray) {
        $schema = Helpers::loadSchema(\SCHEMAS_DIR . $schema);
    }
    $response = new Response($schema, false);
    $response->appendSchemaDir(\SCHEMAS_DIR);
    $user = Faker::getWpUser();
    // Create WP_REST_Request mock
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_route')
        ->andReturn('user');
    // Validate response
    $data = $response->returns($req, $user);
    expect($data)->toEqual(Helper::toJSON($user));
})->with([
    LoadSchema::FromFile,
    LoadSchema::FromArray,
]);

test('Ignores additional properties expect a given type in returns()', function ($loadSchemaFrom, $type, $expectedData) {
    $schema = 'Users/Get';
    if ($loadSchemaFrom == LoadSchema::FromArray) {
        $schema = Helpers::loadSchema(\SCHEMAS_DIR . $schema);
    }
    $response = new Response($schema, $type);
    $response->appendSchemaDir(\SCHEMAS_DIR);
    $user = Faker::getWpUser();
    $user['is_admin'] = true;
    // Create WP_REST_Request mock
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_route')
        ->andReturn('user');
    // Validate response
    $data = $response->returns($req, $user);
    $expectedData = array_merge(["data" => [
        "user_email" => "fake@wpfastendpoints.com",
        "user_url" => "https://www.wpfastendpoints.com/wp",
        "display_name" => "André Gil",
    ]], $expectedData);
    expect($data)->toEqual(Helper::toJSON($expectedData));
})->with([
    [LoadSchema::FromFile, 'integer', ['ID' => 5]],
    [LoadSchema::FromFile, 'string', ['cap_key' => 'wp_capabilities', 'data' => Faker::getWpUser()['data']]],
    [LoadSchema::FromFile, 'number', ['ID' => 5]],
    [LoadSchema::FromFile, 'boolean', ['is_admin' => true]],
    [LoadSchema::FromFile, 'null', ['filter' => null]],
    [LoadSchema::FromFile, 'object', [
        'caps' => ['administrator' => true],
        "allcaps" => ['switch_themes' => true, 'edit_themes' => true, 'administrator' => true],
    ]],
    [LoadSchema::FromFile, 'array', ['roles' => ['administrator']]],
    [LoadSchema::FromArray, 'integer', ['ID' => 5]],
    [LoadSchema::FromArray, 'string', ['cap_key' => 'wp_capabilities', 'data' => Faker::getWpUser()['data']]],
    [LoadSchema::FromArray, 'number', ['ID' => 5]],
    [LoadSchema::FromArray, 'boolean', ['is_admin' => true]],
    [LoadSchema::FromArray, 'null', ['filter' => null]],
    [LoadSchema::FromArray, 'object', [
        'caps' => ['administrator' => true],
        "allcaps" => ['switch_themes' => true, 'edit_themes' => true, 'administrator' => true],
    ]],
    [LoadSchema::FromArray, 'array', ['roles' => ['administrator']]],
]);

test('Ignores additional properties specified by the schema', function ($loadSchemaFrom) {
    $schema = 'Users/WithAdditionalProperties';
    if ($loadSchemaFrom == LoadSchema::FromArray) {
        $schema = Helpers::loadSchema(\SCHEMAS_DIR . $schema);
    }
    $response = new Response($schema, null);
    $response->appendSchemaDir(\SCHEMAS_DIR);
    $user = Faker::getWpUser();
    // Create WP_REST_Request mock
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_route')
        ->andReturn('user');
    // Validate response
    $data = $response->returns($req, $user);
    expect($data)->toEqual(Helper::toJSON([
        "data" => [
            "user_email" => "fake@wpfastendpoints.com",
            "user_url" => "https://www.wpfastendpoints.com/wp",
            "display_name" => "André Gil",
        ],
        "cap_key" => "wp_capabilities",
    ]));
})->with([
    LoadSchema::FromFile,
    LoadSchema::FromArray,
]);
