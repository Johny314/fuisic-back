# Доменное API

Базовый URL: `http://localhost:8080` (или `APP_URL`).

Авторизация — Bearer Sanctum token (см. [AUTH.md](AUTH.md) и пакет fuisic-auth).

OpenAPI/Swagger: `/api/documentation`

## Публичные эндпоинты

| Метод | URI | Описание |
|-------|-----|----------|
| GET | `card_set` | Список наборов карточек |
| GET | `card_set/{card_set}` | Набор карточек |
| GET | `card_set/{card_set}/cards` | Карточки набора |
| GET | `section`, `section/{section}` | Разделы |
| GET | `user`, `user/{user}` | Пользователи |
| GET | `card`, `card/{card}` | Карточки |
| GET | `task`, `task/{task}` | Задания |
| GET | `test`, `test/{test}` | Тесты |
| GET | `test/{test}/tasks` | Задания теста |
| POST | `test/{test}/answers` | Проверка ответов (публично) |
| GET | `filters/classification` | Фильтр: классификация |
| GET | `filters/difficulty` | Фильтр: сложность |

## Защищённые эндпоинты (auth:sanctum)

| Метод | URI | Описание |
|-------|-----|----------|
| POST | `card_set` | Создать набор |
| PUT | `card_set/{card_set}` | Обновить |
| DELETE | `card_set/{card_set}` | Удалить |
| POST/PUT/DELETE | `section`, `section/{section}` | CRUD разделов |
| POST/PUT/DELETE | `user`, `user/{user}` | CRUD пользователей |
| POST/PUT/DELETE | `card`, `card/{card}` | CRUD карточек |
| POST/PUT/DELETE | `task`, `task/{task}` | CRUD заданий |
| POST/PUT/DELETE | `test`, `test/{test}` | CRUD тестов |
| POST | `test/{test}/answers` | Проверка ответов (auth) |

URI задаются enum `App\Enums\Uri`.

## Auth API

Не дублируется здесь — см. [fuisic-auth/docs/API.md](https://github.com/Johny314/fuisic-auth/blob/main/docs/API.md):

- `/register`, `/login`, `/logout`, `/me`
- `/email/verify/*`, `/password/*`
- `/oauth/*`, `/passkeys/*`

## Admin

Backpack CRUD: `/admin/*` (session auth, роль admin).

## Health

```
GET /up
```

Laravel health check — 200 OK.
