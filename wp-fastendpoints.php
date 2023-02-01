<?php

/**
 * Plugin Name: WordPress Fast Endpoints
 * Plugin URI:  ---
 * Description: Fast to type and fast to run WordPress REST endpoints
 * Version:     0.9.0
 * Author:      André Gil
 * Author URI:
 *
 * @since 0.9.0
 * @version 0.9.0
 *
 * @package wp-fastendpoints
 * @license MIT
 */

$composer = __DIR__ . '/vendor/autoload.php';
if (! file_exists($composer)) {
	wp_die(
		esc_html__(
			'Error locating autoloader in plugins/wp-fastendpoints. Please run <code>composer install</code>.',
			'fastendpoints',
		),
	);
}

require_once $composer;
