<?php

use App\Enums\EmailTemplateCode;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Database\Migrations\Migration;
use Webkul\EmailTemplate\Models\EmailTemplate;

/**
 * Deploys only run migrations, so add the 2FA template from EmailTemplateSeeder here.
 */
return new class extends Migration
{
    public function up(): void
    {
        $code = EmailTemplateCode::TWO_FACTOR_CODE->value;

        $template = collect(EmailTemplateSeeder::templates())->firstWhere('code', $code);

        EmailTemplate::firstOrCreate(['code' => $code], $template);
    }

    public function down(): void
    {
        EmailTemplate::query()->where('code', EmailTemplateCode::TWO_FACTOR_CODE->value)->delete();
    }
};
