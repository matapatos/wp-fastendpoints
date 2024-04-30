<?php

/**
 * Holds tests for the Response class.
 *
 * @since 0.9.0
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Tests\Wp\FastEndpoints\Unit\Schemas;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Illuminate\Support\Str;
use Mockery;
use Opis\JsonSchema\Exceptions\ParseException;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\ValidationResult;
use Opis\JsonSchema\Validator;
use org\bovigo\vfs\vfsStream;
use Tests\Wp\FastEndpoints\Helpers\Faker;
use Tests\Wp\FastEndpoints\Helpers\Helpers;
use Tests\Wp\FastEndpoints\Helpers\LoadSchema;
use Wp\FastEndpoints\Helpers\WpError;
use Wp\FastEndpoints\Schemas\Response;

beforeEach(function () {
    Monkey\setUp();
});

afterEach(function () {
    Monkey\tearDown();
    vfsStream::setup();
});

// Constructor

test('Passing invalid options to removeAdditionalProperties', function ($loadSchemaFrom, $removeAdditionalProperties) {
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_html')->returnArg();
    $schema = 'Basics/Array';
    if ($loadSchemaFrom == LoadSchema::FromArray) {
        $schema = Helpers::loadSchema(\SCHEMAS_DIR.$schema);
    }
    expect(function () use ($schema, $removeAdditionalProperties) {
        new Response($schema, $removeAdditionalProperties);
    })->toThrow(\ValueError::class, sprintf("Invalid removeAdditionalProperties property (%s) '%s'",
        gettype($removeAdditionalProperties), $removeAdditionalProperties));
})->with([LoadSchema::FromFile, LoadSchema::FromArray])->with([
    'true', 'false', 'StRing', 'ntege', 'fake',
])->group('response', 'getContents');

// getContents() and updateSchemaToAcceptOrDiscardAdditionalProperties()

test('getContents retrieves correct schema', function ($loadSchemaFrom, $removeAdditionalProperties) {
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1.'/'.$path2;
    });
    $schema = 'Users/Get';
    $expectedContents = Helpers::loadSchema(\SCHEMAS_DIR.$schema);
    if ($loadSchemaFrom == LoadSchema::FromArray) {
        $schema = $expectedContents;
    }
    $response = new Response($schema, $removeAdditionalProperties);
    $response->appendSchemaDir(\SCHEMAS_DIR);
    Filters\expectApplied('fastendpoints_response_contents')
        ->once()
        ->with($expectedContents, $response);
    Filters\expectApplied('fastendpoints_response_remove_additional_properties')
        ->once()
        ->with($removeAdditionalProperties, $response);
    $contents = $response->getContents();
    if (is_bool($removeAdditionalProperties)) {
        $expectedContents['additionalProperties'] = ! $removeAdditionalProperties;
        $expectedContents['properties']['data']['additionalProperties'] = ! $removeAdditionalProperties;
    } elseif (is_string($removeAdditionalProperties)) {
        $expectedContents['additionalProperties'] = ['type' => $removeAdditionalProperties];
        $expectedContents['properties']['data']['additionalProperties'] = ['type' => $removeAdditionalProperties];
    }
    expect($contents)->toEqual($expectedContents);
})->with([LoadSchema::FromFile, LoadSchema::FromArray])->with([
    true, false, null, 'string', 'integer', 'number',
    'boolean', 'null', 'object', 'array',
])->group('response', 'getContents', 'updateSchemaToAcceptOrDiscardAdditionalProperties');

// updateSchemaToAcceptOrDiscardAdditionalProperties

test('Avoids re-updating schema', function () {
    $response = new Response(['hello'], true);
    expect(Helpers::getNonPublicClassProperty($response, 'hasUpdatedSchema'))->toBeFalse();
    Helpers::setNonPublicClassProperty($response, 'hasUpdatedSchema', true);
    Helpers::invokeNonPublicClassMethod($response, 'updateSchemaToAcceptOrDiscardAdditionalProperties');
    $this->assertEquals(Filters\applied('response_remove_additional_properties'), 0);
    expect(Helpers::getNonPublicClassProperty($response, 'contents'))->toMatchArray(['hello']);
})->group('response', 'updateSchemaToAcceptOrDiscardAdditionalProperties');

test('Ignore removing properties if schema is empty or doesnt have a type object', function ($schema) {
    $response = new Response($schema, true);
    Filters\expectApplied('fastendpoints_response_remove_additional_properties')
        ->once()
        ->with(true, $response);
    Helpers::invokeNonPublicClassMethod($response, 'updateSchemaToAcceptOrDiscardAdditionalProperties');
    expect(Helpers::getNonPublicClassProperty($response, 'contents'))->toMatchArray($schema);
})->with([[[]], [[['type' => 'hello']]]])->group('response', 'updateSchemaToAcceptOrDiscardAdditionalProperties');

// returns()

function looseExpectAllReturnHooks($req, $response)
{
    Filters\expectApplied('fastendpoints_response_is_to_validate')
        ->once()
        ->with(true, $response);
    Filters\expectApplied('fastendpoints_response_contents')
        ->once()
        ->withAnyArgs();
    Filters\expectApplied('fastendpoints_response_remove_additional_properties')
        ->once()
        ->with(Mockery::any(), $response);
    Filters\expectApplied('fastendpoints_response_validation_data')
        ->once()
        ->with(Mockery::any(), $req, $response);
    Filters\expectApplied('fastendpoints_response_validator')
        ->once()
        ->with(Mockery::type(Validator::class), Mockery::any(), $req, $response);
    Filters\expectApplied('fastendpoints_response_is_valid')
        ->once()
        ->with(true, Mockery::any(), Mockery::type(ValidationResult::class), $req, $response);
    Filters\expectApplied('fastendpoints_response_on_validation_success')
        ->once()
        ->with(Mockery::any(), $req, $response);
}

test('returns matches expected return value - Basic', function ($loadSchemaFrom, $value) {
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1.'/'.$path2;
    });
    $schemaName = Str::ucfirst(Str::lower(gettype($value)));
    $schema = 'Basics/'.$schemaName;
    if ($loadSchemaFrom == LoadSchema::FromArray) {
        $schema = Helpers::loadSchema(\SCHEMAS_DIR.$schema);
    }
    $response = new Response($schema, true);
    $response->appendSchemaDir(\SCHEMAS_DIR);
    // Create WP_REST_Request mock
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_route')
        ->andReturn('value');
    // Check all filters are called
    Filters\expectApplied('fastendpoints_response_is_to_validate')
        ->once()
        ->with(true, $response);
    Filters\expectApplied('fastendpoints_response_contents')
        ->once()
        ->withAnyArgs();
    Filters\expectApplied('fastendpoints_response_remove_additional_properties')
        ->once()
        ->with(true, $response);
    Filters\expectApplied('fastendpoints_response_validation_data')
        ->once()
        ->with($value, $req, $response);
    Filters\expectApplied('fastendpoints_response_validator')
        ->once()
        ->with(Mockery::type(Validator::class), $value, $req, $response);
    Filters\expectApplied('fastendpoints_response_is_valid')
        ->once()
        ->with(true, Mockery::any(), Mockery::type(ValidationResult::class), $req, $response)
        ->andReturnUsing(function ($isValid, $givenValue, $result, $req, $response) use ($value) {
            expect($givenValue)->toEqual($value);

            return $isValid;
        });
    Filters\expectApplied('fastendpoints_response_on_validation_success')
        ->once()
        ->with(Mockery::any(), $req, $response)
        ->andReturnUsing(function ($givenValue, $givenReq, $givenResponse) use ($value) {
            expect($givenValue)->toEqual($value);

            return $givenValue;
        });
    // Validate response
    $data = $response->returns($req, $value);
    expect($data)->toEqual($value);
    $this->assertEquals(Filters\applied('fastendpoints_response_on_validation_error'), 0);
})->with([LoadSchema::FromArray, LoadSchema::FromFile])->with([
    0.674, 255, true, null, 'this is a string', [[1, 2, 3, 4, 5]],
    (object) [
        'stringVal' => 'hello',
        'intVal' => 1,
        'arrayVal' => [1, 2, 3],
        'doubleVal' => 0.82,
        'boolVal' => false,
    ],
])->group('response', 'returns');

test('Ignoring additional properties in returns', function ($loadSchemaFrom) {
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1.'/'.$path2;
    });
    $schema = 'Users/Get';
    if ($loadSchemaFrom == LoadSchema::FromArray) {
        $schema = Helpers::loadSchema(\SCHEMAS_DIR.$schema);
    }
    $response = new Response($schema, true);
    $response->appendSchemaDir(\SCHEMAS_DIR);
    $user = Faker::getWpUser();
    // Create WP_REST_Request mock
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_route')
        ->andReturn('user');
    // Expected hooks to be applied
    looseExpectAllReturnHooks($req, $response);
    $data = $response->returns($req, $user);
    expect($data)->toEqual(Helper::toJSON([
        'data' => [
            'user_email' => 'fake@wpfastendpoints.com',
            'user_url' => 'https://www.wpfastendpoints.com/wp',
            'display_name' => 'André Gil',
        ],
    ]));
})->with([
    LoadSchema::FromFile,
    LoadSchema::FromArray,
])->group('response', 'returns');

test('Keeps additional properties in returns', function ($loadSchemaFrom) {
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1.'/'.$path2;
    });
    $schema = 'Users/Get';
    if ($loadSchemaFrom == LoadSchema::FromArray) {
        $schema = Helpers::loadSchema(\SCHEMAS_DIR.$schema);
    }
    $response = new Response($schema, false);
    $response->appendSchemaDir(\SCHEMAS_DIR);
    $user = Faker::getWpUser();
    // Create WP_REST_Request mock
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_route')
        ->andReturn('user');
    // Expected hooks to be applied
    looseExpectAllReturnHooks($req, $response);
    // Validate response
    $data = $response->returns($req, $user);
    expect($data)->toEqual(Helper::toJSON($user));
})->with([
    LoadSchema::FromFile,
    LoadSchema::FromArray,
])->group('response', 'returns');

test('Ignores additional properties expect a given type in returns', function ($loadSchemaFrom, $type, $expectedData) {
    Functions\when('esc_html__')->returnArg();
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1.'/'.$path2;
    });
    $schema = 'Users/Get';
    if ($loadSchemaFrom == LoadSchema::FromArray) {
        $schema = Helpers::loadSchema(\SCHEMAS_DIR.$schema);
    }
    $response = new Response($schema, $type);
    $response->appendSchemaDir(\SCHEMAS_DIR);
    $user = Faker::getWpUser();
    $user['is_admin'] = true;
    // Create WP_REST_Request mock
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_route')
        ->andReturn('user');
    // Expected hooks to be applied
    looseExpectAllReturnHooks($req, $response);
    // Validate response
    $data = $response->returns($req, $user);
    $expectedData = array_merge(['data' => [
        'user_email' => 'fake@wpfastendpoints.com',
        'user_url' => 'https://www.wpfastendpoints.com/wp',
        'display_name' => 'André Gil',
    ]], $expectedData);
    expect($data)->toEqual(Helper::toJSON($expectedData));
})->with([LoadSchema::FromFile, LoadSchema::FromArray])->with([
    ['integer', ['ID' => 5]],
    ['string', ['cap_key' => 'wp_capabilities', 'data' => Faker::getWpUser()['data']]],
    ['number', ['ID' => 5]],
    ['boolean', ['is_admin' => true]],
    ['null', ['filter' => null]],
    ['object', [
        'caps' => ['administrator' => true],
        'allcaps' => ['switch_themes' => true, 'edit_themes' => true, 'administrator' => true],
    ]],
    ['array', ['roles' => ['administrator']]],
])->group('response', 'returns');

test('Ignores additional properties specified by the schema', function ($loadSchemaFrom) {
    Functions\when('esc_html__')->returnArg();
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1.'/'.$path2;
    });
    $schema = 'Users/WithAdditionalProperties';
    if ($loadSchemaFrom == LoadSchema::FromArray) {
        $schema = Helpers::loadSchema(\SCHEMAS_DIR.$schema);
    }
    $response = new Response($schema, null);
    $response->appendSchemaDir(\SCHEMAS_DIR);
    $user = Faker::getWpUser();
    // Create WP_REST_Request mock
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_route')
        ->andReturn('user');
    // Expected hooks to be applied
    looseExpectAllReturnHooks($req, $response);
    // Validate response
    $data = $response->returns($req, $user);
    expect($data)->toEqual(Helper::toJSON([
        'data' => [
            'user_email' => 'fake@wpfastendpoints.com',
            'user_url' => 'https://www.wpfastendpoints.com/wp',
            'display_name' => 'André Gil',
        ],
        'cap_key' => 'wp_capabilities',
    ]));
})->with([
    LoadSchema::FromFile,
    LoadSchema::FromArray,
])->group('response', 'returns');

test('Invalid additionalProperties field', function () {
    Functions\when('esc_html__')->returnArg();
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1.'/'.$path2;
    });
    $response = new Response('Invalid/InvalidAdditionalProperties', null);
    $response->appendSchemaDir(\SCHEMAS_DIR);
    // Create WP_REST_Request mock
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_route')
        ->andReturn('user');
    $data = $response->returns($req, 'my-response');
    expect($data)
        ->toBeInstanceOf(WpError::class)
        ->toHaveProperty('code', 500)
        ->toHaveProperty('message', 'Invalid response schema: additionalProperties must be either an object or boolean but \'string\' given')
        ->toHaveProperty('data', ['status' => 500]);
})->group('response', 'returns');

test('Skipping response validation via hook', function () {
    $response = new Response(['my-schema']);
    // Create WP_REST_Request mock
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_route')
        ->andReturn('user');
    Filters\expectApplied('fastendpoints_response_is_to_validate')
        ->once()
        ->andReturn(false);
    $data = $response->returns($req, 'my-response');
    expect($data)->toEqual('my-response');
})->group('response', 'returns');

test('Skipping response validation when empty schema given', function () {
    $response = new Response([]);
    // Create WP_REST_Request mock
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_route')
        ->andReturn('user');
    Filters\expectApplied('fastendpoints_response_is_to_validate')
        ->once()
        ->with(true, $response);
    $data = $response->returns($req, 'my-response');
    expect($data)->toEqual('my-response');
})->group('response', 'returns');

test('SchemaException raised during validation', function () {
    Functions\when('esc_html__')->returnArg();
    $schema = Helpers::loadSchema(\SCHEMAS_DIR.'Basics/Array');
    $mockedValidator = Mockery::mock(Validator::class)
        ->shouldReceive('validate')
        ->andThrow(new ParseException('my-test-error'))
        ->getMock();
    $mockedResponse = Mockery::mock(Response::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('getContents')
        ->andReturn($schema)
        ->getMock();
    // Create WP_REST_Request mock
    $req = Mockery::mock('WP_REST_Request');
    Filters\expectApplied('fastendpoints_response_is_to_validate')
        ->once()
        ->with(true, $mockedResponse);
    Filters\expectApplied('fastendpoints_response_validation_data')
        ->once()
        ->with(Mockery::any(), $req, $mockedResponse);
    Filters\expectApplied('fastendpoints_response_validator')
        ->once()
        ->with(Mockery::type(Validator::class), Mockery::any(), $req, $mockedResponse)
        ->andReturn($mockedValidator);
    Helpers::setNonPublicClassProperty($mockedResponse, 'suffix', 'fastendpoints_response');
    $data = $mockedResponse->returns($req, [1, 2, 3, 4, 5]);
    expect($data)->toBeInstanceOf(WpError::class)
        ->toHaveProperty('code', 500)
        ->toHaveProperty('message', 'Invalid response schema: my-test-error')
        ->toHaveProperty('data', ['status' => 500]);
    $this->assertEquals(Filters\applied('fastendpoints_response_is_valid'), 0);
    $this->assertEquals(Filters\applied('fastendpoints_response_after_validation'), 0);
})->group('response', 'returns');

test('Validation always failing via hook', function () {
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1.'/'.$path2;
    });
    Functions\when('esc_html__')->returnArg();
    $response = new Response('Basics/Double', true);
    $response->appendSchemaDir(\SCHEMAS_DIR);
    // Create WP_REST_Request mock
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_route')
        ->andReturn('value');
    // Loose check for filters being called
    Filters\expectApplied('fastendpoints_response_is_to_validate')
        ->once();
    Filters\expectApplied('fastendpoints_response_contents')
        ->once();
    Filters\expectApplied('fastendpoints_response_remove_additional_properties')
        ->once();
    Filters\expectApplied('fastendpoints_response_validation_data')
        ->once();
    Filters\expectApplied('fastendpoints_response_validator')
        ->once();
    Filters\expectApplied('fastendpoints_response_is_valid')
        ->once()
        ->andReturn(false);
    Filters\expectApplied('fastendpoints_response_on_validation_error')
        ->once()
        ->with(Mockery::type(WpError::class), $req, $response);
    // Validate response
    $data = $response->returns($req, 257.89);
    expect($data)->toBeInstanceOf(WpError::class)
        ->toHaveProperty('code', 422)
        ->toHaveProperty('message', 'Number must be lower than or equal to 1')
        ->toHaveProperty('data', ['status' => 422, 'all_messages' => ['/' => ['Number must be lower than or equal to 1']]]);
    $this->assertEquals(Filters\applied('fastendpoints_response_on_validation_success'), 0);
})->group('response', 'returns');
