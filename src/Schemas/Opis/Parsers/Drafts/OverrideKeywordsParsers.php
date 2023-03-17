<?php

/**
 * Replaces original opis/json-schema AdditionalPropertiesKeywordParser with custom one
 *
 * @version 1.0.0
 * @package wp-fastendpoints
 * @license MIT
 */

namespace Wp\FastEndpoints\Schemas\Opis\Parsers\Drafts;

use Opis\JsonSchema\Parsers\Keywords\AdditionalPropertiesKeywordParser;
use Wp\FastEndpoints\Schemas\Opis\Parsers\Keywords\RemoveAdditionalPropertiesKeywordParser;

/**
 * Draft06 JSON schema that replaces the additionalProperties keyword with custom one - used in Schemas/Response
 *
 * @version 1.0.0
 * @author André Gil <andre_gil22@hotmail.com>
 */
trait OverrideKeywordsParsers
{
    /**
     * Replaces original AdditionalPropertiesKeywordParserParser with a custom one
     *
     * @version 1.0.0
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
