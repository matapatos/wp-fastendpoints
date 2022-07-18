<?php

namespace WP\FastAPI;

use Illuminate\Support\Str;

class Schema {

    /**
     * The filepath of the JSON Schema: absolute path or relative
     */
    private string $filepath;

    /**
     * The JSON Schema
     */
    private $contents;

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
        }
    }

    /**
     * Validates if the given JSON schema filepath is an absolute path or if it exists
     * in the given schema directory
     *
     * @since 0.9.0
     */
    private function validate_schema_filepath(?string $schema_dir = null) {
        if ($schema_dir) {
            if (!is_file($this->filepath)) {
                if (!is_dir($schema_dir)) {
                    wp_die("Expected a directory: {$schema_dir}");
                }

                $this->filepath = path_join($schema_dir, $this->filepath);
            }
        }

        if (!is_file($this->filepath)) {
            wp_die("Unable to find schema file: {$this->filepath}");
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
