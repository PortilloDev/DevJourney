SHELL := /bin/bash
COMPOSE := docker compose
APP := app

.DEFAULT_GOAL := help

.PHONY: help build up down restart stop logs ps shell bash composer install \
        artisan migrate migrate-fresh seed key-generate test pint stan \
        npm npm-dev npm-build clean

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-16s\033[0m %s\n", $$1, $$2}'

build: ## Build (or rebuild) the app image
	$(COMPOSE) build

up: ## Start all containers in the background
	$(COMPOSE) up -d

down: ## Stop and remove all containers
	$(COMPOSE) down

restart: down up ## Restart all containers

stop: ## Stop containers without removing them
	$(COMPOSE) stop

logs: ## Tail logs for all containers
	$(COMPOSE) logs -f

ps: ## List running containers
	$(COMPOSE) ps

shell: bash ## Alias for bash

bash: ## Open a shell inside the app container
	$(COMPOSE) exec $(APP) bash

composer: ## Run composer inside the app container, e.g. make composer ARGS="require foo/bar"
	$(COMPOSE) exec $(APP) composer $(ARGS)

install: ## Install PHP and JS dependencies
	$(COMPOSE) exec $(APP) composer install
	$(COMPOSE) exec $(APP) npm install

artisan: ## Run an artisan command, e.g. make artisan ARGS="make:model Post"
	$(COMPOSE) exec $(APP) php artisan $(ARGS)

migrate: ## Run database migrations
	$(COMPOSE) exec $(APP) php artisan migrate

migrate-fresh: ## Drop all tables and re-run migrations with seeders
	$(COMPOSE) exec $(APP) php artisan migrate:fresh --seed

seed: ## Run database seeders
	$(COMPOSE) exec $(APP) php artisan db:seed

key-generate: ## Generate the application key
	$(COMPOSE) exec $(APP) php artisan key:generate

test: ## Run the test suite
	$(COMPOSE) exec $(APP) php artisan test

pint: ## Run Laravel Pint (code style fixer)
	$(COMPOSE) exec $(APP) ./vendor/bin/pint

stan: ## Run PHPStan static analysis
	$(COMPOSE) exec $(APP) ./vendor/bin/phpstan analyse

npm: ## Run an npm command, e.g. make npm ARGS="run build"
	$(COMPOSE) exec $(APP) npm $(ARGS)

npm-dev: ## Run the Vite dev server
	$(COMPOSE) exec $(APP) npm run dev

npm-build: ## Build frontend assets for production
	$(COMPOSE) exec $(APP) npm run build

clean: ## Stop containers and remove volumes (destroys DB data)
	$(COMPOSE) down -v
