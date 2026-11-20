<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class MetaWebhookMessage extends Model
{
    protected $fillable = [
        'message_id',
        'phone_number_id',
        'sender',
        'type',
        'body',
        'payload',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }
};
