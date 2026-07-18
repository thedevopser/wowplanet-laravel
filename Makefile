.PHONY: help build up down install-hooks test lint lint-check static refactor quality coverage coverage-php coverage-js build-prod build-prod-ssr build-prod-all push push-ssr push-all deploy prod-up prod-down worker worker-stop

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

worker: ## Start queue worker (dev)
	docker compose --profile dev up worker -d

worker-stop: ## Stop queue worker (dev)
	docker compose --profile dev stop worker

clean: ## Clear Laravel caches
	docker compose exec app php artisan config:clear
	docker compose exec app php artisan route:clear
	docker compose exec app php artisan view:clear
	docker compose exec app php artisan cache:clear

test: ## Run Pest tests
	docker compose exec app vendor/bin/pest

lint: ## Fix code style (Laravel Pint)
	docker compose exec app vendor/bin/pint

lint-check: ## Check code style without fixing (Laravel Pint)
	docker compose exec app vendor/bin/pint --test

static: ## Run Larastan static analysis
	docker compose exec app vendor/bin/phpstan analyse --memory-limit=2G

refactor: ## Run Rector automated refactoring
	docker compose exec app vendor/bin/rector process

test-js: ## Run Vitest (Vue component tests)
	docker run --rm -v $(shell pwd):/app -w /app node:22-alpine npx vitest run

quality: lint static refactor test test-js ## Run all quality checks

coverage-php: ## Run Pest with coverage (min 80%)
	docker compose exec app vendor/bin/pest --coverage --min=80

coverage-js: ## Run Vitest with coverage
	docker run --rm -v $(shell pwd):/app -w /app node:22-alpine npx vitest run --coverage

coverage: ## Coverage PHP + JS avec rapports HTML
	@echo ""
	@echo "\033[1;36m══════════════════════════════════════════════════════════════\033[0m"
	@echo "\033[1;36m  PHP Coverage (Pest + pcov) — min 80%\033[0m"
	@echo "\033[1;36m══════════════════════════════════════════════════════════════\033[0m"
	@echo ""
	@docker compose exec app vendor/bin/pest --coverage --min=80 --coverage-html=coverage/php
	@echo ""
	@echo "\033[1;33m══════════════════════════════════════════════════════════════\033[0m"
	@echo "\033[1;33m  JavaScript Coverage (Vitest + v8) — min 80%\033[0m"
	@echo "\033[1;33m══════════════════════════════════════════════════════════════\033[0m"
	@echo ""
	@docker run --rm -v $(shell pwd):/app -w /app node:22-alpine npx vitest run --coverage
	@echo ""
	@echo "\033[1;32m══════════════════════════════════════════════════════════════\033[0m"
	@echo "\033[1;32m  Rapports HTML :\033[0m"
	@echo "\033[1;32m    PHP : coverage/php/index.html\033[0m"
	@echo "\033[1;32m    JS  : coverage/js/index.html\033[0m"
	@echo "\033[1;32m══════════════════════════════════════════════════════════════\033[0m"

# =============================================================================
# Production
# =============================================================================

HARBOR_REGISTRY = harbor.wowplanet.fr
IMAGE_NAME = wowplanet/app
SSR_IMAGE_NAME = wowplanet/ssr

# Portainer auto-redeploy (set these in .env.deploy or environment)
PORTAINER_URL ?= https://portainer.wowplanet.fr
PORTAINER_API_KEY ?=
PORTAINER_STACK_ID ?=
PORTAINER_ENDPOINT_ID ?= 1

-include .env.deploy

build-prod: ## Build production Docker image
	docker build --target prod \
	             -t $(HARBOR_REGISTRY)/$(IMAGE_NAME):latest .

build-prod-ssr: ## Build production SSR (Node) Docker image
	docker build --target ssr \
	             -t $(HARBOR_REGISTRY)/$(SSR_IMAGE_NAME):latest .

build-prod-all: build-prod build-prod-ssr ## Build both prod images (app + SSR)

push: ## Push production image to Harbor
	docker push $(HARBOR_REGISTRY)/$(IMAGE_NAME):latest

push-ssr: ## Push SSR image to Harbor
	docker push $(HARBOR_REGISTRY)/$(SSR_IMAGE_NAME):latest

push-all: push push-ssr ## Push both prod images (app + SSR)

redeploy: ## Trigger Portainer stack redeploy (pull new image)
	@if [ -z "$(PORTAINER_API_KEY)" ]; then echo "ERROR: PORTAINER_API_KEY not set. Create .env.deploy or export it."; exit 1; fi
	@if [ -z "$(PORTAINER_STACK_ID)" ]; then echo "ERROR: PORTAINER_STACK_ID not set."; exit 1; fi
	@echo "==> Fetching current stack config from Portainer..."
	@STACK_FILE=$$(curl -sf -H "X-API-Key: $(PORTAINER_API_KEY)" \
		"$(PORTAINER_URL)/api/stacks/$(PORTAINER_STACK_ID)/file" | jq -r '.StackFileContent') && \
	ENV=$$(curl -sf -H "X-API-Key: $(PORTAINER_API_KEY)" \
		"$(PORTAINER_URL)/api/stacks/$(PORTAINER_STACK_ID)" | jq '.Env') && \
	echo "==> Redeploying stack with image pull..." && \
	curl -sf -X PUT \
		"$(PORTAINER_URL)/api/stacks/$(PORTAINER_STACK_ID)?endpointId=$(PORTAINER_ENDPOINT_ID)" \
		-H "X-API-Key: $(PORTAINER_API_KEY)" \
		-H "Content-Type: application/json" \
		-d "{\"stackFileContent\": $$(echo "$$STACK_FILE" | jq -Rs .), \"env\": $$ENV, \"pullImage\": true, \"prune\": true}" > /dev/null && \
	echo "==> Stack redeployed successfully!" || \
	(echo "ERROR: Redeploy failed. Check Portainer URL/credentials."; exit 1)

deploy: build-prod-all push-all redeploy ## Build, push (app + SSR), and redeploy via Portainer

prod-up: ## Start production stack
	docker compose -f docker-compose.prod.yml up -d

prod-down: ## Stop production stack
	docker compose -f docker-compose.prod.yml down
