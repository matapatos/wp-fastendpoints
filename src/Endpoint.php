<?php

/**
 * Holds logic for registering custom REST endpoints
 *
 * @since 0.9.0
 * @package wp-fastendpoints
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints;

use TypeError;
use Wp\FastEndpoints\Contracts\Endpoint as EndpointInterface;
use Wp\FastEndpoints\Contracts\Schemas\Response as ResponseInterface;
use Wp\FastEndpoints\Contracts\Schemas\Schema as SchemaInterface;
use Wp\FastEndpoints\Helpers\Arr;
use Wp\FastEndpoints\Helpers\WpError;
use Wp\FastEndpoints\Schemas\Response;
use Wp\FastEndpoints\Schemas\Schema;
use WP_Error;
use WP_Http;
use WP_REST_Request;

/**
 * REST Endpoint that registers custom WordPress REST endpoint using register_rest_route
 *
 * @since 0.9.0
 * @author André Gil <andre_gil22@hotmail.com>
 */
class Endpoint implements EndpointInterface
{
	/**
	 * HTTP endpoint method - also supports values from WP_REST_Server (e.g. WP_REST_Server::READABLE)
	 *
	 * @since 0.9.0
	 * @var string
	 */
	protected string $method;

	/**
	 * HTTP route
	 *
	 * @since 0.9.0
	 * @var string
	 */
    protected string $route;

	/**
	 * Same as the register_rest_route $args parameter
	 *
	 * @since 0.9.0
	 * @var array
	 */
    protected array $args = [];

	/**
	 * Main endpoint handler
	 *
	 * @since 0.9.0
	 * @var callable
	 */
    protected $handler;

	/**
	 * Same as the register_rest_route $override parameter
	 *
	 * @since 0.9.0
	 * @var bool
	 */
    protected bool $override;

	/**
	 * JSON Schema used to validate request params
	 *
	 * @since 0.9.0
	 * @var ?SchemaInterface
	 */
	public ?SchemaInterface $schema = null;

	/**
	 * JSON Schema used to retrieve data to client - ignores additional properties
	 *
	 * @since 0.9.0
	 * @var ?ResponseInterface
	 */
	public ?ResponseInterface $responseSchema = null;

	/**
	 * Set of functions used inside the permissionCallback endpoint
	 *
	 * @since 0.9.0
	 * @var array<int,array<callable>>
	 */
    protected array $permissionHandlers = [];

	/**
	 * Set of functions used to validate request before being handled
	 *
	 * @since 0.9.0
	 * @var array<int,array<callable>>
	 */
    protected array $validationHandlers = [];

	/**
	 * Set of middlewares to run before the main handler
	 *
	 * @since 0.9.0
	 * @var array<int,array<callable>>
	 */
    protected array $middlewareHandlers = [];

	/**
	 * Set of functions to be run after processing the request - usually to handle response
	 *
	 * @since 0.9.0
	 * @var array<int,array<callable>>
	 */
    protected array $postHandlers = [];

