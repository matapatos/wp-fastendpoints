<?php

/**
 * Parser that adds the custom RemoveAdditionalPropertiesKeyword.
 *
 * @since 0.9.0
 *
 * @package wp-fastendpoints
 * @license MIT
 */

namespace WP\FastEndpoints\Schemas\Opis\Keywords;


use Opis\JsonSchema\Keywords\AdditionalPropertiesKeyword;
use Opis\JsonSchema\{
    ValidationContext,
    Schema
};
use Opis\JsonSchema\Errors\ValidationError;

/**
 * Keyword parser that adds the custom RemoveAdditionalPropertiesKeyword
 *
 * @since 0.9.0
 *
 * @author André Gil <andre_gil22@hotmail.com>
 */
class RemoveAdditionalPropertiesKeyword extends AdditionalPropertiesKeyword
{
    /**
     * Removes "additionalProperties" from the data, if specified.
     *
     * @see AdditionalPropertiesKeyword->validate()
     * @param ValidationContext $context - Current validation context.
     * @param Schema $schema - Schema currently being used.
     * @return ?ValidationError - ValidationError if an error occurs or null otherwise.
     */
    public function validate(ValidationContext $context, Schema $schema): ?ValidationError
    {
        if ($this->value === true) {
            $context->markAllAsEvaluatedProperties();
            return null;
        }

        $props = $context->getUncheckedProperties();

        if (!$props) {
            return null;
        }

        if ($this->value === false) {
            var_dump(implode('/', $context->fullDataPath()) . '/');
            var_dump($props);
            return null;
        }

        if (is_object($this->value) && !($this->value instanceof Schema)) {
            $this->value = $context->loader()->loadObjectSchema($this->value);
        }

        $object = $this->createArrayObject($context);

        $error = $this->validateIterableData($schema, $this->value, $context, $props,
            'additionalProperties', 'All additional object properties must match schema: {properties}', [
                'properties' => $props
            ], $object);

        if ($object && $object->count()) {
            $context->addEvaluatedProperties($object->getArrayCopy());
        }

        return $error;
    }
}