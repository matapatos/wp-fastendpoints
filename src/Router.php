<?php

namespace WP\FastAPI;

use Illuminate\Support\Str;

class Router {

    /**
     * Router rest base
     * @since 0.9.0
     */
    private string $base;

    /**
     * Flag to determine if the current router has already being built.
     * This is important to prevent building a subRouter before the parent
     * finishes the building process
     * @since 0.9.0
     */
    private bool $registered = false;

    /**
     * Parent router
     * @since 0.9.0
     */
    private ?Router $parent = null;

    /**
     * Sub routers
     * @since 0.9.0
     */
    private array $sub_routers = [];

    /**
     * REST Router endpoints
     * @since 0.9.0
     */
    private array $endpoints = [];

    /**
     * Schema directory path
     * @since 0.9.0
     */
    private array $schema_dirs = [];

    /**
     * Router version - used only if it's a parent router
     * @since 0.9.0
     */
    private $version;

    public function __construct(string $base = 'api', string $version = '') {
        $this->base = $base;
        $this->version = $version;
    }

    /**
     * GET WP Endpoint
     * @since 0.9.0
     */
    public function get(string $route, callable $handler, array $args = []) {
        return $this->endpoint('GET', $route, $handler, $args);
    }

    /**
     * POST WP Endpoint
     * @since 0.9.0
     */
    public function post(string $route, callable $handler, array $args = []) {
        return $this->endpoint('POST', $route, $handler, $args);
    }

    /**
     * PUT WP Endpoint
     * @since 0.9.0
     */
    public function put(string $route, callable $handler, array $args = []) {
        return $this->endpoint('PUT', $route, $handler, $args);
    }

    /**
     * DELETE WP Endpoint
     * @since 0.9.0
     */
    public function delete(string $route, callable $handler, array $args = []) {
        return $this->endpoint('DELETE', $route, $handler, $args);
    }

    /**
     * Includes a router as a sub router
     * @since 0.9.0
     */
    public function include_router(Router &$router) {
        $router->parent = $this;
        $this->sub_routers[] = $router;
    }

    /**
     * Includes a router as a sub router
     * @since 0.9.0
     */
    public function append_schema_dir(string $path) {
        if (!file_exists($path)) {
            wp_die("Schema directory doesn't exists: {$path}");
        }

        if (!is_dir($path)) {
            wp_die("Expected a directory but a file given: {$path}");
        }

        $this->schema_dirs[] = $path;
    }

    /**
     * Adds all actions required to register the defined endpoints
     * @since 0.9.0
     */ 
    public function register() {
        if ($this->parent) {
            if (!$this->parent->registered) {
                wp_die('You are trying to build a sub-router before building the parent router. ' .
                'Call the build() function on the parent router only!');
            }

            if ($this->base) {
                wp_die('No api namespace specified in the parent router');
            }

            if ($this->version) {
                wp_die('No api version specified in the parent router');
            }
        } else {
            do_action('wp_fastapi_before_register', $this);
        }

        // Build current router endpoints
        add_action('rest_api_init', [$this, 'register_endpoints']);

        // Call the register function for each sub router
        foreach ($this->sub_routers as $router) {
            $router->register();
        }

        if (!$this->parent) {
            do_action('wp_fastapi_after_register', $this);
        }
        return true;
    }

    /**
     * Registers the current router REST endpoints
     *
     * NOTE: For internal use only!
     * @since 0.9.0
     */
    public function register_endpoints() {
        $namespace = $this->get_namespace();
        $rest_base = $this->get_rest_base();
        foreach ($this->endpoints as $e) {
            $e->register($namespace, $rest_base, $this->schema_dirs);
        }
        $this->registered = true;
    }

    /**
     * Retrieves the base router namespace for each endpoint
     * @since 0.9.0
     */
    private function get_namespace($is_to_apply_filters = true) {
        if ($this->parent) {
            return $this->parent->get_namespace(false);
        }

        $namespace = Str::of($this->base)
            ->trim('/');
        if ($this->version) {
            $namespace .= '/' . Str::of($this->version)->trim('/');
        }

        // Ignore recursive call to apply_filters - without it, would be anoying for developers
        if (!$is_to_apply_filters) {
            return $namespace;
        }

        return apply_filters('wp_fastapi_router_namespace', $namespace, $this);
    }

    /**
     * Retrieves the base REST path of the current router, if any. This path
     * is what follows the namespace
     * @since 0.9.0
     */
    private function get_rest_base($is_to_apply_filters = true) {
        if (!$this->parent) {
            return '';
        }

        $rest_base = $this->parent->get_endpoint_namespace(false);
        if ($rest_base) {
            $rest_base .= '/';
        }

        $rest_base .= Str::of($this->base)->trim('/');
        if ($this->version) {
            $rest_base .= '/' . Str::of($this->version)->trim('/');
        }

        // Ignore recursive call to apply_filters - without it, would be anoying for developers
        if (!$is_to_apply_filters) {
            return $rest_base;
        }

        return apply_filters('wp_fastapi_router_rest_base', $rest_base, $this);
    }

    /**
     * Creates and retrieves a new endpoint instance
     * @since 0.9.0
     */
    private function endpoint(string $method, string $route, callable $handler, array $args = []) {
        $endpoint = new Endpoint($method, $route, $handler, $args);
        $this->endpoints[] = $endpoint;
        return $endpoint;
    }
}
