<?php

namespace WP\FastAPI;

use Illuminate\Support\Str;

class Router {

    /**
     * Router namespace
     *
     * @since 0.9.0
     */
    private string $namespace;

    /**
     * Flag to determine if the current router has already being built.
     * This is important to prevent building a subRouter before the parent
     * finishes the building process
     *
     * @since 0.9.0
     */
    private bool $registered = false;

    /**
     * Router namespace
     *
     * @since 0.9.0
     */
    private ?Router $parent = null;

    /**
     * Sub routers
     *
     * @since 0.9.0
     */
    private array $sub_routers = [];

    /**
     * REST Router endpoints
     *
     * @since 0.9.0
     */
    private array $endpoints = [];

    /**
     * Router version - used only if it's a parent router
     *
     * @since 0.9.0
     */
    private $version;

    public function __construct(string $namespace = 'api', string $version = '') {
        $this->namespace = $namespace;
        $this->version = $version;
    }

    /**
     * GET WP Endpoint
     *
     * @since 0.9.0
     */
    public function get(string $route, callable $handler, array $args = []) {
        return $this->endpoint('GET', $route, $handler, $args);
    }

    /**
     * POST WP Endpoint
     *
     * @since 0.9.0
     */
    public function post(string $route, callable $handler, array $args = []) {
        return $this->endpoint('POST', $route, $handler, $args);
    }

    /**
     * PUT WP Endpoint
     *
     * @since 0.9.0
     */
    public function put(string $route, callable $handler, array $args = []) {
        return $this->endpoint('PUT', $route, $handler, $args);
    }

    /**
     * DELETE WP Endpoint
     *
     * @since 0.9.0
     */
    public function delete(string $route, callable $handler, array $args = []) {
        return $this->endpoint('DELETE', $route, $handler, $args);
    }

    /**
     * Includes a router as a sub router
     *
     * @since 0.9.0
     */
    public function include_router(Router &$router) {
        $router->parent = $this;
        $this->sub_routers[] = $router;
    }
    }

    /**
     * Adds all actions required to register the defined endpoints
     *
     * @since 0.9.0
     */ 
    public function register() {
        if ($this->parent) {
            if (!$this->parent->registered) {
                wp_die('You are trying to build a sub-router before building the parent router. ' .
                'Call the build() function on the parent router only!');
            }

            if ($this->namespace) {
                wp_die('No api namespace specified in parent router');
            }

            if ($this->version) {
                wp_die('No api version specified in the parent router');
            }
        }

        // Build current router endpoints
        add_action('rest_api_init', [$this, 'register_endpoints']);

        // Call the build function of each sub router
        foreach ($this->sub_routers as $router) {
            $router->build();
        }
        return true;
    }

    /**
     * Registers the current router REST endpoints
     *
     * NOTE: For internal use only!
     *
     * @since 0.9.0
     */
    public function register_endpoints() {
        $base_namespace = $this->get_base_namespace();
        $endpoint_namespace = $this->get_endpoint_namespace();
        foreach ($this->endpoints as $e) {
            $e->register($base_namespace, $endpoint_namespace, $this->schema_dir);
        }
        $this->registered = true;
    }

    /**
     * Retrieves the base router namespace for each endpoint
     *
     * @since 0.9.0
     */
    private function get_base_namespace($is_to_apply_filters = true) {
        if ($this->parent) {
            return $this->parent->get_namespace(false);
        }

        $namespace = Str::of($this->namespace)
            ->trim('/');
        if ($this->version) {
            $namespace .= '/' . Str::of($this->version)->trim('/');
        }

        // Ignore recursive call to apply_filters - without it, would be anoying for developers
        if (!$is_to_apply_filters) {
            return $namespace;
        }

        return apply_filters('wp_fastapi_router_base_namespace', $namespace, $this);
    }

    /**
     * Retrieves the namespace - if any - after the base namespace. In other words, the namespaces + versions
     * of sub routers
     *
     * @since 0.9.0
     */
    private function get_endpoint_namespace($is_to_apply_filters = true) {
        if (!$this->parent) {
            return '';
        }

        $namespace = $this->parent->get_endpoint_namespace(false);
        if ($namespace) {
            $namespace .= '/';
        }

        $namespace .= Str::of($this->namespace)->trim('/');
        if ($this->version) {
            $namespace .= '/' . Str::of($this->version)->trim('/');
        }

        // Ignore recursive call to apply_filters - without it, would be anoying for developers
        if (!$is_to_apply_filters) {
            return $namespace;
        }

        return apply_filters('wp_fastapi_router_endpoint_namespace', $namespace, $this);
    }

    /**
     * Creates and retrieves a new endpoint instance
     *
     * @since 0.9.0
     */
    private function endpoint(string $method, string $route, callable $handler, array $args = []) {
        $endpoint = new Endpoint($method, $route, $handler, $args);
        $this->endpoints[] = $endpoint;
        return $endpoint;
    }
}
