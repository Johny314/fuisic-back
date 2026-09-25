USER_ID ?= $(shell id -u)
GROUP_ID ?= $(shell id -g)
COMPOSE = docker compose

setup-local: env-prepare storage-setup composer-install up app-key-generate package-discover db-setup swagger-generate

start: up
stop: down

env-prepare:
	test -f .env || cp .env.example .env

storage-setup:
	mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/app/public bootstrap/cache

composer-install:
	docker run --rm -u "$(USER_ID):$(GROUP_ID)" \
		-v "$(CURDIR):/var/www/html" \
		-v "$(CURDIR)/../fuisic-auth:/var/www/fuisic-auth:ro" \
		-w /var/www/html \
		-e CACHE_STORE=array \
		laravelsail/php83-composer:latest \
		composer install --ignore-platform-reqs --no-scripts
	docker run --rm -u "$(USER_ID):$(GROUP_ID)" \
		-v "$(CURDIR):/var/www/html" \
		-v "$(CURDIR)/../fuisic-auth:/var/www/fuisic-auth:ro" \
		-w /var/www/html \
		-e CACHE_STORE=array \
		laravelsail/php83-composer:latest \
		composer dump-autoload

up:
	$(COMPOSE) up -d --build

down:
	$(COMPOSE) down

app-key-generate:
	$(COMPOSE) exec app php artisan key:generate

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
