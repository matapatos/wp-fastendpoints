<?php

/**
 * Holds interface for validating a WP_REST_Request before running the enpoint handler.
 *
 * @version 1.0.0
 * @package wp-fastendpoints
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Contracts\Schemas;

use WP_REST_Request;

/**
 * Schema interface for validating a WP_REST_Request
 *
 * @version 1.0.0
 * @author André Gil <andre_gil22@hotmail.com>
 */
interface SchemaInterface
{
    /**
     * Validates the JSON schema
     *
     * @version 1.0.0
     * @param WP_REST_Request $req - Current REST Request.
     * @return true|WP_Error - true on success and WP_Error on error.
     */
    public function validate(WP_REST_Request $req);

    /**
     * Appends an additional directory where to look for the schema
     *
     * @version 1.0.0
     * @param string|array<string> $schemaDir - Directory path or an array of directories where to
     * look for JSON schemas.
     */
    public function appendSchemaDir($schemaDir): void;
}
