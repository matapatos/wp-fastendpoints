When building an API sometimes we want to return a response directly to the client. For example:

```php
$router->get('/posts/(?P<ID>[\d]+)', function ($ID) {
    return get_post($ID);
})
->returns('Posts/Get');  // It will raise a 422 HTTP error when we are unable to find a post
```

The code above, will raise a 422 HTTP status code error when ever we are unable to find
a given post. This is where returning a message directly to the client can be useful.

## Early return

To trigger those scenarios we can either return a WP_Error or a WP_REST_Response.

=== "WP_REST_Response"
    ```php
    $router->get('/posts/(?P<ID>[\d]+)', function ($ID) {
        $post = get_post($ID);
        return $post ?: new WP_REST_Response("No posts found", 404);
    })
    ->returns('Posts/Get');  // This will not be triggered if no posts are found
    ```
=== "WP_Error"
    ```php
    $router->get('/posts/(?P<ID>[\d]+)', function ($ID) {
        $post = get_post($ID);
        return $post ?: new WpError(404, "No posts found");
    })
    ->returns('Posts/Get');  // This will not be triggered if no posts are found
    ```

### Difference between returning WP_REST_Response or WP_Error

The main difference between returning a WP_Error or a WP_REST_Response
is regarding the JSON returned in the body.

=== "WP_REST_Response"
    ```json
    "No posts found"
    ```
=== "WP_Error"
    ```json
    {
        "error": 404,
        "message": "No posts found",
        "data": {
            "status": 404
        }
    }
    ```
