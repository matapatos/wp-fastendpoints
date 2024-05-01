<?php

namespace Tests\Wp\FastEndpoints\Integration\Routers\Middlewares;

use Wp\FastEndpoints\Contracts\Middlewares\OnResponseMiddleware;
use Wp\FastEndpoints\Helpers\WpError;

class OnResponseErrorActionMiddleware implements OnResponseMiddleware
{
    public function onResponse(\WP_REST_Request $request, mixed $response): mixed
    {
        $action = $request->get_param('action');

        return $action !== 'error' ? $response : new WpError(469, 'Triggered error action before sending response');
    }
}
