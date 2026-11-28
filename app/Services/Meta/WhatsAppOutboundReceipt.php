<?php

declare(strict_types=1);

namespace App\Services\Meta;

use Illuminate\Support\Facades\Cache;

/** El id que devuelve Graph, para reenviar en texto si el acuse es fallido. */
final class WhatsAppOutboundReceipt
{
    public static function put(string $id, string $to, string $text): void
    {
        if ($id === '' || $to === '' || $text === '') {
            return;
        }

        Cache::put(self::key($id), ['to' => $to, 'text' => $text], now()->addHours(6));
    }

    /** @return array{to: string, text: string}|null */
    public static function pull(string $id): ?array
    {
        $value = Cache::pull(self::key($id));
        if (! is_array($value)) {
            return null;
        }
        $to = trim((string) ($value['to'] ?? ''));
        $text = trim((string) ($value['text'] ?? ''));
        if ($to === '' || $text === '') {
            return null;
        }

        return ['to' => $to, 'text' => $text];
    }

    private static function key(string $id): string
    {
        return 'meta-wa:'.$id;
    }
}
