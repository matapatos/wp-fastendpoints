<?php

/**
 * Holds tests for the ResponseMiddleware class.
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
use Illuminate\Support\Str;
use Mockery;
use Opis\JsonSchema\Exceptions\ParseException;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\ValidationResult;
use Opis\JsonSchema\Validator;
use Wp\FastEndpoints\Helpers\WpError;
use Wp\FastEndpoints\Schemas\ResponseMiddleware;
use Wp\FastEndpoints\Schemas\SchemaResolver;
use Wp\FastEndpoints\Tests\Helpers\Faker;
use Wp\FastEndpoints\Tests\Helpers\Helpers;
use Wp\FastEndpoints\Tests\Helpers\LoadSchema;

beforeEach(function () {
    Monkey\setUp();
});

afterEach(function () {
    Monkey\tearDown();
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
        new ResponseMiddleware($schema, $removeAdditionalProperties);
    })->toThrow(\ValueError::class, sprintf("Invalid removeAdditionalProperties property (%s) '%s'",
        gettype($removeAdditionalProperties), $removeAdditionalProperties));
})->with([LoadSchema::FromFile, LoadSchema::FromArray])->with([
    'true', 'false', 'StRing', 'ntege', 'fake',
])->group('response', 'getContents');

// getSchema() - remove additional properties

test('getSchema retrieves correct schema', function ($loadSchemaFrom, $removeAdditionalProperties) {
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1.'/'.$path2;
    });
    $schema = 'Users/Get';
    $expectedContents = Helpers::loadSchema(\SCHEMAS_DIR.$schema);
    if ($loadSchemaFrom == LoadSchema::FromArray) {
        $schema = $expectedContents;
    }
    $schemaResolver = new SchemaResolver();
    $schemaResolver->registerPrefix('https://www.wp-fastendpoints.com', \SCHEMAS_DIR);
    $response = new ResponseMiddleware($schema, $removeAdditionalProperties, $schemaResolver);
    Filters\expectApplied('fastendpoints_response_remove_additional_properties')
        ->once()
        ->with($removeAdditionalProperties, $response);
    $contents = $response->getSchema();
    if (is_bool($removeAdditionalProperties)) {
        $expectedContents['additionalProperties'] = ! $removeAdditionalProperties;
        $expectedContents['properties']['data']['additionalProperties'] = ! $removeAdditionalProperties;
    } elseif (is_string($removeAdditionalProperties)) {
        $expectedContents['additionalProperties'] = ['type' => $removeAdditionalProperties];
        $expectedContents['properties']['data']['additionalProperties'] = ['type' => $removeAdditionalProperties];
    } elseif (is_null($removeAdditionalProperties) && $loadSchemaFrom == LoadSchema::FromFile) {
        $expectedContents = 'https://www.wp-fastendpoints.com/Users/Get.json';
    }
    expect($contents)->toEqual($expectedContents);
})->with([LoadSchema::FromFile, LoadSchema::FromArray])->with([
    true, false, null, 'string', 'integer', 'number',
    'boolean', 'null', 'object', 'array',
])->group('response', 'getSchema', 'updateSchemaToAcceptOrDiscardAdditionalProperties');

test('Avoids re-updating schema', function () {
    $response = new ResponseMiddleware(['hello'], true);
    expect(Helpers::getNonPublicClassProperty($response, 'loadedSchema'))->toBeNull();
    $response->getSchema();
    expect(Helpers::getNonPublicClassProperty($response, 'loadedSchema'))->toMatchArray(['hello']);
    $response->getSchema();
    expect(Helpers::getNonPublicClassProperty($response, 'loadedSchema'))->toMatchArray(['hello']);
    $this->assertEquals(Filters\applied('fastendpoints_response_remove_additional_properties'), 1);
})->group('response', 'getSchema');

// updateSchemaToAcceptOrDiscardAdditionalProperties

test('Ignore removing properties if schema is empty or doesnt have a type object', function ($schema) {
    $response = new ResponseMiddleware($schema, true);
    Filters\expectApplied('fastendpoints_response_remove_additional_properties')
        ->once()
        ->with(true, $response);
    Helpers::setNonPublicClassProperty($response, 'loadedSchema', $schema);
    Helpers::invokeNonPublicClassMethod($response, 'updateSchemaToAcceptOrDiscardAdditionalProperties');
    expect(Helpers::getNonPublicClassProperty($response, 'loadedSchema'))->toMatchArray($schema);
})->with([[[]], [[['type' => 'hello']]]])->group('response', 'updateSchemaToAcceptOrDiscardAdditionalProperties');

// returns()

function looseExpectAllReturnHooks($req, $response)
{
    Filters\expectApplied('fastendpoints_response_is_to_validate')
        ->once()
        ->with(true, $response);
    Filters\expectApplied('fastendpoints_response_remove_additional_properties')
        ->once()
        ->with(Mockery::any(), $response);
    Filters\expectApplied('fastendpoints_response_validation_data')
        ->once()
        ->with(Mockery::any(), $req, $response);
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
    $schema = 'https://www.wp-fastendpoints.com/Basics/'.$schemaName;
    if ($loadSchemaFrom == LoadSchema::FromArray) {
        $schema = Helpers::loadSchema(\SCHEMAS_DIR.'Basics/'.$schemaName);
    }
    $schemaResolver = new SchemaResolver();
    $schemaResolver->registerPrefix('https://www.wp-fastendpoints.com', \SCHEMAS_DIR);
    $response = new ResponseMiddleware($schema, true, $schemaResolver);
    // Create WP_REST_Request mock
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_route')
        ->andReturn('value');
    // Check all filters are called
    Filters\expectApplied('fastendpoints_response_is_to_validate')
        ->once()
        ->with(true, $response);
    Filters\expectApplied('fastendpoints_response_remove_additional_properties')
        ->once()
        ->with(true, $response);
    Filters\expectApplied('fastendpoints_response_validation_data')
        ->once()
        ->with($value, $req, $response);
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
    $restResponse = new \WP_REST_Response($value);
    $data = $response->onResponse($req, $restResponse);
    expect($data)->toBeNull()
        ->and($restResponse->data)->toEqual($value);
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
    $schema = 'https://www.wp-fastendpoints.com/Users/Get.json';
    if ($loadSchemaFrom == LoadSchema::FromArray) {
        $schema = Helpers::loadSchema(\SCHEMAS_DIR.'Users/Get.json');
    }
    $schemaResolver = new SchemaResolver();
    $schemaResolver->registerPrefix('https://www.wp-fastendpoints.com', \SCHEMAS_DIR);
    $response = new ResponseMiddleware($schema, true, $schemaResolver);
    $user = Faker::getWpUser();
    // Create WP_REST_Request mock
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_route')
        ->andReturn('user');
    // Expected hooks to be applied
    looseExpectAllReturnHooks($req, $response);
    $restResponse = new \WP_REST_Response($user);
    $data = $response->onResponse($req, $restResponse);
    expect($data)->toBeNull()
        ->and($restResponse->data)->toEqual(Helper::toJSON([
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
    $schema = 'https://www.wp-fastendpoints.com/Users/Get.json';
    if ($loadSchemaFrom == LoadSchema::FromArray) {
        $schema = Helpers::loadSchema(\SCHEMAS_DIR.'Users/Get');
    }
    $schemaResolver = new SchemaResolver();
    $schemaResolver->registerPrefix('https://www.wp-fastendpoints.com', \SCHEMAS_DIR);
    $response = new ResponseMiddleware($schema, false, $schemaResolver);
    $user = Faker::getWpUser();
    // Create WP_REST_Request mock
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_route')
        ->andReturn('user');
    // Expected hooks to be applied
    looseExpectAllReturnHooks($req, $response);
    // Validate response
    $restResponse = new \WP_REST_Response($user);
    $data = $response->onResponse($req, $restResponse);
    expect($data)->toBeNull()
        ->and($restResponse->data)->toEqual(Helper::toJSON($user));
})->with([
    LoadSchema::FromFile,
    LoadSchema::FromArray,
])->group('response', 'returns');

test('Ignores additional properties expect a given type in returns', function ($loadSchemaFrom, $type, $expectedData) {
    Functions\when('esc_html__')->returnArg();
    Functions\when('path_join')->alias(function ($path1, $path2) {
        return $path1.'/'.$path2;
    });
    $schema = 'https://www.wp-fastendpoints.com/Users/Get.json';
    if ($loadSchemaFrom == LoadSchema::FromArray) {
        $schema = Helpers::loadSchema(\SCHEMAS_DIR.'Users/Get');
    }
    $schemaResolver = new SchemaResolver();
    $schemaResolver->registerPrefix('https://www.wp-fastendpoints.com', \SCHEMAS_DIR);
    $response = new ResponseMiddleware($schema, $type, $schemaResolver);
    $user = Faker::getWpUser();
    $user['is_admin'] = true;
    // Create WP_REST_Request mock
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_route')
        ->andReturn('user');
    // Expected hooks to be applied
    looseExpectAllReturnHooks($req, $response);
    // Validate response
    $restResponse = new \WP_REST_Response($user);
    $data = $response->onResponse($req, $restResponse);
    $expectedData = array_merge(['data' => [
        'user_email' => 'fake@wpfastendpoints.com',
        'user_url' => 'https://www.wpfastendpoints.com/wp',
        'display_name' => 'André Gil',
    ]], $expectedData);
    expect($data)->toBeNull()
        ->and($restResponse->data)->toEqual(Helper::toJSON($expectedData));
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
    $schema = 'https://www.wp-fastendpoints.com/Users/WithAdditionalProperties.json';
    if ($loadSchemaFrom == LoadSchema::FromArray) {
        $schema = Helpers::loadSchema(\SCHEMAS_DIR.'Users/WithAdditionalProperties.json');
    }
    $schemaResolver = new SchemaResolver();
    $schemaResolver->registerPrefix('https://www.wp-fastendpoints.com', \SCHEMAS_DIR);
    $response = new ResponseMiddleware($schema, null, $schemaResolver);
    $user = Faker::getWpUser();
    // Create WP_REST_Request mock
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_route')
        ->andReturn('user');
    // Expected hooks to be applied
    looseExpectAllReturnHooks($req, $response);
    // Validate response
    $restResponse = new \WP_REST_Response($user);
    $data = $response->onResponse($req, $restResponse);
    expect($data)->toBeNull()
        ->and($restResponse->data)
        ->toEqual(Helper::toJSON([
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
    $schemaResolver = new SchemaResolver();
    $schemaResolver->registerPrefix('https://www.wp-fastendpoints.com', \SCHEMAS_DIR);
    $schema = 'Invalid/InvalidAdditionalProperties';
    $response = new ResponseMiddleware($schema, null, $schemaResolver);
    // Create WP_REST_Request mock
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_route')
        ->andReturn('user');
    $data = $response->onResponse($req, new \WP_REST_Response('my-response'));
    expect($data)
        ->toBeInstanceOf(WpError::class)
        ->toHaveProperty('code', 500)
        ->toHaveProperty('message', 'Invalid response schema: additionalProperties must be either an object or boolean but \'string\' given')
        ->toHaveProperty('data', ['status' => 500]);
})->group('response', 'returns');

test('Skipping response validation via hook', function () {
    $response = new ResponseMiddleware(['my-schema']);
    // Create WP_REST_Request mock
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_route')
        ->andReturn('user');
    Filters\expectApplied('fastendpoints_response_is_to_validate')
        ->once()
        ->andReturn(false);
    $data = $response->onResponse($req, new \WP_REST_Response('my-response'));
    expect($data)->toBeNull();
})->group('response', 'returns');

test('Skipping response validation when empty schema given', function () {
    $response = new ResponseMiddleware([]);
    // Create WP_REST_Request mock
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_route')
        ->andReturn('user');
    Filters\expectApplied('fastendpoints_response_is_to_validate')
        ->once()
        ->with(true, $response);
    $data = $response->onResponse($req, new \WP_REST_Response('my-response'));
    expect($data)->toBeNull();
})->group('response', 'returns');

test('SchemaException raised during validation', function () {
    Functions\when('esc_html__')->returnArg();
    $schema = Helpers::loadSchema(\SCHEMAS_DIR.'Basics/Array');
    $mockedValidator = Mockery::mock(Validator::class);
    $mockedValidator
        ->shouldReceive('validate')
        ->andThrow(new ParseException('my-test-error'));
    $mockedResponse = Mockery::mock(ResponseMiddleware::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('getSchema')
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
    Helpers::setNonPublicClassProperty($mockedResponse, 'suffix', 'fastendpoints_response');
    Helpers::setNonPublicClassProperty($mockedResponse, 'validator', $mockedValidator);
    $data = $mockedResponse->onResponse($req, new \WP_REST_Response([1, 2, 3, 4, 5]));
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
    $schemaResolver = new SchemaResolver();
    $schemaResolver->registerPrefix('https://www.wp-fastendpoints.com', \SCHEMAS_DIR);
    $response = new ResponseMiddleware('https://www.wp-fastendpoints.com/Basics/Double.json', true, $schemaResolver);
    // Create WP_REST_Request mock
    $req = Mockery::mock('WP_REST_Request');
    $req->shouldReceive('get_route')
        ->andReturn('value');
    // Loose check for filters being called
    Filters\expectApplied('fastendpoints_response_is_to_validate')
        ->once();
    Filters\expectApplied('fastendpoints_response_remove_additional_properties')
        ->once();
    Filters\expectApplied('fastendpoints_response_validation_data')
        ->once();
    Filters\expectApplied('fastendpoints_response_is_valid')
        ->once()
        ->andReturn(false);
    Filters\expectApplied('fastendpoints_response_on_validation_error')
        ->once()
        ->with(Mockery::type(WpError::class), $req, $response);
    // Validate response
    $data = $response->onResponse($req, new \WP_REST_Response(257.89));
    expect($data)->toBeInstanceOf(WpError::class)
        ->toHaveProperty('code', 422)
        ->toHaveProperty('message', 'Number must be lower than or equal to 1')
        ->toHaveProperty('data', ['status' => 422, 'all_messages' => ['/' => ['Number must be lower than or equal to 1']]]);
    $this->assertEquals(Filters\applied('fastendpoints_response_on_validation_success'), 0);
})->group('response', 'returns');
