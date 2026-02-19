.PHONY: help build up down install-hooks test lint static refactor quality build-prod push deploy prod-up prod-down

PHPQA = docker run --rm -v $(shell pwd):/project -w /project jakzal/phpqa:php8.4
PHPQA_IMAGE = phpqa-custom

help: ## Display this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

build: ## Build docker images
	docker compose build --no-cache

up: ## Start the application
	docker compose up -d

down: ## Stop the application
	docker compose down

install: ## Install dependencies (Composer & NPM)
	docker compose exec app composer install
	docker run --rm -v $(shell pwd):/app -w /app node:22-alpine npm install

install-hooks: ## Install git pre-commit hook
	@sh scripts/install-hooks.sh

build-assets: ## Build assets for production
	docker run --rm -v $(shell pwd):/app -w /app node:22-alpine npm run build

dev: ## Start Vite dev server (via Traefik HTTPS)
	docker compose --profile dev up vite -d

dev-stop: ## Stop Vite dev server
	docker compose --profile dev stop vite

clean: ## Clear Laravel caches
	docker compose exec app php artisan config:clear
	docker compose exec app php artisan route:clear
	docker compose exec app php artisan view:clear
	docker compose exec app php artisan cache:clear

test: ## Run PHPUnit tests
	docker compose exec app php artisan test

lint: ## Fix code style (PSR-12 via phpcbf)
	$(PHPQA) phpcbf --standard=phpcs.xml || true

static: ## Run PHPStan static analysis
	$(PHPQA) phpstan analyse -c phpstan.neon --memory-limit=2G

refactor: ## Run Rector automated refactoring
	docker run --rm -v $(PWD):/project -w /project $(PHPQA_IMAGE) \
		rector process --config rector.php

test-js: ## Run Vitest (Vue component tests)
	docker run --rm -v $(shell pwd):/app -w /app node:22-alpine npx vitest run

quality: lint static refactor test test-js ## Run all quality checks

# =============================================================================
# Production
# =============================================================================

HARBOR_REGISTRY = harbor.wowplanet.fr
IMAGE_NAME = wowplanet/app

build-prod: ## Build production Docker image
	docker build --target prod \
	             -t $(HARBOR_REGISTRY)/$(IMAGE_NAME):latest .

push: ## Push production image to Harbor
	docker push $(HARBOR_REGISTRY)/$(IMAGE_NAME):latest

deploy: build-prod push ## Build + push to Harbor

prod-up: ## Start production stack
	docker compose -f docker-compose.prod.yml up -d

prod-down: ## Stop production stack
	docker compose -f docker-compose.prod.yml down
