<?php

declare(strict_types=1);

namespace Wp\FastEndpoints\DependencyInjection\ParameterResolver;

use Invoker\ParameterResolver\ParameterResolver;
use ReflectionFunctionAbstract;
use ReflectionNamedType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ValidationResolver implements ParameterResolver
{
    private ValidatorInterface $validator;

    public function __construct(?ValidatorInterface $validator = null)
    {
        $this->validator = $validator ?? Validation::createValidator();
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
                // Union types are not supported
                continue;
            }

            if ($parameterType->isBuiltin()) {
                $resolvedParameters[$index] = $this->getValidPrimitiveValue($parameter, $providedParameters);
                continue;
            }

//            $resolvedParameters[$index] = $this->getValidPrimitiveValue($parameter, $providedParameters);
        }

        return $resolvedParameters;
    }

    protected function getValidPrimitiveValue($providedParameters): mixed
    {
        $parameterValue = $providedParameters[$parameter->name];
        $constraint = new Assert\Type(type: $parameterType->getName());
        $this->validator->validate($parameterValue, $constraint);
        $resolvedParameters[$index] = $providedParameters[$parameter->name];
    }
}
