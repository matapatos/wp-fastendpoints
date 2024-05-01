<?php

/**
 * Holds interface for middlewares that needs to be run before a response is sent to the client
 *
 * @since 1.0.0
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Contracts\Middlewares;

/**
 * Interface for middlewares that needs to be run before a response is sent to the client
 *
 * @since 1.0.0
 *
 * @author André Gil <andre_gil22@hotmail.com>
 */
interface OnResponseMiddleware
{
    /**
     * Handles the response before sending to the client
     *
     * @param  \WP_REST_Request  $request  Request handled
     * @param  mixed  $response  The response to be sent
     * @return mixed The response to be sent to the client. If a WP_Error is returned no further middlewares will
     *               be triggered.
     *
     * @since 1.0.0
     */
    public function onResponse(\WP_REST_Request $request, mixed $response): mixed;
}
