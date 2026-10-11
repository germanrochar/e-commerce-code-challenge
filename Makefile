COMPOSE ?= docker compose
DEV_COMPOSE = $(COMPOSE) -f docker-compose.yml -f docker-compose.dev.yml
APP_PORT ?= 8080
ROWS ?= 10000
SCRIPT ?= catalog

.DEFAULT_GOAL := help

.PHONY: help setup up down restart logs shell fresh test lint import generate-csv loadtest dev dev-down dev-fresh

help: ## Show this help
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage: make \033[36m<target>\033[0m\n\n"} /^[a-zA-Z_-]+:.*##/ { printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)
	@echo ""

setup: ## Build and start everything, then print the URL and demo credentials
	$(COMPOSE) up -d --build --wait
	@echo ""
	@echo "  Store:     http://localhost:$(APP_PORT)"
	@echo "  Admin:     admin@example.com / password"
	@echo "  Customer:  customer@example.com / password"
	@echo ""

up: ## Start the stack without rebuilding
	$(COMPOSE) up -d --wait

down: ## Stop the stack (data is kept)
	$(COMPOSE) down

restart: ## Restart the application containers
	$(COMPOSE) restart app queue queue-imports scheduler

logs: ## Follow the logs of every container
	$(COMPOSE) logs -f

shell: ## Open a shell inside the app container
	$(COMPOSE) exec app bash

fresh: ## Wipe all data (database, uploads, cache) and start again with fresh seed data
	$(COMPOSE) down -v
	$(COMPOSE) up -d --build --wait

test: ## Run the PHPUnit test suite against MySQL inside a container
	$(COMPOSE) --profile tools run --rm --build test

lint: ## Run Pint and Larastan inside a container
	$(COMPOSE) --profile tools run --rm --build test sh -c "[ -f .env ] || cp .env.example .env; vendor/bin/pint --test && vendor/bin/phpstan analyse --memory-limit=1G"

import: ## Import a CSV from your machine: make import FILE=path/to/products.csv
	@test -n "$(FILE)" || (echo "Usage: make import FILE=path/to/products.csv" && exit 1)
	$(COMPOSE) cp "$(FILE)" app:/tmp/import.csv
	$(COMPOSE) exec app php artisan products:import /tmp/import.csv

generate-csv: ## Generate a large CSV on your machine: make generate-csv ROWS=100000
	$(COMPOSE) exec app php artisan products:generate-csv $(ROWS) --path=/tmp/generated.csv
	$(COMPOSE) cp app:/tmp/generated.csv ./products-$(ROWS).csv
	@echo "Written to ./products-$(ROWS).csv"

loadtest: ## Run a k6 load test: make loadtest SCRIPT=catalog|checkout
	$(COMPOSE) --profile tools run --rm k6 run /scripts/$(SCRIPT).js

dev: ## Start the development stack (live code mount, Vite hot reload, Mailpit)
	$(DEV_COMPOSE) up -d --build --wait
	@echo ""
	@echo "  Store:     http://localhost:$(APP_PORT)"
	@echo "  Mailpit:   http://localhost:8025"
	@echo ""

dev-down: ## Stop the development stack
	$(DEV_COMPOSE) down

dev-fresh: ## Re-run all migrations and seeders in the development stack
	$(DEV_COMPOSE) exec app php artisan migrate:fresh --seed
