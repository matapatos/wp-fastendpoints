<?php

/**
 * Holds class overrides from WordPress
 *
 * @since 0.9.0
 */

use Tests\Wp\FastEndpoints\Helpers\Helpers;

if (Helpers::isIntegrationTest()) {
    return;
}

if (! class_exists('WP_Http')) {
    class WP_Http
    {
        const FORBIDDEN = 403;

        const NOT_FOUND = 404;

        const UNPROCESSABLE_ENTITY = 422;

        const INTERNAL_SERVER_ERROR = 500;
    }
}

if (! class_exists('WP_Error')) {
    class WP_Error
    {
        public $code;

        public $message;

        public $data;

        public function __construct($code = '', $message = '', $data = '')
        {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }
    }
}
