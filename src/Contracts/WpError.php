<?php

/**
 * Holds Class that removes repeating http status in $data.
 *
 * @since 0.9.0
 * @package wp-fastendpoints
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Contracts;

use WP_Error;

class WpError extends WP_Error
{
	/**
	 * @since 0.9.0
	 * @param string $message - The error message.
	 */
	public function __construct(int $statusCode, string $message, array $data = [])
	{
		$data = array_merge(['status' => $statusCode], $data);
		parent::__construct($statusCode, esc_html__($message), $data);
	}
}
