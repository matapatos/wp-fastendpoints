<?php

declare(strict_types=1);

namespace Wp\FastEndpoints\DependencyInjection\ParameterResolver;

use Invoker\ParameterResolver\ParameterResolver;
use ParaTest\Runners\PHPUnit\Options;
use ReflectionFunctionAbstract;
use ReflectionNamedType;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Wp\FastEndpoints\Contracts\Validation\BaseModel;
use Wp\FastEndpoints\Contracts\Validation\Options\From;
use Wp\FastEndpoints\Validation\Option;
use WP_REST_Request;

class ValidationResolver implements ParameterResolver
{
    protected ValidatorInterface $validator;

    protected array $typeHintMappings;

    public function __construct(array $typeHintMappings, ?ValidatorInterface $validator = null)
    {
        $this->typeHintMappings = $typeHintMappings;
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

            $typeName = $parameterType->getName();
            if (! is_subclass_of($typeName, BaseModel::class)) {
                continue;
            }

            $model = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : new $typeName;
            $requestParams = Option::applyAll($model, $this->typeHintMappings);

            $errors = $this->validator->validate($model);
            $resolvedParameters[$index] = $this->getValidPrimitiveValue($parameter, $providedParameters);
        }

        return $resolvedParameters;
    }


    /**
     * Retrieves the parameters to take into account according to the option
     *
     * @param WP_REST_Request $request
     * @return array<string,mixed>
     */
    public function getRequestParams(WP_REST_Request $request, BaseModel $model): array
    {
        return match($model->_from)
        {
            From::ANY => $request->get_params(),
            From::JSON => $request->get_json_params(),
            From::BODY => $request->get_body_params(),
            From::FILE => $request->get_file_params(),
            From::QUERY => $request->get_query_params(),
            From::URL => $request->get_url_params(),
        };
    }
}
