<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    public static function findHerniaId(): string
    {
        return Department::query()->where('name', 'Hernia')->firstOrFail()->id;
    }

    public static function findPrivateScanId(): string
    {
        return Department::query()->where('name', 'Privatescan')->firstOrFail()->id;
    }
}
