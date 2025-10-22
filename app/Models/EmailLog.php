<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    use HasAuditTrail, HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'email_logs';

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'metadata'     => 'array',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'sync_type',
        'started_at',
        'completed_at',
        'processed_count',
        'error_count',
        'error_message',
        'metadata',
    ];

    /**
     * Check if the sync was successful
     */
    public function isSuccessful(): bool
    {
        return $this->completed_at !== null && $this->error_count === 0;
    }

    /**
     * Check if the sync failed
     */
    public function isFailed(): bool
    {
        return $this->completed_at !== null && $this->error_count > 0;
    }

    /**
     * Check if the sync is still running
     */
    public function isRunning(): bool
    {
        return $this->completed_at === null;
    }
}
