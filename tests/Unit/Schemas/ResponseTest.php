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
use Brain\Monkey;
use Brain\Monkey\Functions;

use Tests\Wp\FastEndpoints\Helpers\Helpers;
use Tests\Wp\FastEndpoints\Helpers\FileSystemCache;
use Tests\Wp\FastEndpoints\Helpers\Faker;
use Tests\Wp\FastEndpoints\Helpers\LoadSchema;

use Wp\FastEndpoints\Schemas\Response;

beforeEach(function () {
    Monkey\setUp();
});

afterEach(function () {
    Monkey\tearDown();
    Mockery::close();
    vfsStream::setup();
});

// Constructor

test('Creating Response instance with $schema as a string', function () {
    expect(new Response('User/Get'))->toBeInstanceOf(Response::class);
})->group('constructor');

test('Creating Response instance with $schema as an array', function () {
    expect(new Response([]))->toBeInstanceOf(Response::class);
})->group('constructor');

test('Creating Response instance with an invalid $schema type', function ($value) {
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_html')->returnArg();
    expect(function () use ($value) {
        new Response($value);
    })->toThrow(TypeError::class);
})->with([1, 1.67, true, false])->group('constructor');

// getSuffix()

test('Checking correct Response suffix', function () {
    $response = new Response([]);
    $suffix = Helpers::invokeNonPublicClassMethod($response, 'getSuffix');
    expect($suffix)->toBe('response');
})->group('getSuffix');

// appendSchemaDir()

test('Passing invalid schema directories to appendSchemaDir()', function (...$invalidDirectories) {
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_html')->returnArg();
    $response = new Response([]);
    expect(function () use ($response, $invalidDirectories) {
        Helpers::invokeNonPublicClassMethod($response, 'appendSchemaDir', $invalidDirectories);
    })->toThrow(TypeError::class);
})->with([
    125, 62.5, 'fakedirectory', 'fake/dir/ups', '',
    ['', ''], ['fake', '/fake/ups'], [1,2],
])->group('appendSchemaDir');

test('Passing both valid and invalid schema directories to appendSchemaDir()', function (...$invalidDirectories) {
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_html')->returnArg();
    $response = new Response([]);
    $cache = new FileSystemCache();
    $invalidDirectories[0] = $cache->touchDirectory($invalidDirectories[0]);

    expect(function () use ($response, $invalidDirectories) {
        Helpers::invokeNonPublicClassMethod($response, 'appendSchemaDir', $invalidDirectories);
    })->toThrow(TypeError::class);
})->with([
    ['valid', 'invalid'], ['fake', 'fake/ups'], ['yup', 'true', 'yes'],
])->group('appendSchemaDir');

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
])->group('appendSchemaDir');

// getValidSchemaFilepath()

test('Trying to retrieve a json schema filepath without providing a filename', function () {
    $response = new Response([]);
    expect(function () use ($response) {
        Helpers::invokeNonPublicClassMethod($response, 'getValidSchemaFilepath');
    })->toThrow(Exception::class);
})->group('getValidSchemaFilepath');

test('Trying to retrieve a json schema filepath of a file that doesn\'t exists', function () {
    $response = new Response('random.json');
    expect(function () use ($response) {
        Helpers::invokeNonPublicClassMethod($response, 'getValidSchemaFilepath');
    })->toThrow(Exception::class);
})->group('getValidSchemaFilepath');

test('Retrieving a json schema filepath when providing a full filepath', function () {
    $cache = new FileSystemCache();
    $schemaFullPath = $cache->store('schema.json', '{}');
    $response = new Response($schemaFullPath);
    expect(Helpers::invokeNonPublicClassMethod($response, 'getValidSchemaFilepath'))
        ->toBe($schemaFullPath);
})->group('getValidSchemaFilepath');

test('Retrieving a json schema filepath when providing a relative filepath', function (string $schemaRelativePath) {
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1 . '/' . $path2;
    });
    $cache = new FileSystemCache();
    $schemaFullpath = $cache->store(Str::finish($schemaRelativePath, '.json'), '{}');
    $response = new Response($schemaRelativePath);
    Helpers::setNonPublicClassProperty($response, 'schemaDirs', [$cache->getRootDir()]);
    expect(Helpers::invokeNonPublicClassMethod($response, 'getValidSchemaFilepath'))
        ->toBe($schemaFullpath);
})->with(['schema', 'schema.json'])->group('getValidSchemaFilepath');

// getContents() and updateSchemaToAcceptOrDiscardAdditionalProperties()

test('getContents() retrieves correct schema', function ($loadSchemaFrom, $removeAdditionalProperties) {
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1 . '/' . $path2;
    });
    $schema = 'Users/Get';
    $expectedContents = Helpers::loadSchema(\SCHEMAS_DIR . $schema);
    if ($loadSchemaFrom == LoadSchema::FromArray) {
        $schema = $expectedContents;
    }
    $response = new Response($schema, $removeAdditionalProperties);
    $response->appendSchemaDir(\SCHEMAS_DIR);
    $contents = $response->getContents();
    if (is_bool($removeAdditionalProperties)) {
        $expectedContents["additionalProperties"] = !$removeAdditionalProperties;
        $expectedContents["properties"]["data"]["additionalProperties"] = !$removeAdditionalProperties;
    } else if (is_string($removeAdditionalProperties)) {
        $expectedContents["additionalProperties"] = ["type" => $removeAdditionalProperties];
        $expectedContents["properties"]["data"]["additionalProperties"] = ["type" => $removeAdditionalProperties];
    }
    expect($contents)->toEqual($expectedContents);
})->with([
    [LoadSchema::FromFile, true],
    [LoadSchema::FromArray, true],
    [LoadSchema::FromFile, false],
    [LoadSchema::FromArray, false],
    [LoadSchema::FromFile, null],
    [LoadSchema::FromArray, null],
    [LoadSchema::FromFile, "string"],
    [LoadSchema::FromArray, "string"],
    [LoadSchema::FromFile, "integer"],
    [LoadSchema::FromArray, "integer"],
    [LoadSchema::FromFile, "number"],
    [LoadSchema::FromArray, "number"],
    [LoadSchema::FromFile, "boolean"],
    [LoadSchema::FromArray, "boolean"],
    [LoadSchema::FromFile, "null"],
    [LoadSchema::FromArray, "null"],
    [LoadSchema::FromFile, "object"],
    [LoadSchema::FromArray, "object"],
    [LoadSchema::FromFile, "array"],
    [LoadSchema::FromArray, "array"],
])->group('getContents');

// returns()

test('returns() matches expected return value - Basic', function ($loadSchemaFrom, $value) {
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1 . '/' . $path2;
    });
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
])->group('returns');

test('Ignoring additional properties in returns()', function ($loadSchemaFrom) {
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1 . '/' . $path2;
    });
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
])->group('returns');

test('Keeps additional properties in returns()', function ($loadSchemaFrom) {
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1 . '/' . $path2;
    });
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
])->group('returns');

test('Ignores additional properties expect a given type in returns()', function ($loadSchemaFrom, $type, $expectedData) {
    Functions\when('esc_html__')->returnArg();
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1 . '/' . $path2;
    });
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
])->group('returns');

test('Ignores additional properties specified by the schema', function ($loadSchemaFrom) {
    Functions\when('esc_html__')->returnArg();
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1 . '/' . $path2;
    });
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
])->group('returns');
