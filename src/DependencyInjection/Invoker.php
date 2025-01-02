<?php

declare(strict_types=1);

namespace Wp\FastEndpoints\DependencyInjection;

use Invoker\Invoker as BaseInvoker;
use Invoker\ParameterResolver\AssociativeArrayResolver;
use Invoker\ParameterResolver\DefaultValueResolver;
use Invoker\ParameterResolver\ParameterResolver;
use Invoker\ParameterResolver\ResolverChain;
use Wp\FastEndpoints\DependencyInjection\ParameterResolver\TypeHintMappingResolver;
use Wp\FastEndpoints\DependencyInjection\ParameterResolver\ValidationResolver;

class Invoker extends BaseInvoker
{
    protected array $typeHintMapping;

    /**
     * @param  array  $typeHintMapping  - mapping between type hints and dependencies
     */
    public function __construct(array $typeHintMapping)
    {
        $this->typeHintMapping = $typeHintMapping;
        $parameterResolver = $this->createParameterResolver();
        parent::__construct(parameterResolver: $parameterResolver);
    }

    private function createParameterResolver(): ParameterResolver
    {
        return new ResolverChain([
            new TypeHintMappingResolver($this->typeHintMapping),
            new ValidationResolver($this->typeHintMapping),
            new AssociativeArrayResolver,
            new DefaultValueResolver,
        ]);
    }
}
