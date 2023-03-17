<?php

/**
 * Overrides drafts of with ones that removes additionalProperties from the data instead of throwing errors.
 *
 * @version 1.0.0
 * @package wp-fastendpoints
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Schemas\Opis\Parsers;

use Opis\JsonSchema\Parsers\SchemaParser;
use Opis\JsonSchema\Parsers\Vocabulary;
use Wp\FastEndpoints\Schemas\Opis\Parsers\Drafts\Draft06;
use Wp\FastEndpoints\Schemas\Opis\Parsers\Drafts\Draft07;
use Wp\FastEndpoints\Schemas\Opis\Parsers\Drafts\Draft201909;
use Wp\FastEndpoints\Schemas\Opis\Parsers\Drafts\Draft202012;

/**
 * Parser with JSON schema drafts that removes "additionalProperties" from the data instead of throwing errors.
 *
 * @version 1.0.0
 * @author André Gil <andre_gil22@hotmail.com>
 */
class ResponseSchemaParser extends SchemaParser
{
    /**
     * Retrieves a dictionary with the supported JSON schema drafts.
     *
     * @version 1.0.0
     * @param ?Vocabulary $extraVocabulary - To add additional vocabulary to the drafts.
     * @return array<string, mixed> - Supported JSON schema drafts.
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
