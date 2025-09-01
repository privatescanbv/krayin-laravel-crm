#!/bin/bash

./vendor/bin/sail artisan migrate:fresh --seed &&
./vendor/bin/sail artisan import:users &&
./vendor/bin/sail artisan import:persons --limit=1500 &&
./vendor/bin/sail artisan import:leads --limit=50


