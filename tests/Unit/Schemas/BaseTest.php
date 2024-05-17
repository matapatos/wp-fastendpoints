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
use Mockery;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\ValidationResult;
use Opis\JsonSchema\Validator;
use org\bovigo\vfs\vfsStream;
use TypeError;
use Wp\FastEndpoints\Schemas\ResponseMiddleware;
use Wp\FastEndpoints\Schemas\SchemaMiddleware;
use Wp\FastEndpoints\Schemas\SchemaResolver;
use Wp\FastEndpoints\Tests\Helpers\Helpers;

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

test('Checking correct Middleware suffix', function (string $class) {
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

// createValidatorWithResolver

test('Creating JSON schema validator with custom resolver', function (string $class) {
    $middleware = new $class([]);
    $schemaResolver = new SchemaResolver();
    $validator = $middleware->createValidatorWithResolver($schemaResolver);
    expect($validator)->toBeInstanceOf(Validator::class)
        ->and($validator->resolver())
        ->toBe($schemaResolver);
})->with('base_classes')->group('base', 'createValidatorWithResolver');

test('Creating JSON schema validator', function (string $class) {
    $middleware = new $class([]);
    $validator = $middleware->createValidatorWithResolver(null);
    expect($validator)->toBeInstanceOf(Validator::class)
        ->and($validator->resolver())
        ->toBeInstanceOf(SchemaResolver::class);
})->with('base_classes')->group('base', 'createValidatorWithResolver');

test('Calling hooks while creating JSON schema validator', function (string $class) {
    $suffix = Helpers::getHooksSuffixFromClass($class);
    Filters\expectApplied('fastendpoints_validator')
        ->once()
        ->with(Mockery::type(Validator::class), Mockery::type($class));
    Filters\expectApplied($suffix.'_validator')
        ->once()
        ->with(Mockery::type(Validator::class), Mockery::type($class));
    new $class([]);
})->with('base_classes')->group('base', 'createValidatorWithResolver');
