<?php

/**
 * Holds logic check/parse the REST response of an endpoint. Making sure that the required data is sent
 * back and that no unnecessary fields are retrieved.
 *
 * @since 0.9.0
 *
 * @package wp-fastendpoints
 * @license MIT
 */

declare(strict_types=1);

namespace WP\FastEndpoints\Schemas;

use WP\FastEndpoints\Contracts\Schemas\Base;
use WP\FastEndpoints\Contracts\Schemas\Response as ResponseInterface;
use Opis\JsonSchema\Validator;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Exceptions\SchemaException;
use WP_REST_Request;
use WP_Error;
use WP_Http;

/**
 * Response class that checks/parses the REST response of an endpoint before sending it to the client.
 *
 * @since 0.9.0
 *
 * @author André Gil <andre_gil22@hotmail.com>
 */
class Response extends Base implements ResponseInterface
{
	/**
	 * JSON Schema property used to remove additional properties from the schema
	 *
	 * @since 0.9.0
	 *
	 * @var string
	 */
	protected const ADDITIONAL_PROPERTIES = 'additionalProperties';

	/**
	 * JSON Schema property used to define the properties of an object - we use it
	 * to check if the error in the properties is due to additionalProperties as well
	 *
	 * @since 0.9.0
	 *
	 * @var string
	 */
	protected const PROPERTIES = 'properties';

	/**
	 * Makes sure that the data to be sent back to the client corresponds to the given JSON schema.
	 * It removes additional properties if the schema has 'additionalProperties' set to false (i.e. default value).
	 *
	 * @since 0.9.0
	 *
	 * @param WP_REST_Request $req - Current REST Request.
	 * @param mixed $res - Current REST response.
	 * @return mixed|WP_Error - Mixed on parsed response or WP_Error on error.
	 */
	public function returns(WP_REST_Request $req, $res)
	{
		if (!\apply_filters($this->suffix . '_is_to_validate', true, $this)) {
			return $res;
		}

		$this->contents = $this->getContents();
		if (!$this->contents) {
			return $res;
		}

		$schemaId = $this->getSchemaId($req);
		$validator = new Validator();
		$resolver = $validator->resolver();
		$res = \apply_filters($this->suffix . '_response', $res, $req, $this);
		$res = Helper::toJSON($res);
		$schema = Helper::toJSON($this->contents);
		try {
			$result = $validator->validate($res, $schema);
		} catch (SchemaException $e) {
			return new WP_Error(
				'unprocessable_entity',
				"Unprocessable resource {$schemaId}",
				['status' => WP_Http::UNPROCESSABLE_ENTITY],
			);
		}

		if (!$result->isValid()) {
			$error = $result->error();
			if (!$this->removeAdditionalProperties($error, $res)) {
				$error = $this->getError($result);
				return new WP_Error(
					'unprocessable_entity',
					$error,
					['status' => WP_Http::UNPROCESSABLE_ENTITY],
				);
			}
		}

		return $res;
	}

	/**
	 * Removes additional properties from the data if the 'additionalProperties' field
	 * in the JSON Schema is set to false.
	 *
	 * @since 0.9.0
	 *
	 * @param ValidationError $error - Opis/json-schema error.
	 * @param mixed $data - The data to be retrieved to the client.
	 * @return bool - true if it's an additionalProperties error or false otherwise.
	 */
	protected function removeAdditionalProperties(ValidationError $error, &$data): bool
	{
		$keyword = $error->keyword();
		if ($keyword === self::ADDITIONAL_PROPERTIES) {
			$fullpath = $error->data()->fullPath();
			foreach ($error->args()['properties'] as $index => $name) {
				$d = &$data;
				foreach ($fullpath as $path) {
					$d = &$d->{$path};
				}

				unset($d);
			}
			return true;
		}

		// Is the error not regarding the properties field? If so, we already know that it's not
		// an 'additionalProperty' error.
		if ($keyword !== self::PROPERTIES) {
			return false;
		}

		// Check errors from the 'properties' field.
		$isAdditionalProperties = false;
		foreach ($error->subErrors() as $e) {
			if (!$this->removeAdditionalProperties($e, $data)) {
				return false;
			}
		}
		return true;
	}
}
