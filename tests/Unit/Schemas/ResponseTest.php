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
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

use Tests\Wp\FastEndpoints\Helpers\Helpers;
use Tests\Wp\FastEndpoints\Helpers\FileSystemCache;

use Wp\FastEndpoints\Schemas\Response;

afterEach(function () {
	Mockery::close();
	vfsStream::setup();
});

test('Creating Response instance with $schema as a string', function () {
	expect(new Response('User/Get'))->toBeInstanceOf(Response::class);
});

test('Creating Response instance with $schema as an array', function () {
	expect(new Response([]))->toBeInstanceOf(Response::class);
});

test('Creating Response instance with an invalid $schema type', function ($value) {
	expect(fn() => new Response($value))->toThrow(TypeError::class);
})->with([1, 1.67, true, false]);

test('Checking correct Response suffix', function () {
	$response = new Response([]);
	$suffix = Helpers::invokeNonPublicClassMethod($response, 'getSuffix');
	expect($suffix)->toBe('response');
});

test('Passing invalid schema directories to appendSchemaDir()', function (...$invalidDirectories) {
	$response = new Response([]);
	expect(fn() => Helpers::invokeNonPublicClassMethod($response, 'appendSchemaDir', $invalidDirectories))->toThrow(TypeError::class);
})->with([
	125, 62.5, 'fakedirectory', 'fake/dir/ups', '',
	['', ''], ['fake', '/fake/ups'], [1,2],
]);

test('Passing both valid and invalid schema directories to appendSchemaDir()', function (...$invalidDirectories) {
	$response = new Response([]);
	$cache = new FileSystemCache();
	$invalidDirectories[0] = $cache->touchDirectory($invalidDirectories[0]);

	expect(fn() => Helpers::invokeNonPublicClassMethod($response, 'appendSchemaDir', $invalidDirectories))->toThrow(TypeError::class);
})->with([
	['valid', 'invalid'], ['fake', 'fake/ups'], ['yup', 'true', 'yes'],
]);

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
		->toMatchArray($validDirectories);
})->with([
	'Schemas', 'Others/Schemas', 'Random/Another/Schemas',
	['Hey', 'Dude'], ['Great/Man', 'Yes/ItWorks'],
]);

test('Trying to retrieve a json schema filepath without providing a filename', function () {
	$response = new Response([]);
	expect(fn() => Helpers::invokeNonPublicClassMethod($response, 'getValidSchemaFilepath'))
		->toThrow(Exception::class);
});

test('Trying to retrieve a json schema filepath of a file that doesn\'t exists', function () {
	$response = new Response('random.json');
	expect(fn() => Helpers::invokeNonPublicClassMethod($response, 'getValidSchemaFilepath'))
		->toThrow(Exception::class);
});

test('Retrieving a json schema filepath when providing a full filepath', function () {
	$cache = new FileSystemCache();
	$schemaFullpath = $cache->store('schema.json', '{}');
	$response = new Response($schemaFullpath);
	expect(Helpers::invokeNonPublicClassMethod($response, 'getValidSchemaFilepath'))
		->toBe($schemaFullpath);
});

test('Retrieving a json schema filepath when providing a relative filepath', function (string $schemaRelativePath) {
	$cache = new FileSystemCache();
	$schemaFullpath = $cache->store(Str::finish($schemaRelativePath, '.json'), '{}');
	$response = new Response($schemaRelativePath);
	Helpers::setNonPublicClassProperty($response, 'schemaDirs', [$cache->getRootDir()]);
	expect(Helpers::invokeNonPublicClassMethod($response, 'getValidSchemaFilepath'))
		->toBe($schemaFullpath);
})->with(['schema', 'schema.json']);

test('returns() matches expected return value - Basic', function ($value) {
	$schemaName = Str::ucfirst(Str::lower(gettype($value)));
	$response = new Response('Basics/' . $schemaName);
	$response->appendSchemaDir(\SCHEMAS_DIR);
	// Create WP_REST_Request mock
	$req = Mockery::mock('WP_REST_Request');
	$req->shouldReceive('get_route')
		->andReturn('value');
	// Validate response
	$data = $response->returns($req, $value);
	expect($data)->toEqual($value);
})->with([
	0.674,
	255,
	true,
	null,
	"this is a string",
	[[1,2,3,4,5]],
	[(object) [
		"stringVal" => "hello",
		"intVal" 	=> 1,
		"arrayVal" 	=> [1,2,3],
		"doubleVal" => 0.82,
		"boolVal" 	=> false,
	]],
]);

// test('Validating if Response returns() ignores unnecessary properties', function () {
// 	$response = new Response('User/Get');
// 	$response->appendSchemaDir(\SCHEMAS_DIR);
// 	// Similar fields as a WP_User
// 	$user = [
// 		"data" => [
// 			"ID" => "1",
// 			"user_login" => "fake_username",
// 			"user_pass" => "fake_pass",
// 			"user_nicename" => "1",
// 			"user_email" => "fake@wpfastendpoints.com",
// 			"user_url" => "",
// 			"user_registered" => "2022-08-29 13 => 46 => 28",
// 			"user_activation_key" => "random_key",
// 			"user_status" => "0",
// 			"display_name" => "André Gil",
// 			"spam" => "0",
// 			"deleted" => "0",
// 			"first" => [
// 				"second" => [
// 					"third" => [
// 						"forth" => true,
// 					],
// 				],
// 			],
// 		],
// 		"ID" => 1,
// 		"caps" => ["administrator" => true],
// 		"cap_key" => "wp_capabilities",
// 		"roles" => ["administrator"],
// 		"filter" => null,
// 	];
// 	// Create WP_REST_Request mock
// 	$req = Mockery::mock('WP_REST_Request');
// 	$req->shouldReceive('get_route')
// 		->andReturn('user');
// 	// Validate response
// 	$data = (array) $response->returns($req, $user);
// 	var_dump($data);
// 	expect($data)->toMatchArray([
// 		"data" => [
// 			"ID" => "1",
// 			"user_email" => "fake@wpfastendpoints.com",
// 			"user_url" => "",
// 			"display_name" => "André Gil",
// 		],
// 		"is_admin" => true,
// 	]);
// });
