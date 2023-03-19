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
use WP_Error;

/**
 * Class used when a user doesn't have enough permissions to access an endpoint
 *
 * @since 0.9.0
 * @author André Gil <andre_gil22@hotmail.com>
 */
class NotEnoughPermissionsError extends WP_Error
{
	/**
	 * Creates a new instance of NotEnoughPermissionsError
	 *
	 * @since 0.9.0
	 * @param string|array $missingCapabilities - The capabilities missing from the user.
	 */
	public function __construct($missingCapabilities)
	{
		$response = ['status' => WP_Http::FORBIDDEN];
		if (\defined('WP_DEBUG') && \WP_DEBUG === true) {
			$response['missing_capabilities'] = $missingCapabilities;
		}
		parent::__construct(WP_Http::FORBIDDEN, \esc_html__('Not enough permissions'), $response);
	}
}
