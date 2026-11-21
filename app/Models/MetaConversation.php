<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MetaConversationState;
use Illuminate\Database\Eloquent\Model;

final class MetaConversation extends Model
{
    protected $fillable = [
        'wa_id',
        'open_key',
        'profile_name',
        'state',
        'installation_id',
        'report_type_id',
        'body',
        'media',
        'candidates',
        'reporter_role',
        'is_anonymous',
        'last_message_at',
        'report_id',
    ];

    protected function casts(): array
    {
        return [
            'state' => MetaConversationState::class,
            'media' => 'array',
            'candidates' => 'array',
            'is_anonymous' => 'boolean',
            'last_message_at' => 'datetime',
        ];
    }

    /** @return list<array{id: string, mime: string}> */
    public function mediaItems(): array
    {
        $items = [];
        foreach ($this->media ?? [] as $item) {
            if (is_array($item) && isset($item['id'], $item['mime'])) {
                $items[] = ['id' => (string) $item['id'], 'mime' => (string) $item['mime']];
            }
        }

        return $items;
    }

    public function close(MetaConversationState $state): void
    {
        $this->forceFill(['state' => $state, 'open_key' => null])->save();
    }
}
