<?php

/**
 * Holds tests for the Base class.
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
use ParagonIE\Sodium\Core\Curve25519\H;
use TypeError;
use Mockery;
use org\bovigo\vfs\vfsStream;
use Illuminate\Support\Str;
use Opis\JsonSchema\Helper;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;

use Tests\Wp\FastEndpoints\Helpers\Helpers;
use Tests\Wp\FastEndpoints\Helpers\FileSystemCache;
use Tests\Wp\FastEndpoints\Helpers\Faker;
use Tests\Wp\FastEndpoints\Helpers\LoadSchema;

use Wp\FastEndpoints\Contracts\Schemas\Base;
use Wp\FastEndpoints\Schemas\Response;
use Wp\FastEndpoints\Schemas\Schema;

beforeEach(function () {
    Monkey\setUp();
});

afterEach(function () {
    Monkey\tearDown();
    Mockery::close();
    vfsStream::setup();
});

dataset('base_classes', [Response::class, Schema::class]);

// Constructor

test('Creating Response instance with $schema as a string', function (string $class) {
    expect(new $class('User/Get'))->toBeInstanceOf($class);
})->with('base_classes')->group('base', 'constructor');

test('Creating Response instance with $schema as an array', function (string $class) {
    expect(new $class([]))->toBeInstanceOf($class);
})->with('base_classes')->group('base', 'constructor');

test('Creating Response instance with an invalid $schema type', function (string $class, $value) {
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_html')->returnArg();
    expect(function () use ($class, $value) {
        new $class($value);
    })->toThrow(TypeError::class);
})->with('base_classes')->with([1, 1.67, true, false])->group('base', 'constructor');

// getSuffix()

test('Checking correct Response suffix', function (string $class) {
    $schema = new $class([]);
    $suffix = Helpers::invokeNonPublicClassMethod($schema, 'getSuffix');
    $expectedSuffix = Helpers::getClassNameInSnakeCase($schema);
    expect($suffix)->toBe($expectedSuffix);
})->with('base_classes')->group('base', 'getSuffix');

// getError()

test('Getting error', function (string $class) {
    $schema = new $class([]);
    $mockedValidationError = Mockery::mock(ValidationError::class);
    $mockedValidationResult = Mockery::mock(ValidationResult::class)
        ->expects()
        ->error()
        ->andReturn($mockedValidationError)
        ->getMock();
    $mockedErrorFormatter = Mockery::mock(ErrorFormatter::class)
        ->expects()
        ->formatKeyed($mockedValidationError)
        ->andReturn(['My error message'])
        ->getMock();
    $className = Helpers::getClassNameInSnakeCase($schema);
    Filters\expectApplied($className . '_error')
        ->with(['My error message'], Mockery::type(ValidationResult::class), Mockery::type($schema))
        ->once();
    Helpers::setNonPublicClassProperty($schema, 'errorFormatter', $mockedErrorFormatter);
    expect(Helpers::invokeNonPublicClassMethod($schema, 'getError', $mockedValidationResult))
        ->toBe(['My error message']);
})->with('base_classes')->group('base', 'getError');

// appendSchemaDir()

test('Passing invalid schema directories to appendSchemaDir()', function (string $class, ...$invalidDirectories) {
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_html')->returnArg();
    $schema = new $class([]);
    expect(function () use ($schema, $invalidDirectories) {
        Helpers::invokeNonPublicClassMethod($schema, 'appendSchemaDir', $invalidDirectories);
    })->toThrow(TypeError::class);
})->with('base_classes')->with([
    125, 62.5, 'fakedirectory', 'fake/dir/ups', '',
    ['', ''], ['fake', '/fake/ups'], [1,2],
])->group('base', 'appendSchemaDir');

test('Passing both valid and invalid schema directories to appendSchemaDir()', function (string $class, ...$invalidDirectories) {
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_html')->returnArg();
    $schema = new $class([]);
    $cache = new FileSystemCache();
    $invalidDirectories[0] = $cache->touchDirectory($invalidDirectories[0]);

    expect(function () use ($schema, $invalidDirectories) {
        Helpers::invokeNonPublicClassMethod($schema, 'appendSchemaDir', $invalidDirectories);
    })->toThrow(TypeError::class);
})->with('base_classes')->with([
    ['valid', 'invalid'], ['fake', 'fake/ups'], ['yup', 'true', 'yes'],
])->group('base', 'appendSchemaDir');

test('Passing a valid schema directories to appendSchemaDir()', function (string $class, ...$validDirectories) {
    $cache = new FileSystemCache();
    $validDirectories = $cache->touchDirectories($validDirectories);

    $schema = new $class([]);
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
})->with('base_classes')->with([
    'Schemas', 'Others/Schemas', 'Random/Another/Schemas',
    ['Hey', 'Dude'], ['Great/Man', 'Yes/ItWorks'],
])->group('base', 'appendSchemaDir');

// getValidSchemaFilepath()

test('Trying to retrieve a json schema filepath without providing a filename', function (string $class) {
    $schema = new $class([]);
    expect(function () use ($schema) {
        Helpers::invokeNonPublicClassMethod($schema, 'getValidSchemaFilepath');
    })->toThrow(Exception::class);
})->with('base_classes')->group('base', 'getValidSchemaFilepath');

test('Trying to retrieve a json schema filepath of a file that doesn\'t exists', function (string $class) {
    $schema = new $class('random.json');
    expect(function () use ($schema) {
        Helpers::invokeNonPublicClassMethod($schema, 'getValidSchemaFilepath');
    })->toThrow(Exception::class);
})->with('base_classes')->group('base', 'getValidSchemaFilepath');

test('Retrieving a json schema filepath when providing a full filepath', function (string $class) {
    $cache = new FileSystemCache();
    $schemaFullPath = $cache->store('schema.json', '{}');
    $schema = new $class($schemaFullPath);
    expect(Helpers::invokeNonPublicClassMethod($schema, 'getValidSchemaFilepath'))
        ->toBe($schemaFullPath);
})->with('base_classes')->group('base', 'getValidSchemaFilepath');

test('Retrieving a json schema filepath when providing a relative filepath', function (string $class, string $schemaRelativePath) {
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1 . '/' . $path2;
    });
    $cache = new FileSystemCache();
    $schemaFullpath = $cache->store(Str::finish($schemaRelativePath, '.json'), '{}');
    $schema = new $class($schemaRelativePath);
    Helpers::setNonPublicClassProperty($schema, 'schemaDirs', [$cache->getRootDir()]);
    expect(Helpers::invokeNonPublicClassMethod($schema, 'getValidSchemaFilepath'))
        ->toBe($schemaFullpath);
})->with('base_classes')->with(['schema', 'schema.json'])->group('base', 'getValidSchemaFilepath');
