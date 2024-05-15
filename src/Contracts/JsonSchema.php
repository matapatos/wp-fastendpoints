<?php

/**
 * Holds logic to search and retrieve the contents of a JSON schema.
 *
 * @since 0.9.0
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Contracts;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Parsers\SchemaParser;
use Opis\JsonSchema\SchemaLoader;
use Opis\JsonSchema\ValidationResult;
use Opis\JsonSchema\Validator;
use Wp\FastEndpoints\Schemas\SchemaResolver;

/**
 * Abstract class that holds logic to search and retrieve the contents of a
 * JSON schema.
 *
 * @since 0.9.0
 *
 * @author André Gil <andre_gil22@hotmail.com>
 */
abstract class JsonSchema extends Middleware
{
    /**
     * Filter suffix used in this class
     *
     * @since 0.9.0
     */
    protected string $suffix;

    /**
     * The JSON SchemaMiddleware
     *
     * @since 1.2.1
     */
    protected string|array $schema;

    /**
     * Class used to format validation errors which are shown to the user
     *
     * @since 0.9.0
     */
    protected ErrorFormatter $errorFormatter;

    /**
     * Validator used for checking data against their JSON schema
     */
    protected Validator $validator;

    /**
     * Creates a new instance of JsonSchema
     *
     * @param  string|array  $schema  File name or path to the JSON schema or a JSON schema as an array.
     * @param  ?SchemaResolver  $schemaResolver  The validator to be used for validation.
     *
     * @since 0.9.0
     */
    public function __construct(string|array $schema, ?SchemaResolver $schemaResolver = null)
    {
        parent::__construct();
        $this->schema = $schema;
        $this->validator = $this->createValidatorWithResolver($schemaResolver);
        $this->errorFormatter = new ErrorFormatter();
        $this->suffix = $this->getSuffix();
    }

    /**
     * Retrieves the child class name in snake case
     *
     * @since 0.9.0
     */
    protected function getSuffix(): string
    {
        $suffix = \basename(\str_replace('\\', '/', \get_class($this)));
        $suffix = \ltrim(\strtolower(\preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '_$0', $suffix)), '_');
        $suffix = \str_replace('_middleware', '', $suffix);

        return "fastendpoints_{$suffix}";
    }

    /**
     * Retrieves a properly formatted error from Opis/json-schema
     *
     * @since 0.9.0
     *
     * @param  ValidationResult  $result  JSON Opis validation error result.
     */
    protected function getError(ValidationResult $result): mixed
    {
        return \apply_filters($this->suffix.'_error', $this->errorFormatter->formatKeyed($result->error()), $result, $this);
    }

    /**
     * Retrieves the JSON schema to be used for validation
     */
    public function getSchema(): mixed
    {
        $schema = $this->schema;
        if (! is_string($schema)) {
            return $schema;
        }

        if (! str_ends_with($schema, '.json')) {
            $schema .= '.json';
        }

        if (filter_var($schema, FILTER_VALIDATE_URL) === false) {
            $schema = $this->validator->resolver()->getDefaultPrefix().'/'.ltrim($schema, '/');
        }

        return $schema;
    }

    /**
     * Retrieves a JSON schema validator with a given SchemaResolver
     *
     * @param  ?SchemaResolver  $resolver
     */
    public function createValidatorWithResolver(?SchemaResolver $resolver): Validator
    {
        $resolver = $resolver ?? new SchemaResolver();
        $schemaLoader = new SchemaLoader(new SchemaParser(), $resolver, true);

        return new Validator($schemaLoader);
    }
}
