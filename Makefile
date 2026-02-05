SAIL = ./vendor/bin/sail

build:
	docker-compose build --no-cache

start:
	./start-dev.sh

stop:
	docker-compose stop

restart:
	make stop
	make start

cli:
	$(SAIL) shell

qa-fix:
	./vendor/bin/duster fix

reset:
	./reset_base.sh && $(SAIL) artisan planning:create-test-data

test:
	$(SAIL) artisan config:clear && $(SAIL) artisan test --parallel --colors=always
