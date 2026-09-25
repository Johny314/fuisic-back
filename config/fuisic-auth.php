<?php

use App\Enums\UserType;
use Illuminate\Validation\Rule;

$packageConfig = dirname(__DIR__).'/vendor/fuisic/auth/config/fuisic-auth.php';

if (! file_exists($packageConfig)) {
    $packageConfig = dirname(__DIR__).'/../fuisic-auth/config/fuisic-auth.php';
}

return array_replace_recursive(
    require $packageConfig,
    [
        'user_model' => App\Models\User::class,

        'register' => [
            'validation' => [
                'user_type' => ['sometimes', Rule::enum(UserType::class)],
            ],
            'fillable' => ['user_type'],
            'defaults' => [
                'user_type' => UserType::student->value,
            ],
        ],

        'oauth' => [
            'providers' => [
                'vkontakte' => [
                    'enabled' => env('FUISIC_AUTH_VK_ENABLED', false),
                ],
                'yandex' => [
                    'enabled' => env('FUISIC_AUTH_YANDEX_ENABLED', false),
                ],
            ],
        ],

        'passkeys' => [
            'relying_party' => [
                'name' => env('APP_NAME', 'FUISIC'),
                'id' => env('FUISIC_AUTH_PASSKEY_RP_ID', parse_url(env('APP_URL', 'http://localhost:8080'), PHP_URL_HOST)),
            ],
        ],
    ]
);
