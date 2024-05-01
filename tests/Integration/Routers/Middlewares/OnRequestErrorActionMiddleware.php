<?php

namespace Tests\Wp\FastEndpoints\Integration\Routers\Middlewares;

use Wp\FastEndpoints\Contracts\Middlewares\OnRequestMiddleware;
use Wp\FastEndpoints\Helpers\WpError;

class OnRequestErrorActionMiddleware implements OnRequestMiddleware
{
    public function onRequest(\WP_REST_Request $request): ?\WP_Error
    {
        $action = $request->get_param('action');

        return $action !== 'error' ? null : new WpError(469, 'Triggered error action before handling request');
    }
}
