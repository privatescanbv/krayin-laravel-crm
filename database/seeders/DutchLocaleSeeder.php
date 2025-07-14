<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Webkul\Core\Models\CoreConfig;

class DutchLocaleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Set the default locale to Dutch
        $this->setDefaultLocale();

        // Clear configuration cache to ensure changes take effect
        $this->command->info('Dutch locale configuration has been set up successfully!');
    }

    /**
     * Set the default locale to Dutch
     */
    private function setDefaultLocale(): void
    {
        $config = CoreConfig::where('code', 'general.general.locale_settings.locale')->first();

        if (! $config) {
            CoreConfig::create([
                'code'  => 'general.general.locale_settings.locale',
                'value' => 'nl',
            ]);

            $this->command->info('Created Dutch locale configuration.');
        } else {
            if ($config->value !== 'nl') {
                $config->update(['value' => 'nl']);
                $this->command->info('Updated locale configuration to Dutch.');
            } else {
                $this->command->info('Dutch locale is already configured.');
            }
        }
    }
}
