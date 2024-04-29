<?php

declare(strict_types=1);

namespace Tests\Wp\FastEndpoints\Helpers;

class Faker
{
    /**
     * Retrieves an array like a WP_User
     */
    public static function getWpUser(): array
    {
        return [
            'data' => [
                'ID' => '5',
                'user_login' => 'fake',
                'user_pass' => 'my-secure-password',
                'user_nicename' => 'fake',
                'user_email' => 'fake@wpfastendpoints.com',
                'user_url' => 'https://www.wpfastendpoints.com/wp',
                'user_registered' => '2022-03-14 10:34:29',
                'user_activation_key' => 'my-activation-key',
                'user_status' => '0',
                'display_name' => 'André Gil',
            ],
            'ID' => 5,
            'caps' => [
                'administrator' => true,
            ],
            'cap_key' => 'wp_capabilities',
            'roles' => [
                'administrator',
            ],
            'allcaps' => [
                'switch_themes' => true,
                'edit_themes' => true,
                'administrator' => true,
            ],
            'filter' => null,
        ];
    }
}
