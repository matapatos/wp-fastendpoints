For most projects, all JSON schemas might be kept inside a single root directory, like:

```text
my-plugin
│
└───src
│   │
│   └───Api
│   │   │
│   │   └───Schemas  // Root dir
│   │       │
│   │       └───Posts
│   │       │   │   (...)
│   │       │
│   │       └───Users
│   │           │   (...)
```

However, when your API starts to grow you might end up having the need for multiple root directories.

## Example

Let's imagine that your API consists on two different versions: v1 and v2, like the following:

```text
my-plugin
│
└───src
│   │
│   └───Api
│   │   │
│   │   └───v1
│   │   │   └───Schemas  // V1 JSON schemas root dir
│   │   │       │
│   │   │       └───Posts
│   │   │           │   (...)
│   │   │
│   │   └───v2
│   │       └───Schemas  // V2 JSON schemas root dir
│   │           │
│   │           └───Posts
│   │               │   (...)
```

In this case scenario your code would look something like this:

```php
$router->appendSchemaDir(MY_PLUGIN_DIR.'Api/v1/Schemas', 'https://www.wp-fastendpoints.com/v1');
$router->appendSchemaDir(MY_PLUGIN_DIR.'Api/v2/Schemas', 'https://www.wp-fastendpoints.com/v2');
```

Then in all your endpoints you will have to specify the full schema prefix. It's important that
you specify the full prefix because we can't guarantee the order or even if the same schema
directory is returned all the time.

=== "Using v1 schemas"
    ```php
    $router->get('/test', function(){return true;})
    ->returns('https://www.wp-fastendpoints.com/v1/Posts/Get.json');
    ```
=== "Using v2 schemas"
    ```php
    $router->get('/test', function(){return true;})
    ->returns('https://www.wp-fastendpoints.com/v2/Posts/Get.json');
    ```