.PHONY: up down build logs shell test lint stan format setup composer npm-install npm-dev npm-build

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build

logs:
	docker compose logs -f app

shell:
	docker compose exec app bash

setup: ## Primer arranque: copia .env, levanta servicios, instala dependencias
	cp -n .env.example .env
	docker compose up -d --build
	docker compose exec app composer update
	docker compose exec app ./vendor/bin/pest --init
	docker compose exec app php artisan key:generate
	@echo "Listo. Web: http://localhost:8080 | Mailpit: http://localhost:8025"

composer:
	docker compose exec app composer $(cmd)

test:
	docker compose exec app php artisan test

lint:
	docker compose exec app ./vendor/bin/pint --test

stan:
	docker compose exec app ./vendor/bin/phpstan analyse

format:
	docker compose exec app ./vendor/bin/pint

npm-install:
	docker compose run --rm assets npm install

npm-dev: ## Levanta el dev server de Vite (servicio assets)
	docker compose up -d assets

npm-build:
	docker compose run --rm assets sh -c "npm install && npm run build"
