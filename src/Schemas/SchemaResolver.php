<?php

/**
 * Holds schema resolver to look up for JSON schemas.
 *
 * @since 1.2.1
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Schemas;

use Opis\JsonSchema\Resolvers\SchemaResolver as OpisJsonResolver;

/**
 * Class that looks up for JSON schemas
 *
 * @since 1.2.1
 *
 * @author André Gil <andre_gil22@hotmail.com>
 */
class SchemaResolver extends OpisJsonResolver
{
    /**
     * Retrieves the default router prefix
     */
    public function getDefaultPrefix(): null|int|string
    {
        return array_key_first($this->prefixes);
    }
}
