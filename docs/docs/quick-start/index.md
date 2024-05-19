To better exemplify the benefits of using **FastEndpoints** we are going to build an API for manipulating blog posts.

This API will be able to:

* Create
* Retrieve
* Update and
* Delete a blog post

Full source code can be found at **[matapatos/wp-fastendpoints-my-plugin »](https://github.com/matapatos/wp-fastendpoints-my-plugin)**

## Plugin code structure 🔨

To hold this API we are going to create a plugin called *MyPLugin* - don't forget that logic shouldn't
be contained in a theme - with the following structure:

```text
my-plugin
│   my-plugin.php  # Registers the plugin provider
│   composer.json
│
└───src
│   │   constants.php
│   │
│   └───Api
│   │   │
│   │   └───Routers
│   │   │   │   Posts.php  # Holds our custom endpoints
│   │   │
│   │   └───Schemas
│   │       │
│   │       └───Posts
│   │           │   CreateOrUpdate.json  # Validates request payload
│   │           │   Get.json             # Validates responses and discards unwanted fields
│   │
│   │
│   └───Providers
│       │   ApiServiceProvider.php       # Registers all routers
│       │   MyPluginProvider.php         # Bootstraps our plugin
│       │   ProviderContract.php
│
└───tests
```

