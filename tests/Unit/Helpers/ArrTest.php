<?php

/**
 * Holds tests for the Arr class.
 *
 * @since 0.9.0
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Tests\Wp\FastEndpoints\Unit\Schemas;

use Brain\Monkey;
use Mockery;
use org\bovigo\vfs\vfsStream;
use Tests\Wp\FastEndpoints\Helpers\Helpers;
use Wp\FastEndpoints\Helpers\Arr;

beforeEach(function () {
    Monkey\setUp();
});

afterEach(function () {
    Monkey\tearDown();
    Mockery::close();
    vfsStream::setup();
});

// Wrap

test('Wrapping values', function ($value) {
    $result = Arr::wrap($value);
    expect($result)->toBeArray();
    if (is_array($value)) {
        expect($result)->toMatchArray($value);
    }
})->with([
    1,
    1.223,
    '',
    true,
    false,
    null,
    'hello',
    ['test', 'nope'],
    ['first' => 1, 'second' => 2],
])->group('helpers', 'arr', 'wrap');

// Recursive key value search

test('Recursive search of key and value', function ($schema, $key, $value, $expectedResult) {
    $schema = Helpers::loadSchema(\SCHEMAS_DIR.$schema);
    expect(Arr::recursiveKeyValueSearch($schema, $key, $value))
        ->toBe($expectedResult);
})->with([
    ['Basics/Array', 'type', 'array', [[]]],
    ['Basics/Boolean', 'type', 'boolean', [[]]],
    ['Basics/Double', 'type', 'number', [[]]],
    ['Basics/Integer', 'type', 'integer', [[]]],
    ['Basics/Null', 'type', 'null', [[]]],
    ['Basics/Object', 'type', 'object', [[]]],
    ['Basics/String', 'type', 'string', [[]]],
    ['Users/Get', 'type', 'object', [[], ['properties', 'data']]],
    ['Users/WithAdditionalProperties', 'type', 'object', [[], ['properties', 'data']]],
    ['Misc/MultipleTypeObjects.json', 'type', 'object', [[], ['properties', 'first'], ['properties', 'first', 'properties', 'second'], ['properties', 'first', 'another'], ['properties', 'first', 'another', 'properties', 'bro']]],
])->group('helpers', 'arr', 'recursiveKeyValueSearch');

// Is associative array

// NOTE: [0 => 'hello', 1 => 'another'] are not considered associative arrays :(
test('Valid associative arrays', function ($value) {
    expect(Arr::isAssoc($value))->toBeTrue();
})->with([
    [['first' => 1, 'second' => 2]],
    [[1 => 'a', 2 => 'b']],
    [['ok' => 'ok']],
])->group('helpers', 'arr', 'isAssoc');

test('Non associative arrays', function ($value) {
    expect(Arr::isAssoc($value))->toBeFalse();
})->with([
    [[1, 2, 3, 4]],
    [['ao']],
    [[]],
])->group('helpers', 'arr', 'isAssoc');
