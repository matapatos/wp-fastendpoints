<?php

/**
 * Holds function overrides from WordPress
 *
 * @since 0.9.0
 */ 

use Tests\Wp\FastEndpoints\Helpers\Helpers;

if (!function_exists('get_site_url')) {
	function get_site_url($blog_id = null, $path = '', $scheme = null) {
		return 'http://testing.com' . $path;
	}
}

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

if (!class_exists('WP_Http')) {
	class WP_Http {
		const NOT_FOUND = 404;
		const UNPROCESSABLE_ENTITY = 422;
	}
}

if (!class_exists('WP_Error')) {
	class WP_Error {
		public $code;
		public $message;
		public $data;
		public function __construct($code = '', $message = '', $data = '') {
			$this->code = $code;
			$this->message = $message;
			$this->data = $data;
		}
	}
}

if (!function_exists('path_is_absolute')) {
	/**
	 * Copied from WordPress
	 * @version 6.2
	 */
	function path_is_absolute( $path ) {
		/*
		 * Check to see if the path is a stream and check to see if its an actual
		 * path or file as realpath() does not support stream wrappers.
		 */
		if ( wp_is_stream( $path ) && ( is_dir( $path ) || is_file( $path ) ) ) {
			return true;
		}

		/*
		 * This is definitive if true but fails if $path does not exist or contains
		 * a symbolic link.
		 */
		if ( realpath( $path ) === $path ) {
			return true;
		}

		if ( strlen( $path ) === 0 || '.' === $path[0] ) {
			return false;
		}

		// Windows allows absolute paths like this.
		if ( preg_match( '#^[a-zA-Z]:\\\\#', $path ) ) {
			return true;
		}

		// A path starting with / or \ is absolute; anything else is relative.
		return ( '/' === $path[0] || '\\' === $path[0] );
	}
}

if (!function_exists('path_join')) {
	/**
	 * Copied from WordPress
	 * @version 6.2
	 */
	function path_join( $base, $path ) {
		if ( path_is_absolute( $path ) ) {
			return $path;
		}

		return rtrim( $base, '/' ) . '/' . $path;
	}
}

if (!function_exists('wp_is_stream')) {
	/**
	 * Copied from WordPress
	 * @version 6.2
	 */
	function wp_is_stream( $path ) {
		$scheme_separator = strpos( $path, '://' );

		if ( false === $scheme_separator ) {
			// $path isn't a stream.
			return false;
		}

		$stream = substr( $path, 0, $scheme_separator );

		return in_array( $stream, stream_get_wrappers(), true );
	}
}
