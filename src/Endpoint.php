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

use Wp\FastEndpoints\Schemas\Schema;
use Wp\FastEndpoints\Schemas\Response;
use Wp\FastEndpoints\Contracts\Schemas\Schema as SchemaInterface;
use Wp\FastEndpoints\Contracts\Schemas\Response as ResponseInterface;
use Wp\FastEndpoints\Contracts\Endpoint as EndpointInterface;
use Wp\FastEndpoints\Helpers\Arr;
use WP_REST_Request;
use TypeError;
use Wp\FastEndpoints\Contracts\WpError;
use WP_Error;
use WP_Http;

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
	private string $method;

	/**
	 * HTTP route
	 *
	 * @since 0.9.0
	 * @var string
	 */
	private string $route;

	/**
	 * Same as the register_rest_route $args parameter
	 *
	 * @since 0.9.0
	 * @var array<mixed>
	 */
	private array $args = [];

	/**
	 * Main endpoint handler
	 *
	 * @since 0.9.0
	 * @var callable
	 */
	private $handler;

	/**
	 * Same as the register_rest_route $override parameter
	 *
	 * @since 0.9.0
	 * @var bool
	 */
	private bool $override;

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
	private array $permissionHandlers = [];

	/**
	 * Set of functions used to validate request before being handled
	 *
	 * @since 0.9.0
	 * @var array<int,array<callable>>
	 */
	private array $validationHandlers = [];

	/**
	 * Set of middlewares to run before the main handler
	 *
	 * @since 0.9.0
	 * @var array<int,array<callable>>
	 */
	private array $middlewareHandlers = [];

	/**
	 * Set of functions to be run after processing the request - usually to handle response
	 *
	 * @since 0.9.0
	 * @var array<int,array<callable>>
	 */
	private array $postHandlers = [];

	/**
	 * Creates a new instance of Endpoint
	 *
	 * @since 0.9.0
	 * @param string $method - POST, GET, PUT or DELETE or a value from WP_REST_Server (e.g. WP_REST_Server::EDITABLE).
	 * @param string $route - Endpoint route.
	 * @param callable $handler - User specified handler for the endpoint.
	 * @param array<mixed> $args - Same as the WordPress register_rest_route $args parameter. If set it can override the default
	 * WP FastEndpoints arguments.
	 * @param bool $override - Same as the WordPress register_rest_route $override parameter. Default value: false.
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
	 * @param string $namespace - WordPress REST namespace.
	 * @param string $restBase - Endpoint REST base.
	 * @param array<string> $schemaDirs - Array of directories to look for JSON schemas. Default value: [].
	 * @return true|false - true if successfully registered a REST route or false otherwise.
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
		$args = \apply_filters('wp_fastendpoints_endpoint_args', $args, $this, $namespace, $restBase);

		// Skip registration if no args specified.
		if (!$args) {
			return false;
		}
		$route = $this->getRoute($restBase);
		\register_rest_route($namespace, $route, $args, $this->override);
		return true;
	}

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
	public function hasCap($capabilities, int $priority = 10): Endpoint
	{
		if (!\is_array($capabilities) || Arr::isAssoc($capabilities)) {
			$capabilities = [$capabilities];
		}
		$this->permission(function (WP_REST_Request $req) use ($capabilities) {
			foreach ($capabilities as $cap) {
				if (\is_string($cap)) {
					if (!\current_user_can($cap)) {
						$data = defined('WP_DEBUG') && \WP_DEBUG ? ['missing_capabilities' => Arr::wrap($cap)] : [];
						return new WpError(WP_Http::FORBIDDEN, 'Not enough permissions', $data);
					}
				} elseif (\is_array($cap)) {
					if (!$cap) {
						\wp_die(\esc_html__('Invalid capability. Empty array given'));
					}

					if (Arr::isAssoc($cap)) {
						if (\count($cap) !== 1) {
							\wp_die(\sprintf(
								/* translators: 1: User capability */
								\esc_html__('Invalid capability. Expected one dictionary key but %d given'),
								\esc_html(\count($cap)),
							));
						}
						$keys = \array_keys($cap);
						// Flatten array.
						$value = Arr::wrap($this->replaceSpecialValue($req, $cap[$keys[0]]));
						$cap = \array_merge($keys, $value);
					}

					if (!\current_user_can(...$cap)) {
						$data = defined('WP_DEBUG') && \WP_DEBUG ? ['missing_capabilities' => $cap] : [];
						return new WpError(WP_Http::FORBIDDEN, 'Not enough permissions', $data);
					}
				} else {
					\wp_die(\sprintf(
						/* translators: 1: User capability */
						\esc_html__('Invalid capability. Expected string or array but %s given'),
						\esc_html($cap),
					));
				}
			}

			return true;
		}, $priority);
		return $this;
	}

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
	 * @param string|array<mixed> $schema - Filepath to the JSON schema or a JSON schema as an array.
	 * @param int $priority - Specifies the order in which the function is executed.
	 * Lower numbers correspond with earlier execution, and functions with the same priority
	 * are executed in the order in which they were added. Default value: 10.
	 * @throws TypeError - If $schema is neither a string|array.
	 * @return Endpoint
	 */
	public function returns($schema, int $priority = 10, ?bool $removeAdditionalProperties = true): Endpoint
	{
		$this->responseSchema = new Response($schema, $removeAdditionalProperties);
		$this->append($this->postHandlers, [$this->responseSchema, 'returns'], $priority);
		return $this;
	}

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
	public function middleware(callable $middleware, int $priority = 10): Endpoint
	{
		$this->append($this->middlewareHandlers, $middleware, $priority);
		return $this;
	}

	/**
	 * Registers a middleware to set the current endpoint post. Used in get_post(...) function.
	 *
	 * @since 0.9.0
	 * @param string|int $id - The post id or a string with a replaceable REST param.
	 * @param int $priority - Specifies the order in which the function is executed.
	 * Lower numbers correspond with earlier execution, and functions with the same priority
	 * are executed in the order in which they were added. Default value: 10.
	 * @param bool $override - Flag that determines if a post is already set if it should override it or not. Default: false.
	 * @return Endpoint
	 */
	public function post($id, int $priority = 30, bool $override = false): Endpoint
	{
		$this->append($this->middlewareHandlers, function (WP_REST_Request $req) use ($id, $override) {
			if (isset($GLOBALS['post']) && $override === false) {
				return;
			}

			if (\is_string($id)) {
				$id = $this->replaceSpecialValue($req, $id);
			}
			if (!\is_int($id)) {
				return new WpError(
					WP_Http::UNPROCESSABLE_ENTITY,
					sprintf(esc_html__('Expected post id to be an int. Given %s with type %s'), $id, gettype($id)),
				);
			}

			$GLOBALS['post'] = $id;
		}, $priority);
		return $this;
	}

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
	public function arg(string $name, $validate): Endpoint
	{
		if (!isset($this->args['args'])) {
			$this->args['args'] = [];
		}
		$args = [];
		if (\is_array($validate)) {
			$args = $validate;
		} elseif (\is_callable($validate)) {
			$args['validate_callback'] = $validate;
		} else {
			throw new TypeError(\sprintf(
				/* translators: 1: PHP Type of $validate argument */
				\esc_html__('Expected an array or a callable as the second argument of validateArg but %s given'),
				\esc_html(\gettype($validate)),
			));
		}

		$this->args['args'][$name] = $args;
		return $this;
	}

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
	 * @param WP_REST_Request $req - Current REST Request.
	 * @see rest_ensure_response
	 * @return WP_REST_Response|WP_Error
	 */
	public function callback(WP_REST_Request $req)
	{
		// Run pre validation methods.
		$result = $this->runHandlers($this->validationHandlers, $req);
		if (\is_wp_error($result)) {
			return \rest_ensure_response($result);
		}

		// Middleware methods.
		$result = $this->runHandlers($this->middlewareHandlers, $req);
		if (\is_wp_error($result)) {
			return \rest_ensure_response($result);
		}

		// Main handler.
		$result = $this->handler->call($this, $req);
		if (\is_wp_error($result)) {
			return \rest_ensure_response($result);
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
	 * @param WP_REST_Request $req - Current REST request.
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
	 * @param string $restBase - REST base route.
	 * @return string
	 */
	protected function getRoute(string $restBase): string
	{
		$route = $restBase;
		if (!\str_ends_with($restBase, '/') && !\str_starts_with($this->route, '/')) {
			$route .= '/';
		}
		$route .= $this->route;
		return \apply_filters('wp_fastendpoints_endpoint_route', $route, $this);
	}

	/**
	 * Replaces specials values, like: {jobId} by $req->get_param('jobId')
	 *
	 * @since 0.9.0
	 * @param WP_REST_Request $req - Current REST request.
	 * @param mixed $value - Value to be checked.
	 * @return mixed - The $value variable with all special parameters replaced.
	 */
	protected function replaceSpecialValue(WP_REST_Request $req, $value)
	{
		// Recursively search and replace all special value.
		if (\is_array($value)) {
			foreach ($value as &$v) {
				$v = $this->replaceSpecialValue($req, $v);
			}
			return $value;
		}

		if (!\is_string($value)) {
			return $value;
		}

		// Checks if value matches a special value.
		// If so, replaces with request variable.
		$newValue = \trim($value);
		if (!\str_starts_with($newValue, '{') && !\str_ends_with($newValue, '}')) {
			return $value;
		}

		$newValue = \ltrim($newValue, '{');
		$newValue = \rtrim($newValue, '}');
		if (!$req->has_param($newValue)) {
			return $value;
		}

		$newValue = $req->get_param($newValue);
		return \is_numeric($newValue) ? $newValue + 0 : $newValue;
	}

	/**
	 * Calls each handler ordered by priority.
	 *
	 * @since 0.9.0
	 * @param array<int,array<callable>> $allHandlers - Associative array of callables indexed by priority.
	 * @param mixed ...$args - Arguments to be passed in handlers.
	 * @return mixed|WP_Error - Returns the result of the last callable or if no handlers are set the
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
	 * @param array<int,array<callable>> $arrVar - Variable used to store the priority of the function.
	 * @param callable $cb - Function to be called.
	 * @param int $priority - Specifies the order in which the function is executed.
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
