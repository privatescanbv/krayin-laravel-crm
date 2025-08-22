# 🚨 Database Migratie Vereist

## Probleem
Tests falen met error: `table leads has no column named combine_order`

## Oplossing
De volgende migratie moet worden gedraaid:

```bash
php artisan migrate --path=packages/Webkul/Lead/src/Database/Migrations/2025_01_15_000006_modify_leads_table_remove_title_add_combine_order.php
```

## Wat doet deze migratie:
1. **Verwijdert** `title` kolom uit `leads` tabel
2. **Voegt toe** `combine_order` boolean kolom (default: true)

## Na migratie:
- ✅ Tests zullen slagen
- ✅ Lead forms werken met combine_order veld
- ✅ API responses bevatten combine_order
- ✅ Title veld is volledig verwijderd

## Verificatie:
```sql
DESCRIBE leads; -- Moet combine_order tonen, geen title
```