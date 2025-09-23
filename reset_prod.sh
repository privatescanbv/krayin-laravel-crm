#!/bin/bash

php artisan migrate:fresh --seed &&
php artisan import:users &&
php artisan import:persons --limit=10000 &&
php artisan import:leads --limit=10000 &&
php artisan import:email-attachment-files


