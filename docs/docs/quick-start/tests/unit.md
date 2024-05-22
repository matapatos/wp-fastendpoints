As an example of a unit test, we are going add a test to check the 1) request payload schema
used and 2) the necessary user permissions on the endpoint that allows a user to create a new blog post.

We could have separated each assertion in its own unit test but for the sake of simplicity we
are going to make both of them in the same test.

```php
test('Creating post endpoint has correct permissions and schema', function () {
    // Create endpoint mock
    $endpoint = Mockery::mock(Endpoint::class);
    // Assert that the request payload schema passed is correct
    $endpoint
        ->shouldReceive('schema')
        ->once()
        ->with('Posts/CreateOrUpdate')
        ->andReturnSelf();
    // Assert that user permissions are correct
    $endpoint
        ->shouldReceive('hasCap')
        ->once()
        ->with('publish_posts');
    // To ignore all the other endpoints
    $ignoreEndpoint = Mockery::mock(Endpoint::class)->shouldIgnoreMissing(Mockery::self());
    // Create router. Make sure that var name matches your router variable
    $router = Mockery::mock(Router::class)
        ->shouldIgnoreMissing($ignoreEndpoint);
    // Assert that router endpoint is called
    $router
        ->shouldReceive('post')
        ->once()
        ->with('/', Mockery::type('callable'))
        ->andReturn($endpoint);
    // Needed to attach endpoints
    require \ROUTERS_DIR.'/Posts.php';
})->group('api', 'posts');
```

The reason we are able to make the assertions above is
[due to this line](https://github.com/matapatos/wp-fastendpoints/wiki/Quick-start#the-actual-code---srcapirouterspostsphp).
Specially, regarding this part ```$router ??```. This allows us to replace our original router with our mocked version.

Nothing magical happening here, just pure PHP code! 🪄
