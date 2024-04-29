<?php

/**
 * Parser that adds the custom RemoveAdditionalPropertiesKeyword.
 *
 * @since 0.9.0
 *
 * @license MIT
 */

namespace Wp\FastEndpoints\Schemas\Opis\Parsers\Keywords;

use Opis\JsonSchema\Info\SchemaInfo;
use Opis\JsonSchema\Keyword;
use Opis\JsonSchema\Parsers\Keywords\AdditionalPropertiesKeywordParser;
use Opis\JsonSchema\Parsers\SchemaParser;
use Wp\FastEndpoints\Schemas\Opis\Keywords\RemoveAdditionalPropertiesKeyword;

/**
 * Keyword parser that adds the custom RemoveAdditionalPropertiesKeyword
 *
 * @since 0.9.0
 *
 * @author André Gil <andre_gil22@hotmail.com>
 */
class RemoveAdditionalPropertiesKeywordParser extends AdditionalPropertiesKeywordParser
{
    /**
     * Replaces the original AdditionalPropertiesKeyword with a custom one that
     *
     * @see AdditionalPropertiesKeywordParser->parse()
     *
     * @param  SchemaInfo  $info  Schema information.
     * @param  SchemaParser  $parser  Parser to be used.
     * @param  object  $shared  Data shared accross.
     * @return ?Keyword null if nothing to be parsed or RemoveAdditionalPropertiesKeyword otherwise.
     *
     * @throws Opis\JsonSchema\Exceptions\InvalidKeywordException if the keyword value is not in a proper format.
     */
    public function parse(SchemaInfo $info, SchemaParser $parser, object $shared): ?Keyword
    {
        $schema = $info->data();

        if (! $this->keywordExists($schema)) {
            return null;
        }

        $value = $this->keywordValue($schema);

        if (! \is_bool($value) && ! \is_object($value)) {
            throw $this->keywordException(sprintf(\esc_html__("{keyword} must be either an object or boolean but '%s' given"), gettype($value)), $info);
        }

        return new RemoveAdditionalPropertiesKeyword($value);
    }
}
