# Локальная разработка

## Docker Compose

Стек описан в `docker-compose.yml`, имя проекта — `fuisic` (контейнеры `fuisic-app-1`, `fuisic-pgsql-1`, …). Конфиги сервисов лежат в `docker/<сервис>/`.

| Сервис | Образ | Назначение |
|--------|-------|------------|
| `app` | `fuisic-back:dev` (`docker/app/Dockerfile`, PHP 8.4-FPM) | Приложение |
| `queue` | `fuisic-back:dev` | Worker: `queue:work rabbitmq` |
| `nginx` | `nginx:1.30-alpine` | Веб-сервер, порт `APP_PORT` (8080) |
| `pgsql` | `postgres:18` | БД `fuisic` + `testing` для тестов |
| `redis` | `redis:8-alpine` | Кэш, порт `FORWARD_REDIS_PORT` (6380) |
| `rabbitmq` | `rabbitmq:4-management-alpine` | Очереди + UI :15672 |
| `mailpit` | `axllent/mailpit` | Письма, UI :8025 |
| `minio` / `minio-init` | `pgsty/minio`, `pgsty/mc` | S3, бакет `fuisic` |
| `front` | `fuisic-front:dev` | Expo, профиль `front`, порт 8081 |

Официальные образы MinIO больше не публикуются, поэтому используется совместимый форк [pgsty/minio](https://hub.docker.com/r/pgsty/minio). Версии образов закреплены — обновляйте их осознанно.

Пакет авторизации монтируется в контейнер как `../fuisic-auth → /var/www/fuisic-auth` (для composer path repository).

## Makefile

| Команда | Описание |
|---------|----------|
| `make setup-local` | Полная первичная настройка |
| `make start` / `make stop` | Запуск / остановка |
| `make composer-install` | Composer без Sail (через Docker-образ) |
| `make db-setup` | `migrate --seed` |
| `make run-tests` | PHPUnit в отдельной БД `testing` |
| `make lint` / `make lint-fix` | Pint: проверка / автоформат |
| `make artisan …` | Любая artisan-команда в контейнере |
| `make start-front` | Профиль `front` (Expo из `../fuisic-front`) |

## Пакет авторизации (локально)

`composer.json` подключает `fuisic/auth` через path repository:

```json
"url": "../fuisic-auth"
```

Клонируйте репозиторий рядом:

```bash
cd ..
git clone git@github.com:Johny314/fuisic-auth.git
cd fuisic-back
docker compose exec app composer update fuisic/auth
```

Изменения в пакете подхватываются через symlink без переустановки.

## Storage

При первом запуске `make setup-local` создаёт:

```
storage/framework/cache/data
storage/framework/sessions
storage/framework/views
storage/app/public
bootstrap/cache
```

## Passkeys

Таблица `passkeys` (laravel/passkeys) уже опубликована в `database/migrations`. Старые ключи `webauthn_credentials` (laragear) не переносятся — миграция удаляет таблицу, passkey регистрируется заново.

## Очереди

Worker запускается контейнером `queue`. Проверка RabbitMQ:

- UI: http://localhost:15672
- Очередь писем: `auth.notifications`

Ручной запуск worker:

```bash
docker compose exec app php artisan queue:work rabbitmq \
  --queue=auth.notifications,default --tries=3
```

## Pint (форматирование)

```bash
make lint-fix
```

CI запускает `pint --test` — неотформатированный код не пройдёт проверку.

## Telescope

В local включён по умолчанию: `/telescope`

## Troubleshooting

### 502 Bad Gateway

nginx переразрешает `app` через DNS Docker, поэтому перезапуск `app` больше не даёт 502. Если всё же 502 — контейнер `app` не запущен: `docker compose ps`, `docker compose logs app`.

### Composer / Redis при install

`make composer-install` использует `CACHE_STORE=array` и `--no-scripts`, затем `package:discover` после старта контейнеров.

### Порт 8080 занят

Измените `APP_PORT` в `.env`.
