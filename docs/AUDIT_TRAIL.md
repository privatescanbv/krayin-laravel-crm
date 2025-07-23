# Audit Trail Systeem

Dit document beschrijft hoe het audit trail systeem werkt en hoe het te gebruiken.

## Overzicht

Het audit trail systeem zorgt ervoor dat elk model automatisch de volgende velden bijhoudt:
- `created_by` - ID van de gebruiker die het record heeft aangemaakt
- `updated_by` - ID van de gebruiker die het record het laatst heeft bijgewerkt
- `created_at` - Tijdstip waarop het record is aangemaakt (standaard Laravel)
- `updated_at` - Tijdstip waarop het record het laatst is bijgewerkt (standaard Laravel)

## Gebruik voor nieuwe modellen

### Optie 1: Gebruik BaseModel (Aanbevolen)

Voor nieuwe modellen, extend van `BaseModel` in plaats van `Model`:

```php
<?php

namespace App\Models;

class MyModel extends BaseModel
{
    protected $fillable = [
        'name',
        'description',
        // created_by en updated_by worden automatisch toegevoegd
    ];
}
```

### Optie 2: Gebruik HasAuditTrail trait

Als je niet van BaseModel kunt extenden, gebruik dan de trait:

```php
<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Model;

class MyModel extends Model
{
    use HasAuditTrail;

    protected $fillable = [
        'name',
        'description',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];
}
```

## Database migrations

### Voor nieuwe tabellen

Gebruik de `AuditTrailMigrationHelper` in je migrations:

```php
<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('my_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
            
            // Voeg audit trail velden toe
            AuditTrailMigrationHelper::addAuditTrailColumns($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('my_table');
    }
};
```

### Voor bestaande tabellen

Gebruik de `AuditTrailMigrationHelper` om audit trail velden toe te voegen:

```php
<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('existing_table', function (Blueprint $table) {
            AuditTrailMigrationHelper::addAuditTrailColumns($table);
        });
    }

    public function down(): void
    {
        Schema::table('existing_table', function (Blueprint $table) {
            AuditTrailMigrationHelper::dropAuditTrailColumns($table);
        });
    }
};
```

### Artisan commando

Je kunt ook het Artisan commando gebruiken om snel audit trail velden toe te voegen:

```bash
php artisan audit:add-trail my_table_name
```

Dit commando:
1. Controleert of de tabel bestaat
2. Controleert of audit trail velden al bestaan
3. Voegt de audit trail velden toe
4. Genereert automatisch een migration bestand

## Relaties gebruiken

Elk model met audit trail heeft automatisch relaties naar de gebruikers:

```php
// Krijg de gebruiker die het record heeft aangemaakt
$creator = $model->creator;

// Krijg de gebruiker die het record het laatst heeft bijgewerkt
$updater = $model->updater;
```

## Automatische werking

Het systeem werkt volledig automatisch:

- Bij het aanmaken van een record worden `created_by` en `updated_by` ingesteld op de huidige gebruiker
- Bij het bijwerken van een record wordt `updated_by` bijgewerkt naar de huidige gebruiker
- Als er geen gebruiker is ingelogd, blijven de velden `null`

## Bestaande modellen updaten

Voor bestaande modellen die al audit trail velden hebben:

1. Voeg de trait toe of extend van BaseModel
2. Voeg de velden toe aan `$fillable`
3. Voeg de casts toe
4. Zorg dat de database tabel de juiste velden heeft

## Voorbeeld gebruik

```php
// Maak een nieuw record aan (automatisch audit trail)
$address = Address::create([
    'street' => 'Hoofdstraat',
    'house_number' => '123',
    'city' => 'Amsterdam',
]);

// created_by en updated_by zijn nu automatisch ingesteld

// Update het record (automatisch updated_by bijwerken)
$address->update(['city' => 'Rotterdam']);

// Toegang tot audit informatie
echo "Aangemaakt door: " . $address->creator->name;
echo "Laatst bijgewerkt door: " . $address->updater->name;
echo "Aangemaakt op: " . $address->created_at;
echo "Laatst bijgewerkt op: " . $address->updated_at;
```

## Componenten

Het audit trail systeem bestaat uit:

1. **HasAuditTrail trait** (`app/Traits/HasAuditTrail.php`) - Basis functionaliteit
2. **BaseModel** (`app/Models/BaseModel.php`) - Abstract model met audit trail
3. **AuditTrailMigrationHelper** (`app/Helpers/AuditTrailMigrationHelper.php`) - Migration helper
4. **AddAuditTrailCommand** (`app/Console/Commands/AddAuditTrailCommand.php`) - Artisan commando

Dit systeem vereist minimale code en is goed onderhoudbaar door de gecentreerde logica.