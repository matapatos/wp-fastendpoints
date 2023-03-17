<?php

/**
 * Replaces original opis/json-schema AdditionalPropertiesKeywordParser with custom one
 *
 * @version 1.0.0
 * @package wp-fastendpoints
 * @license MIT
 */

namespace Wp\FastEndpoints\Schemas\Opis\Parsers\Drafts;

use Opis\JsonSchema\Parsers\Drafts\Draft202012 as OpisDraft202012;

/**
 * Draft202012 JSON schema that replaces the additionalProperties keyword with custom one - used in Schemas/Response
 *
 * @version 1.0.0
 * @author André Gil <andre_gil22@hotmail.com>
 */
class Draft202012 extends OpisDraft202012
{
    use OverrideKeywordsParsers;
}
