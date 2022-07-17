<?php

/**
 * Plugin Name: WP FastAPI
 * Plugin URI:  ---
 * Description: REST endpoints made easy
 * Version:     0.9.0
 * Author:      o@N
 * Author URI:  
 */

$composer = __DIR__ . '/vendor/autoload.php';
if (! file_exists($composer)) {
    wp_die(__('Error locating autoloader in mu-plugins/wp-fastapi. Please run <code>composer install</code>.', 'fastapi'));
}

require_once $composer;
