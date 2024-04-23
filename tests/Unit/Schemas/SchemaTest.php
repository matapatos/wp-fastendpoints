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
use TypeError;
use Mockery;
use org\bovigo\vfs\vfsStream;
use Illuminate\Support\Str;
use Brain\Monkey;
use Brain\Monkey\Functions;

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

// Constructor

test('Creating Schema instance with $schema as a string', function () {
    expect(new Schema('User/Get'))->toBeInstanceOf(Schema::class);
})->group('constructor');

test('Creating Schema instance with $schema as an array', function () {
    expect(new Schema([]))->toBeInstanceOf(Schema::class);
})->group('constructor');

test('Creating Schema instance with an invalid $schema type', function ($value) {
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_html')->returnArg();
    expect(function () use ($value) {
        new Schema($value);
    })->toThrow(TypeError::class);;
})->with([1, 1.67, true, false])->group('constructor');

// getSuffix()

test('Checking correct Schema suffix', function () {
    $schema = new Schema([]);
    $suffix = Helpers::invokeNonPublicClassMethod($schema, 'getSuffix');
    expect($suffix)->toBe('schema');
})->group('getSuffix');

// appendSchemaDir()

test('Passing invalid schema directories to appendSchemaDir()', function (...$invalidDirectories) {
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_html')->returnArg();
    $schema = new Schema([]);
    expect(function () use ($invalidDirectories, $schema) {
        Helpers::invokeNonPublicClassMethod($schema, 'appendSchemaDir', $invalidDirectories);
    })->toThrow(TypeError::class);
})->with([
    125, 62.5, 'fakedirectory', 'fake/dir/ups', '',
    ['', ''], ['fake', '/fake/ups'], [1,2],
])->group('appendSchemaDir');

test('Passing both valid and invalid schema directories to appendSchemaDir()', function (...$invalidDirectories) {
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_html')->returnArg();
    $schema = new Schema([]);
    $cache = new FileSystemCache();
    $invalidDirectories[0] = $cache->touchDirectory($invalidDirectories[0]);

    expect(function() use ($invalidDirectories, $schema) {
        Helpers::invokeNonPublicClassMethod($schema, 'appendSchemaDir', $invalidDirectories);
    })->toThrow(TypeError::class);
})->with([
    ['valid', 'invalid'], ['fake', 'fake/ups'], ['yup', 'true', 'yes'],
])->group('appendSchemaDir');

test('Passing a valid schema directories to appendSchemaDir()', function (...$validDirectories) {
    $cache = new FileSystemCache();
    $validDirectories = $cache->touchDirectories($validDirectories);

    $schema = new Schema([]);
    $schemaDirs = Helpers::getNonPublicClassProperty($schema, 'schemaDirs');
    expect($schemaDirs)
        ->toBeArray()
        ->toBeEmpty();
    Helpers::invokeNonPublicClassMethod($schema, 'appendSchemaDir', $validDirectories);
    $schemaDirs = Helpers::getNonPublicClassProperty($schema, 'schemaDirs');
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
    $schema = new Schema([]);
    expect(function () use ($schema) {
        Helpers::invokeNonPublicClassMethod($schema, 'getValidSchemaFilepath');
    })->toThrow(Exception::class);
})->group('getValidSchemaFilepath');

test('Trying to retrieve a json schema filepath of a file that doesn\'t exists', function () {
    $schema = new Schema('random.json');
    expect(function () use ($schema) {
        Helpers::invokeNonPublicClassMethod($schema, 'getValidSchemaFilepath');
    })->toThrow(Exception::class);
})->group('getValidSchemaFilepath');

test('Retrieving a json schema filepath when providing a full filepath', function () {
    $cache = new FileSystemCache();
    $schemaFullpath = $cache->store('schema.json', '{}');
    $schema = new Schema($schemaFullpath);
    expect(Helpers::invokeNonPublicClassMethod($schema, 'getValidSchemaFilepath'))
        ->toBe($schemaFullpath);
})->group('getValidSchemaFilepath');

test('Retrieving a json schema filepath when providing a relative filepath', function (string $schemaRelativePath) {
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1 . '/' . $path2;
    });
    $cache = new FileSystemCache();
    $schemaFullPath = $cache->store(Str::finish($schemaRelativePath, '.json'), '{}');
    $schema = new Schema($schemaRelativePath);
    Helpers::setNonPublicClassProperty($schema, 'schemaDirs', [$cache->getRootDir()]);
    expect(Helpers::invokeNonPublicClassMethod($schema, 'getValidSchemaFilepath'))
        ->toBe($schemaFullPath);
})->with(['schema', 'schema.json'])->group('getValidSchemaFilepath');

// getContents()

test('getContents() retrieves correct schema', function ($loadSchemaFrom) {
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
    $contents = $schema->getContents();
    expect($contents)->toEqual($expectedContents);
})->with([
    LoadSchema::FromFile,
    LoadSchema::FromArray,
])->group('getContents');

// validate()

test('validate() valid parameters', function ($loadSchemaFrom) {
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
    expect($result)->toBe(true);
})->with([
    LoadSchema::FromFile,
    LoadSchema::FromArray,
])->group('validate');

test('validate() invalid parameters', function ($loadSchemaFrom) {
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
//    LoadSchema::FromArray,
])->group('validate');

test('validate() invalid schema', function () {
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
})->group('validate');
