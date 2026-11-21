<?php

declare(strict_types=1);

namespace App\Support\Meta;

/** Topes de texto que impone la API de WhatsApp en botones y listas. */
final class WhatsAppLimits
{
    public const BUTTON_TITLE = 20;

    public const ROW_TITLE = 24;

    public const ROW_DESCRIPTION = 72;

    public const LIST_ROWS = 10;

    public const BUTTONS = 3;

    public const BODY = 1024;

    public static function clip(string $text, int $max): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return mb_strlen($text) <= $max ? $text : rtrim(mb_substr($text, 0, $max - 1)).'…';
    }

    /**
     * Parte un nombre largo en título y resto, cortando entre palabras.
     *
     * @return array{0: string, 1: string}
     */
    public static function split(string $text, int $max): array
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($text) <= $max) {
            return [$text, ''];
        }

        $head = mb_substr($text, 0, $max + 1);
        $cut = mb_strrpos($head, ' ');
        $length = ($cut === false || $cut < 1) ? $max : $cut;

        return [
            rtrim(mb_substr($text, 0, $length)),
            ltrim(mb_substr($text, $length)),
        ];
    }
}
