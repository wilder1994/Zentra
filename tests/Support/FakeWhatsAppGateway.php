<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Meta\OutboundMessage;
use App\Services\Meta\WhatsAppGateway;

final class FakeWhatsAppGateway implements WhatsAppGateway
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /** @var list<OutboundMessage> */
    public array $sent = [];

    public function send(OutboundMessage $message): void
    {
        $this->sent[] = $message;
    }

    public function download(string $mediaId): ?array
    {
        return ['contents' => (string) base64_decode(self::PNG, true), 'mime' => 'image/png'];
    }

    public function last(): OutboundMessage
    {
        return $this->sent[array_key_last($this->sent)];
    }

    /** @return list<string> */
    public function rowIds(): array
    {
        return array_map(static fn (array $row): string => $row['id'], $this->last()->options['rows'] ?? []);
    }

    /** @return list<string> */
    public function rowTitles(): array
    {
        return array_map(static fn (array $row): string => $row['title'], $this->last()->options['rows'] ?? []);
    }
}
