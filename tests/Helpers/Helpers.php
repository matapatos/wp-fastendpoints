<?php

declare(strict_types=1);

namespace Tests\Wp\FastEndpoints\Helpers;

use Exception;

class Helpers
{
    /**
     * Determines if we only want to run unit tests
     *
     * @since 0.9.0
     * @return bool
     */
    public static function isUnitTest(): bool
    {
        return !empty($GLOBALS['argv']) && in_array('--group=unit', $GLOBALS['argv'], true);
    }

    /**
     * Invokes a private/protected class method and retrieves it's result
     *
     * @since 0.9.0
     * @param object $class - Class containing the private/protected method.
     * @param string $methodName - The name of the method to be called.
     * @param array $args - Arguments to be sent over method, if needed.
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
     * @param object $class - Class containing the private/protected property.
     * @param string $propertyName - The name of the property to be retrieved.
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
     * @param object $class - Class containing the private/protected property.
     * @param string $propertyName - The name of the property to be updated.
     * @param mixed $propertyValue - The new value of the property.
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
     * @param string $filepath - Schema filepath to be loaded.
     * @return mixed - Loaded schema.
     * @throws Exception if unable to read or invalid schema
     */
    public static function loadSchema(string $filepath)
    {
        if (!\str_ends_with($filepath, '.json')) {
            $filepath .= '.json';
        }

        // Read JSON file and retrieve it's content.
        $result = \file_get_contents($filepath);
        if ($result === false) {
            throw new Exception(sprintf("Unable to read schema %s", $filepath));
        }

        $schema = \json_decode($result, true);
        if ($schema === null && \JSON_ERROR_NONE !== \json_last_error()) {
            throw new Exception(sprintf("Invalid schema %s. Are you sure it's a valid JSON?", $filepath));
        }

        return $schema;
    }
}
