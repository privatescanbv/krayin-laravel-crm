#!/bin/bash

./vendor/bin/sail artisan migrate:fresh --seed &&
./vendor/bin/sail artisan import:users &&
./vendor/bin/sail artisan import:persons --person-ids=40496b1a-f2c5-07c1-20b5-67ee969bce82 &&
./vendor/bin/sail artisan import:leads --lead-ids=d6cf3336-cabc-04e1-3f3e-67ee9271be99 &&
./vendor/bin/sail artisan import:email-attachment-files


