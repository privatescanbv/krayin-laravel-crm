#!/bin/bash

echo "Do you want to import a single lead or multiple leads?"
echo "1) Single lead (current setup)"
echo "2) Multiple leads (limit 500 leads)"
read -p "Enter your choice (1 or 2): " choice

./reset_base.sh dev &&
./vendor/bin/sail artisan planning:create-test-data

if [ "$choice" = "1" ]; then
    echo "Importing single lead setup..."
#    ./vendor/bin/sail artisan import:persons --person-ids=40496b1a-f2c5-07c1-20b5-67ee969bce82 &&
#    ./vendor/bin/sail artisan import:leads --lead-ids=d6cf3336-cabc-04e1-3f3e-67ee9271be99 &&
    ./vendor/bin/sail artisan import:orders --import-leads --order-ids=202500625,202500001 &&
#    ./vendor/bin/sail artisan import:email-attachment-files

elif [ "$choice" = "2" ]; then
    echo "Importing multiple leads setup..."
#    ./vendor/bin/sail artisan import:leads --import-persons --limit=2000 &&
    ./vendor/bin/sail artisan import:orders --import-leads --order-ids=202500625,202500001
#    ./vendor/bin/sail artisan import:orders --import-leads --limit=2
#    ./vendor/bin/sail artisan import:email-attachment-files

else
    echo "Invalid choice. Please run the script again and choose 1 or 2."
    exit 1
fi
