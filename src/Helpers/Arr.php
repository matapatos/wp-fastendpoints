<?php

/**
 * Holds array helper functions
 *
 * @since 0.9.0
 * @package wp-fastendpoints
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Helpers;

/**
 * Class that holds array helper functions
 *
 * @since 0.9.0
 * @author André Gil <andre_gil22@hotmail.com>
 */
class Arr
{
	/**
	 * Checks if the given array is an associative array.
	 *
	 * @version 0.9.0
	 * @param array $array - Array to be checked.
	 * @return bool - true if it seems an associative array or false otherwise.
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
	 * @param mixed $value - The variable to be wrapped as an array.
	 * @return array
	 */
	public static function wrap($value): array
	{
		if (!\is_array($value)) {
			$value = [$value];
		}
		return $value;
	}
}
