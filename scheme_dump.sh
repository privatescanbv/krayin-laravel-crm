#!/bin/bash
# Laravel scheme dump migrations, tests should be done with mysql db

./vendor/bin/sail artisan schema:dump --prune --database=mysql
