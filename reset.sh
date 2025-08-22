#!/bin/bash

sail artisan migrate:fresh --seed && sail artisan import:persons --limit=1500 && sail artisan import:leads --limit=50

