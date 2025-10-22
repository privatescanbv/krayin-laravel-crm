<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportLog extends Model
{
    use HasAuditTrail, HasFactory;

    protected $table = 'import_logs';

    protected $fillable = [
        'import_run_id',
        'level',
        'message',
        'context',
        'record_id',
    ];

    protected $casts = [
        'context'        => 'array',
        'import_run_id'  => 'integer',
    ];

    public function importRun()
    {
        return $this->belongsTo(ImportRun::class);
    }
}
