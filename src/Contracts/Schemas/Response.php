<?php

/**
 * Holds an interface for checking/parsing the REST response of an endpoint
 *
 * @since 0.9.0
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Contracts\Schemas;

use Wp\FastEndpoints\Helpers\WpError;
use WP_REST_Request;

/**
 * Response interface for parsing/checking the REST response of an endpoint before sending it to the client.
 *
 * @since 0.9.0
 *
 * @author André Gil <andre_gil22@hotmail.com>
 */
interface Response
{
    /**
     * Parses and checks the data to be sent back to the client.
     *
     * @since 0.9.0
     *
     * @param  WP_REST_Request  $req  Current REST Request.
     * @param  mixed  $res  Current REST response.
     * @return mixed The parsed response on success or WpError on error.
     */
    public function returns(WP_REST_Request $req, mixed $res): mixed;

    /**
     * Appends an additional directory where to look for the schema
     *
     * @since 0.9.0
     *
     * @param  string|array<string>  $schemaDir  Directory path or an array of directories where to
     *                                           look for JSON schemas.
     */
    public function appendSchemaDir(string|array $schemaDir): void;
}
