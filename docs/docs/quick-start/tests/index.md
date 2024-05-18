For testing our **WP-FastEndpoints** router we are going to use [pest/php](https://pestphp.com/).

Pest is a testing framework that makes it super easy to test functionality in PHP,
that's why we are going to use it here. However, if you have a preference for some other testing
framework, the some principles should apply 😊

Full source code can be found at [**matapatos/wp-fastendpoints-my-plugin »**](https://github.com/matapatos/wp-fastendpoints-my-plugin)

## Testing dependencies

First, let's add all the necessary testing dependencies:

```bash
composer require mockery/mockery --dev  # For mocking classes/functions
composer require dingo-d/wp-pest --dev  # Adds Pest support for integration tests
```

## Testing structure

For testing our plugin, we are going to assume the following structure:

```
my-plugin
│   my-plugin.php
│   composer.json
│
└───src
│       (...)
│
└───tests
    │   bootstrap.php   # Loads WordPress for integration tests
    │   Helpers.php     # (optional) Helper functions
    │   Pest.php        # Pest configuration file
    │
    └───Integration
    │       PostsApiTest.php
    │
    └───Unit
            PostsApiTest.php
```
