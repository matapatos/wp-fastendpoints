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

use Tests\Wp\FastEndpoints\Helpers\Helpers;
use Tests\Wp\FastEndpoints\Helpers\FileSystemCache;
use Tests\Wp\FastEndpoints\Helpers\LoadSchema;

use Wp\FastEndpoints\Schemas\Schema;
use WP_Error;

afterEach(function () {
    Mockery::close();
    vfsStream::setup();
});

// Constructor

test('Creating Schema instance with $schema as a string', function () {
    expect(new Schema('User/Get'))->toBeInstanceOf(Schema::class);
});

test('Creating Schema instance with $schema as an array', function () {
    expect(new Schema([]))->toBeInstanceOf(Schema::class);
});

test('Creating Schema instance with an invalid $schema type', function ($value) {
    expect(fn() => new Schema($value))->toThrow(TypeError::class);
})->with([1, 1.67, true, false]);

// getSuffix()

test('Checking correct Schema suffix', function () {
    $schema = new Schema([]);
    $suffix = Helpers::invokeNonPublicClassMethod($schema, 'getSuffix');
    expect($suffix)->toBe('schema');
});

// appendSchemaDir()

test('Passing invalid schema directories to appendSchemaDir()', function (...$invalidDirectories) {
    $schema = new Schema([]);
    expect(fn() => Helpers::invokeNonPublicClassMethod($schema, 'appendSchemaDir', $invalidDirectories))->toThrow(TypeError::class);
})->with([
    125, 62.5, 'fakedirectory', 'fake/dir/ups', '',
    ['', ''], ['fake', '/fake/ups'], [1,2],
]);

test('Passing both valid and invalid schema directories to appendSchemaDir()', function (...$invalidDirectories) {
    $schema = new Schema([]);
    $cache = new FileSystemCache();
    $invalidDirectories[0] = $cache->touchDirectory($invalidDirectories[0]);

    expect(fn() => Helpers::invokeNonPublicClassMethod($schema, 'appendSchemaDir', $invalidDirectories))->toThrow(TypeError::class);
})->with([
    ['valid', 'invalid'], ['fake', 'fake/ups'], ['yup', 'true', 'yes'],
]);

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
]);

// getValidSchemaFilepath()

test('Trying to retrieve a json schema filepath without providing a filename', function () {
    $schema = new Schema([]);
    expect(fn() => Helpers::invokeNonPublicClassMethod($schema, 'getValidSchemaFilepath'))
        ->toThrow(Exception::class);
});

test('Trying to retrieve a json schema filepath of a file that doesn\'t exists', function () {
    $schema = new Schema('random.json');
    expect(fn() => Helpers::invokeNonPublicClassMethod($schema, 'getValidSchemaFilepath'))
        ->toThrow(Exception::class);
});

test('Retrieving a json schema filepath when providing a full filepath', function () {
    $cache = new FileSystemCache();
    $schemaFullpath = $cache->store('schema.json', '{}');
    $schema = new Schema($schemaFullpath);
    expect(Helpers::invokeNonPublicClassMethod($schema, 'getValidSchemaFilepath'))
        ->toBe($schemaFullpath);
});

test('Retrieving a json schema filepath when providing a relative filepath', function (string $schemaRelativePath) {
    $cache = new FileSystemCache();
    $schemaFullpath = $cache->store(Str::finish($schemaRelativePath, '.json'), '{}');
    $schema = new Schema($schemaRelativePath);
    Helpers::setNonPublicClassProperty($schema, 'schemaDirs', [$cache->getRootDir()]);
    expect(Helpers::invokeNonPublicClassMethod($schema, 'getValidSchemaFilepath'))
        ->toBe($schemaFullpath);
})->with(['schema', 'schema.json']);

// getContents()

test('getContents() retrieves correct schema', function ($loadSchemaFrom) {
    $schema = 'Users/Get';
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
]);

// validate()

test('validate() valid parameters', function ($loadSchemaFrom) {
    $schema = 'Users/Get';
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
]);

test('validate() invalid parameters', function ($loadSchemaFrom) {
    $schema = 'Users/Get';
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
    expect($result)->toBeInstanceOf(WP_Error::class);
    expect($result->code)->toBe(422);
})->with([
    LoadSchema::FromFile,
    LoadSchema::FromArray,
]);

test('validate() invalid schema', function () {
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
    expect($result)->toBeInstanceOf(WP_Error::class);
    expect($result->code)->toBe(500);
});
