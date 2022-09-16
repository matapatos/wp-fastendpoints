<?php

/**
 * Holds logic to easily register WordPress endpoints that have the same base URL.
 *
 * @since 0.9.0
 *
 * @package wp-fastendpoints
 * @license MIT
 */

declare(strict_types=1);

namespace WP\FastEndpoints;

use WP\FastEndpoints\Contracts\Router as RouterContract;
use WP\FastEndpoints\Contracts\Endpoint as EndpointContract;

/**
 * A Router can help developers in creating groups of endpoints. This way developers can aggregate
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
 *
 * @author André Gil <andre_gil22@hotmail.com>
 */
class Router implements RouterContract
{
	/**
	 * Router rest base
	 *
	 * @since 0.9.0
	 *
	 * @var string
	 */
	protected string $base;

	/**
	 * Flag to determine if the current router has already being built.
	 * This is important to prevent building a subRouter before the parent
	 * finishes the building process
	 *
	 * @since 0.9.0
	 *
	 * @var bool
	 */
	protected bool $registered = false;

	/**
	 * Parent router
	 *
	 * @since 0.9.0
	 *
	 * @var ?Router
	 */
	protected ?Router $parent = null;

	/**
	 * Sub routers
	 *
	 * @since 0.9.0
	 *
	 * @var array<Router>
	 */
	protected array $subRouters = [];

	/**
	 * REST Router endpoints
	 *
	 * @since 0.9.0
	 *
	 * @var array<Endpoint>
	 */
	protected array $endpoints = [];

	/**
	 * Schema directory path
	 *
	 * @since 0.9.0
	 *
	 * @var array<string>
	 */
	protected array $schemaDirs = [];

	/**
	 * Router version - used only if it's a parent router
	 *
	 * @since 0.9.0
	 *
	 * @var string
	 */
	protected string $version;

	/**
	 * Creates a new Router instance
	 *
	 * @param string $base - Router base path - if this router is the parent router would be used as
	 * the namespace. Default value: 'api'.
	 * @param string $version - Router version. Default value: ''.
	 */
	public function __construct(string $base = 'api', string $version = '')
	{
		$this->base = $base;
		$this->version = $version;
	}

	/**
	 * Adds a new GET endpoint
	 *
	 * @since 0.9.0
	 *
	 * @param string $route - Endpoint route.
	 * @param callable $handler - User specified handler for the endpoint.
	 * @param array<mixed> $args - Same as the WordPress register_rest_route $args parameter. If set it can override the default
	 * WP FastEndpoints arguments. Default value: [].
	 * @param bool $override - Same as the WordPress register_rest_route $override parameter. Defaul value: false.
	 * @return EndpointContract
	 */
	public function get(string $route, callable $handler, array $args = [], bool $override = false): EndpointContract
	{
		return $this->endpoint('GET', $route, $handler, $args);
	}

	/**
	 * Adds a new POST endpoint
	 *
	 * @since 0.9.0
	 *
	 * @param string $route - Endpoint route.
	 * @param callable $handler - User specified handler for the endpoint.
	 * @param array<mixed> $args - Same as the WordPress register_rest_route $args parameter. If set it can override the default
	 * WP FastEndpoints arguments. Default value: [].
	 * @param bool $override - Same as the WordPress register_rest_route $override parameter. Defaul value: false.
	 * @return EndpointContract
	 */
	public function post(string $route, callable $handler, array $args = [], $override = false): EndpointContract
	{
		return $this->endpoint('POST', $route, $handler, $args);
	}

	/**
	 * Adds a new PUT endpoint
	 *
	 * @since 0.9.0
	 *
	 * @param string $route - Endpoint route.
	 * @param callable $handler - User specified handler for the endpoint.
	 * @param array<mixed> $args - Same as the WordPress register_rest_route $args parameter. If set it can override the default
	 * WP FastEndpoints arguments. Default value: [].
	 * @param bool $override - Same as the WordPress register_rest_route $override parameter. Defaul value: false.
	 * @return EndpointContract
	 */
	public function put(string $route, callable $handler, array $args = [], bool $override = false): EndpointContract
	{
		return $this->endpoint('PUT', $route, $handler, $args);
	}

	/**
	 * Adds a new DELETE endpoint
	 *
	 * @since 0.9.0
	 *
	 * @param string $route - Endpoint route.
	 * @param callable $handler - User specified handler for the endpoint.
	 * @param array<mixed> $args - Same as the WordPress register_rest_route $args parameter. If set it can override the default
	 * WP FastEndpoints arguments. Default value: [].
	 * @param bool $override - Same as the WordPress register_rest_route $override parameter. Defaul value: false.
	 * @return EndpointContract
	 */
	public function delete(string $route, callable $handler, array $args = [], bool $override = false): EndpointContract
	{
		return $this->endpoint('DELETE', $route, $handler, $args, $override);
	}

	/**
	 * Includes a router as a sub router
	 *
	 * @since 0.9.0
	 *
	 * @param Router $router - REST sub router.
	 */
	public function includeRouter(RouterContract &$router): void
	{
		$router->parent = $this;
		$this->subRouters[] = $router;
	}

