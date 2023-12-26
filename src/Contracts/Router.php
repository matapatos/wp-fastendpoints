<?php

/**
 * Holds the interface to easily register WordPress endpoints that have the same base URL.
 *
 * @since 0.9.0
 * @package wp-fastendpoints
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Contracts;

/**
 * An interface that helps developers in creating groups of endpoints. This way developers can aggregate
 * closely related endpoints in the same router. Example:
 *
 * $usersRouter = new Router('users');
 * $usersRouter->get(...); // Retrieve a user
 * $usersRouter->put(...); // Update a user
 *
 * $postsRouter = new Router('posts');
 * $postsRouter->get(...); // Retrieve a post
 * $postsRouter->put(...); // Update a post
 *
 * @since 0.9.0
 * @author André Gil <andre_gil22@hotmail.com>
 */
interface Router
{
	/**
	 * Adds a new GET endpoint
	 *
	 * @since 0.9.0
	 * @param string $route - Endpoint route.
	 * @param callable $handler - User specified handler for the endpoint.
	 * @param array<mixed> $args - Same as the WordPress register_rest_route $args parameter. If set it can override the default
	 * WP FastEndpoints arguments. Default value: [].
	 * @param bool $override - Same as the WordPress register_rest_route $override parameter. Defaul value: false.
	 * @return Endpoint
	 */
	public function get(string $route, callable $handler, array $args = [], bool $override = false): Endpoint;

	/**
	 * Adds a new POST endpoint
	 *
	 * @since 0.9.0
	 * @param string $route - Endpoint route.
	 * @param callable $handler - User specified handler for the endpoint.
	 * @param array<mixed> $args - Same as the WordPress register_rest_route $args parameter. If set it can override the default
	 * WP FastEndpoints arguments. Default value: [].
	 * @param bool $override - Same as the WordPress register_rest_route $override parameter. Defaul value: false.
	 * @return Endpoint
	 */
	public function post(string $route, callable $handler, array $args = [], $override = false): Endpoint;

	/**
	 * Adds a new PUT endpoint
	 *
	 * @since 0.9.0
	 * @param string $route - Endpoint route.
	 * @param callable $handler - User specified handler for the endpoint.
	 * @param array<mixed> $args - Same as the WordPress register_rest_route $args parameter. If set it can override the default
	 * WP FastEndpoints arguments. Default value: [].
	 * @param bool $override - Same as the WordPress register_rest_route $override parameter. Defaul value: false.
	 * @return Endpoint
	 */
	public function put(string $route, callable $handler, array $args = [], bool $override = false): Endpoint;

	/**
	 * Adds a new DELETE endpoint
	 *
	 * @since 0.9.0
	 * @param string $route - Endpoint route.
	 * @param callable $handler - User specified handler for the endpoint.
	 * @param array<mixed> $args - Same as the WordPress register_rest_route $args parameter. If set it can override the default
	 * WP FastEndpoints arguments. Default value: [].
	 * @param bool $override - Same as the WordPress register_rest_route $override parameter. Defaul value: false.
	 * @return Endpoint
	 */
	public function delete(string $route, callable $handler, array $args = [], bool $override = false): Endpoint;

	/**
	 * Includes a router as a sub router
	 *
	 * @since 0.9.0
	 * @param Router $router - REST sub router.
	 */
	public function includeRouter(Router &$router): void;

	/**
	 * Includes a router as a sub router
	 *
	 * @since 0.9.0
	 * @param string|array<string> $path - Directory path where to look for JSON schemas.
	 */
	public function appendSchemaDir($path): void;

	/**
	 * Adds all actions required to register the defined endpoints
	 *
	 * @since 0.9.0
	 */
	public function register(): void;

	/**
	 * Creates and retrieves a new endpoint instance
	 *
	 * @since 0.9.0
	 * @param string $method - POST, GET, PUT or DELETE or a value from WP_REST_Server (e.g. WP_REST_Server::EDITABLE).
	 * @param string $route - Endpoint route.
	 * @param callable $handler - User specified handler for the endpoint.
	 * @param array<mixed> $args - Same as the WordPress register_rest_route $args parameter. If set it can override the default
	 * WP FastEndpoints arguments. Default value: [].
	 * @param bool $override - Same as the WordPress register_rest_route $override parameter. Defaul value: false.
	 * @return Endpoint
	 */
	public function endpoint(
		string $method,
		string $route,
		callable $handler,
		array $args = [],
		bool $override = false
	): Endpoint;
}
