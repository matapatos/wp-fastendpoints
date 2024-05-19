The first thing we need to do is to create a Router.

```php
use Wp\FastEndpoints\Router;

// Dependency injection to enable us to mock router in tests
$router = $router ?? new Router('posts');
```

A router is just an instance which allow us to attach and register endpoints.

We can have an application with one or multiple routers. One main benefit of using multiple routers is to group endpoints by same namespace and (optionally) same version. For instance,
in this tutorial we are going to create a main router with a base namespace `my-plugin` and a version of `v1`
which will add `/my-plugin/v1/` to the beginning of each attached endpoint from sub-routers.

#### Create a post

With the posts router in place we can now start attaching our endpoints. We start adding the one to that
allows a user to create a blog post.

```php
$router->post('/', function (\WP_REST_Request $request): int|\WP_Error {
    $payload = $request->get_params();

    return wp_insert_post($payload, true);
})
    ->schema('Posts/CreateOrUpdate')
    ->hasCap('publish_posts');
```

When a request is received by this endpoint the following happens:

1) Firstly, the user permissions are checked - Makes sure that the user has [*publish_posts*](https://wordpress.org/documentation/article/roles-and-capabilities/#publish_posts) capability
2) Then, if successful, it validates the request payload by using the *Posts/CreateOrUpdate* schema.
   We still didn't specify where the endpoints should look for the schemas, but don't worry we are getting into that in a moment
3) Lastly, if the validation process also passes the handler is called.

!!! info
    In this scenario we are not using a JSON schema to discard fields because the [_wp_insert_post_](https://developer.wordpress.org/reference/functions/wp_insert_post/)
    either returns the ID of the post or a WP_Error which is already what we want 😊

#### Retrieve a post

Some endpoints however do need to return more complex objects. And in those cases JSON
schemas can be of a great help.

JSON schemas can help us to make sure that we are returning all the required fields
as well as to avoid retrieving sensitive information. The last one is configurable.

```php
use Wp\FastEndpoints\Helpers\WpError;

$router->get('(?P<ID>[\d]+)', function ($ID) {
    $post = get_post($ID);

    return $post ?: new WpError(404, 'Post not found');
})
    ->returns('Posts/Get')
    ->hasCap('read');
```

In this case, we didn't set a JSON schema on purpose because we only need the
*post_id* which is already parsed by the regex rule - we could have made that rule
to match only positive integers though 🤔

Going back to the endpoint, this is what happens if a request comes in:

1) Firstly, it checks the user has [_read_](https://wordpress.org/documentation/article/roles-and-capabilities/#read)
   capability - one of the lowest WordPress users capabilities
2) If so, it then calls the handler which either retrieves the post data (e.g. array or object)
   or a [_WpError_](https://github.com/matapatos/wp-fastendpoints/blob/main/src/Helpers/WpError.php)
   in case that is not found. If a WpError or WP_Error is returned it stops further code execution
   and returns that error message to the client - avoiding triggering response schema validation for example.
3) Lastly, if the post data is returned by the handler the response schema will be triggered
   and will check the response according to the given schema (e.g. _Posts/Get_)

!!! note
    The [WpError](https://github.com/matapatos/wp-fastendpoints/blob/main/src/Helpers/WpError.php)
    is just a subclass of WP_Error which automatically set's the HTTP status code of the response

#### Update a post

Checking for user capabilities such as `publish_posts` and `read` is cool. However, in the
real world we sometimes also need to check for a particular resource.

```php
$router->put('(?P<ID>[\d]+)', function (\WP_REST_Request $request): int|\WP_Error {
    $payload = $request->get_params();

    return wp_update_post($payload, true);
})
    ->schema('Posts/CreateOrUpdate')
    ->hasCap('edit_post', '{ID}');
```

The code above is not that different from the one for creating a post. However, in the last line
`hasCap('edit_post', '{post_id}')` the second parameter is a special one for FastEndpoints
which will try to replace it by the _post_id_ parameter.

!!! warning
    FastEndpoints will only replace the *{PARAM_NAME}* if that parameter
    exists in the request payload. Otherwise, will not touch it. Also, bear in mind that the first stage
    in an endpoint is checking the user capabilities. As such, at that time the request params have not
    been already validated by the request payload schema.

#### Delete a post

```php
use Wp\FastEndpoints\Helpers\WpError;

$router->delete('(?P<ID>[\d]+)', function ($ID) {
    $post = wp_delete_post($postId);

    return $post ?: new WpError(500, 'Unable to delete post');
})
    ->returns('Posts/Get')
    ->hasCap('delete_post', '{ID}');
```

### Everything together

```php
"""
Api/Endpoints/Posts.php
"""
declare(strict_types=1);

namespace MyPlugin\Api\Routers;

use Wp\FastEndpoints\Helpers\WpError;
use Wp\FastEndpoints\Router;

// Dependency injection to enable us to mock router in the tests
$router = $router ?? new Router('posts');

// Creates a post
$router->post('/', function (\WP_REST_Request $request): int|\WP_Error {
    $payload = $request->get_params();

    return wp_insert_post($payload, true);
})
    ->schema('Posts/CreateOrUpdate')
    ->hasCap('publish_posts');

// Fetches a single post
$router->get('(?P<ID>[\d]+)', function ($ID) {
    $post = get_post($ID);

    return $post ?: new WpError(404, 'Post not found');
})
    ->returns('Posts/Get')
    ->hasCap('read');

// Updates a post
$router->put('(?P<ID>[\d]+)', function (\WP_REST_Request $request): int|\WP_Error {
    $payload = $request->get_params();

    return wp_update_post($payload, true);
})
    ->schema('Posts/CreateOrUpdate')
    ->hasCap('edit_post', '{ID}');

// Deletes a post
$router->delete('(?P<ID>[\d]+)', function ($ID) {
    $post = wp_delete_post($postId);

    return $post ?: new WpError(500, 'Unable to delete post');
})
    ->returns('Posts/Get')
    ->hasCap('delete_post', '{ID}');

// IMPORTANT: If no service provider is used make sure to set a version to the $router and call
//            the following function here:
// $router->register();

// Used later on by the ApiProvider
return $router;
```