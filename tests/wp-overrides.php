<?php

/**
 * Holds function overrides from WordPress
 *
 * @since 0.9.0
 */ 

use Tests\WP\FastEndpoints\Helpers\Helpers;

if (!Helpers::isUnitTest()) {
	return;
}

if (!function_exists('esc_html__')) {
	function esc_html__(string $string): string
	{
		return $string;
	}
}

if (!function_exists('esc_html')) {
	function esc_html(string $string): string
	{
		return $string;
	}
}
