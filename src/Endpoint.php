<?php

namespace WP\FastAPI;

use Illuminate\Support\{
    Arr,
    Str,
};

class Endpoint {

    /**
     * HTTP endpoint method - supports the same as WP
     */
    private string $method;

    /**
     * HTTP route
     */
    private string $route;

    /**
     * WP endpoint $args argument
     */
    private array $args = [];

    /**
     * Main endpoint handler
     */
    private $handler;

    /**
     * register_rest_route $override argument
     */
    private bool $override;

    /**
     * JSON Schema to validate body
     */
    public Schema $jsonSchema;

    /**
     * Set of functions used inside the permission_callback endpoint
     */    
    private array $permissionHandlers = [];

    /**
     * Set of functions used to validate request before being handled
     */
    private array $validationHandlers = [];

    /**
     * Set of middlewares to run before the main handler
     */
    private array $middlewareHandlers = [];

    /**
     * Set of functions to be run after processing the request - usually to handle response
     */
    private array $postHandlers = [];

    public function __construct(string $method, string $route, Callable $handler, array $args = [], $override = false) {
        $this->method = $method;
        $this->route = $route;
        $this->handler = $handler;
        $this->args = $args;
        $this->override = $override;
    }

    /**
     * Registers the current endpoint to using register_rest_route function.
     * Expects to be called inside the 'rest_api_init' WP action
     *
     * @since 0.9.0
     */
    public function register(string $baseNamespace, string $endpointNamespace): bool {
        $args = [[
            'methods'               => $this->method,
            'callback'              => [$this, 'callback'],
            'permission_callback'   => $this->permissionHandlers ? [$this, 'permissionCallback'] : '__return_true',
        ]];
        if (isset($this->jsonSchema)) {
            $args['schema'] = $this->jsonSchema->get();
        }
        // Override default arguments
        $args = array_merge($args, $this->args);
        $args = apply_filters('wp_fastapi_endpoint_args', $args, $this, $baseNamespace, $endpointNamespace);

        // Skip registration if no args specified
        if (!$args) {
            return false;
        }
        $route = $this->getRoute($endpointNamespace);
        register_rest_route($baseNamespace, $route, $args, $this->override);
        return true;
    }

    /**
     * Checks if the current user has the given WP capabilities
     *
     * @param string|array $capabilities - WP user capabilities
     * @param int $priority - permissions callback priority
     *
     * @since 0.9.0
     */
    public function hasCap($capabilities, int $priority = 10): Endpoint {
        $capabilities = Arr::wrap($capabilities);
        $this->permission(function (\WP_REST_Request $req) use ($capabilities) {
            foreach ($capabilities as $cap) {
                if (is_string($cap)) {
                    if (!current_user_can($cap)) {
                        return new \WP_Error(403, 'Not enough permissions');
                    }
                } elseif (is_array($cap)) {
                    if (count($cap) > 1) {
                        $cap[1] = $this->replaceSpecialValue($req, $cap[1]);
                    }
                    if (!current_user_can(...$cap)) {
                        return new \WP_Error(403, 'Not enough permissions');   
                    }
                } else {
                    return new \WP_Error(500, 'Invalid capability. Expected string or array but ' . $cap . ' given');
                }
            }

            return true;
        }, $priority);
        return $this;
    }

    /**
     * Validates request body with a given schema
     *
     * @since 0.9.0
     */
    public function schema($schema, $additionalProperties = false): Endpoint {
        $this->jsonSchema = new Schema($schema, $additionalProperties);
        return $this;
    }

    /**
     * Response schema type - ignores additional fields
     *
     * @since 0.9.0
     */
    public function returns($schema, int $priority = 10, $additionalProperties = false): Endpoint {
        $this->schema = new Schema($schema, $additionalProperties);
        $this->append($this->postHandlers, $this->schema, $priority);
        return $this;
    }

    /**
     * Registers a middleware with a given priority
     *
     * @since 0.9.0
     */
    public function middleware(Callable $middleware, int $priority = 10): Endpoint {
        $this->append($this->middlewareHandlers, $middleware, $priority);
        return $this;
    }

    /**
     * Registers an argument
     *
     * @param string $name - Name of the parameter
     * @param array|Callable $validate - array to be used in WP (e.g. ['required'=>true, 'default'=>null])
     *                                   or validation callback to be used 
     *
     * @since 0.9.0
     */
    public function arg(string $name, $validate): Endpoint {
        if (!isset($this->args['args'])) {
            $this->args['args'] = [];
        }
        $args = [];
        if (is_array($validate)) {
            $args = $validate;
        } elseif (is_callable($validate)) {
            $args['validate_callback'] = $validate;
        } else {
            throw new TypeError('Expected an array or a callable as the second argument of validateArg but ' . gettype($validate) . ' given');
        }

        $this->args['args'][$name] = $args;
        return $this;
    }

    /**
     * Registers a permission callback
     *
     * @since 0.9.0
     */
    public function permission(Callable $permissionCb, int $priority = 10): Endpoint {
        $this->append($this->permissionHandlers, $permissionCb, $priority);
        return $this;
    }

    /**
     * WP function callback to handle this endpoint
     *
     * @since 0.9.0
     */
    public function callback(\WP_REST_Request $req) {
        // Run pre validation methods
        $result = $this->runHandlers($this->validationHandlers, $req);
        if (is_wp_error($result)) {
            return rest_ensure_response($result);
        }

        // Middleware methods
        $result = $this->runHandlers($this->middlewareHandlers, $req);
        if (is_wp_error($result)) {
            return rest_ensure_response($result);
        }

        // Main handler
        $result = $this->handler->call($this, $req);
        if (is_wp_error($result)) {
            return rest_ensure_response($result);
        }

        // Post handlers
        $result = $this->runHandlers($this->postHandlers, $req, $result);
        return rest_ensure_response($result);
    }

    /**
     * WP function callback to check permissions for this endpoint
     *
     * @since 0.9.0
     */
    public function permissionCallback(\WP_REST_Request $req) {
        return $this->runHandlers($this->permissionHandlers, $req);
    }

    /**
     * Retrieves the current endpoint route
     *
     * @since 0.9.0
     */
    protected function getRoute(string $endpointNamespace): string {
        $route = Str::finish($endpointNamespace, '/');
        $route .= $this->route;
        return apply_filters('wp_fastapi_endpoint_route', $route, $this);
    }

    /**
     * Replaces specials values, like: {jobId} by $req->get_param('jobId')
     *
     * @since 0.9.0
     */
    protected function replaceSpecialValue(\WP_REST_Request $req, $value) {
        if (!is_string($value)) {
            return $value;
        }

        // Checks if value matches a special value
        // If so, replaces with request variable
        return $value;
    }

    /**
     * Calls each handler
     *
     * @since 0.9.0
     */
    protected function runHandlers(array &$allHandlers, ...$args) {
        // If no handlers are set we have to make sure to return the previous result if set
        $result = (count($args) >= 2) ? $args[1] : null;
        // Sort dictionary by keys
        ksort($allHandlers);
        foreach ($allHandlers as $priority => $handlers) {
            foreach ($handlers as $h) {
                $result = call_user_func_array($h, $args);
                if (is_wp_error($result)) {
                    return $result;
                }
            }
        }
        return $result;
    }

    /**
     * Appends a Callable to a given array, regarding it's priority
     *
     * @since 0.9.0
     */
    protected function append(array &$arrVar, Callable $cb, int $priority): void {
        if (!isset($arrVar[$priority])) {
            $arrVar[$priority] = [];
        }
        $arrVar[$priority][] = $cb;
    }
}
