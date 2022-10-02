<?php

/**
 * Holds logic to search and retrieve the contents of a JSON schema.
 *
 * @since 0.9.0
 * @package wp-fastendpoints
 * @license MIT
 */

declare(strict_types=1);

namespace WP\FastEndpoints\Contracts\Schemas;

use Opis\JsonSchema\ValidationResult;
use Opis\JsonSchema\Errors\ErrorFormatter;
use WP_REST_Request;
use TypeError;
use WP\FastEndpoints\Helpers\Arr;

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
	 * @var string
	 */
	protected string $filepath;

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
	 * Creates a new instance of Base
	 *
	 * @since 0.9.0
	 * @param string|array<mixed> $schema - File name or path to the JSON schema or a JSON schema as an array.
	 * @throws TypeError - if $schema is neither a string or an array.
	 */
	public function __construct($schema)
	{
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
			throw new TypeError("Schema expected an array or a string in \$schema but {$type} given");
		}
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
		return \ltrim(\strtolower(\preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '_$0', $suffix)), '_');
	}

	/**
	 * Appends an additional directory where to look for the schema
	 *
	 * @since 0.9.0
	 * @param string|array<string> $schemaDir - Directory path or an array of directories where to
	 * look for JSON schemas.
	 */
	public function appendSchemaDir($schemaDir): void
	{
		if (!$schemaDir) {
			\wp_die('Invalid schema directory');
		}

		$schemaDir = Arr::wrap($schemaDir);
		foreach ($schemaDir as $dir) {
			if (\is_file($dir)) {
				\wp_die(\esc_html("Expected a directory with schemas but got a file: {$dir}"));
			}

			if (!\is_dir($dir)) {
				\wp_die(\esc_html("Schema directory not found: {$dir}"));
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
	 */
	protected function getValidSchemaFilepath(): string
	{
		if (\is_file($this->filepath)) {
			return $this->filepath;
		}

		foreach ($this->schemaDirs as $dir) {
			$filepath = \path_join($dir, $this->filepath);
			if (\is_file($filepath)) {
				return $filepath;
			}
		}

		\wp_die(\esc_html("Unable to find schema file: {$this->filepath}"));
	}

	/**
	 * Retrieves the ID of the schema
	 *
	 * @since 0.9.0
	 * @param WP_REST_Request $req - Current REST Request.
	 * @return string - URL schema id.
	 */
	protected function getSchemaId(WP_REST_Request $req): string
	{
		$filename = \basename($this->filepath);
		$route = $req->get_route();
		if (!\str_starts_with($route, '/wp-json')) {
			$route = "/wp-json{$route}";
		}
		if (!\str_ends_with($route, $filename)) {
			$route = "{$route}{$filename}";
		}
		$schemaId = \get_site_url(null, $route);
		return \apply_filters($this->suffix . '_id', $schemaId, $this, $req);
	}

	/**
	 * Retrieves a properly formatted error from Opis/json-schema
	 *
	 * @since 0.9.0
	 * @param ValidationResult $result - JSON Opis validation error result.
	 * @return mixed
	 */
	protected function getError(ValidationResult $result)
	{
		$formatter = new ErrorFormatter();
		return \apply_filters($this->suffix . '_error', $formatter->formatKeyed($result->error()), $result, $this);
	}

	/**
	 * Retrieves the JSON contents of the schema
	 *
	 * @since 0.9.0
	 * @return mixed
	 */
	public function getContents()
	{
		if ($this->contents) {
			$this->contents = \apply_filters($this->suffix . '_contents', $this->contents, $this);
			return $this->contents;
		}

		$filepath = $this->getValidSchemaFilepath();

		// Read JSON file and retrieve it's content.
		$result = \file_get_contents($filepath);
		if ($result === false) {
			return \wp_die(\esc_html("Unable to read file: {$this->filepath}"));
		}

		$this->contents = \json_decode($result, true);
		if ($this->contents === null && \JSON_ERROR_NONE !== \json_last_error()) {
			return \wp_die(\esc_html("Invalid json file: {$this->filepath} " . \json_last_error_msg()));
		}

		$this->contents = \apply_filters($this->suffix . '_contents', $this->contents, $this);
		return $this->contents;
	}
}
