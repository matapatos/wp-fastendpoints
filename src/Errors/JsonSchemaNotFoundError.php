<?php

/**
 * WP_Error returned when a user doesn't have enough permissions to access an endpoint.
 *
 * @since 0.9.0
 * @package wp-fastendpoints
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Errors;

use WP_Http;
use Wp\FastEndpoints\Contracts\WpException;

/**
 * Raised when a json file schema is not found
 *
 * @since 0.9.0
 * @author André Gil <andre_gil22@hotmail.com>
 */
class JsonSchemaNotFoundError extends WpException
{
	/**
	 * Stores the value of where we were expecting to find a json file schema
	 *
	 * @since 0.9.0
	 */
	public ?string $expectedFilepath = null;

	/**
	 * @since 0.9.0
	 * @param string $message - The error message.
	 */
	public function __construct(?string $expectedFilepath = null)
	{
		$this->expectedFilepath = $expectedFilepath;
		$message = 'Json file schema not found';
		if ($this->expectedFilepath) {
			$message = sprintf(esc_html__('Json file schema %s not found'), $this->expectedFilepath);
		}
		parent::__construct(WP_Http::NOT_FOUND, $message);
	}
}
