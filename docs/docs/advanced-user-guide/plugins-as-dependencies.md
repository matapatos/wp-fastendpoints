One of the main strengths of WordPress is the wide range of plugins available
which allow us to fully customise a website in a short time period. However, every time a plugin
is added it can negatively impact the performance of our API endpoints, because even
though those endpoints might not need some of the activated plugins to work properly, they will
still be loaded.

To address this issue [WP-FastEndpoints Depends](https://github.com/matapatos/wp-fastendpoints-depends)
was created to enable us to treat plugins as REST endpoint dependencies.

## Adding another plugin?? 😱

Yes, this is a plugin! It could seem counterintuitive that adding another plugin could
positively impact our API endpoints. However, given that in most cases our API
endpoints don't need all the plugins that are active e.g. BuddyPress, Elementor
it can actually improve your API endpoints.

## How it works?

Given this plugin needs to be setup as a MU-plugin it will always run before any regular plugin
which allow us to decide which plugins are necessary for a given REST endpoint before loading them.

## How to use it?

Currently, we support both native WP endpoints and FastEndpoints 😊

=== "With FastEndpoints"

    ```php
    $router->get('/example/all-plugins', function () {
        return "Loads all active plugins";
    });

    $router->get('/example/buddypress', function () {
        return "Only MyPlugin and BuddyPress plugins are loaded"; 
    })->depends(['my-plugin', 'buddypress']);
    ```

=== "Native WP endpoints"

    ```php
    // Loads all active plugins
    register_rest_route('native/v1', 'example/all-plugins', [
        'methods' => 'GET',
        (...)
    ]);

    // Only MyPlugin and BuddyPress plugins are loaded
    register_rest_route('native/v1', 'example/buddypress', [
        'methods' => 'GET',
        'depends' => ['my-plugin', 'buddypress'],
        (...)
    ]);
    ```

???+ tip
    By default, if no dependencies are specified in an endpoint it assumes that all active plugins needs
    to be loaded. This behaviour could be overridden for a given set of WP-FastEndpoint's by setting
    router dependencies e.g. `$router->depends(['my-plugin'])`

### Router vs Endpoint dependencies

With WP-FastEndpoint's we are able to either define _global_ endpoint dependencies via router dependencies
or specific endpoint dependencies.

One common scenario where router dependencies might be useful is when we want to change the default behaviour
of loading all active plugins per endpoint.

```php
$router = new \Wp\FastEndpoints\Router('my-api', 'v1');
$router->depends(['my-plugin']); // All endpoints and sub-routers would have this dependency
```

!!! danger
    When adding dependencies to endpoints, make sure to at least include the given plugin that holds those endpoints.
    For instance, if your endpoints reside inside a plugin with a slug `my-plugin` you have to set the dependencies
    to `['my-plugin']` otherwise when a request is received for that endpoint `my-plugin` will not be loaded.

### Endpoint dependencies up-to-date

Under the hood, this plugin generates a config file with all the route dependencies (see [example](https://github.com/matapatos/wp-fastendpoints-depends/blob/main/tests/Data/config.php)).
To have the most up-to-date endpoint dependencies, make sure to either:

- run the `wp fastendpoints depends` command or 
- activate any plugin on the website - this also triggers the re-generation of the route dependencies

## Useful constants

- **FASTENDPOINTS_DEPENDS_ENABLED** - If set to false, always loads all active plugins. **Useful for local development**  
- **FASTENDPOINTS_DEPENDS_CONFIG_FILEPATH** - Overrides dependencies config file path
- **FASTENDPOINTS_DEPENDS_REFRESH_ON_PLUGIN_ACTIVATION** - If set to false, disables re-generating endpoint dependencies
when any plugin is activated
