<?php

namespace WP\FastAPI\Schemas;

use Opis\JsonSchema\{
    Validator,
    Helper,
    Exceptions\SchemaException,
};

class Schema extends Base {

    /**
     * Parses the JSON schema contents using the Opis/json-schema library
     * @see https://opis.io/json-schema
     * @since 0.9.0
     */ 
    protected function parse(\WP_REST_Request $req) {
        $is_to_parse = apply_filters($this->suffix . '_is_to_parse', true, $this);
        if (!$is_to_parse) {
            return;
        }

        if (!$this->contents) {
            return true;
        }

        $schema_id = $this->get_schema_id($req);
        $validator = new Validator();
        $resolver = $validator->resolver();
        $params = apply_filters($this->suffix . '_params', $req->get_params(), $req, $this);
        $json = Helper::toJSON($params);
        $schema = Helper::toJSON($this->contents);
        try {
            $result = $validator->validate($json, $schema);
        } catch (SchemaException $e) {
            return new \WP_Error(
                'unprocessable_entity',
                "Unprocessable schema {$schema_id}",
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

        return true;
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
