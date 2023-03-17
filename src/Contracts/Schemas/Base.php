<?php

/**
 * Holds logic to search and retrieve the contents of a JSON schema.
 *
 * @version 1.0.0
 * @package wp-fastendpoints
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Contracts\Schemas;

use Opis\JsonSchema\ValidationResult;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Wp\Exceptions\HttpException;
use WP_REST_Request;
use TypeError;
use Illuminate\Support\Arr;
use WP_Http;

/**
 * Abstract class that holds logic to search and retrieve the contents of a
 * JSON schema.
 *
 * @version 1.0.0
 * @author André Gil <andre_gil22@hotmail.com>
 */
abstract class BaseSchema
{
    /**
     * Filter suffix used in this class
     *
     * @version 1.0.0
     * @var string
     */
    protected string $suffix;

    /**
     * The filepath of the JSON Schema: absolute or relative path
     *
     * @version 1.0.0
     */
    protected ?string $filepath = null;

    /**
     * The JSON Schema
     *
     * @version 1.0.0
     * @var mixed
     */
    protected $contents;

    /**
     * Directories where to look for a schema
     *
     * @version 1.0.0
     * @var array<string>
     */
    protected array $schemaDirs = [];

    /**
     * Creates a new instance of Base
     *
     * @version 1.0.0
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
            throw new TypeError(\sprintf(
                /* translators: 1: JSON Schema, 2: JSON Schema type */
                \esc_html__('Schema expected an array or a string in %1$s but %2$s given'),
                \esc_html($schema),
                \esc_html($type),
            ));
        }
    }

    /**
     * Retrieves the child class name in snake case
     *
     * @version 1.0.0
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
     * @version 1.0.0
     * @param string|array<string> $schemaDir - Directory path or an array of directories where to
     * look for JSON schemas.
     */
    public function appendSchemaDir($schemaDir): void
    {
        if (!$schemaDir) {
            throw new TypeError(\esc_html__('Invalid schema directory'));
        }

        $schemaDir = Arr::wrap($schemaDir);
        foreach ($schemaDir as $dir) {
            if (\is_file($dir)) {
                throw new TypeError(
                    /* translators: 1: Directory */
                    \sprintf(\esc_html__("Expected a directory with schemas but got a file: %s"), \esc_html($dir)),
                );
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
     * @version 1.0.0
     * @return string
     */
    protected function getValidSchemaFilepath(): string
    {
        if (!$this->filepath) {
            throw new HttpException('No json schema specified', WP_Http::NOT_FOUND);
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

        $message = sprintf(esc_html__('Json schema %s not found'), $this->filepath);
        throw new HttpException($message, WP_Http::NOT_FOUND);
    }

    /**
     * Retrieves the ID of the schema
     *
     * @version 1.0.0
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
     * @version 1.0.0
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
     * @version 1.0.0
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
            /* translators: 1: Schema filepath */
            return \wp_die(\sprintf(\esc_html__("Unable to read file: %s"), \esc_html($this->filepath)));
        }

        $this->contents = \json_decode($result, true);
        if ($this->contents === null && \JSON_ERROR_NONE !== \json_last_error()) {
            /* translators: 1: Schema filepath, 2: JSON error message */
            return \wp_die(\sprintf(
                \esc_html__("Invalid json file: %1\$s %2\$s"),
                \esc_html($this->filepath),
                \esc_html(\json_last_error_msg()),
            ));
        }

        $this->contents = \apply_filters($this->suffix . '_contents', $this->contents, $this);
        return $this->contents;
    }
}
