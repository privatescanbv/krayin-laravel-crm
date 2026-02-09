<?php

namespace Webkul\EmailTemplate\Models;

use App\Enums\EmailTemplateCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Webkul\EmailTemplate\Contracts\EmailTemplate as EmailTemplateContract;
use Webkul\EmailTemplate\Database\Factories\EmailTemplateFactory;

class EmailTemplate extends Model implements EmailTemplateContract
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return EmailTemplateFactory::new();
    }

    protected $fillable = [
        'name',
        'code',
        'type',
        'language',
        'departments',
        'subject',
        'content',
    ];

    protected $casts = [
        'type' => 'string',
        'language' => 'string',
        'departments' => 'array',
    ];

    /**
     * Scope: filter email template by code.
     */
    public function scopeByCodeEnum($query, EmailTemplateCode $code)
    {
        return self::scopeByCode($query, $code->value);
    }

    /**
     * Scope: filter email template by code.
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }
}
