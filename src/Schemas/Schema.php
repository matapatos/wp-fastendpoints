<?php

/**
 * Holds logic to validate a WP_REST_Request before running the enpoint handler.
 *
 * @version 1.0.0
 * @package wp-fastendpoints
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Schemas;

use Opis\JsonSchema\Validator;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Exceptions\SchemaException;
use Wp\Exceptions\HttpException;
use WP_REST_Request;
use WP_Http;
use Wp\FastEndpoints\Contracts\Schemas\BaseSchema;
use Wp\FastEndpoints\Contracts\Schemas\SchemaInterface;

/**
 * Schema class that validates a WP_REST_Request using Opis/json-schema
 *
 * @version 1.0.0
 * @author André Gil <andre_gil22@hotmail.com>
 */
class Schema extends BaseSchema implements SchemaInterface
{
    /**
     * Validates the JSON schema
     *
     * @version 1.0.0
     * @see $this->parse()
     * @param WP_REST_Request $req - Current REST Request.
     * @throws HttpException
     */
    public function validate(WP_REST_Request $req): void
    {
        $this->contents = $this->getContents();
        $this->parse($req);
    }

    /**
     * Parses the JSON schema contents using the Opis/json-schema library
     *
     * @version 1.0.0
     * @see https://opis.io/json-schema
     * @param WP_REST_Request $req - Current REST Request.
     * @throws HttpException
     */
    protected function parse(WP_REST_Request $req): void
    {
        if (!apply_filters($this->suffix . '_is_to_parse', true, $this)) {
            return;
        }

        if (!$this->contents) {
            return;
        }

        $schemaId = $this->getSchemaId($req);
        $params = apply_filters($this->suffix . '_params', $req->get_params(), $req, $this);
        $json = Helper::toJSON($params);
        $schema = Helper::toJSON($this->contents);
        $validator = apply_filters($this->suffix . '_validator', new Validator(), $req, $this);
        try {
            $result = $validator->validate($json, $schema);
        } catch (SchemaException $e) {
            $message = sprintf(esc_html__('Unprocessable schema %s'), $schemaId);
            throw new HttpException($message, WP_Http::UNPROCESSABLE_ENTITY);
        }

        $isValid = apply_filters($this->suffix . '_is_valid', $result->isValid(), $result, $req, $this);
        if (!$isValid) {
            $error = $this->getError($result);
            throw new HttpException($error, WP_Http::UNPROCESSABLE_ENTITY);
        }
    }
}
