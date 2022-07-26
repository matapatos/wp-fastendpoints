<?php

namespace WP\FastAPI;

use Illuminate\Support\Str;

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
    public function append_schema_dir(string $schema_dir) {
        if (!$schema_dir) {
            wp_die('Invalid schema directory');
        }

        if (is_file($schema_dir)) {
            wp_die("Expected a directory with schemas but got a file: {$schema_dir}");
        }

        if (!is_dir($schema_dir)) {
            wp_die("Schema directory not found: {$schema_dir}");
        }

        $this->schema_dirs[] = $schema_dir;
    }

    /**
     * Validates if the given JSON schema filepath is an absolute path or if it exists
     * in the given schema directory
     * @since 0.9.0
     */
    private function get_valid_schema_filepath() {
        if (!is_file($this->filepath)) {
            return $this->filepath;
        }

        foreach ($this->schema_dirs as $dir) {
            $filepath = path_join($this->schema_dir, $this->filepath);
            if (is_file($filepath)) {
                return $filepath;
            }
        }

        wp_die("Unable to find schema file: {$this->filepath}");
    }
        }
    }

    /**
     * Retrieves the json schema
     *
     * @since 0.9.0
     */
    public function get(?string $schema_dir = null) {
        if ($this->contents) {
            return $this->contents;
        }

        $this->validate_schema_filepath($schema_dir);

        // Read JSON file and retrieve it's content
        $result = file_get_contents($this->filepath);
        if ($result === false) {
            return wp_die("Unable to read json schema file: {$this->filepath}");
        }

        $this->contents = json_decode($result, true);
        if (!$this->contents) {
            return wp_die("Invalid json schema: {$this->filepath}");
        }

        // Update additional_properties value if set
        if ($this->additional_properties !== null) {
            $this->contents['additionalProperties'] = $this->additional_properties;
        }
        return $this->contents;
    }
}
