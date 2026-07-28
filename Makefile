up:
	docker compose up -d

down:
	docker compose down

shell:
	docker compose exec app bash

test:
	docker compose exec app php artisan test

migrate:
	docker compose exec app php artisan migrate

fresh:
	docker compose exec app php artisan migrate:fresh

logs:
	docker compose logs -f