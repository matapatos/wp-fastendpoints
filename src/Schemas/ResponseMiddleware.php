<?php

/**
 * Holds logic check/parse the REST response of an endpoint. Making sure that the required data is sent
 * back and that no unnecessary fields are retrieved.
 *
 * @since 0.9.0
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Schemas;

use Opis\JsonSchema\Exceptions\SchemaException;
use Opis\JsonSchema\Helper;
use TypeError;
use Wp\FastEndpoints\Contracts\JsonSchema;
use Wp\FastEndpoints\Helpers\Arr;
use Wp\FastEndpoints\Helpers\WpError;
use WP_Error;
use WP_Http;
use WP_REST_Request;
use WP_REST_Response;

/**
 * ResponseMiddleware class that checks/parses the REST response of an endpoint before sending it to the client.
 *
 * @since 0.9.0
 *
 * @author André Gil <andre_gil22@hotmail.com>
 */
class ResponseMiddleware extends JsonSchema
{
    /**
     * Data to be sent in the response to the client
     *
     * @since 0.9.0
     */
    protected static mixed $data = null;

    /**
     * Do we want to remove additional properties from the response
     *
     * @since 0.9.0
     */
    protected bool|string|null $removeAdditionalProperties;

    /**
     * Determines if a schema has been updated regarding the additional properties
     *
     * @since 1.0.0
     */
    protected bool $hasUpdatedSchema = false;

    /**
     * Holds all the possible removeAdditionalProperties options. Uses a dict for fast reading
     *
     * @since 1.0.0
     *
     * @var array
     */
    protected const VALID_STR_REMOVE_ADDITIONAL_PROPERTIES = [
        'string' => 0,
        'number' => 0,
        'integer' => 0,
        'boolean' => 0,
        'null' => 0,
        'object' => 0,
        'array' => 0,
    ];

    /**
     * Creates a new instance of JsonSchema
     *
     * @since 0.9.0
     *
     * @param  string|array  $schema  File name or path to the JSON schema or a JSON schema as an array.
     * @param  bool|string|null  $removeAdditionalProperties  Determines if we want to keep additional properties.
     *                                                        If set to null assumes that the schema will take care of that. If a string is given it assumes only those
     *                                                        types of properties are allowed.
     *
     * @throws TypeError if $schema is neither a string or an array.
     */
    public function __construct(string|array $schema, bool|string|null $removeAdditionalProperties = null)
    {
        parent::__construct($schema);
        $this->removeAdditionalProperties = $this->parseRemoveAdditionalProperties($removeAdditionalProperties);
    }

    /**
     * Validates a given removeAdditionalProperties option
     *
     * @param  bool|null|string  $removeAdditionalProperties  Option to be validated
     *
     * @returns bool|null|string validated option
     *
     * @throws \ValueError if an invalid option is found
     */
    protected function parseRemoveAdditionalProperties(bool|string|null $removeAdditionalProperties): bool|string|null
    {
        if (is_bool($removeAdditionalProperties) || is_null($removeAdditionalProperties)) {
            return $removeAdditionalProperties;
        }

        if (array_key_exists($removeAdditionalProperties, self::VALID_STR_REMOVE_ADDITIONAL_PROPERTIES)) {
            return $removeAdditionalProperties;
        }

        throw new \ValueError(sprintf(esc_html__("Invalid removeAdditionalProperties property (%s) '%s'"),
            esc_html(gettype($removeAdditionalProperties)), esc_html($removeAdditionalProperties)));
    }

    /**
     * Makes sure that the data to be sent back to the client corresponds to the given JSON schema.
     * It removes additional properties if the schema has 'additionalProperties' set to false (i.e. default value).
     */
    protected function updateSchemaToAcceptOrDiscardAdditionalProperties(): void
    {
        if ($this->hasUpdatedSchema) {
            return;
        }

        $this->hasUpdatedSchema = true;
        $removeAdditionalProperties = \apply_filters($this->suffix.'_remove_additional_properties', $this->removeAdditionalProperties, $this);
        // Do we want to let the schema decide if we want additionalProperties?
        if (is_null($removeAdditionalProperties)) {
            return;
        }

        if (! $this->contents || ! is_array($this->contents)) {
            return;
        }

        // Is there any type object properties in the schema?
        $foundTypeObjectsIndexes = Arr::recursiveKeyValueSearch($this->contents, 'type', 'object');
        if (! $foundTypeObjectsIndexes) {
            return;
        }

        // Update additional_properties
        foreach ($foundTypeObjectsIndexes as $schemaKeys) {
            $contents = &$this->contents;
            foreach ($schemaKeys as $key) {
                $contents = &$contents[$key];
            }

            if (is_bool($this->removeAdditionalProperties)) {
                $contents['additionalProperties'] = ! $this->removeAdditionalProperties;
            } else {
                $contents['additionalProperties'] = ['type' => $this->removeAdditionalProperties];
            }
        }
    }

    /**
     * Makes sure that the data to be sent back to the client corresponds to the given JSON schema.
     * It removes additional properties if the schema has 'additionalProperties' set to false (i.e. default value).
     *
     * @param  WP_REST_Request  $request  Current REST Request.
     * @param  mixed  $response  Current REST response.
     * @return ?WP_Error null if nothing to change or WpError on error.
     *
     *@since 0.9.0
     */
    public function onResponse(WP_REST_Request $request, WP_REST_Response $response): ?WP_Error
    {
        if (! \apply_filters($this->suffix.'_is_to_validate', true, $this)) {
            return null;
        }

        $this->contents = $this->getContents();
        if (! $this->contents) {
            return null;
        }

        // Create Validator and enable it to return all errors.
        self::$data = \apply_filters($this->suffix.'_validation_data', $response->get_data(), $request, $this);
        $validator = \apply_filters($this->suffix.'_validator', self::getDefaultValidator(), self::$data, $request, $this);
        $schema = Helper::toJSON($this->contents);
        self::$data = Helper::toJSON(self::$data);
        try {
            $result = $validator->validate(self::$data, $schema);
        } catch (SchemaException $e) {
            $wpError = new WpError(
                WP_Http::INTERNAL_SERVER_ERROR,
                sprintf(esc_html__('Invalid response schema: %s'), esc_html__($e->getMessage())),
            );

            return \apply_filters($this->suffix.'_on_validation_error', $wpError, $request, $this);
        }

        $isValid = \apply_filters($this->suffix.'_is_valid', $result->isValid(), self::$data, $result, $request, $this);
        if (! $isValid) {
            $wpError = new WpError(WP_Http::UNPROCESSABLE_ENTITY, $this->getError($result));

            return \apply_filters($this->suffix.'_on_validation_error', $wpError, $request, $this);
        }

        $response->set_data(\apply_filters($this->suffix.'_on_validation_success', self::$data, $request, $this));

        return null;
    }

    /**
     * Retrieves the JSON contents of the schema
     *
     * @since 0.9.0
     */
    public function getContents(): mixed
    {
        $this->contents = parent::getContents();
        $this->updateSchemaToAcceptOrDiscardAdditionalProperties();

        return $this->contents;
    }

    /**
     * Updates the data to be sent in the response
     *
     * @since 0.9.0
     *
     * @param  mixed  $data  The data to be sent in the response.
     */
    public static function setData(mixed $data): void
    {
        self::$data = $data;
    }

    /**
     * Retrieves the data to be sent in the response
     *
     * @since 0.9.0
     *
     * @return mixed $data The data to be sent in the response.
     */
    public static function getData(): mixed
    {
        return self::$data;
    }
}
