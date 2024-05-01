# WP-FastEndpoints

<img src="https://raw.githubusercontent.com/matapatos/wp-fastendpoints/main/docs/images/FastEndpoints-Wallpaper-vYDGs.png" alt="WordPress REST endpoints made easy">
<p align="center">
    <a href="https://github.com/matapatos/wp-fastendpoints/actions"><img alt="GitHub Actions Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/matapatos/wp-fastendpoints/tests.yml"></a>
    <a href="https://packagist.org/packages/matapatos/wp-fastendpoints"><img alt="Code Coverage" src="https://img.shields.io/codecov/c/github/matapatos/wp-fastendpoints"></a>
    <a href="https://packagist.org/packages/matapatos/wp-fastendpoints"><img alt="Latest Version" src="https://img.shields.io/packagist/v/matapatos/wp-fastendpoints"></a>
    <a href="https://packagist.org/packages/matapatos/wp-fastendpoints"><img alt="Supported WordPress Versions" src="https://img.shields.io/badge/6.x-versions?logo=wordpress&label=versions"></a>
    <a href="https://packagist.org/packages/matapatos/wp-fastendpoints"><img alt="Software License" src="https://img.shields.io/packagist/l/matapatos/wp-fastendpoints"></a>
</p>

------
**FastEndpoints** is an elegant way of writing custom WordPress REST endpoints with a focus on readability and IDE auto completion support.

- Explore our docs at **[FastEndpoints Wiki »](https://github.com/matapatos/wp-fastendpoints/wiki)**

## Features

- Request validation via JSON schemas
- Removes unwanted fields from Responses 
- Middlewares support
- IDE auto completion support
- Support for newer JSON schema drafts via [json/opis](https://opis.io/json-schema/2.x/)

## Requirements

- PHP 8.1+
- WordPress 6.x
- [json/opis 2.x](https://opis.io/json-schema/2.x/)

We aim to support versions that haven't reached their end-of-life.

## Installation

```bash
composer require wp-fastendpoints
```

FastEndpoints was created by **[André Gil](https://www.linkedin.com/in/andre-gil/)** and is open-sourced software licensed under the **[MIT license](https://opensource.org/licenses/MIT)**.
