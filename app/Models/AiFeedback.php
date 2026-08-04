<?php

namespace App\Models;

use Database\Factories\AiFeedbackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Webkul\User\Models\User;

/**
 * @mixin IdeHelperAiFeedback
 */
class AiFeedback extends Model
{
    /** @use HasFactory<AiFeedbackFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'ai_feedback';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'user_id',
        'feedback',
        'is_active',
        'included_in_generation_at',
    ];

    protected $casts = [
        'is_active'                 => 'boolean',
        'included_in_generation_at' => 'datetime',
    ];

    protected static function newFactory(): AiFeedbackFactory
    {
        return AiFeedbackFactory::new();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
