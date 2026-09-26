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
| GET | `teacher_verification` | Последняя заявка учителя на «Проверенного учителя» (404 — заявок не было) |
| POST | `teacher_verification` | Подать заявку (только роль teacher; 409 — есть заявка на рассмотрении или статус уже подтверждён) |

URI задаются enum `App\Enums\Uri`.

## Проверенный учитель

Учитель (роль `teacher`) подаёт заявку: `full_name`, `workplace_type` (`Школа` / `Учебный центр` / `Частная практика`), `workplace_name`, `subjects` (1–10 строк), `link` и `comment` — необязательно. Сканы документов не принимаются.

- Статусы (`status`): `pending` → `approved` / `rejected`; `approved` → `revoked`. Новая заявка — если заявок не было или последняя `rejected` / `revoked`; одна `pending` за раз.
- Решение принимают в админке (`/admin/teacher-verification`, право `teachers.verify`); `reviewer_comment` виден учителю, при отказе и отзыве обязателен. Кто проверял — учителю не отдаётся.
- Одобрение выдаёт пользователю прямое право `catalog.submit`, отзыв — забирает. Письмо о решении уходит через очередь (`App\Notifications\TeacherVerificationDecided`).
- `GET /me`: `teacher_verified` и `teacher_verification: {status, reviewer_comment, submitted_at, reviewed_at} | null` (последняя заявка). У автора материалов (`user` в наборах и тестах) — `teacher_verified`.

## Auth API

Не дублируется здесь — см. [fuisic-auth/docs/API.md](https://github.com/Johny314/fuisic-auth/blob/main/docs/API.md):

- `/register`, `/login`, `/logout`, `/me`
- `/email/verify/*`, `/password/*`
- `/oauth/*`, `/passkeys/*`

## Admin

Backpack CRUD: `/admin/*` (session auth, роль admin).

- `/admin/teacher-verification` — очередь заявок учителей (фильтр `?status=pending|approved|rejected|revoked|all`, по умолчанию `pending`), одобрение / отказ / отзыв статуса; закрыта правом `teachers.verify`.

## Health

```
GET /up
```

Laravel health check — 200 OK.
