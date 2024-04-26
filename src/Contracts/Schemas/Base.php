<?php

/**
 * Holds logic to search and retrieve the contents of a JSON schema.
 *
 * @since 0.9.0
 * @package wp-fastendpoints
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Contracts\Schemas;

use Exception;
use Opis\JsonSchema\Resolvers\SchemaResolver;
use Opis\JsonSchema\SchemaLoader;
use Opis\JsonSchema\ValidationResult;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Wp\FastEndpoints\Schemas\Opis\Parsers\ResponseSchemaParser;
use WP_REST_Request;
use TypeError;
use Wp\FastEndpoints\Helpers\Arr;

/**
 * Abstract class that holds logic to search and retrieve the contents of a
 * JSON schema.
 *
 * @since 0.9.0
 * @author André Gil <andre_gil22@hotmail.com>
 */
abstract class Base
{
	/**
	 * Filter suffix used in this class
	 *
	 * @since 0.9.0
	 * @var string
	 */
	protected string $suffix;

	/**
	 * The filepath of the JSON Schema: absolute or relative path
	 *
	 * @since 0.9.0
	 */
	protected ?string $filepath = null;

	/**
	 * The JSON Schema
	 *
	 * @since 0.9.0
	 * @var mixed
	 */
	protected $contents;

	/**
	 * Directories where to look for a schema
	 *
	 * @since 0.9.0
	 * @var array<string>
	 */
	protected array $schemaDirs = [];

    /**
     * Class used to format validation errors which are shown to the user
     *
     * @since 0.9.0
     * @var ErrorFormatter
     */
    protected ErrorFormatter $errorFormatter;

    /**
     * Validator used for checking data against their JSON schema
     *
     * @var Validator
     */
    protected static Validator $validator;

	/**
	 * Creates a new instance of Base
	 *
	 * @since 0.9.0
	 * @param string|array $schema File name or path to the JSON schema or a JSON schema as an array.
	 * @throws TypeError if $schema is neither a string or an array.
	 */
	public function __construct($schema)
	{
        $this->errorFormatter = new ErrorFormatter();
        $this->suffix = $this->getSuffix();
		if (\is_string($schema)) {
			$this->filepath = $schema;
			if (!\str_ends_with($schema, '.json')) {
				$this->filepath .= '.json';
			}
		} elseif (\is_array($schema)) {
			$this->contents = $schema;
		} else {
			$type = \gettype($schema);
			throw new TypeError(\sprintf(
				/* translators: 1: JSON Schema, 2: JSON Schema type */
				\esc_html__('Schema expected an array or a string in %1$s but %2$s given'),
				\esc_html($schema),
				\esc_html($type),
			));
		}
	}

    /**
     * Retrieves the default validator for checking responses against a JSON schema
     *
     * @return Validator the default validator
     */
    protected static function getDefaultValidator(): Validator
    {
        if(!isset(self::$validator)) {
            $loader = new SchemaLoader(new ResponseSchemaParser(), new SchemaResolver(), true);
            self::$validator = new Validator($loader);
        }

        return self::$validator;
    }

	/**
	 * Retrieves the child class name in snake case
	 *
	 * @since 0.9.0
	 * @return string
	 */
	protected function getSuffix(): string
	{
		$suffix = \basename(\str_replace('\\', '/', \get_class($this)));
		$suffix = \ltrim(\strtolower(\preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '_$0', $suffix)), '_');
        return "fastendpoints_{$suffix}";
	}

	/**
	 * Appends an additional directory where to look for the schema
	 *
	 * @since 0.9.0
	 * @param string|array<string> $schemaDir Directory path or an array of directories where to
	 * look for JSON schemas.
	 */
	public function appendSchemaDir($schemaDir): void
	{
		$schemaDir = Arr::wrap($schemaDir);
		foreach ($schemaDir as $dir) {
            if (!\is_string($dir)) {
                throw new TypeError(\sprintf(\esc_html__("Expected a directory as a string but got: %s"), gettype($dir)));
            }

            if (!$dir) {
                throw new TypeError(\esc_html__('Invalid schema directory'));
            }

			if (\is_file($dir)) {
				/* translators: 1: Directory */
				throw new TypeError(\sprintf(\esc_html__("Expected a directory with schemas but got a file: %s"), \esc_html($dir)));
			}

			if (!\is_dir($dir)) {
				/* translators: 1: Directory */
				throw new TypeError(\sprintf(\esc_html__("Schema directory not found: %s"), \esc_html($dir)));
			}
		}

		$this->schemaDirs = $this->schemaDirs + $schemaDir;
	}

	/**
	 * Validates if the given JSON schema filepath is an absolute path or if it exists
	 * in the given schema directory
	 *
	 * @since 0.9.0
	 * @return string
	 * @throws Exception if no schema is specified or cannot be found.
	 */
	protected function getValidSchemaFilepath(): string
	{
		if (!$this->filepath) {
			throw new Exception('No schema filepath specified');
		}

		if (\is_file($this->filepath)) {
			return $this->filepath;
		}

		foreach ($this->schemaDirs as $dir) {
			$filepath = \path_join($dir, $this->filepath);
			if (\is_file($filepath)) {
				return $filepath;
			}
		}

		throw new Exception("Schema filepath not found {$this->filepath}");
	}

	/**
	 * Retrieves a properly formatted error from Opis/json-schema
	 *
	 * @since 0.9.0
	 * @param ValidationResult $result JSON Opis validation error result.
	 * @return mixed
	 */
	protected function getError(ValidationResult $result)
	{
		return \apply_filters($this->suffix . '_error', $this->errorFormatter->formatKeyed($result->error()), $result, $this);
	}

    /**
     * Retrieves the content of a file
     *
     * @since 0.9.0
     * @param string $filePath the file to be loaded
     * @return mixed
     */
    protected function getFileContents(string $filePath)
    {
        return \file_get_contents($filePath);
    }

	/**
	 * Retrieves the JSON contents of the schema
	 *
	 * @since 0.9.0
	 * @return mixed
	 */
	public function getContents()
	{
		if ($this->contents || is_array($this->contents)) {
			$this->contents = \apply_filters($this->suffix . '_contents', $this->contents, $this);
			return $this->contents;
		}

		$filepath = $this->getValidSchemaFilepath();

		// Read JSON file and retrieve it's content.
		$result = $this->getFileContents($filepath);
		if ($result === false) {
			/* translators: 1: Schema filepath */
			\wp_die(\sprintf(\esc_html__("Unable to read file: %s"), \esc_html($this->filepath)));
		}

		$this->contents = \json_decode($result, true);
		if ($this->contents === null && \JSON_ERROR_NONE !== \json_last_error()) {
			/* translators: 1: Schema filepath, 2: JSON error message */
			\wp_die(\sprintf(
				\esc_html__("Invalid json file: %1\$s %2\$s"),
				\esc_html($this->filepath),
				\esc_html(\json_last_error_msg()),
			));
		}

		$this->contents = \apply_filters($this->suffix . '_contents', $this->contents, $this);
		return $this->contents;
	}
}
