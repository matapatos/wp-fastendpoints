## v2.0.0

Support for treating plugins as dependencies.

- `depends(['buddypress''])` - Only loads the BuddyPress plugin for this given endpoint

```php
$router->get('/users/(?P<ID>[\d]+)', function ($ID) {
    return get_user_by('id', $ID);
})
->returns('Users/Get')
->depends(['buddypress']);  // Only BuddyPress plugin will be loaded for this REST endpoint
```

!!! warn
Make sure to run the following WP-CLI command after a successfull deployment:
`wp `

## v1.2.2

Three new filters that allows us to customise our JSON schema validator's.

- `fastendpoints_validator` - Triggered by both middlewares
- `fastendpoints_schema_validator` - Only triggered for Schema middlewares validators
- `fastendpoints_response_validator` - Only triggered for Response middlewares validators

```php
use Opis\JsonSchema\Validator;

add_filter('fastendpoints_validator', function (Validator $validator): Validator {
    $formatsResolver = $validator->parser()->getFormatResolver();
    $formatsResolver->registerCallable('integer', 'even', function (int $value): bool {
        return $value % 2 === 0;
    });

    return $validator;
});
```

!!! info
    For more customisations check the following links:
    1. [Custom formats](https://opis.io/json-schema/2.x/php-format.html),
    2. [Custom filters](https://opis.io/json-schema/2.x/php-filter.html),
    3. [Custom media types](https://opis.io/json-schema/2.x/php-media-type.html) and,
    4. [Custom content encoding](https://opis.io/json-schema/2.x/php-content-encoding.html)

## v1.2.1

Using JSON/opis schema loader and resolver which allows us to
[reference schemas](https://opis.io/json-schema/2.x/references.html) inside
other schemas.

```php
// Now we also need to set a prefix while appending a directory. This prefix
// will be used to reference schemas from inside another schema.
$router->appendSchemaDir('/my-dir', 'http://www.example.com');
```

## v1.2.0

Dependency injection support in main handler, middlewares and permission handlers.

```php
// In the past, the $request parameter was mandatory:
$router->get('/posts/(?P<ID>[\d]+)', function (WP_REST_Request $request) {
    return $request->get_param('ID');
});

// Now you only type what you need
$router->get('/posts/(?P<ID>[\d]+)', function ($ID) {
    return $ID;
});
// Middleware changes
class MyCustomMiddleware extends \Wp\FastEndpoints\Contracts\Middleware {
    public function onRequest(/* Type what you need e.g. $request */) {
        // Called before handling the request
    }
    public function onResponse(/* Type what you need e.g. $response, $request */) {
        // Called after the request being handled
    }
}
```

## v1.1.0

- 100% test coverage
- Integration tests
- Middleware support
- Upgraded PHP version to 8.1
- Full support for WordPress 6.x versions
- Updated both Response and Schema to a middleware

```php
// Middleware example
class MyCustomMiddleware extends \Wp\FastEndpoints\Contracts\Middleware {
    public function onRequest(\WP_REST_Request $request): ?\WP_Error {
        // Called before handling the request
        return null;
    }
    public function onResponse(\WP_REST_Request $request, mixed $response): mixed {
        // Called after the request being handled
        return $response;
    }
}
```

## v1.0.0

Initial release - don't use it!
