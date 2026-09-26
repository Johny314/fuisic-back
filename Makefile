USER_ID ?= $(shell id -u)
GROUP_ID ?= $(shell id -g)
COMPOSE = docker compose

setup-local: env-prepare storage-setup build composer-install up app-key-generate storage-link package-discover db-setup swagger-generate

start: up
stop: down

env-prepare:
	test -f .env || cp .env.example .env

storage-setup:
	mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/app/public bootstrap/cache

# Composer в образе приложения (PHP 8.4 + все расширения), от имени текущего пользователя
COMPOSER = $(COMPOSE) run --rm --no-deps -u "$(USER_ID):$(GROUP_ID)" -e CACHE_STORE=array app composer

build:
	$(COMPOSE) build app

composer-install:
	$(COMPOSER) install --no-interaction --no-scripts
	$(COMPOSER) dump-autoload

composer:
	$(COMPOSER) $(filter-out $@,$(MAKECMDGOALS))

up:
	$(COMPOSE) up -d --build

down:
	$(COMPOSE) down

app-key-generate:
	$(COMPOSE) exec app php artisan key:generate

# public/storage → storage/app/public: оттуда Backpack (basset) отдаёт CSS/JS админки;
# относительный — чтобы работал и на хосте, и в контейнерах; --force — повторный запуск не падает
storage-link:
	$(COMPOSE) exec app php artisan storage:link --relative --force

package-discover:
	$(COMPOSE) exec app php artisan package:discover --ansi

db-setup:
	$(COMPOSE) exec app php artisan migrate --seed

swagger-generate:
	$(COMPOSE) exec app php artisan l5-swagger:generate

# Тесты идут в отдельной БД `testing` (см. phpunit.xml), RefreshDatabase мигрирует её сам
run-tests:
	$(COMPOSE) exec app php artisan test

lint:
	$(COMPOSE) exec app vendor/bin/pint --test

lint-fix:
	$(COMPOSE) exec app vendor/bin/pint

start-front:
	$(COMPOSE) --profile front up -d front

artisan:
	$(COMPOSE) exec app php artisan $(filter-out $@,$(MAKECMDGOALS))

%:
	@:
