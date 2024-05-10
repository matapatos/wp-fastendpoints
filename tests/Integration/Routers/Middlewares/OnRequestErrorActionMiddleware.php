<?php

namespace Wp\FastEndpoints\Tests\Integration\Routers\Middlewares;

use Wp\FastEndpoints\Contracts\Middleware;
use Wp\FastEndpoints\Helpers\WpError;

class OnRequestErrorActionMiddleware extends Middleware
{
    public function onRequest(string $action): ?\WP_Error
    {
        return $action !== 'error' ? null : new WpError(469, 'Triggered error action before handling request');
    }
}
