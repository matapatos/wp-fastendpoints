<?php

/**
 * Parser that adds the custom RemoveAdditionalPropertiesKeyword.
 *
 * @since 0.9.0
 * @package wp-fastendpoints
 * @license MIT
 */

namespace Wp\FastEndpoints\Schemas\Opis\Keywords;

use Opis\JsonSchema\Keywords\AdditionalPropertiesKeyword;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\ValidationContext;
use Wp\FastEndpoints\Schemas\Response;

/**
 * Keyword parser that adds the custom RemoveAdditionalPropertiesKeyword
 *
 * @since 0.9.0
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
			$this->removeAdditionalProperties($context, $props);
			return null;
		}

		if (\is_object($this->value) && !($this->value instanceof Schema)) {
			$this->value = $context->loader()->loadObjectSchema($this->value);
		}

		$object = $this->createArrayObject($context);

		$error = $this->validateIterableData(
			$schema,
			$this->value,
			$context,
			$props,
			'additionalProperties',
			esc_html__('All additional object properties must match schema: {properties}'),
			['properties' => $props],
			$object,
		);

		if ($object && $object->count()) {
			$context->addEvaluatedProperties($object->getArrayCopy());
			$props = $context->getUnevaluatedProperties();
			if (!$props) {
				return null;
			}
		}

		if ($error) {
			foreach ($error->subErrors() as $subError) {
				$data = $subError->data();
				$this->removeAdditionalProperties($context, $data->path());
			}
		}

		$this->removeAdditionalProperties($context, $props);
		return null;
	}

	/**
	 * Removes the Response::$data "additionalProperties" fields from the data it self.
	 *
	 * @since 0.9.0
	 *
	 * @param ValidationContext $context - Current validation context.
	 * @param array $properties - Additional properties to be removed.
	 */
	protected function removeAdditionalProperties(ValidationContext $context, array $properties)
	{
		$data = Response::getData();
		// Get full path object.
		$path = &$data;
		foreach ($context->fullDataPath() as $dataPath) {
			$path = &$path->{$dataPath};
		}

		// Remove additional properties.
		foreach ($properties as $prop) {
			unset($path->{$prop});
		}

		Response::setData($data);
	}
}
