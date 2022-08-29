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

use Opis\JsonSchema\Validator;
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
class Response extends Base
{
	/**
	 * Makes sure that the data to be sent back to the client corresponds to the given JSON schema.
	 * It removes additional properties $this->additionalProperties is set to false.
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
			if ($error->keyword() !== 'additionalProperties') {
				$error = $this->getError($result);
				return new WP_Error(
					'unprocessable_entity',
					$error,
					['status' => WP_Http::UNPROCESSABLE_ENTITY],
				);
			}

			// Remove additional data.
			foreach ($error->args()['properties'] as $index => $name) {
				unset($res->{$name});
			}
		}

		return $res;
	}
}
