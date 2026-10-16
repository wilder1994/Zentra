<?php

declare(strict_types=1);

namespace App\Support\Commercial;

final class CommercialCopyDefaults
{
    /**
     * @return array{
     *   subject: string,
     *   introduction?: string,
     *   highlight?: string,
     *   conclusions?: string,
     *   context?: string,
     *   solution?: string,
     *   why?: string,
     *   next_steps?: string,
     *   closing?: string
     * }
     */
    public static function for(string $kind): array
    {
        $key = match ($kind) {
            'quote' => 'quote',
            'deck' => 'deck',
            default => 'brochure',
        };
        $block = config("commercial.defaults.{$key}", []);

        if ($key === 'deck') {
            return [
                'subject' => (string) ($block['subject'] ?? ''),
                'context' => (string) ($block['context'] ?? ''),
                'solution' => (string) ($block['solution'] ?? ''),
                'why' => (string) ($block['why'] ?? ''),
                'next_steps' => (string) ($block['next_steps'] ?? ''),
                'closing' => (string) ($block['closing'] ?? ''),
            ];
        }

        return [
            'subject' => (string) ($block['subject'] ?? ''),
            'introduction' => (string) ($block['introduction'] ?? ''),
            'highlight' => (string) ($block['highlight'] ?? ''),
            'conclusions' => (string) ($block['conclusions'] ?? ''),
        ];
    }

    public static function city(): string
    {
        return (string) config('commercial.defaults.city', 'Cali');
    }
}
