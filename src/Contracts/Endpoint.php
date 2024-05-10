<?php

/**
 * Holds interface for registering custom REST endpoints
 *
 * @since 0.9.0
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Contracts;

/**
 * REST Endpoint interface that registers custom WordPress REST endpoints
 *
 * @since 0.9.0
 *
 * @author André Gil <andre_gil22@hotmail.com>
 */
interface Endpoint
{
    /**
     * Registers the current endpoint using register_rest_route function.
     *
     * NOTE: Expects to be called inside the 'rest_api_init' WordPress action
     *
     * @since 0.9.0
     *
     * @param  string  $namespace  WordPress REST namespace.
     * @param  string  $restBase  Endpoint REST base.
     * @param  array<string>  $schemaDirs  Array of directories to look for JSON schemas. Default value: [].
     * @return true|false true if successfully registered a REST route or false otherwise.
     */
    public function register(string $namespace, string $restBase, array $schemaDirs = []): bool;

    /**
     * Checks if the current user has the given WP capabilities. Example usage:
     *
     *      hasCap('edit_posts');
     *      hasCap(['edit_post', $post->ID]);
     *      hasCap(['edit_post_meta', $post->ID, $meta_key]);
     *      hasCap(['edit_post', '{post_id}']);  // Replaces {post_id} with request parameter named post_id
     *
     * @param  string  $capability  WordPress user capability to be checked against
     * @param array Optional parameters, typically the object ID. You can also pass a future request parameter via
     *              curly braces e.g. {post_id}
     *
     * @since 0.9.0
     */
    public function hasCap(string $capability, ...$args): Endpoint;

    /**
     * Adds a schema validation to the validationHandlers, which will be later called in advance to
     * validate a REST request according to the given JSON schema.
     *
     * @since 0.9.0
     *
     * @param  string|array  $schema  Filepath to the JSON schema or a JSON schema as an array.
     */
    public function schema(string|array $schema): Endpoint;

    /**
     * Adds a response schema to the endpoint. This JSON schema will later on filter the response before sending
     * it to the client. This can be great to:
     * 1) Discard unnecessary properties in the response to avoid the leakage of sensitive data and
     * 2) Making sure that the required data is retrieved.
     *
     * @param  string|array  $schema  Filepath to the JSON schema or a JSON schema as an array.
     * @param  string|bool|null  $removeAdditionalProperties  Option which defines if we want to remove additional properties.
     *                                                        If true removes all additional properties from the response. If false allows additional properties to be retrieved.
     *                                                        If null it will use the JSON schema additionalProperties value. If a string allows only those variable types (e.g. integer)
     *
     * @since 0.9.0
     */
    public function returns(string|array $schema, string|bool|null $removeAdditionalProperties = true): Endpoint;

    /**
     * Registers a middleware
     *
     * @since 0.9.0
     *
     * @param  Middleware  $middleware  Middleware to be attached to endpoint.
     */
    public function middleware(Middleware $middleware): Endpoint;

    /**
     * Registers a permission callback
     *
     * @since 0.9.0
     *
     * @param  callable  $permissionCb  Method to be called to check current user permissions.
     */
    public function permission(callable $permissionCb): Endpoint;
}
