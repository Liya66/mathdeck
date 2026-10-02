IMAGE := mathdeck-test
RUN   := docker run --rm -v "$(PWD)":/app -w /app $(IMAGE)

.PHONY: image install test coverage stan deptrac mutation check
.PHONY: db-up db-down test-db test-integration up down logs postman test-js css migrate project seed-demo account
.PHONY: local-test local-coverage local-stan

## Containerised (no local PHP needed) -----------------------------------------

image:
	docker build -q -f docker/test.Dockerfile -t $(IMAGE) . >/dev/null

install: image
	$(RUN) composer install --no-interaction --no-progress

test: image
	$(RUN) vendor/bin/phpunit

coverage: image
	$(RUN) php -d pcov.enabled=1 vendor/bin/phpunit --coverage-text

stan: image
	$(RUN) vendor/bin/phpstan analyse --no-progress

# The architecture rule: Engine may not depend on anything outside itself.
deptrac: image
	$(RUN) vendor/bin/deptrac analyse --no-progress

# Mutation testing is deliberately not in `check`: it re-runs the suite once per
# mutant, so it belongs in CI and in a deliberate local run, not in the loop you
# sit through between edits.
mutation: image
	$(RUN) php -d pcov.enabled=1 vendor/bin/infection --threads=max --no-progress

check: stan deptrac test test-js

## Integration suite (needs MySQL) --------------------------------------------

TEST_DSN := mysql:host=mysql;dbname=mathdeck_test;charset=utf8mb4

db-up:
	docker compose up -d --wait mysql
	docker compose run --rm php php bin/migrate

db-down:
	docker compose down -v

# Integration tests get their own database. They truncate tables in setUp, and
# pointing them at the development database meant a test run silently wiped the
# matches and reset the demo accounts — which is exactly how this was found.
test-db:
	docker compose up -d --wait mysql
	docker compose exec -T mysql mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS mathdeck_test; \
		GRANT ALL ON mathdeck_test.* TO 'mathdeck'@'%'; FLUSH PRIVILEGES;"
	docker compose run --rm -e 'MATCHDECK_DSN=$(TEST_DSN)' php php bin/migrate

test-integration: test-db
	docker compose build --quiet php
	docker compose run --rm -e 'MATCHDECK_DSN=$(TEST_DSN)' php vendor/bin/phpunit

## Local PHP ------------------------------------------------------------------

local-test:
	vendor/bin/phpunit

local-coverage:
	XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text

local-stan:
	vendor/bin/phpstan analyse

## Serving --------------------------------------------------------------------

up: db-up
	docker compose up -d --wait app nginx worker
	@echo "API on http://localhost:8080"

down:
	docker compose down -v

logs:
	docker compose logs -f app nginx

# The collection is generated from openapi.yaml, never hand-maintained: a second
# hand-written artifact is a second thing to get out of date.
postman:
	docker run --rm -v "$(PWD)":/spec -w /spec node:20-alpine \
		npx -y openapi-to-postmanv2 -s openapi.yaml -o postman_collection.json -p

## Browser client -------------------------------------------------------------

test-js:
	docker run --rm -v "$(PWD)":/app -w /app node:20-alpine node --test tests/js/

# Generated stylesheet is committed, so the page has no CDN dependency at runtime.
css:
	docker run --rm -v "$(PWD)":/app -w /app node:20-alpine \
		npx -y tailwindcss@3 -i public/app/tailwind.css -o public/app/app.css --minify

migrate:
	docker compose run --rm php php bin/migrate

project:
	docker compose run --rm php php bin/project-analytics

# bin/create-account <playerId> "<Name>" [student|teacher] [passcode]
account:
	docker compose run --rm php php bin/create-account $(ARGS)

# Local accounts with known passcodes. Refuses to run without the opt-in flag.
seed-demo:
	docker compose run --rm -e MATCHDECK_ALLOW_DEMO_SEED=1 php php bin/seed-demo
