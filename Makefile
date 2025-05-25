.PHONY: prod-up
prod-up:
	docker compose -f compose.prod.yaml up -d

.PHONY: prod-down
prod-down:
	docker compose -f compose.prod.yaml down


.PHONY: dev-up
dev-up:
	docker compose -f compose.dev.yaml up -d

.PHONY: dev-down
dev-down:
	docker compose -f compose.dev.yaml down
