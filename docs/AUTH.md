# Авторизация (fuisic/auth)

Backend использует отдельный пакет **[fuisic/auth](https://github.com/Johny314/fuisic-auth)** для всей auth-логики.

## Подключение

```json
// composer.json
"repositories": [
    {
        "type": "path",
        "url": "../fuisic-auth",
        "options": { "symlink": true }
    }
],
"require": {
    "fuisic/auth": "@dev"
}
```

Production — VCS repository на GitHub (см. README пакета).

## Модель User

```php
// app/Models/User.php
use Fuisic\Auth\Traits\HasFuisicAuth;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laragear\WebAuthn\Contracts\WebAuthnAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail, WebAuthnAuthenticatable
{
    use HasApiTokens, HasFuisicAuth;
}
```

## Конфигурация приложения

Файл `config/fuisic-auth.php` расширяет базовый config пакета:

- `register.validation` / `fillable` / `defaults` — поле `user_type` (enum `UserType`)
- включение OAuth-провайдеров через env
- `passkeys.relying_party` для WebAuthn

Переменные — в `.env.example` (секции `FUISIC_AUTH_*`, `VKONTAKTE_*`, `YANDEX_*`, `RABBITMQ_*`).

## Маршруты

Auth-маршруты **не** объявлены в `routes/api.php` — их регистрирует `FuisicAuthServiceProvider`.

Доменные маршруты в `routes/api.php` защищены `auth:sanctum`:

```php
Route::middleware('auth:sanctum')->group(function () {
    // card_set, test, ...
});
```

Публичные auth-эндпоинты пакета: `/register`, `/login`, `/password/*`, `/oauth/*`, `/passkeys/*`.

Полный список: [fuisic-auth/docs/API.md](https://github.com/Johny314/fuisic-auth/blob/main/docs/API.md)

## RabbitMQ

Письма (verification, password reset) уходят в очередь `auth.notifications`:

```env
QUEUE_CONNECTION=rabbitmq
FUISIC_AUTH_QUEUE_CONNECTION=rabbitmq
```

Worker — контейнер `queue` в docker-compose.

## OAuth VK и Yandex

1. Создайте приложения в [VK ID](https://id.vk.com/about/business/go/docs/ru/vkid/latest/vk-id/connection/create-application) и [Yandex OAuth](https://oauth.yandex.ru/)
2. Callback: `{APP_URL}/oauth/vkontakte/callback` и `{APP_URL}/oauth/yandex/callback`
3. В `.env`:

```env
FUISIC_AUTH_VK_ENABLED=true
VKONTAKTE_CLIENT_ID=...
VKONTAKTE_CLIENT_SECRET=...

FUISIC_AUTH_YANDEX_ENABLED=true
YANDEX_CLIENT_ID=...
YANDEX_CLIENT_SECRET=...
```

### Привязка аккаунта

Авторизованный пользователь:

```
GET /oauth/vkontakte/link   → { "url": "..." }
GET /oauth/linked           → список провайдеров
DELETE /oauth/vkontakte     → отвязка
```

## Passkeys (Apple Face ID / Touch ID)

WebAuthn через Laragear. RP ID для local:

```env
FUISIC_AUTH_PASSKEY_RP_ID=localhost
```

Фронтенд вызывает `/passkeys/login/options` → WebAuthn API → `/passkeys/login`.

## Admin (Backpack)

Админка `/admin` использует **отдельную** session-авторизацию Backpack, не Sanctum API. Middleware `CheckIfAdmin` проверяет `user_type === admin`.

## Миграции пакета

Автозагрузка из пакета:

- `oauth_accounts`
- `password_reset_tokens`

Дополнительно в проекте:

- `personal_access_tokens` (Sanctum)
- `webauthn_credentials` (publish Laragear)

## Сиды

`UserFactory` создаёт пользователей с `email_verified_at = now()` для локального login без письма.
