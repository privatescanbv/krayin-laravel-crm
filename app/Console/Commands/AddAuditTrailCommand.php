<?php

namespace App\Console\Commands;

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AddAuditTrailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:add-trail {table : The table name to add audit trail to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add audit trail columns (created_by, updated_by) to a specified table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tableName = $this->argument('table');

        if (! Schema::hasTable($tableName)) {
            $this->error("Table '{$tableName}' does not exist.");

            return 1;
        }

        // Check if audit trail columns already exist
        if (Schema::hasColumn($tableName, 'created_by') || Schema::hasColumn($tableName, 'updated_by')) {
            $this->error("Table '{$tableName}' already has audit trail columns.");

            return 1;
        }

        try {
            Schema::table($tableName, function (Blueprint $table) {
                AuditTrailMigrationHelper::addAuditTrailColumns($table);
            });

            $this->info("Successfully added audit trail columns to '{$tableName}' table.");

            // Generate migration file
            $this->generateMigrationFile($tableName);

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to add audit trail columns: '.$e->getMessage());

            return 1;
        }
    }

    /**
     * Generate a migration file for the audit trail addition
     */
    private function generateMigrationFile(string $tableName): void
    {
        $timestamp = date('Y_m_d_His');
        $className = 'AddAuditTrailTo'.Str::studly($tableName).'Table';
        $fileName = $timestamp.'_add_audit_trail_to_'.$tableName.'_table.php';
        $filePath = database_path('migrations/'.$fileName);

        $stub = <<<PHP
<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('{$tableName}', function (Blueprint \$table) {
            AuditTrailMigrationHelper::addAuditTrailColumns(\$table);
        });
    }

    public function down(): void
    {
        Schema::table('{$tableName}', function (Blueprint \$table) {
            AuditTrailMigrationHelper::dropAuditTrailColumns(\$table);
        });
    }
};
PHP;

        file_put_contents($filePath, $stub);
        $this->info("Generated migration file: {$fileName}");
    }
}
