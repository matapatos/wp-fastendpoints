<?php

namespace Wp\FastEndpoints\Validation;

use ReflectionClass;
use Wp\FastEndpoints\Contracts\Validation\BaseModel;
use Wp\FastEndpoints\Contracts\Validation\Options\Alias;
use Wp\FastEndpoints\Contracts\Validation\Options\From;
use WP_REST_Request;

use function Symfony\Component\String\u;

function getAliasData(BaseModel $model, array $data, string $aliasFunction): array
{
    $aliasData = [];
    $reflect = new ReflectionClass($model);
    $properties = $reflect->getProperties();
    foreach ($properties as $property) {
        $propertyName = $property->getName();
        if (isset($data[$propertyName])) {
            continue;
        }

        $alias = call_user_func([u($propertyName), $aliasFunction]);
        if (! isset($data[$alias])) {
            continue;
        }

        $aliasData[$propertyName] = $data[$alias];
    }

    return $aliasData;
}

/**
 * Adds option which determines where to fetch the data to populate model
 */
Option::add(From::class, function (From $from, WP_REST_Request $request, array $payload): array {
    return match ($from) {
        From::JSON => $payload + $request->get_json_params(),
        From::BODY => $payload + $request->get_body_params(),
        FROM::QUERY => $payload + $request->get_query_params(),
        FROM::URL => $payload + $request->get_url_params(),
        FROM::FILE => $payload + $request->get_file_params(),
        default => $payload + $request->get_params(),
    };
}, override: true);

/**
 * Adds option to with additional alias for a given property
 */
Option::add(Alias::class, function (Alias $alias, BaseModel $model, array $payload): array {
    return match ($alias) {
        Alias::CAMEL => $payload + getAliasData($model, $payload, 'camel'),
        Alias::PASCAL => $payload + getAliasData($model, $payload, 'pascal'),
        Alias::SNAKE => $payload + getAliasData($model, $payload, 'snake'),
        Alias::KEBAB => $payload + getAliasData($model, $payload, 'kebab'),
        default => $payload,
    };
}, override: true);
