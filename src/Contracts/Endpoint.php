<?php

/**
 * Holds interface for registering custom REST endpoints
 *
 * @since 0.9.0
 * @package wp-fastendpoints
 * @license MIT
 */

declare(strict_types=1);

namespace WP\FastEndpoints\Contracts;

/**
 * REST Endpoint interface that registers custom WordPress REST endpoints
 *
 * @since 0.9.0
 * @author André Gil <andre_gil22@hotmail.com>
 */
interface Endpoint
{
	/**
	 * Registers the current endpoint using register_rest_route function.
	 *
	 * NOTE: Expects to be called inside the 'rest_api_init' WordPress action
	 *
	 * @since 0.9.0
	 * @param string $namespace - WordPress REST namespace.
	 * @param string $restBase - Endpoint REST base.
	 * @param array<string> $schemaDirs - Array of directories to look for JSON schemas. Default value: [].
	 * @return true|false - true if successfully registered a REST route or false otherwise.
	 */
	public function register(string $namespace, string $restBase, array $schemaDirs = []): bool;

	/**
	 * Checks if the current user has the given WP capabilities
	 *
	 * @since 0.9.0
	 * @param string|array<mixed> $capabilities - WordPress user capabilities.
	 * @param int $priority - Specifies the order in which the function is executed.
	 * Lower numbers correspond with earlier execution, and functions with the same priority
	 * are executed in the order in which they were added. Default value: 10.
	 * @return Endpoint
	 */
	public function hasCap($capabilities, int $priority = 10): Endpoint;

	/**
	 * Adds a schema validation to the validationHandlers, which will be later called in advance to
	 * validate a REST request according to the given JSON schema.
	 *
	 * @since 0.9.0
	 * @param string|array<mixed> $schema - Filepath to the JSON schema or a JSON schema as an array.
	 * @param int $priority - Specifies the order in which the function is executed.
	 * Lower numbers correspond with earlier execution, and functions with the same priority
	 * are executed in the order in which they were added. Default value: 10.
	 * @return Endpoint
	 */
	public function schema($schema, int $priority = 10): Endpoint;

	/**
	 * Adds a resource function to the postHandlers, which will be later called to filter the REST response
	 * according to the JSON schema specified. In other words, it will:
	 * 1) Ignore additional properties in WP_REST_Response, avoiding the leakage of unnecessary data and
	 * 2) Making sure that the required data is retrieved.
	 *
	 * @since 0.9.0
	 * @param string|array<mixed> $schema - Filepath to the JSON schema or a JSON schema as an array.
	 * @param int $priority - Specifies the order in which the function is executed.
	 * Lower numbers correspond with earlier execution, and functions with the same priority
	 * are executed in the order in which they were added. Default value: 10.
	 * @throws TypeError - If $schema is neither a string|array.
	 * @return Endpoint
	 */
	public function returns($schema, int $priority = 10): Endpoint;

	/**
	 * Registers a middleware with a given priority
	 *
	 * @since 0.9.0
	 * @param callable $middleware - Function to be used as a middleware.
	 * @param int $priority - Specifies the order in which the function is executed.
	 * Lower numbers correspond with earlier execution, and functions with the same priority
	 * are executed in the order in which they were added. Default value: 10.
	 * @return Endpoint
	 */
	public function middleware(callable $middleware, int $priority = 10): Endpoint;

	/**
	 * Registers an argument
	 *
	 * @since 0.9.0
	 * @param string $name - Name of the argument.
	 * @param array<mixed>|callable $validate - Either an array that WordPress uses (e.g. ['required'=>true, 'default'=>null])
	 * or a validation callback.
	 * @throws TypeError - if $validate is neither an array or callable.
	 * @return Endpoint
	 */
	public function arg(string $name, $validate): Endpoint;

	/**
	 * Registers a permission callback
	 *
	 * @since 0.9.0
	 * @param callable $permissionCb - Method to be called to check current user permissions.
	 * @param int $priority - Specifies the order in which the function is executed.
	 * Lower numbers correspond with earlier execution, and functions with the same priority
	 * are executed in the order in which they were added. Default value: 10.
	 * @return Endpoint
	 */
	public function permission(callable $permissionCb, int $priority = 10): Endpoint;
}
