<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportRun extends Model
{
    use HasFactory;

    protected $table = 'import_runs';

    protected $fillable = [
        'started_at',
        'completed_at',
        'status',
        'import_type',
        'records_processed',
        'records_imported',
        'records_skipped',
        'records_errored',
    ];

    protected $casts = [
        'started_at'         => 'datetime',
        'completed_at'       => 'datetime',
        'records_processed'  => 'integer',
        'records_imported'   => 'integer',
        'records_skipped'    => 'integer',
        'records_errored'    => 'integer',
    ];

    public function importLogs()
    {
        return $this->hasMany(ImportLog::class);
    }
}
