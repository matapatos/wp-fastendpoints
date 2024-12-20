<?php

declare(strict_types=1);

namespace Wp\FastEndpoints\DependencyInjection\ParameterResolver;

use Invoker\ParameterResolver\ParameterResolver;
use ReflectionFunctionAbstract;
use ReflectionNamedType;

/**
 * Resolves arguments according to their type hinting - used for mapping internal endpoint dependencies
 */
class TypeHintMappingResolver implements ParameterResolver
{
    /**
     * Mapping between type hints and dependencies
     *
     * @var array<string,mixed>
     */
    protected array $mappings;

    public function __construct(array $mappings)
    {
        $this->mappings = $mappings;
    }

    public function getParameters(
        ReflectionFunctionAbstract $reflection,
        array $providedParameters,
        array $resolvedParameters
    ): array {
        $parameters = $reflection->getParameters();

        // Skip parameters already resolved
        if (! empty($resolvedParameters)) {
            $parameters = array_diff_key($parameters, $resolvedParameters);
        }

        foreach ($parameters as $index => $parameter) {
            $parameterType = $parameter->getType();
            if (! $parameterType) {
                // No type
                continue;
            }

            if (! $parameterType instanceof ReflectionNamedType) {
                continue;
            }

            $typeName = $parameterType->getName();
            if (! array_key_exists($typeName, $this->mappings)) {
                continue;
            }

            $resolvedParameters[$index] = $this->mappings[$typeName];
        }

        return $resolvedParameters;
    }
}
