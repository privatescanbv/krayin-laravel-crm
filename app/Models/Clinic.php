<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Clinic extends Model
{
    use HasFactory;

    protected $table = 'clinics';

    protected $fillable = [
        'name',
        'emails',
        'phones',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'emails' => 'array',
        'phones' => 'array',
    ];
}

