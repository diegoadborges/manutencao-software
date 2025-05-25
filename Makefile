.PHONY: prod-up
prod-up:
	docker compose -f compose.prod.yaml up -d

.PHONY: prod-down
prod-down:
	docker compose -f compose.prod.yaml down
