<?php

/**
 * Overrides drafts of with ones that removes additionalProperties from the data instead of throwing errors.
 *
 * @since 0.9.0
 * @package wp-fastendpoints
 * @license MIT
 */

declare(strict_types=1);

namespace WP\FastEndpoints\Schemas\Opis\Parsers;

use Opis\JsonSchema\Parsers\SchemaParser;
use Opis\JsonSchema\Parsers\Vocabulary;
use WP\FastEndpoints\Schemas\Opis\Parsers\Drafts\{
    Draft06,
    Draft07,
    Draft201909,
    Draft202012,
};

/**
 * Parser with JSON schema drafts that removes "additionalProperties" from the data instead of throwing errors.
 *
 * @since 0.9.0
 * @author André Gil <andre_gil22@hotmail.com>
 */
class ResponseSchemaParser extends SchemaParser
{
	/**
	 * Retrieves a dictionary with the supported JSON schema drafts.
	 *
	 * @param Vocabulary|null $extraVocabulary - To add additional vocabulary to the drafts.
	 * @return array - Supported JSON schema drafts.
	 */
	protected function getDrafts(?Vocabulary $extraVocabulary): array
	{
		return [
			'06' => new Draft06($extraVocabulary),
			'07' => new Draft07($extraVocabulary),
			'2019-09' => new Draft201909($extraVocabulary),
			'2020-12' => new Draft202012($extraVocabulary),
		];
	}
}
