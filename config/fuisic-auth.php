<?php

use App\Enums\RoleName;
use App\Models\User;

$packageConfig = dirname(__DIR__).'/vendor/fuisic/auth/config/fuisic-auth.php';

if (! file_exists($packageConfig)) {
    $packageConfig = dirname(__DIR__).'/../fuisic-auth/config/fuisic-auth.php';
}

return array_replace_recursive(
    require $packageConfig,
    [
        'user_model' => User::class,

        // вход по логину ребёнка (поле `login`), см. App\Rules\Username
        'login' => [
            'username_column' => 'username',
        ],

        'register' => [
            // admin и moderator назначаются только вручную
            'roles' => array_map(fn (RoleName $role) => $role->value, RoleName::REGISTRABLE),
            'default_role' => RoleName::student->value,
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
