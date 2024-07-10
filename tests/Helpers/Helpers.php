<?php

declare(strict_types=1);

namespace Wp\FastEndpoints\Tests\Helpers;

use Exception;
use Illuminate\Support\Str;
use Wp\FastEndpoints\Router;

class Helpers
{
    /**
     * Invokes a private/protected class method and retrieves it's result
     *
     * @since 0.9.0
     *
     * @param  object  $class  - Class containing the private/protected method.
     * @param  string  $methodName  - The name of the method to be called.
     * @param  array  $args  - Arguments to be sent over method, if needed.
     * @return mixed
     */
    public static function invokeNonPublicClassMethod($class, string $methodName, ...$args)
    {
        $className = get_class($class);
        $reflection = new \ReflectionClass($className);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($class, $args);
    }

    /**
     * Retrieves the value of a private/protected class property
     *
     * @since 0.9.0
     *
     * @param  object  $class  - Class containing the private/protected property.
     * @param  string  $propertyName  - The name of the property to be retrieved.
     * @return mixed
     */
    public static function getNonPublicClassProperty($class, string $propertyName)
    {
        $className = get_class($class);
        $reflection = new \ReflectionClass($className);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);

        return $property->getValue($class);
    }

    /**
     * Retrieves the value of a private/protected class property
     *
     * @since 0.9.0
     *
     * @param  object  $class  - Class containing the private/protected property.
     * @param  string  $propertyName  - The name of the property to be updated.
     * @param  mixed  $propertyValue  - The new value of the property.
     */
    public static function setNonPublicClassProperty($class, string $propertyName, $propertyValue): void
    {
        $className = get_class($class);
        $reflection = new \ReflectionClass($className);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($class, $propertyValue);
    }

    /**
     * Reads a schema from a file and parses it
     *
     * @since 0.9.0
     *
     * @param  string  $filepath  - SchemaMiddleware filepath to be loaded.
     * @return mixed - Loaded schema.
     *
     * @throws Exception if unable to read or invalid schema
     */
    public static function loadSchema(string $filepath)
    {
        if (! \str_ends_with($filepath, '.json')) {
            $filepath .= '.json';
        }

        // Read JSON file and retrieve it's content.
        $result = \file_get_contents($filepath);
        if ($result === false) {
            throw new Exception(sprintf('Unable to read schema %s', $filepath));
        }

        $schema = \json_decode($result, true);
        if ($schema === null && \json_last_error() !== \JSON_ERROR_NONE) {
            throw new Exception(sprintf("Invalid schema %s. Are you sure it's a valid JSON?", $filepath));
        }

        return $schema;
    }

    /**
     * Retrieves the class name in snake case
     *
     * @since 0.9.0
     *
     * @param  mixed  $instance  class instance to get the name
     */
    protected static function getClassNameInSnakeCase($instance): string
    {
        $className = is_string($instance) ? $instance : \get_class($instance);
        $suffix = \basename(\str_replace('\\', '/', $className));

        return \ltrim(\strtolower(\preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '_$0', $suffix)), '_');
    }

    /**
     * Retrieves the hooks suffix for a given class instance
     *
     * @since 0.9.0
     *
     * @param  mixed  $instance  class instance to retrieve the suffix
     */
    public static function getHooksSuffixFromClass($instance): string
    {
        $suffix = self::getClassNameInSnakeCase($instance);
        $suffix = str_replace('_middleware', '', $suffix);

        return "fastendpoints_{$suffix}";
    }

    /**
     * Checks if weather we want to run integration tests or not
     */
    public static function isIntegrationTest(): bool
    {
        return isset($GLOBALS['argv']) && in_array('--group=integration', $GLOBALS['argv'], true);
    }

    /**
     * Retrieves a router from a file
     *
     * @param  string  $filename  The router filename to be included
     */
    public static function getRouter(string $filename): Router
    {
        $filename = Str::finish($filename, '.php');

        return require \ROUTERS_DIR.$filename;
    }
}
