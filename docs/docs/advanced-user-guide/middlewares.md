Another cool feature of WP-FastEndpoints is the support for middlewares.

Middlewares are pieces of code that can either run before and/or after a request is handled.

At this stage, you might be already familiar with both the `schema(...)` and `returns(...)`
middlewares. However, you can also create your own.

```php
use Wp\FastEndpoints\Contracts\Middleware;

class MyCustomMiddleware extends Middleware
{
    /**
    * Create this function if you want that your middleware is
    * triggered when it receives a request and after checking
    * the user permissions.
     */
    public function onRequest(/* Type what you need */)
    {
        return;
    }
    
    /**
    * Create this function when you want your middleware to be
    * triggered before sending a response to the client 
     */
    public function onResponse(/* Type what you need */) {
        return;
    }
}

// Attach middleware to endpoint
$router->get('/test', function () {
    return true;
})
->middleware(new MyCustomMiddleware());
```

???+ tip
    You can create both methods in a middleware: `onRequest` and `onResponse`.
    However, to save some CPU cycles only create the one you need [CPU emoji]

## Responses

If you need you can also take advantage of either WP_Error and WP_REST_Response to send
a direct response to the client. See [Responses page](/wp-fastendpoints/advanced-user-guide/responses)
for more info