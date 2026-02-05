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

