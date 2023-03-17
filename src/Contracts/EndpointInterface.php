<?php

/**
 * Holds interface for registering custom REST endpoints
 *
 * @version 1.0.0
 * @package wp-fastendpoints
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Contracts;

/**
 * REST Endpoint interface that registers custom WordPress REST endpoints
 *
 * @version 1.0.0
 * @author André Gil <andre_gil22@hotmail.com>
 */
interface EndpointInterface
{
    /**
     * Registers the current endpoint using register_rest_route function.
     *
     * NOTE: Expects to be called inside the 'rest_api_init' WordPress action
     *
     * @version 1.0.0
     * @param string $namespace - WordPress REST namespace.
     * @param string $restBase - Endpoint REST base.
     * @param array<string> $schemaDirs - Array of directories to look for JSON schemas. Default value: [].
     * @return true|false - true if successfully registered a REST route or false otherwise.
     */
    public function register(string $namespace, string $restBase, array $schemaDirs = []): bool;

    /**
     * Checks if the current user has the given WP capabilities
     *
     * @version 1.0.0
     * @param string|array<mixed> $capabilities - WordPress user capabilities.
     * @param int $priority - Specifies the order in which the function is executed.
     * Lower numbers correspond with earlier execution, and functions with the same priority
     * are executed in the order in which they were added. Default value: 10.
     * @return EndpointInterface
     */
    public function hasCap($capabilities, int $priority = 10): EndpointInterface;

    /**
     * Adds a schema validation to the validationHandlers, which will be later called in advance to
     * validate a REST request according to the given JSON schema.
     *
     * @version 1.0.0
     * @param string|array<mixed> $schema - Filepath to the JSON schema or a JSON schema as an array.
     * @param int $priority - Specifies the order in which the function is executed.
     * Lower numbers correspond with earlier execution, and functions with the same priority
     * are executed in the order in which they were added. Default value: 10.
     * @return EndpointInterface
     */
    public function schema($schema, int $priority = 10): EndpointInterface;

    /**
     * Adds a resource function to the postHandlers, which will be later called to filter the REST response
     * according to the JSON schema specified. In other words, it will:
     * 1) Ignore additional properties in WP_REST_Response, avoiding the leakage of unnecessary data and
     * 2) Making sure that the required data is retrieved.
     *
     * @version 1.0.0
     * @param string|array<mixed> $schema - Filepath to the JSON schema or a JSON schema as an array.
     * @param int $priority - Specifies the order in which the function is executed.
     * Lower numbers correspond with earlier execution, and functions with the same priority
     * are executed in the order in which they were added. Default value: 10.
     * @throws TypeError - If $schema is neither a string|array.
     * @return EndpointInterface
     */
    public function returns($schema, int $priority = 10): EndpointInterface;

    /**
     * Registers a middleware with a given priority
     *
     * @version 1.0.0
     * @param callable $middleware - Function to be used as a middleware.
     * @param int $priority - Specifies the order in which the function is executed.
     * Lower numbers correspond with earlier execution, and functions with the same priority
     * are executed in the order in which they were added. Default value: 10.
     * @return EndpointInterface
     */
    public function middleware(callable $middleware, int $priority = 10): EndpointInterface;

    /**
     * Registers an argument
     *
     * @version 1.0.0
     * @param string $name - Name of the argument.
     * @param array<mixed>|callable $validate - Either an array that WordPress uses
     * (e.g. ['required'=>true, 'default'=>null]) or a validation callback.
     * @throws TypeError - if $validate is neither an array or callable.
     * @return EndpointInterface
     */
    public function arg(string $name, $validate): EndpointInterface;

    /**
     * Registers a permission callback
     *
     * @version 1.0.0
     * @param callable $permissionCb - Method to be called to check current user permissions.
     * @param int $priority - Specifies the order in which the function is executed.
     * Lower numbers correspond with earlier execution, and functions with the same priority
     * are executed in the order in which they were added. Default value: 10.
     * @return EndpointInterface
     */
    public function permission(callable $permissionCb, int $priority = 10): EndpointInterface;
}
