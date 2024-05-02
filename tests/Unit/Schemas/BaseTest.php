<?php

/**
 * Holds tests for the JsonSchema class.
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
use Exception;
use Illuminate\Support\Str;
use Mockery;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\ValidationResult;
use org\bovigo\vfs\vfsStream;
use TypeError;
use Wp\FastEndpoints\Schemas\ResponseMiddleware;
use Wp\FastEndpoints\Schemas\SchemaMiddleware;
use Wp\FastEndpoints\Tests\Helpers\FileSystemCache;
use Wp\FastEndpoints\Tests\Helpers\Helpers;
use Wp\FastEndpoints\Tests\Helpers\LoadSchema;

beforeEach(function () {
    Monkey\setUp();
});

afterEach(function () {
    Monkey\tearDown();
    vfsStream::setup();
});

dataset('base_classes', [ResponseMiddleware::class, SchemaMiddleware::class]);
dataset('schemas', [
    'Basics/Array',
    'Basics/Boolean',
    'Basics/Double',
    'Basics/Integer',
    'Basics/Null',
    'Basics/Object',
    'Basics/String',
    'Users/Get',
    'Users/WithAdditionalProperties',
    'Misc/MultipleTypeObjects',
]);

// Constructor

test('Creating ResponseMiddleware instance with $schema as a string', function (string $class) {
    expect(new $class('User/Get'))->toBeInstanceOf($class);
})->with('base_classes')->group('base', 'constructor');

test('Creating ResponseMiddleware instance with $schema as an array', function (string $class) {
    expect(new $class([]))->toBeInstanceOf($class);
})->with('base_classes')->group('base', 'constructor');

test('Creating ResponseMiddleware instance with an invalid $schema type', function (string $class, $value) {
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_html')->returnArg();
    expect(function () use ($class, $value) {
        new $class($value);
    })->toThrow(TypeError::class);
})->with('base_classes')->with([1, 1.67, true, false])->group('base', 'constructor');

// getSuffix()

test('Checking correct ResponseMiddleware suffix', function (string $class) {
    $schema = new $class([]);
    $suffix = Helpers::invokeNonPublicClassMethod($schema, 'getSuffix');
    $expectedSuffix = Helpers::getHooksSuffixFromClass($schema);
    expect($suffix)->toBe($expectedSuffix);
})->with('base_classes')->group('base', 'getSuffix');

// getError()

test('Getting error', function (string $class) {
    $schema = new $class([]);
    $mockedValidationError = Mockery::mock(ValidationError::class);
    $mockedValidationResult = Mockery::mock(ValidationResult::class)
        ->shouldReceive('error')
        ->andReturn($mockedValidationError)
        ->getMock();
    $mockedErrorFormatter = Mockery::mock(ErrorFormatter::class)
        ->shouldReceive('formatKeyed')
        ->with(Mockery::type(ValidationError::class))
        ->andReturn(['My error message'])
        ->getMock();
    $className = Helpers::getHooksSuffixFromClass($schema);
    Helpers::setNonPublicClassProperty($schema, 'errorFormatter', $mockedErrorFormatter);
    Filters\expectApplied($className.'_error')
        ->once()
        ->with(['My error message'], Mockery::type(ValidationResult::class), $schema);
    expect(Helpers::invokeNonPublicClassMethod($schema, 'getError', $mockedValidationResult))
        ->toBe(['My error message']);
})->with('base_classes')->group('base', 'getError');

// appendSchemaDir()

test('Passing invalid schema directories to appendSchemaDir', function (string $class, $invalidDirectories, string $expectedErrorMessage) {
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_html')->returnArg();
    $schema = new $class([]);
    expect(function () use ($schema, $invalidDirectories) {
        Helpers::invokeNonPublicClassMethod($schema, 'appendSchemaDir', $invalidDirectories);
    })->toThrow(TypeError::class, $expectedErrorMessage);
})->with('base_classes')->with([
    ['', 'Invalid schema directory'],
    [['', ''], 'Invalid schema directory'],
    [[true], 'Expected a directory as a string but got: boolean'],
    [__FILE__, 'Expected a directory with schemas but got a file: '.__FILE__],
    [[__FILE__], 'Expected a directory with schemas but got a file: '.__FILE__],
    ['fakedirectory', 'SchemaMiddleware directory not found: fakedirectory'],
    [['fake', '/fake/ups'], 'SchemaMiddleware directory not found: fake'],
])->group('base', 'appendSchemaDir');

test('Passing both valid and invalid schema directories to appendSchemaDir', function (string $class, ...$invalidDirectories) {
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

test('Passing a valid schema directories to appendSchemaDir', function (string $class, ...$validDirectories) {
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
        return $path1.'/'.$path2;
    });
    $cache = new FileSystemCache();
    $schemaFullpath = $cache->store(Str::finish($schemaRelativePath, '.json'), '{}');
    $schema = new $class($schemaRelativePath);
    Helpers::setNonPublicClassProperty($schema, 'schemaDirs', [$cache->getRootDir()]);
    expect(Helpers::invokeNonPublicClassMethod($schema, 'getValidSchemaFilepath'))
        ->toBe($schemaFullpath);
})->with('base_classes')->with(['schema', 'schema.json'])->group('base', 'getValidSchemaFilepath');

// getContents()

test('getContents retrieves correct schema', function (string $class, $schema, $loadSchemaFrom) {
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1.'/'.$path2;
    });
    $expectedContents = Helpers::loadSchema(\SCHEMAS_DIR.$schema);
    if ($loadSchemaFrom == LoadSchema::FromArray) {
        $schema = $expectedContents;
    }
    $schema = new $class($schema);
    $suffix = Helpers::getHooksSuffixFromClass($schema);
    Filters\expectApplied($suffix.'_contents')
        ->once()
        ->with($expectedContents, $schema);
    $schema->appendSchemaDir(\SCHEMAS_DIR);
    if ($loadSchemaFrom == LoadSchema::FromFile) {
        expect(Helpers::getNonPublicClassProperty($schema, 'contents'))->toBeNull();
    } else {
        expect(Helpers::getNonPublicClassProperty($schema, 'contents'))->toEqual($expectedContents);
    }
    $contents = $schema->getContents();
    expect($contents)->toEqual($expectedContents)
        ->and(Helpers::getNonPublicClassProperty($schema, 'contents'))->toEqual($expectedContents);
})->with('base_classes')->with('schemas')->with([
    LoadSchema::FromFile,
    LoadSchema::FromArray,
])->group('base', 'getContents');

test('Getting schema that has been already loaded', function (string $class, $schema) {
    $expectedContents = Helpers::loadSchema(\SCHEMAS_DIR.$schema);
    $schema = new $class([]);
    $suffix = Helpers::getHooksSuffixFromClass($schema);
    Helpers::setNonPublicClassProperty($schema, 'contents', $expectedContents);
    Filters\expectApplied($suffix.'_contents')
        ->once()
        ->with($expectedContents, $schema);
    $schema->appendSchemaDir(\SCHEMAS_DIR);
    $contents = $schema->getContents();
    expect($contents)->toEqual($expectedContents);
})->with('base_classes')->with('schemas')->group('base', 'getContents');

test('Trying to load invalid json', function (string $class, $schemaFilepath) {
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('wp_die')->alias(function ($msg) {
        throw new Exception($msg);
    });
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1.'/'.$path2;
    });
    $schema = new $class($schemaFilepath);
    $suffix = Helpers::getHooksSuffixFromClass($schema);
    $schema->appendSchemaDir(\SCHEMAS_DIR);
    expect(function () use ($schema) {
        $schema->getContents();
    })->toThrow(Exception::class, sprintf('Invalid json file: %1$s', $schemaFilepath));
    $this->assertEquals(Filters\applied($suffix.'_contents'), 0);
})->with('base_classes')->with([
    'Invalid/InvalidJson.json',
    'Invalid/Text.json',
])->group('base', 'getContents');

test('Failed to load file contents', function (string $class) {
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('wp_die')->alias(function ($msg) {
        throw new Exception($msg);
    });
    $mockedSchema = Mockery::mock($class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('getValidSchemaFilepath')
        ->andReturn(false)
        ->shouldReceive('getFileContents')
        ->andReturn(false)
        ->getMock();
    $suffix = Helpers::getHooksSuffixFromClass($class);

    expect(function () use ($mockedSchema) {
        $mockedSchema->getContents();
    })->toThrow(Exception::class, 'Unable to read file: ');
    $this->assertEquals(Filters\applied($suffix.'_contents'), 0);
})->with('base_classes')->group('base', 'getContents');
