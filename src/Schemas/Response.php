<?php

/**
 * Holds logic check/parse the REST response of an endpoint. Making sure that the required data is sent
 * back and that no unnecessary fields are retrieved.
 *
 * @since 0.9.0
 * @package wp-fastendpoints
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Schemas;

use Wp\FastEndpoints\Contracts\Schemas\Base;
use Wp\FastEndpoints\Contracts\Schemas\Response as ResponseContract;
use Opis\JsonSchema\Validator;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\SchemaLoader;
use Opis\JsonSchema\Resolvers\SchemaResolver;
use Opis\JsonSchema\Exceptions\SchemaException;
use Wp\FastEndpoints\Contracts\WpError;
use WP_REST_Request;
use WP_Error;
use WP_Http;
use Wp\FastEndpoints\Schemas\Opis\Parsers\ResponseSchemaParser;

/**
 * Response class that checks/parses the REST response of an endpoint before sending it to the client.
 *
 * @since 0.9.0
 * @author André Gil <andre_gil22@hotmail.com>
 */
class Response extends Base implements ResponseContract
{
	/**
	 * Data to be sent in the response to the client
	 *
	 * @since 0.9.0
	 * @var mixed
	 */
	private static $data = null;

	/**
	 * Makes sure that the data to be sent back to the client corresponds to the given JSON schema.
	 * It removes additional properties if the schema has 'additionalProperties' set to false (i.e. default value).
	 *
	 * @since 0.9.0
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

		// Create Validator and enable it to return all errors.
		$loader = new SchemaLoader(new ResponseSchemaParser(), new SchemaResolver(), true);
		self::$data = \apply_filters($this->suffix . '_before_validating', $res, $req, $this);
		self::$data = Helper::toJSON(self::$data);
		$schema = Helper::toJSON($this->contents);
		$validator = \apply_filters($this->suffix . '_validator', new Validator($loader), self::$data, $req, $this);
		try {
			$result = $validator->validate(self::$data, $schema);
		} catch (SchemaException $e) {
			$schemaId = $this->getSchemaId($req);
			return new WpError(
				WP_Http::UNPROCESSABLE_ENTITY,
				sprintf(esc_html__("Unprocessable resource %s"), $schemaId),
			);
		}

		$isValid = \apply_filters($this->suffix . '_is_valid', $result->isValid(), self::$data, $result, $req, $this);
		if (!$isValid) {
			$error = $this->getError($result);
			return new WpError(
				WP_Http::UNPROCESSABLE_ENTITY,
				$error,
			);
		}

		return \apply_filters($this->suffix . '_after_validating', self::$data, $req, $this);
	}

	/**
	 * Updates the data to be sent in the response
	 *
	 * @since 0.9.0
	 * @param mixed $data - The data to be sent in the response.
	 */
	public static function setData($data): void
	{
		self::$data = $data;
	}

	/**
	 * Retrieves the data to be sent in the response
	 *
	 * @since 0.9.0
	 * @return mixed $data - The data to be sent in the response.
	 */
	public static function getData()
	{
		return self::$data;
	}
}
