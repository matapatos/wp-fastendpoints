<?php

/**
 * Holds logic to validate a WP_REST_Request before running the enpoint handler.
 *
 * @since 0.9.0
 *
 * @package wp-fastendpoints
 * @license MIT
 */

declare(strict_types=1);

namespace WP\FastEndpoints\Schemas;

use Opis\JsonSchema\Validator;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Exceptions\SchemaException;
use WP_REST_Request;
use WP_Error;
use WP_Http;

/**
 * Schema class that validates a WP_REST_Request using Opis/json-schema
 *
 * @since 0.9.0
 *
 * @author André Gil <andre_gil22@hotmail.com>
 */
class Schema extends Base
{
	/**
	 * Parses the JSON schema contents using the Opis/json-schema library
	 *
	 * @since 0.9.0
	 *
	 * @see https://opis.io/json-schema
	 * @param WP_REST_Request $req - Current REST Request.
	 * @return true|WP_Error - true on success and WP_Error on error.
	 */
	protected function parse(WP_REST_Request $req)
	{
		if (!\apply_filters($this->suffix . '_is_to_parse', true, $this)) {
			return true;
		}

		if (!$this->contents) {
			return true;
		}

		$schemaId = $this->getSchemaId($req);
		$validator = new Validator();
		$resolver = $validator->resolver();
		$params = \apply_filters($this->suffix . '_params', $req->get_params(), $req, $this);
		$json = Helper::toJSON($params);
		$schema = Helper::toJSON($this->contents);
		try {
			$result = $validator->validate($json, $schema);
		} catch (SchemaException $e) {
			return new WP_Error(
				'unprocessable_entity',
				"Unprocessable schema {$schemaId}",
				['status' => WP_Http::UNPROCESSABLE_ENTITY],
			);
		}

		if (!$result->isValid()) {
			$error = $this->getError($result);
			return new WP_Error(
				'unprocessable_entity',
				$error,
				['status' => WP_Http::UNPROCESSABLE_ENTITY],
			);
		}

		return true;
	}

	/**
	 * Validates the JSON schema
	 *
	 * @since 0.9.0
	 *
	 * @see $this->parse()
	 * @param WP_REST_Request $req - Current REST Request.
	 * @return true|WP_Error - true on success and WP_Error on error.
	 */
	public function validate(WP_REST_Request $req)
	{
		$this->contents = $this->getContents();
		return $this->parse($req);
	}
}
