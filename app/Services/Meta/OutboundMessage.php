<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Support\Meta\WhatsAppLimits;

final readonly class OutboundMessage
{
    public const TEXT = 'text';

    public const BUTTONS = 'buttons';

    public const LIST = 'list';

    public const LOCATION_REQUEST = 'location_request';

    /** @param  array<string, mixed>  $options */
    private function __construct(
        public string $kind,
        public string $to,
        public string $body,
        public array $options = [],
    ) {}

    public static function text(string $to, string $body): self
    {
        return new self(self::TEXT, $to, $body);
    }

    /** @param  list<array{id: string, title: string}>  $buttons */
    public static function buttons(string $to, string $body, array $buttons): self
    {
        $buttons = array_slice($buttons, 0, WhatsAppLimits::BUTTONS);

        return new self(self::BUTTONS, $to, $body, [
            'buttons' => array_map(static fn (array $button): array => [
                'id' => $button['id'],
                'title' => WhatsAppLimits::clip($button['title'], WhatsAppLimits::BUTTON_TITLE),
            ], $buttons),
        ]);
    }

    /** @param  list<array{id: string, title: string, description?: string}>  $rows */
    public static function list(string $to, string $body, string $button, array $rows): self
    {
        $rows = array_slice($rows, 0, WhatsAppLimits::LIST_ROWS);

        return new self(self::LIST, $to, $body, [
            'button' => WhatsAppLimits::clip($button, WhatsAppLimits::BUTTON_TITLE),
            'rows' => array_map(static function (array $row): array {
                $description = WhatsAppLimits::clip((string) ($row['description'] ?? ''), WhatsAppLimits::ROW_DESCRIPTION);
                $out = [
                    'id' => $row['id'],
                    'title' => WhatsAppLimits::clip($row['title'], WhatsAppLimits::ROW_TITLE),
                ];
                if ($description !== '') {
                    $out['description'] = $description;
                }

                return $out;
            }, $rows),
        ]);
    }

    public static function locationRequest(string $to, string $body): self
    {
        return new self(self::LOCATION_REQUEST, $to, $body);
    }
}
