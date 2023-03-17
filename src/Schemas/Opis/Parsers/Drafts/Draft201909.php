<?php

/**
 * Replaces original opis/json-schema AdditionalPropertiesKeywordParser with custom one
 *
 * @version 1.0.0
 * @package wp-fastendpoints
 * @license MIT
 */

namespace Wp\FastEndpoints\Schemas\Opis\Parsers\Drafts;

use Opis\JsonSchema\Parsers\Drafts\Draft201909 as OpisDraft201909;

/**
 * Draft201909 JSON schema that replaces the additionalProperties keyword with custom one - used in Schemas/Response
 *
 * @version 1.0.0
 * @author André Gil <andre_gil22@hotmail.com>
 */
class Draft201909 extends OpisDraft201909
{
    use OverrideKeywordsParsers;
}
