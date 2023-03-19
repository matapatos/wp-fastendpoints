<?php

/**
 * General exception used for throwing custom exceptions inside wp-fastendpoints.
 *
 * @since 0.9.0
 * @package wp-fastendpoints
 * @license MIT
 */

declare(strict_types=1);

namespace WP\FastEndpoints\Contracts;

use WP_Error;
use Exception;

/**
 * General exception used for throwing custom exceptions inside wp-fastendpoints.
 *
 * @since 0.9.0
 * @author André Gil <andre_gil22@hotmail.com>
 */
abstract class WpException extends Exception
{
	/**
	 * WP_Error instance of the given exception. Used for retrieving a response to the client.
	 *
	 * @since 0.9.0
	 */
	private WP_Error $wpError;

	/**
	 * @since 0.9.0
	 * @param string $message - The error message.
	 */
	public function __construct(int $statusCode, string $message, array $data = [])
	{
		$data['status'] = $statusCode;
		$this->wpError = new WP_Error(
			$statusCode,
			esc_html__($message),
			$data,
		);
		parent::__construct(esc_html__($message));
	}

	/**
	 * Retrieves the WP_Error instance that corresponds to the given exception.
	 *
	 * @since 0.9.0
	 * @return WP_Error
	 */
	public function getWpError(): WP_Error
	{
		return $this->wpError;
	}
}
