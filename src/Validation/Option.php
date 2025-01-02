<?php

/**
 * Holds logic to register validation option for BaseModels
 *
 * @since 3.0.0
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Validation;

use Invoker\Invoker;
use Invoker\ParameterResolver\AssociativeArrayResolver;
use Invoker\ParameterResolver\ResolverChain;
use UnitEnum;
use Wp\FastEndpoints\Contracts\Exceptions\OptionAlreadyExists;
use Wp\FastEndpoints\Contracts\Exceptions\OptionNotFound;
use Wp\FastEndpoints\Contracts\Validation\BaseModel;
use Wp\FastEndpoints\DependencyInjection\ParameterResolver\TypeHintMappingResolver;

/**
 * Registers validation options available for BaseModel's
 *
 * @since 3.0.0
 *
 * @author André Gil <andre_gil22@hotmail.com>
 */
class Option
{
    private static Option $instance;

    /**
     * Holds all available options
     *
     * @var array<string,callable>
     */
    private array $availableOptions = [];

    /**
     * Applies all options of a BaseModel to retrieve the data to be used to populate the model
     *
     * @param  BaseModel  $model  - Validation model used
     * @param  array<string,mixed>  $typeHintMappings  - Dependencies to be injected e.g. WP_REST_Request
     * @return array<string,mixed> - Request parameters to populate model
     *
     * @throws OptionNotFound
     * @throws \Invoker\Exception\InvocationException
     * @throws \Invoker\Exception\NotCallableException
     * @throws \Invoker\Exception\NotEnoughParametersException
     */
    public static function applyAll(BaseModel $model, array $typeHintMappings): array
    {
        $requestParams = [];
        foreach ($model->getOptions() as $option) {
            $invoker = self::getInvoker($option, $model, $typeHintMappings);
            $requestParams = $invoker->call(Option::get($option::class), ['data' => $requestParams]);
        }

        return $requestParams;
    }

    /**
     * Retrieves the dependency injection invoker for the given option and model
     *
     * @param  UnitEnum  $option  - Current option
     * @param  BaseModel  $model  - Current validation model
     * @param  array<string,mixed>  $typeHintMappings  - Dependencies to be injected e.g. WP_REST_Request
     */
    private static function getInvoker(UnitEnum $option, BaseModel $model, array $typeHintMappings): Invoker
    {
        $dependencies = $typeHintMappings + [
            BaseModel::class => $model,
            $option::class => $option,
        ];
        $parameterResolver = new ResolverChain([
            new TypeHintMappingResolver($dependencies),
            new AssociativeArrayResolver,
        ]);

        return new Invoker(parameterResolver: $parameterResolver);
    }

    /**
     * Retrieves a singleton of Option
     */
    protected static function getInstance(): Option
    {
        if (! isset(self::$instance)) {
            self::$instance = new Option;
        }

        return self::$instance;
    }

    /**
     * Retrieves the handler for the given option
     *
     * @return mixed
     *
     * @throws OptionNotFound - if no handler has been found
     */
    protected static function get(string $optionClassName): callable
    {
        $instance = self::getInstance();
        if (! isset($instance->availableOptions[$optionClassName])) {
            throw new OptionNotFound($optionClassName);
        }

        return self::getInstance()->availableOptions[$optionClassName];
    }

    /**
     * Registers a new option to populate the data into validation models
     *
     * @throws OptionAlreadyExists
     */
    public static function add(string $option, callable $handler, bool $override = false): void
    {
        $instance = self::getInstance();
        if (! $override && isset($instance->availableOptions[$option])) {
            throw new OptionAlreadyExists($option);
        }

        $instance->availableOptions[$option] = $handler;
    }
}
