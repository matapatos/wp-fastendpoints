<?php

namespace Wp\FastEndpoints\Tests\Integration\Routers\Middlewares;

use Wp\FastEndpoints\Contracts\Middleware;
use Wp\FastEndpoints\Helpers\WpError;

class OnResponseErrorActionMiddleware extends Middleware
{
    public function onResponse(string $action): ?\WP_Error
    {
        return $action !== 'error' ? null : new WpError(469, 'Triggered error action before sending response');
    }
}