	/**
	 * Appends an additional directory where to look for the schema
	 *
	 * @since 0.9.0
	 *
	 * @param string|array<string> $schemaDir - Directory path or an array of directories where to
	 * look for JSON schemas.
	 */
	public function appendSchemaDir($dir): void
	{
		if (!$dir) {
			\wp_die('Invalid schema directory');
		}

		if (!\is_array($dir)) {
			$dir = [$dir];
		}

		foreach ($dir as $d) {
			if (\is_file($d)) {
				\wp_die(\esc_html("Expected a directory with schemas but got a file: {$d}"));
			}

			if (!\is_dir($d)) {
				\wp_die(\esc_html("Schema directory not found: {$d}"));
			}
		}

		$this->schemaDirs = $this->schemaDirs + $dir;
	}

	/**
	 * Adds all actions required to register the defined endpoints
	 *
	 * @since 0.9.0
	 */
	public function register(): void
	{
		if (!\apply_filters('wp_fastendpoints_is_to_register', true, $this)) {
			return;
		}

		if ($this->parent) {
			if (!has_action('rest_api_init', [$this->parent, 'registerEndpoints'])) {
				\wp_die('You are trying to build a sub-router before building the parent router. ' .
				'Call the build() function on the parent router only!');
			}
		} else {
			if (!$this->base) {
				\wp_die('No api namespace specified in the parent router');
			}

			if (!$this->version) {
				\wp_die('No api version specified in the parent router');
			}

			\do_action('wp_fastendpoints_before_register', $this);
		}

		// Build current router endpoints.
		\add_action('rest_api_init', [$this, 'registerEndpoints']);

		// Register each sub router, if any.
		foreach ($this->subRouters as $router) {
			$router->appendSchemaDir($this->schemaDirs);
			$router->register();
		}

		if (!$this->parent) {
			\do_action('wp_fastendpoints_after_register', $this);
		}
	}

	/**
	 * Registers the current router REST endpoints
	 *
	 * @since 0.9.0
	 * @internal
	 */
	public function registerEndpoints(): void
	{
		$namespace = $this->getNamespace();
		$restBase = $this->getRestBase();
		foreach ($this->endpoints as $e) {
			$e->register($namespace, $restBase, $this->schemaDirs);
		}
		$this->registered = true;
	}

	/**
	 * Retrieves the base router namespace for each endpoint
	 *
	 * @since 0.9.0
	 *
	 * @param bool $isToApplyFilters - Flag used to ignore wp_fastendpoints filters
	 * (i.e. this is needed to disable multiple calls to the filter given that it's a
	 * recursive function). Default value: true.
	 * @return string
	 */
	protected function getNamespace($isToApplyFilters = true): string
	{
		if ($this->parent) {
			return $this->parent->getNamespace(false);
		}

		$namespace = \trim($this->base, '/');
		if ($this->version) {
			$namespace .= '/' . \trim($this->version, '/');
		}

		// Ignore recursive call to apply_filters - without it, would be anoying for developers.
		if (!$isToApplyFilters) {
			return $namespace;
		}

		return \apply_filters('wp_fastendpoints_router_namespace', $namespace, $this);
	}

	/**
	 * Retrieves the base REST path of the current router, if any. The base is what follows
	 * the namespace and is before the endpoint route.
	 *
	 * @since 0.9.0
	 *
	 * @param bool $isToApplyFilters - Flag used to ignore wp_fastendpoints filters
	 * (i.e. this is needed to disable multiple calls to the filter given that it's a
	 * recursive function). Default value: true.
	 * @return string
	 */
	protected function getRestBase(bool $isToApplyFilters = true): string
	{
		if (!$this->parent) {
			return '';
		}

		$restBase = $this->parent->getRestBase(false);
		if ($restBase) {
			$restBase .= '/';
		}

		$restBase = \trim($this->base, '/');
		if ($this->version) {
			$restBase .= '/' . \trim($this->version, '/');
		}

		// Ignore recursive call to apply_filters - without it, would be anoying for developers.
		if (!$isToApplyFilters) {
			return $restBase;
		}

		return \apply_filters('wp_fastendpoints_router_rest_base', $restBase, $this);
	}

	/**
	 * Creates and retrieves a new endpoint instance
	 *
	 * @since 0.9.0
	 *
	 * @param string $method - POST, GET, PUT or DELETE or a value from WP_REST_Server (e.g. WP_REST_Server::EDITABLE).
	 * @param string $route - Endpoint route.
	 * @param callable $handler - User specified handler for the endpoint.
	 * @param array<mixed> $args - Same as the WordPress register_rest_route $args parameter. If set it can override the default
	 * WP FastEndpoints arguments. Default value: [].
	 * @param bool $override - Same as the WordPress register_rest_route $override parameter. Defaul value: false.
	 * @return EndpointContract
	 */
	public function endpoint(
		string $method,
		string $route,
		callable $handler,
		array $args = [],
		bool $override = false
	): EndpointContract {
		$endpoint = new Endpoint($method, $route, $handler, $args, $override);
		$this->endpoints[] = $endpoint;
		return $endpoint;
	}
}
