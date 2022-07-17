<?php

namespace WP\FastAPI;

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
    private $additionalProperties;

    public function __construct($filepath, $additionalProperties = null) {
        $this->additionalProperties = $additionalProperties;
        if (is_array($filepath)) {
            $this->contents = $filepath;
            if ($this->additionalProperties !== null) {
                $this->contents['additionalProperties'] = $this->additionalProperties;
            }
        } else {
            if (!file_exists($filepath)) {
                return wp_die("Schema not found: {$filepath}");
            }
            $this->filepath = $filepath;
        }
    }

    /**
     * Retrieves the json schema
     *
     * @since 0.9.0
     */
    public function get() {
        if ($this->contents) {
            return $this->contents;
        }

        // Read JSON file and retrieve it's content
        $result = file_get_contents($this->filepath);
        if ($result === false) {
            return new \WP_Error(500, "Unable to read json schema file: {$this->filepath}");
        }

        $this->contents = json_decode($result, true);
        if (!$this->contents) {
            return new \WP_Error(500, "Invalid json schema: {$this->filepath}")
        }

        // Update additionalProperties value if set
        if ($this->additionalProperties !== null) {
            $this->contents['additionalProperties'] = $this->additionalProperties;
        }
        return $this->contents;
    }
}
