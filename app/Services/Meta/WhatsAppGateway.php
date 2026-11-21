<?php

declare(strict_types=1);

namespace App\Services\Meta;

interface WhatsAppGateway
{
    public function send(OutboundMessage $message): void;

    /** @return array{contents: string, mime: string}|null */
    public function download(string $mediaId): ?array;
}
