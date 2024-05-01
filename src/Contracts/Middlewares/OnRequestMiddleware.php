<?php

/**
 * Holds interface for middlewares that needs to be run before a request is handled
 *
 * @since 1.0.0
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Contracts\Middlewares;

/**
 * Interface used by middlewares that needs to run before a request is handled
 *
 * @since 1.0.0
 *
 * @author André Gil <andre_gil22@hotmail.com>
 */
interface OnRequestMiddleware
{
    /**
     * Handles the request before the main handler
     *
     * @param  \WP_REST_Request  $request  The request being handled
     * @return ?\WP_Error If a WP_Error is returned it stops handling the request and sends that error message
     *                    to the client
     *
     * @since 1.0.0
     */
    public function onRequest(\WP_REST_Request $request): ?\WP_Error;
}
