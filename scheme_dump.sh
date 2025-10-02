#!/bin/bash
# Laravel scheme dump migrations, tests should be done with mysql db
# --prune -> does not work with sqlite
./vendor/bin/sail artisan schema:dump --database=mysql
