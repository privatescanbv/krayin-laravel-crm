<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Contact\Models\Person;

class OrderPersonConfirmation extends Model
{
    protected $table = 'order_person_confirmations';

    protected $fillable = [
        'order_id',
        'person_id',
        'confirmation_letter_content',
        'email_sent_at',
    ];

    protected $casts = [
        'email_sent_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function isLetterSaved(): bool
    {
        return ! empty($this->confirmation_letter_content);
    }

    public function isEmailSent(): bool
    {
        return $this->email_sent_at !== null;
    }
}
