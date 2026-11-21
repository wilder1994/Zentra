<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Models\MetaWebhookMessage;

final readonly class MetaInboundMessage
{
    public function __construct(
        public string $from,
        public ?string $name,
        public string $type,
        public ?string $text,
        public ?string $replyId,
        public ?float $latitude,
        public ?float $longitude,
        public ?string $mediaId,
        public ?string $mediaMime,
    ) {}

    public static function fromRow(MetaWebhookMessage $row): self
    {
        $payload = is_array($row->payload) ? $row->payload : [];
        $type = (string) $row->type;

        $replyId = null;
        if ($type === 'interactive') {
            $interactive = is_array($payload['interactive'] ?? null) ? $payload['interactive'] : [];
            $reply = $interactive['button_reply'] ?? $interactive['list_reply'] ?? null;
            $id = is_array($reply) ? trim((string) ($reply['id'] ?? '')) : '';
            $replyId = $id !== '' ? $id : null;
        }

        $location = is_array($payload['location'] ?? null) ? $payload['location'] : [];
        $media = is_array($payload[$type] ?? null) && in_array($type, ['image', 'video', 'audio', 'document', 'sticker'], true)
            ? $payload[$type]
            : [];
        $mediaId = trim((string) ($media['id'] ?? ''));
        $mediaMime = trim((string) ($media['mime_type'] ?? ''));

        return new self(
            from: trim((string) $row->sender),
            name: $row->sender_name,
            type: $type,
            text: $type === 'text' ? $row->body : null,
            replyId: $replyId,
            latitude: isset($location['latitude']) && is_numeric($location['latitude']) ? (float) $location['latitude'] : null,
            longitude: isset($location['longitude']) && is_numeric($location['longitude']) ? (float) $location['longitude'] : null,
            mediaId: $mediaId !== '' ? $mediaId : null,
            mediaMime: $mediaMime !== '' ? $mediaMime : null,
        );
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
