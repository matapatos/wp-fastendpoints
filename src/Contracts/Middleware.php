<?php

/**
 * Holds interface for middlewares
 *
 * @since 1.0.0
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Contracts;

use Exception;

/**
 * Interface for middlewares. It supports onRequest or onResponse methods.
 * Make sure to create at least one of those methods.
 *
 *       class MyCustomMiddleware extends Middleware {
 *          public function onRequest($request) {
 *              // called before handling a request
 *          }
 *
 *          public function onResponse($response) {
 *              // called after a request has been handled
 *          }
 *       }
 *
 * @since 1.0.0
 *
 * @author André Gil <andre_gil22@hotmail.com>
 *
 * @method onRequest - Called before handling a request
 * @method onResponse - Called after a request has been handled
 */
abstract class Middleware
{
    public function __construct()
    {
        if (! method_exists($this, 'onRequest') && ! method_exists($this, 'onResponse')) {
            throw new Exception(esc_html__('At least one method onRequest() or onResponse() must be declared on the class.'));
        }
    }
}
