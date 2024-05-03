<?php

/**
 * Holds logic to validate a WP_REST_Request before running the enpoint handler.
 *
 * @since 0.9.0
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Schemas;

use Opis\JsonSchema\Exceptions\SchemaException;
use Opis\JsonSchema\Helper;
use Wp\FastEndpoints\Contracts\JsonSchema;
use Wp\FastEndpoints\Helpers\WpError;
use WP_Error;
use WP_Http;
use WP_REST_Request;

/**
 * SchemaMiddleware class that validates a WP_REST_Request using Opis/json-schema
 *
 * @since 0.9.0
 *
 * @author André Gil <andre_gil22@hotmail.com>
 */
class SchemaMiddleware extends JsonSchema
{
    /**
     * Validates the JSON schema
     *
     * @param  WP_REST_Request  $request  Current REST Request.
     * @return ?WP_Error null on success or WP_Error on error.
     *
     * @since 0.9.0
     * @see $this->parse()
     */
    public function onRequest(WP_REST_Request $request): ?WP_Error
    {
        return $this->parse($request);
    }

    /**
     * Parses the JSON schema contents using the Opis/json-schema library
     *
     * @since 0.9.0
     * @see https://opis.io/json-schema
     *
     * @param  WP_REST_Request  $request  Current REST Request.
     * @return ?WpError null on success or WpError on error.
     */
    protected function parse(WP_REST_Request $request): ?WpError
    {
        if (! \apply_filters($this->suffix.'_is_to_parse', true, $this)) {
            return null;
        }

        $schema = $this->getSchema();
        if (! $schema) {
            return new WpError(
                WP_Http::UNPROCESSABLE_ENTITY,
                esc_html__('Unprocessable request. Always fails'),
            );
        }

        $params = \apply_filters($this->suffix.'_params', $request->get_params(), $request, $this);
        $payload = Helper::toJSON($params);
        $requestPayloadSchema = Helper::toJSON($schema);

        try {
            $result = $this->validator->validate($payload, $requestPayloadSchema);
        } catch (SchemaException $e) {
            return new WpError(
                WP_Http::INTERNAL_SERVER_ERROR,
                sprintf(esc_html__('Invalid request route schema %s'), $e->getMessage()),
            );
        }

        $isValid = \apply_filters($this->suffix.'_is_valid', $result->isValid(), $result, $request, $this);
        if (! $isValid) {
            $error = $this->getError($result);
            $data = is_string($error) ? [] : $error;

            return new WpError(
                WP_Http::UNPROCESSABLE_ENTITY,
                esc_html__('Unprocessable request'),
                $data,
            );
        }

        return null;
    }
}
