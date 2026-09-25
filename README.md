# FUISIC Backend

[![CI](https://github.com/Johny314/fuisic-back/actions/workflows/ci.yml/badge.svg)](https://github.com/Johny314/fuisic-back/actions/workflows/ci.yml)

API-бэкенд платформы FUISIC на Laravel 11: карточки, тесты, задания и авторизация через пакет [fuisic/auth](https://github.com/Johny314/fuisic-auth).

**Версия:** `v2.2.0`

## Стек

- PHP 8.3, Laravel 11
- PostgreSQL 15, Redis
- RabbitMQ (очереди авторизации)
- Docker Compose (nginx + php-fpm)
- Laravel Sanctum, Backpack (admin)
- OpenAPI (l5-swagger)

## Быстрый старт

### Требования

- Docker Desktop
- Репозиторий `fuisic-auth` рядом с проектом (для локальной разработки):

```
FUISIC/
├── fuisic-back/          ← этот репозиторий
└── fuisic-auth/  ← пакет авторизации
```

### Первый запуск

```bash
make setup-local
```

Команда выполнит:

1. Копирование `.env.example` → `.env`
2. Создание директорий `storage/`
3. `composer install`
4. Симлинк `docker-compose.yml`
5. Сборку и запуск контейнеров
6. `php artisan key:generate`
7. Миграции и сиды

Пересоздать БД с демо-данными:

```bash
make artisan migrate:fresh --seed
```

### Демо-аккаунты (после seed)

| Роль | Email | Пароль |
|------|-------|--------|
| admin | `admin@fuisic.local` | `password` |
| teacher | `teacher@fuisic.local` | `password` |
| student | `ivan@student.ru` | `password` |

Сидеры: `UserSeeder`, `SectionSeeder` (8 разделов физики), `PhysicsContentSeeder` (карточки и тесты).

### Ежедневная работа

```bash
make start    # запуск контейнеров
make stop     # остановка
make run-tests
```

Artisan через Docker:

```bash
make artisan migrate
make artisan queue:work rabbitmq --queue=auth.notifications,default
```

## Сервисы

| Сервис | URL / порт |
|--------|------------|
| API | http://localhost:8080 |
| Health | http://localhost:8080/up |
| Admin (Backpack) | http://localhost:8080/admin |
| Swagger | http://localhost:8080/api/documentation |
| PostgreSQL | `localhost:5432` |
| Redis | `localhost:6380` |
| RabbitMQ AMQP | `localhost:5672` |
| RabbitMQ UI | http://localhost:15672 (guest/guest) |

## Документация

| Файл | Описание |
|------|----------|
| [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md) | Локальная разработка, Docker, Makefile |
| [docs/AUTH.md](docs/AUTH.md) | Интеграция fuisic/auth |
| [docs/API.md](docs/API.md) | Доменное API (карточки, тесты) |

Документация пакета авторизации — в репозитории [fuisic-auth](https://github.com/Johny314/fuisic-auth).

## Структура проекта

```
app/Http/Controllers/{Domain}/{Action}.php   # invokable контроллеры
app/Models/, app/Data/, app/Enums/
config/fuisic-auth.php, config/cors.php
database/seeders/   # User, Section, PhysicsContent
routes/api.php      # доменные маршруты (auth — в пакете)
```

Карта проекта: [fuisic-workspace/CLAUDE.md](https://github.com/Johny314/fuisic-workspace/blob/main/CLAUDE.md) (локально: `../CLAUDE.md`)

## Авторизация

Auth-эндпоинты предоставляет пакет `fuisic/auth`:

- `POST /register`, `POST /login`, `POST /logout`, `GET /me`
- Email verification, password reset
- OAuth VK / Yandex, passkeys

См. [docs/AUTH.md](docs/AUTH.md) и [fuisic-auth/docs/API.md](../fuisic-auth/docs/API.md).

## Переменные окружения

Скопируйте `.env.example` и настройте:

- `DB_*` — PostgreSQL
- `QUEUE_CONNECTION=rabbitmq`, `RABBITMQ_*`
- `FUISIC_AUTH_*`, `VKONTAKTE_*`, `YANDEX_*` — OAuth
- `MAIL_*` — отправка писем (production)

## Тесты

```bash
make run-tests
```

## Лицензия

MIT
