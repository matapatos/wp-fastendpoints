<?php

/**
 * Holds logic for registering custom REST endpoints
 *
 * @since 0.9.0
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints;

use Wp\FastEndpoints\Contracts\Http\Endpoint as EndpointInterface;
use Wp\FastEndpoints\Contracts\Middlewares\Middleware;
use Wp\FastEndpoints\Helpers\WpError;
use WP_Error;
use WP_Http;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST Endpoint that registers custom WordPress REST endpoint using register_rest_route
 *
 * @since 0.9.0
 *
 * @author André Gil <andre_gil22@hotmail.com>
 */
class Endpoint implements EndpointInterface
{
    use DependencyInjectionTrait;

    /**
     * HTTP endpoint method - also supports values from WP_REST_Server (e.g. WP_REST_Server::READABLE)
     *
     * @since 0.9.0
     */
    protected string $method;

    /**
     * HTTP route
     *
     * @since 0.9.0
     */
    protected string $route;

    /**
     * Same as the register_rest_route $args parameter
     *
     * @since 0.9.0
     */
    protected array $args = [];

    /**
     * Main endpoint handler
     *
     * @since 0.9.0
     *
     * @var callable
     */
    protected $handler;

    /**
     * Plugins needed for the REST route
     *
     * @since 1.3.0
     *
     * @var ?array<string>
     */
    protected ?array $plugins = null;

    /**
     * Same as the register_rest_route $override parameter
     *
     * @since 0.9.0
     */
    protected bool $override;

    /**
     * Set of functions used inside the permissionCallback endpoint
     *
     * @since 0.9.0
     *
     * @var array<callable>
     */
    protected array $permissionHandlers = [];

    /**
     * Set of functions used to be called before handling a request e.g. schema validation
     *
     * @since 0.9.0
     *
     * @var array<callable>
     */
    protected array $onRequestHandlers = [];

    /**
     * Set of functions used to be called before sending a response to the client
     *
     * @since 0.9.0
     *
     * @var array<callable>
     */
    protected array $onResponseHandlers = [];

    /**
     * Creates a new instance of Endpoint
     *
     * @since 0.9.0
     *
     * @param  string  $method  POST, GET, PUT or DELETE or a value from WP_REST_Server (e.g. WP_REST_Server::EDITABLE).
     * @param  string  $route  Endpoint route.
     * @param  callable  $handler  User specified handler for the endpoint.
     * @param  array  $args  Same as the WordPress register_rest_route $args parameter. If set it can override the default
     *                       WP FastEndpoints arguments.
     * @param  bool  $override  Same as the WordPress register_rest_route $override parameter. Default value: false.
     */
    public function __construct(string $method, string $route, callable $handler, array $args = [], bool $override = false)
    {
        $this->method = $method;
        $this->route = $route;
        $this->handler = $handler;
        $this->args = $args;
        $this->override = $override;
    }

    /**
     * Registers the current endpoint using register_rest_route function.
     *
     * NOTE: Expects to be called inside the 'rest_api_init' WordPress action
     *
     * @since 0.9.0
     *
     * @param  string  $namespace  WordPress REST namespace.
     * @param  string  $restBase  Endpoint REST base.
     * @return true|false true if successfully registered a REST route or false otherwise.
     */
    public function register(string $namespace, string $restBase): bool
    {
        $args = [
            'methods' => $this->method,
            'callback' => [$this, 'callback'],
            'permission_callback' => $this->permissionHandlers ? [$this, 'permissionCallback'] : '__return_true',
            'depends' => $this->plugins,
        ];
        // Override default arguments.
        $args = \array_merge($args, $this->args);
        $args = \apply_filters('fastendpoints_endpoint_args', $args, $namespace, $restBase, $this);

        // Skip registration if no args specified.
        if (! $args) {
            return false;
        }
        $route = $this->getRoute($restBase);
        \register_rest_route($namespace, $route, $args, $this->override);

        return true;
    }

