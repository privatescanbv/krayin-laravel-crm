<?php

use Illuminate\Database\Migrations\Migration;
use Webkul\Core\Models\CoreConfig;

return new class extends Migration
{
    public function up(): void
    {
        $config = CoreConfig::where('code', 'general.general.locale_settings.locale')->first();

        if (! $config) {
            CoreConfig::create([
                'code'  => 'general.general.locale_settings.locale',
                'value' => 'nl',
            ]);
        } else {
            $config->update(['value' => 'nl']);
        }
    }

    public function down(): void
    {
        $config = CoreConfig::where('code', 'general.general.locale_settings.locale')->first();
        if ($config) {
            // Zet terug naar Engels (of verwijder als je wilt)
            $config->update(['value' => 'en']);
        }
    }
};
