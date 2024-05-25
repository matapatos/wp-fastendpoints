<?php

/**
 * Holds tests for the WpError class.
 *
 * @since 0.9.0
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Tests\Unit\Schemas;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Wp\FastEndpoints\Helpers\WpError;
use Wp\FastEndpoints\Tests\Helpers\Helpers;

beforeEach(function () {
    Monkey\setUp();
});

afterEach(function () {
    Monkey\tearDown();
    Mockery::close();
});

// Constructor

test('Setting correct params', function () {
    Functions\when('esc_html__')->returnArg();
    $wpError = new WpError(123, 'My error message');
    expect($wpError)
        ->toBeInstanceOf(\WP_Error::class)
        ->toHaveProperty('code', 123)
        ->toHaveProperty('message')
        ->toHaveProperty('data', ['status' => 123]);

    $wpError = new WpError(543, ['My error message'], ['custom-field' => 'Testing']);
    expect($wpError)
        ->toBeInstanceOf(\WP_Error::class)
        ->toHaveProperty('code', 543)
        ->toHaveProperty('message')
        ->toHaveProperty('data', ['status' => 543, 'all_messages' => ['My error message'], 'custom-field' => 'Testing']);
})->group('helpers', 'WpError', 'constructor');

// Getting first message

test('Get first message from message', function ($message) {
    $wpError = Mockery::mock(WpError::class);
    $firstMessage = Helpers::invokeNonPublicClassMethod($wpError, 'getFirstErrorMessage', $message);
    expect($firstMessage)->toBe('first message');
})->with([
    'first message',
    [['first message', 'second message']],
    [['first-key' => 'first message', 'second-key' => 'second message']],
    [['first-key' => ['first' => ['first message']], 'second-key' => 'second message']],
])->group('helpers', 'WpError', 'getFirstErrorMessage');

test('Missing error message', function ($message) {
    $wpError = Mockery::mock(WpError::class);
    $firstMessage = Helpers::invokeNonPublicClassMethod($wpError, 'getFirstErrorMessage', $message);
    expect($firstMessage)->toBe('No error description provided');
})->with([
    [['first-key' => [], 'second-key' => 'second message']],
    [[]],
])->group('helpers', 'WpError', 'getFirstErrorMessage');
