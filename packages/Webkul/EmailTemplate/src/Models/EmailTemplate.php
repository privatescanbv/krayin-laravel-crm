<?php

namespace Webkul\EmailTemplate\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Webkul\EmailTemplate\Contracts\EmailTemplate as EmailTemplateContract;

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
        return \Webkul\EmailTemplate\Database\Factories\EmailTemplateFactory::new();
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
}
