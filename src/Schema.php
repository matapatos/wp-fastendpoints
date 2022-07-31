<?php

namespace WP\FastAPI;

use Illuminate\Support\{
    Str,
    Arr,
};
use Opis\JsonSchema\{
    Validator,
    Helper,
    ValidationResult,
    Errors\ErrorFormatter,
    Errors\ValidationError,
    Exceptions\SchemaException,
};

class Schema {

    /**
     * The filepath of the JSON Schema: absolute path or relative*
     * @since 0.9.0
     */
    private string $filepath;

    /**
     * The JSON Schema
     * @since 0.9.0
     */
    private $contents;

    /**
     * Directories where to look for a schema
     * @since 0.9.0
     */
    private array $schema_dirs = [];

    /**
     * Should the schema allow additional properties?
     * If set to null it will use the value set in the schema
     */
    private ?bool $additional_properties;

    public function __construct($filepath, ?bool $additional_properties = null) {
        $this->additional_properties = $additional_properties;
        if (is_string($filepath)) {
            $this->filepath = Str::finish($filepath, '.json');
        } else if (is_array($filepath)) {
            $this->contents = $filepath;
            if ($this->additional_properties !== null) {
                $this->contents['additionalProperties'] = $this->additional_properties;
            }
        } else {
            $type = gettype($filepath);
            wp_die("Schema expected an array or the filepath but {$type} given");
        }
    }

    /**
     * Appends an additional directory where to look for the schema
     * @since 0.9.0
     */
    public function append_schema_dir($schema_dir) {
        if (!$schema_dir) {
            wp_die('Invalid schema directory');
        }

        $schema_dir = Arr::wrap($schema_dir);
        foreach ($schema_dir as $dir) {
            if (is_file($dir)) {
                wp_die("Expected a directory with schemas but got a file: {$dir}");
            }

            if (!is_dir($dir)) {
                wp_die("Schema directory not found: {$dir}");
            }
        }

        $this->schema_dirs = $this->schema_dirs + $schema_dir;
    }

    /**
     * Validates if the given JSON schema filepath is an absolute path or if it exists
     * in the given schema directory
     * @since 0.9.0
     */
    private function get_valid_schema_filepath() {
        if (is_file($this->filepath)) {
            return $this->filepath;
        }

        foreach ($this->schema_dirs as $dir) {
            $filepath = path_join($dir, $this->filepath);
            if (is_file($filepath)) {
                return $filepath;
            }
        }

        wp_die("Unable to find schema file: {$this->filepath}");
    }

    /**
     * Retrieves the ID of the schema
     * @since 0.9.0
     */
    private function get_schema_id(\WP_REST_Request $req) {
        $filename = basename($this->filepath);
        $route = Str::start('/wp-json', $req->get_route());
        $route = Str::finish($route, $filename);
        $schema_id = get_site_url(null, $route);
        return apply_filters('wp_fastapi_schema_id', $schema_id, $this, $req);
    }

    /**
     * Formats a Opis/json-schema error
     * @since 0.9.0
     */
    private function get_error(ValidationResult $result) {
        $formatter = new ErrorFormatter();
        return apply_filters('wp_fastapi_schema_error', $formatter->formatKeyed($result->error()), $result, $this);
    }

    /**
     * Parses the JSON schema contents using the Opis/json-schema library
     * @see https://opis.io/json-schema
     * @since 0.9.0
     */ 
    protected function parse(\WP_REST_Request $req) {
        $is_to_parse = apply_filters('wp_fastapi_schema_is_to_parse', true, $this);
        if (!$is_to_parse) {
            return;
        }

        if (!$this->contents) {
            return true;
        }

        $schema_id = $this->get_schema_id($req);
        $validator = new Validator();
        $resolver = $validator->resolver();
        $params = apply_filters('wp_fastapi_schema_params', $req->get_params(), $req, $this);
        $json = Helper::toJSON($params);
        $schema = Helper::toJSON($this->contents);
        try {
            $result = $validator->validate($json, $schema);
        } catch (SchemaException $e) {
            return new \WP_Error(\WP_Http::UNPROCESSABLE_ENTITY, "Unprocessable schema {$schema_id}", $e->getMessage());
        }

        if (!$result->isValid()) {
            $error = $this->get_error($result);
            return new \WP_Error(\WP_Http::UNPROCESSABLE_ENTITY, $error);
        }

        return true;
    }

    /**
     * Retrieves the JSON contents of the schema
     * @since 0.9.0
     */
    public function get_contents() {
        if ($this->contents) {
            $this->contents = apply_filters('wp_fastapi_schema_contents', $this->contents, $this);
            return $this->contents;
        }

        $filepath = $this->get_valid_schema_filepath();

        // Read JSON file and retrieve it's content
        $result = file_get_contents($filepath);
        if ($result === false) {
            return wp_die("Unable to read file: {$this->filepath}");
        }

        $this->contents = json_decode($result, true);
        if ($this->contents === null && \JSON_ERROR_NONE !== json_last_error()) {
            return wp_die("Invalid json file: {$this->filepath} " . json_last_error_msg());
        }

        // Update additional_properties value if set
        if (is_array($this->contents) && $this->additional_properties !== null) {
            $this->contents['additionalProperties'] = $this->additional_properties;
        }

        $this->contents = apply_filters('wp_fastapi_schema_contents', $this->contents, $this);
        return $this->contents;
    }

    /**
     * Validates the JSON schema
     * @see $this->parse()
     * @since 0.9.0
     */
    public function validate(\WP_REST_Request $req) {
        $this->contents = $this->get_contents();
        return $this->parse($req);
    }
}
