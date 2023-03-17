<?php

/**
 * Holds the interface to easily register WordPress endpoints that have the same base URL.
 *
 * @version 1.0.0
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
 * @version 1.0.0
 * @author André Gil <andre_gil22@hotmail.com>
 */
interface RouterInterface
{
    /**
     * Adds a new GET endpoint
     *
     * @version 1.0.0
     * @param string $route - Endpoint route.
     * @param callable $handler - User specified handler for the endpoint.
     * @param array<mixed> $args - Same as the WordPress register_rest_route $args parameter.
     * If set it can override the default WP FastEndpoints arguments. Default value: [].
     * @param bool $override - Same as the WordPress register_rest_route $override parameter. Defaul value: false.
     * @return EndpointInterface
     */
    public function get(string $route, callable $handler, array $args = [], bool $override = false): EndpointInterface;

    /**
     * Adds a new POST endpoint
     *
     * @version 1.0.0
     * @param string $route - Endpoint route.
     * @param callable $handler - User specified handler for the endpoint.
     * @param array<mixed> $args - Same as the WordPress register_rest_route $args parameter.
     * If set it can override the default WP FastEndpoints arguments. Default value: [].
     * @param bool $override - Same as the WordPress register_rest_route $override parameter. Defaul value: false.
     * @return EndpointInterface
     */
    public function post(string $route, callable $handler, array $args = [], $override = false): EndpointInterface;

    /**
     * Adds a new PUT endpoint
     *
     * @version 1.0.0
     * @param string $route - Endpoint route.
     * @param callable $handler - User specified handler for the endpoint.
     * @param array<mixed> $args - Same as the WordPress register_rest_route $args parameter.
     * If set it can override the default WP FastEndpoints arguments. Default value: [].
     * @param bool $override - Same as the WordPress register_rest_route $override parameter. Defaul value: false.
     * @return EndpointInterface
     */
    public function put(string $route, callable $handler, array $args = [], bool $override = false): EndpointInterface;

    /**
     * Adds a new DELETE endpoint
     *
     * @version 1.0.0
     * @param string $route - Endpoint route.
     * @param callable $handler - User specified handler for the endpoint.
     * @param array<mixed> $args - Same as the WordPress register_rest_route $args parameter.
     * If set it can override the default WP FastEndpoints arguments. Default value: [].
     * @param bool $override - Same as the WordPress register_rest_route $override parameter. Defaul value: false.
     * @return EndpointInterface
     */
    public function delete(
        string $route,
        callable $handler,
        array $args = [],
        bool $override = false
    ): EndpointInterface;

    /**
     * Includes a router as a sub router
     *
     * @version 1.0.0
     * @param RouterInterface $router - REST sub router.
     */
    public function includeRouter(RouterInterface &$router): void;

    /**
     * Includes a router as a sub router
     *
     * @version 1.0.0
     * @param string $path - Directory path where to look for JSON schemas.
     */
    public function appendSchemaDir(string $path): void;

    /**
     * Adds all actions required to register the defined endpoints
     *
     * @version 1.0.0
     */
    public function register(): void;

    /**
     * Creates and retrieves a new endpoint instance
     *
     * @version 1.0.0
     * @param string $method - POST, GET, PUT or DELETE or a value from WP_REST_Server (e.g. WP_REST_Server::EDITABLE).
     * @param string $route - Endpoint route.
     * @param callable $handler - User specified handler for the endpoint.
     * @param array<mixed> $args - Same as the WordPress register_rest_route $args parameter.
     * If set it can override the default WP FastEndpoints arguments. Default value: [].
     * @param bool $override - Same as the WordPress register_rest_route $override parameter. Defaul value: false.
     * @return EndpointInterface
     */
    public function endpoint(
        string $method,
        string $route,
        callable $handler,
        array $args = [],
        bool $override = false
    ): EndpointInterface;
}