    /**
     * Checks if the current user has the given WP capabilities. Example usage:
     *
     *      hasCap('edit_posts');
     *      hasCap('edit_post', $post->ID);
     *      hasCap('edit_post', '{post_id}');  // Replaces {post_id} with request parameter named post_id
     *      hasCap('edit_post_meta', $post->ID, $meta_key);
     *
     * @param  string  $capability  WordPress user capability to be checked against
     * @param  array  $args  Optional parameters, typically the object ID. You can also pass a future request parameter
     *                       via curly braces e.g. {post_id}
     *
     * @since 0.9.0
     */
    public function hasCap(string $capability, ...$args): self
    {
        if (! $capability) {
            \wp_die(\esc_html__('Invalid capability. Empty capability given'));
        }

        return $this->permission(function (WP_REST_Request $request) use ($capability, $args): bool|WpError {
            foreach ($args as &$arg) {
                if (! \is_string($arg)) {
                    continue;
                }

                $arg = $this->replaceSpecialValue($request, $arg);
            }

            if (! \current_user_can($capability, ...$args)) {
                return new WpError(WP_Http::FORBIDDEN, 'Not enough permissions');
            }

            return true;
        });
    }

    /**
     * Registers a middleware
     *
     * @since 0.9.0
     *
     * @param  Middleware  $middleware  Middleware to be plugged.
     */
    public function middleware(Middleware $middleware): self
    {
        if (method_exists($middleware, 'onRequest')) {
            $this->onRequestHandlers[] = [$middleware, 'onRequest'];
        }
        if (method_exists($middleware, 'onResponse')) {
            $this->onResponseHandlers[] = [$middleware, 'onResponse'];
        }

        return $this;
    }

    /**
     * Specifies a set of plugins that are needed by the endpoint
     */
    public function depends(string|array $plugins): self
    {
        if (is_string($plugins)) {
            $plugins = [$plugins];
        }

        $this->plugins = array_merge($this->plugins ?: [], $plugins);

        return $this;
    }

    /**
     * Registers a permission callback
     *
     * @since 0.9.0
     *
     * @param  callable  $permissionCb  Method to be called to check current user permissions.
     */
    public function permission(callable $permissionCb): self
    {
        $this->permissionHandlers[] = $permissionCb;

        return $this;
    }

    /**
     * WordPress function callback to handle this endpoint
     *
     * @since 0.9.0
     *
     * @internal
     *
     * @param  WP_REST_Request  $req  Current REST Request.
     */
    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $this->addDependencies($request);

        $urlParams = $request->get_url_params();

        // onRequest handlers.
        $result = $this->call($this->onRequestHandlers, $urlParams);
        if (\is_wp_error($result) || $result instanceof WP_REST_Response) {
            return $result;
        }

        // Main endpoint handler.
        $result = $this->call($this->handler, $urlParams);
        if (\is_wp_error($result) || $result instanceof WP_REST_Response) {
            return $result;
        }
        $this->container->get('___response')->set_data($result);

        // onResponse handlers.
        $result = $this->call($this->onResponseHandlers, $urlParams);
        if (\is_wp_error($result) || $result instanceof WP_REST_Response) {
            return $result;
        }

        return $this->container->get('___response');
    }

    /**
     * WordPress function callback to check permissions for this endpoint
     *
     * @since 0.9.0
     *
     * @internal
     *
     * @param  WP_REST_Request  $request  Current REST request.
     * @return bool|WP_Error true on success or WP_Error on error
     */
    public function permissionCallback(WP_REST_Request $request): bool|WP_Error
    {
        $this->addDependencies($request);
        $urlParams = $request->get_url_params();

        $result = $this->call($this->permissionHandlers, $urlParams);
        if (\is_wp_error($result)) {
            return $result;
        }

        return true;
    }

    /**
     * Retrieves the current endpoint route
     *
     * @since 0.9.0
     *
     * @param  string  $restBase  REST base route.
     */
    protected function getRoute(string $restBase): string
    {
        $route = $restBase;
        if (! \str_ends_with($restBase, '/') && ! \str_starts_with($this->route, '/')) {
            $route .= '/';
        }
        $route .= $this->route;

        return \apply_filters('fastendpoints_endpoint_route', $route, $this);
    }

    /**
     * Replaces specials values, like: {jobId} by $req->get_param('jobId')
     *
     * @since 0.9.0
     *
     * @param  WP_REST_Request  $request  Current REST request.
     * @param  string  $value  Value to be checked.
     * @return mixed The $value variable with all special parameters replaced.
     */
    protected function replaceSpecialValue(WP_REST_Request $request, string $value): mixed
    {
        // Checks if value matches a special value.
        // If so, replaces with request variable.
        $newValue = \trim($value);
        if (! \str_starts_with($newValue, '<') || ! \str_ends_with($newValue, '>')) {
            return $value;
        }

        $newValue = substr($newValue, 1, -1);
        if (! $request->has_param($newValue)) {
            return $value;
        }

        return $request->get_param($newValue);
    }
}
