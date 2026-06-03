# BuckleUp WordPress — dev shortcuts.  Run `make help` for the list.
.DEFAULT_GOAL := help
SHELL := /bin/bash

.PHONY: help up down restart logs provision wp shell assets build-assets reset psql ps clean

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

up: ## Start the core stack (db, redis, wordpress, nginx, adminer, mailpit)
	docker compose up -d
	@echo "Site: http://localhost:$${HTTP_PORT:-8080}  |  Mailpit: http://localhost:$${MAILPIT_UI_PORT:-8025}  |  Adminer: http://localhost:$${ADMINER_PORT:-8081}"

down: ## Stop the stack (keep volumes)
	docker compose down

restart: ## Restart all services
	docker compose restart

logs: ## Tail logs (use SVC=wordpress to scope)
	docker compose logs -f $(SVC)

ps: ## Show running services
	docker compose ps

provision: ## Install WP, theme, plugins, roles, users, seed data (idempotent)
	./scripts/provision.sh

wp: ## Run a WP-CLI command, e.g. make wp CMD="plugin list"
	docker compose run --rm -T wpcli wp $(CMD)

shell: ## Shell into the wordpress (php-fpm) container
	docker compose exec wordpress bash

assets: ## Run the theme asset builder in watch mode (Ctrl-C to stop)
	docker compose --profile assets up assets

build-assets: ## One-off production build of theme CSS/JS
	docker compose --profile assets run --rm assets sh -c "npm install && npm run build"

reset: ## DESTROY db + wp-core volumes, then re-provision from scratch
	docker compose down -v
	docker compose up -d
	./scripts/provision.sh

clean: ## Remove containers, networks, and volumes
	docker compose down -v --remove-orphans
