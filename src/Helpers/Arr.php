<?php

/**
 * Holds array helper functions
 *
 * @since 0.9.0
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Helpers;

/**
 * Class that holds array helper functions
 *
 * @since 0.9.0
 *
 * @author André Gil <andre_gil22@hotmail.com>
 */
class Arr
{
    /**
     * Checks if the given array is an associative array.
     *
     * @version 0.9.0
     *
     * @param  array  $array  Array to be checked.
     * @return bool true if it seems an associative array or false otherwise.
     */
    public static function isAssoc(array $array): bool
    {
        $keys = \array_keys($array);

        return $keys !== \array_keys($keys);
    }

    /**
     * Wraps a variable as an array
     *
     * @since 0.9.0
     *
     * @param  mixed  $value  The variable to be wrapped as an array.
     */
    public static function wrap($value): array
    {
        if (! \is_array($value)) {
            $value = [$value];
        }

        return $value;
    }

    /**
     * Recursively search for a key and value in an array
     *
     * @param  array  $haystack  Array to be searched for.
     * @param  string|int  $searchKey  The key to search for.
     * @param  mixed  $searchValue  The value to search for.
     * @param  mixed  $path  The paths to each index found.
     * @param  mixed  $currentIndex  The current index.
     * @return array<array<string|int>> An array of found indexes.
     */
    public static function recursiveKeyValueSearch(array $haystack, string|int $searchKey, mixed $searchValue, array $path = [], array $currentIndex = []): array
    {
        foreach ($haystack as $key => $item) {
            if ($key == $searchKey && $item == $searchValue) {
                $path[] = $currentIndex;
            }

            if (! is_array($item)) {
                continue;
            }

            $currentIndex[] = $key;
            $path = self::recursiveKeyValueSearch($item, $searchKey, $searchValue, $path, $currentIndex);
        }

        return $path;
    }
}
