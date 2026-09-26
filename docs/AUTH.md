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
use Laravel\Passkeys\Contracts\PasskeyUser;

class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    use HasApiTokens, HasFuisicAuth, HasRoles;
}
```

## Конфигурация приложения

Файл `config/fuisic-auth.php` расширяет базовый config пакета:

- `register.roles` — роли на выбор при регистрации (`student`, `teacher`, `parent` из `App\Enums\RoleName::REGISTRABLE`), `register.default_role` — `student`; admin и moderator назначаются только вручную
- `register.defaults` — `user_type = student` (устаревшее поле, удаляется в fuisic-back#29)
- `login.username_column = username` — `POST /login` принимает поле `login`: email или логин ребёнка (без учёта регистра); старое поле `email` тоже работает
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

WebAuthn через [laravel/passkeys](https://github.com/laravel/passkeys-server) (свои API-маршруты на токенах, опции в кэше). RP ID для local:

```env
FUISIC_AUTH_PASSKEY_RP_ID=localhost
```

Фронтенд вызывает `/passkeys/login/options` → WebAuthn API → `/passkeys/login` с `{ credential }`. Origin берётся из `FRONTEND_URL` и `APP_URL`.

## Роли и права

- [spatie/laravel-permission](https://spatie.be/docs/laravel-permission) 8.x, guard `web` для API и админки (`User::$guard_name`).
- Стартовые роли и права — `App\Enums\RoleName`, `App\Enums\PermissionName`; создаёт их `App\Support\RoleCatalog::install()` (миграция и `RoleSeeder`). Повторный запуск добавляет недостающее и не трогает права существующих ролей — их меняют в админке.
- admin — суперадмин через `Gate::before` (`AppServiceProvider`), прав в роли не хранит.
- Пока жив `user_type`, модель держит соответствующую роль admin/teacher/student (хуки `created`/`updated` в `User`); moderator и parent назначаются только ролью.
- `GET /me` дополнительно отдаёт `roles`, `permissions` (у admin — все) и `teacher_verified` (роль teacher и право `catalog.submit`) — `User::authProfile()`.

## Родитель и дети

- Связь многие ко многим — таблица `parent_child` (`User::children()` / `User::parents()`); `users.created_by_id` — родитель, создавший аккаунт.
- Аккаунт ребёнка: роль student, `email = null`, логин в `users.username` (unique, хранится в нижнем регистре), класс — `users.grade` (1–11). Формат логина — `App\Rules\Username`: латиница, цифры, `_` и `.`, 3–32 символа, без `@`; уникален без учёта регистра, включая удалённые аккаунты.
- Ребёнок входит `POST /login` с `{ "login": "<логин>", "password": "..." }` без подтверждения email. Email можно добавить позже через `PUT /user/{id}` (у пользователя без логина email обязателен); вход по неподтверждённому email по-прежнему даёт 403.
- Логин, роли и связь с родителем ребёнок сам не меняет: `PUT /user/{id}` их не принимает, `/children/*` требует прав родителя.
- `GET /me` отдаёт `username` (`User::authProfile()`).

| Метод | URI | Право | Описание |
|-------|-----|-------|----------|
| GET | `/children` | `children.view` | Мои дети |
| GET | `/children/{child}` | `children.view` | Аккаунт ребёнка |
| POST | `/children` | `children.manage` | Создать: `name`, `username`, `grade`, `password`, `password_confirmation` |
| PUT | `/children/{child}` | `children.manage` | Изменить `name`, `username`, `grade` |
| PUT | `/children/{child}/password` | `children.manage` | Сбросить пароль (`password`, `password_confirmation`); все токены ребёнка отзываются |
| DELETE | `/children/{child}` | `children.manage` | Удалить аккаунт (soft delete, токены отзываются); только родитель-создатель, иначе 403 |

Без права — 403; чужой ребёнок — 404 (существование не раскрывается). Демо: родитель `parent@fuisic.local`, ребёнок — логин `masha`, пароль `password`.

## Admin (Backpack)

Админка `/admin` использует **отдельную** session-авторизацию Backpack (группа `web`, CSRF включён), не Sanctum API. Middleware `CheckIfAdmin` проверяет `user_type === admin`.

API (`routes/api.php`) подключён группой `api` без префикса: без сессий и CSRF, авторизация только Bearer-токеном.

## Миграции пакета

Автозагрузка из пакета:

- `oauth_accounts`
- `password_reset_tokens`

Дополнительно в проекте:

- `personal_access_tokens` (Sanctum)
- `passkeys` (`vendor:publish --tag=passkeys-migrations`, уже в `database/migrations`)

## Сиды

`UserFactory` создаёт пользователей с `email_verified_at = now()` для локального login без письма; `UserFactory::child($parent)` — аккаунт ребёнка без email с логином.
