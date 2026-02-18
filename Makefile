.PHONY: help build up down test static refactor format quality build-prod push deploy prod-up prod-down

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
	./vendor/bin/sail php unit

static: ## Run PHPStan static analysis
	docker run --rm -v $(shell pwd):/project jakzal/phpqa:php8.4 phpstan analyse --memory-limit=2G

refactor: ## Run Rector for automated refactoring
	docker run --rm -v $(shell pwd):/project jakzal/phpqa:php8.4 rector process app --dry-run

format: ## Run PHP CS Fixer to format code
	docker run --rm -v $(shell pwd):/project jakzal/phpqa:php8.4 php-cs-fixer fix --dry-run --diff

quality: static refactor format test ## Run all quality checks

# =============================================================================
# Production
# =============================================================================

HARBOR_REGISTRY = harbor.wowplanet.fr
IMAGE_NAME = wowplanet/app
IMAGE_TAG = $(shell git rev-parse --short HEAD 2>/dev/null || echo "latest")

build-prod: ## Build production Docker image
	docker build --target prod \
	             -t $(HARBOR_REGISTRY)/$(IMAGE_NAME):$(IMAGE_TAG) \
	             -t $(HARBOR_REGISTRY)/$(IMAGE_NAME):latest .

push: ## Push production image to Harbor
	docker push $(HARBOR_REGISTRY)/$(IMAGE_NAME):$(IMAGE_TAG)
	docker push $(HARBOR_REGISTRY)/$(IMAGE_NAME):latest

deploy: build-prod push ## Build + push to Harbor

prod-up: ## Start production stack
	docker compose -f docker-compose.prod.yml up -d

prod-down: ## Stop production stack
	docker compose -f docker-compose.prod.yml down
