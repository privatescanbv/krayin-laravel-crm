<?php

use App\Enums\EmailTemplateLanguage;
use App\Enums\EmailTemplateType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $connection = Schema::getConnection()->getDriverName();
        $supportsAfter = $connection !== 'sqlite';

        // Use string instead of enum for SQLite compatibility
        if (! Schema::hasColumn('email_templates', 'type')) {
            Schema::table('email_templates', function (Blueprint $table) use ($supportsAfter) {
                if ($supportsAfter) {
                    $table->string('type')->default(EmailTemplateType::ALGEMEEN->value)->after('name');
                } else {
                    $table->string('type')->default(EmailTemplateType::ALGEMEEN->value);
                }
            });
        }

        if (! Schema::hasColumn('email_templates', 'code')) {
            Schema::table('email_templates', function (Blueprint $table) use ($supportsAfter) {
                if ($supportsAfter) {
                    $table->string('code')->nullable()->unique()->after('name');
                } else {
                    $table->string('code')->nullable()->unique();
                }
            });
        }

        if (! Schema::hasColumn('email_templates', 'language')) {
            Schema::table('email_templates', function (Blueprint $table) use ($supportsAfter) {
                if ($supportsAfter) {
                    $table->string('language')->default(EmailTemplateLanguage::NEDERLANDS->value)->after('type');
                } else {
                    $table->string('language')->default(EmailTemplateLanguage::NEDERLANDS->value);
                }
            });
        }

        if (! Schema::hasColumn('email_templates', 'departments')) {
            Schema::table('email_templates', function (Blueprint $table) use ($supportsAfter) {
                if ($supportsAfter) {
                    $table->json('departments')->nullable()->after('language');
                } else {
                    $table->json('departments')->nullable();
                }
            });
        }
    }

    public function down()
    {
        Schema::table('email_templates', function (Blueprint $table) {
            if (Schema::hasColumn('email_templates', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('email_templates', 'code')) {
                $table->dropColumn('code');
            }
            if (Schema::hasColumn('email_templates', 'language')) {
                $table->dropColumn('language');
            }
            if (Schema::hasColumn('email_templates', 'departments')) {
                $table->dropColumn('departments');
            }
        });
    }
};
