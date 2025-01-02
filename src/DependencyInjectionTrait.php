<?php

namespace Wp\FastEndpoints;

use DI\Container;
use DI\ContainerBuilder;
use Exception;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

trait DependencyInjectionTrait
{
    private Container $container;

    /**
     * Calls each handler.
     *
     * @since 3.0.0
     *
     * @param  array<callable>  $allHandlers  Holds all callables that we wish to run.
     * @param  array<mixed>  $additionalDependencies  Holds additional dependencies for .
     * @return WP_Error|WP_REST_Response|null Returns the result of the last callable or if no handlers are set the
     *                                        last result passed as argument if any. If an error occurs a WP_Error instance is returned.
     */
    protected function call(callable|array $allHandlers, ...$additionalDependencies): WP_Error|WP_REST_Response|null
    {
        if (is_callable($allHandlers)) {
            $allHandlers = [$allHandlers];
        }

        foreach ($allHandlers as $handler) {
            $result = $this->container->call($handler, $additionalDependencies);
            if (\is_wp_error($result) || $result instanceof WP_REST_Response) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Builds a Dependency Injection container
     *
     * @throws Exception
     */
    protected function buildContainer($request): void
    {
        if ($this->container) {
            return;
        }

        $builder = new ContainerBuilder;
        do_action('fastendpoints_container_builder', $builder, $request);

        $this->container = $builder->build();
        do_action('fastendpoints_container', $this->container, $request);
    }

    /**
     * Adds internal dependencies to be injeted
     *
     * @throws Exception
     */
    protected function addDependencies(WP_REST_Request $request): void
    {
        $this->buildContainer($request);

        if (! $this->container->has('___request')) {
            $this->container->set('___request', \DI\value(function () use ($request) {
                return $request;
            }));
        }
        if (! $this->container->has('___response')) {
            $this->container->set('___response', \DI\create('WP_REST_Response'));
        }
        if (! $this->container->has('___self')) {
            $this->container->set('___self', $this);
        }
    }
}