	/**
	 * Creates a new instance of Endpoint
	 *
	 * @since 0.9.0
	 * @param string $method POST, GET, PUT or DELETE or a value from WP_REST_Server (e.g. WP_REST_Server::EDITABLE).
	 * @param string $route Endpoint route.
	 * @param callable $handler User specified handler for the endpoint.
	 * @param array $args Same as the WordPress register_rest_route $args parameter. If set it can override the default
	 * WP FastEndpoints arguments.
	 * @param bool $override Same as the WordPress register_rest_route $override parameter. Default value: false.
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
	 * @param string $namespace WordPress REST namespace.
	 * @param string $restBase Endpoint REST base.
	 * @param array<string> $schemaDirs Array of directories to look for JSON schemas. Default value: [].
	 * @return true|false true if successfully registered a REST route or false otherwise.
	 */
	public function register(string $namespace, string $restBase, array $schemaDirs = []): bool
	{
		$args = [
			'methods'               => $this->method,
			'callback'              => [$this, 'callback'],
			'permission_callback'   => $this->permissionHandlers ? [$this, 'permissionCallback'] : '__return_true',
		];
		if ($this->schema) {
			$this->schema->appendSchemaDir($schemaDirs);
			$args['schema'] = [$this->schema, 'getContents'];
		}
		if ($this->responseSchema) {
			$this->responseSchema->appendSchemaDir($schemaDirs);
		}
		// Override default arguments.
		$args = \array_merge($args, $this->args);
		$args = \apply_filters('fastendpoints_endpoint_args', $args, $namespace, $restBase, $this);

		// Skip registration if no args specified.
		if (!$args) {
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
     *      hasCap(['edit_post', $post->ID]);
     *      hasCap(['edit_post_meta', $post->ID, $meta_key]);
     *      hasCap(['edit_post', '{post_id}']);  // Replaces {post_id} with request parameter named post_id
     *
	 * @param string|array $capability WordPress user capability. If string should be the capability to check against.
     * If array it should consist of the capability to be checked followed by optional parameters, typically the object ID.
     * You can also replace with a given request parameter via curly braces e.g. {post_id}
	 * @param int $priority Specifies the order in which the function is executed.
	 * Lower numbers correspond with earlier execution, and functions with the same priority
	 * are executed in the order in which they were added. Default value: 10.
	 * @return Endpoint
     * @since 0.9.0
	 */
	public function hasCap($capability, int $priority = 10): Endpoint
	{
        if (!$capability) {
            \wp_die(\esc_html__('Invalid capability. Empty capability given'));
        }

		return $this->permission(function (WP_REST_Request $req) use ($capability) {
            $allCaps = Arr::wrap($capability);
            foreach ($allCaps as &$cap) {
                if (!\is_string($cap)) {
                    continue;
                }

                $cap = $this->replaceSpecialValue($req, $cap);
            }

            if (!\current_user_can(...$allCaps)) {
                return new WpError(WP_Http::FORBIDDEN, 'Not enough permissions');
            }

			return true;
		}, $priority);
	}

	/**
	 * Adds a schema validation to the validationHandlers, which will be later called in advance to
	 * validate a REST request according to the given JSON schema.
	 *
	 * @since 0.9.0
	 * @param string|array $schema Filepath to the JSON schema or a JSON schema as an array.
	 * @param int $priority Specifies the order in which the function is executed.
	 * Lower numbers correspond with earlier execution, and functions with the same priority
	 * are executed in the order in which they were added. Default value: 10.
	 * @return Endpoint
	 */
	public function schema($schema, int $priority = 10): Endpoint
	{
		$this->schema = new Schema($schema);
		$this->append($this->validationHandlers, [$this->schema, 'validate'], $priority);
		return $this;
	}

	/**
	 * Adds a resource function to the postHandlers, which will be later called to filter the REST response
	 * according to the JSON schema specified. In other words, it will:
	 * 1) Ignore additional properties in WP_REST_Response, avoiding the leakage of unnecessary data and
	 * 2) Making sure that the required data is retrieved.
	 *
	 * @since 0.9.0
	 * @param string|array $schema Filepath to the JSON schema or a JSON schema as an array.
	 * @param int $priority Specifies the order in which the function is executed.
	 * Lower numbers correspond with earlier execution, and functions with the same priority
	 * are executed in the order in which they were added. Default value: 10.
     * @param string|bool|null $removeAdditionalProperties Option which defines if we want to remove additional properties.
     * If true removes all additional properties from the response. If false allows additional properties to be retrieved.
     * If null it will use the JSON schema additionalProperties value. If a string allows only those variable types (e.g. integer)
	 * @throws TypeError If $schema is neither a string|array.
	 * @return Endpoint
	 */
	public function returns($schema, int $priority = 10, $removeAdditionalProperties = true): Endpoint
	{
		$this->responseSchema = new Response($schema, $removeAdditionalProperties);
		$this->append($this->postHandlers, [$this->responseSchema, 'returns'], $priority);
		return $this;
	}

	/**
	 * Registers a middleware with a given priority
	 *
	 * @since 0.9.0
	 * @param callable $middleware Function to be used as a middleware.
	 * @param int $priority Specifies the order in which the function is executed.
	 * Lower numbers correspond with earlier execution, and functions with the same priority
	 * are executed in the order in which they were added. Default value: 10.
	 * @return Endpoint
	 */
	public function middleware(callable $middleware, int $priority = 10): Endpoint
	{
		$this->append($this->middlewareHandlers, $middleware, $priority);
		return $this;
	}

	/**
	 * Registers a permission callback
	 *
	 * @since 0.9.0
	 * @param callable $permissionCb Method to be called to check current user permissions.
	 * @param int $priority Specifies the order in which the function is executed.
	 * Lower numbers correspond with earlier execution, and functions with the same priority
	 * are executed in the order in which they were added. Default value: 10.
	 * @return Endpoint
	 */
	public function permission(callable $permissionCb, int $priority = 10): Endpoint
	{
		$this->append($this->permissionHandlers, $permissionCb, $priority);
		return $this;
	}

	/**
	 * WordPress function callback to handle this endpoint
	 *
	 * @since 0.9.0
	 * @internal
	 *
	 * @param WP_REST_Request $req Current REST Request.
	 * @see rest_ensure_response
	 * @return WP_REST_Response|WP_Error
	 */
	public function callback(WP_REST_Request $req)
	{
		// Run pre validation methods.
		$result = $this->runHandlers($this->validationHandlers, $req);
		if (\is_wp_error($result)) {
			return $result;
		}

		// Middleware methods.
		$result = $this->runHandlers($this->middlewareHandlers, $req);
		if (\is_wp_error($result)) {
			return $result;
		}

		// Main handler.
		$result = $this->handler->call($this, $req);
		if (\is_wp_error($result)) {
			return $result;
		}

		// Post handlers.
		$result = $this->runHandlers($this->postHandlers, $req, $result);
		return \rest_ensure_response($result);
	}

	/**
	 * WordPress function callback to check permissions for this endpoint
	 *
	 * @since 0.9.0
	 * @internal
	 *
	 * @param WP_REST_Request $req Current REST request.
	 * @return mixed
	 */
	public function permissionCallback(WP_REST_Request $req)
	{
		$result = $this->runHandlers($this->permissionHandlers, $req);
		if (\is_wp_error($result)) {
			return $result;
		}
		return true;
	}

	/**
	 * Retrieves the current endpoint route
	 *
	 * @since 0.9.0
	 * @param string $restBase REST base route.
	 * @return string
	 */
	protected function getRoute(string $restBase): string
	{
		$route = $restBase;
		if (!\str_ends_with($restBase, '/') && !\str_starts_with($this->route, '/')) {
			$route .= '/';
		}
		$route .= $this->route;
		return \apply_filters('fastendpoints_endpoint_route', $route, $this);
	}

	/**
	 * Replaces specials values, like: {jobId} by $req->get_param('jobId')
	 *
	 * @since 0.9.0
	 * @param WP_REST_Request $req Current REST request.
	 * @param string $value Value to be checked.
	 * @return mixed The $value variable with all special parameters replaced.
	 */
	protected function replaceSpecialValue(WP_REST_Request $req, string $value)
	{
		// Checks if value matches a special value.
		// If so, replaces with request variable.
		$newValue = \trim($value);
		if (!\str_starts_with($newValue, '{') && !\str_ends_with($newValue, '}')) {
			return $value;
		}

        $newValue = substr($newValue, 1, -1);
		if (!$req->has_param($newValue)) {
			return $value;
		}

		return $req->get_param($newValue);
	}

	/**
	 * Calls each handler ordered by priority.
	 *
	 * @since 0.9.0
	 * @param array<int,array<callable>> $allHandlers Associative array of callables indexed by priority.
	 * @param mixed ...$args Arguments to be passed in handlers.
	 * @return mixed|WP_Error Returns the result of the last callable or if no handlers are set the
	 * last result passed as argument if any. If an error occurs a WP_Error instance is returned.
	 */
	protected function runHandlers(array &$allHandlers, ...$args)
	{
		// If no handlers are set we have to make sure to return the previous result if set.
		$result = (\count($args) >= 2) ? $args[1] : null;
		// Sort dictionary by keys.
		\ksort($allHandlers);
		foreach ($allHandlers as $priority => $handlers) {
			foreach ($handlers as $h) {
				$result = \call_user_func_array($h, $args);
				if (\is_wp_error($result)) {
					return $result;
				}
			}
		}
		return $result;
	}

	/**
	 * Saves a callable into an array, which can later on be called in order of priority
	 * (works the same as the WordPress actions/filters priority argument)
	 *
	 * @since 0.9.0
	 * @param array<int,array<callable>> $arrVar Variable used to store the priority of the function.
	 * @param callable $cb Function to be called.
	 * @param int $priority Specifies the order in which the function is executed.
	 * Lower numbers correspond with earlier execution, and functions with the same priority
	 * are executed in the order in which they were added.
	 * @return void
	 */
	protected function append(array &$arrVar, callable $cb, int $priority): void
	{
		if (!isset($arrVar[$priority])) {
			$arrVar[$priority] = [];
		}
		$arrVar[$priority][] = $cb;
	}
}
