Now that we have our posts router built the last main three bits missing are the following:

1) create a main router to hold all sub-routers (e.g. posts router)
2) specifying where to look for the JSON schemas (one or multiple directories) and
3) lastly register the router. This is what adds the `rest_api_init` hook for registering all
   the endpoints.

```php
<?php
"""
src/Providers/ApiProvider.php
"""
declare(strict_types=1);

namespace MyPlugin\Providers;

use Wp\FastEndpoints\Router;

class ApiProvider implements ProviderContract
{
    protected Router $appRouter;

    public function register(): void
    {
        $this->appRouter = new Router('my-plugin', 'v1');
        $this->appRouter->appendSchemaDir(\SCHEMAS_DIR, 'http://www.my-plugin.com');
        foreach (glob(\ROUTERS_DIR.'/*.php') as $filename) {
            $router = require $filename;
            $this->appRouter->includeRouter($router);
        }
        $this->appRouter->register();
    }
}
```

!!! tip
      Adding the schema directory to the main router will share it across
      all sub-routers.

## It's running

🎉 Congrats you just created your first set of REST FastEndpoints

Now let's see [how to test it out](https://github.com/matapatos/wp-fastendpoints/wiki/Testing)! 😄

Full source code can be found at **[matapatos/wp-fastendpoints-my-plugin »](https://github.com/matapatos/wp-fastendpoints-my-plugin)**