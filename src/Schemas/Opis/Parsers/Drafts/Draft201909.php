<?php

/**
 * Replaces original opis/json-schema AdditionalPropertiesKeywordParser with custom one
 *
 * @since 0.9.0
 * @package wp-fastendpoints
 * @license MIT
 */

namespace WP\FastEndpoints\Schemas\Opis\Parsers\Drafts;

use Opis\JsonSchema\Parsers\Drafts\Draft201909 as OpisDraft201909;
use Opis\JsonSchema\Parsers\Keywords\AdditionalPropertiesKeywordParser;
use WP\FastEndpoints\Schemas\Opis\Parsers\Keywords\RemoveAdditionalPropertiesKeywordParser;

/**
 * Draft201909 JSON schema that replaces the additionalProperties keyword with custom one - used in Schemas/Response
 *
 * @since 0.9.0
 * @author André Gil <andre_gil22@hotmail.com>
 */
class Draft201909 extends OpisDraft201909
{
	/**
	 * Replaces original AdditionalPropertiesKeywordParser with custom one
	 *
	 * @since 0.9.0
	 * @return array<int,mixed>
	 */
	protected function getKeywordParsers(): array
	{
		$parsers = parent::getKeywordParsers();
		for ($i = 0; $i < \count($parsers); $i += 1) {
			if (!($parsers[$i] instanceof AdditionalPropertiesKeywordParser)) {
				continue;
			}

			$parsers[$i] = new RemoveAdditionalPropertiesKeywordParser("additionalProperties");
			break;
		}

		return $parsers;
	}
}
