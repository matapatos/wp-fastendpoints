<?php

namespace WP\FastAPI\Schemas;

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

class Resource extends Base {

    public function __construct($filepath) {
        parent::__construct($filepath, false);
    }

    /**
     * Retrieves data according to the json schema
     * @since 0.9.0
     */
    public function returns(\WP_REST_Request $req, $res) {
        $is_to_validate = apply_filters($this->suffix . '_is_to_validate', true, $this);
        if (!$is_to_validate) {
            return $res;
        }

        $this->contents = $this->get_contents();
        if (!$this->contents) {
            return $res;
        }

        $schema_id = $this->get_schema_id($req);
        $validator = new Validator();
        $resolver = $validator->resolver();
        $response = apply_filters($this->suffix . '_response', $res, $req, $this);
        $json = Helper::toJSON($response);
        $schema = Helper::toJSON($this->contents);
        try {
            $result = $validator->validate($json, $schema);
        } catch (SchemaException $e) {
            return new \WP_Error(
                'unprocessable_entity',
                "Unprocessable resource {$schema_id}",
                ['status' => \WP_Http::UNPROCESSABLE_ENTITY],
            );
        }

        if (!$result->isValid()) {
            $error = $this->get_error($result);
            return new \WP_Error(
                'unprocessable_entity',
                $error,
                ['status' => \WP_Http::UNPROCESSABLE_ENTITY],
            );
        }

        return $res;
    }
}
