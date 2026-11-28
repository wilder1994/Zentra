<?php

declare(strict_types=1);

namespace App\Services\Meta;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class CloudApiWhatsAppGateway implements WhatsAppGateway
{
    private const HOST = 'https://graph.facebook.com';

    public function send(OutboundMessage $message): void
    {
        $url = sprintf('%s/%s/%s/messages', self::HOST, $this->version(), (string) config('services.meta.phone_number_id'));

        try {
            $response = $this->request()->post($url, $this->payload($message));
        } catch (ConnectionException) {
            Log::error('meta.whatsapp.send_failed', ['reason' => 'connection', 'kind' => $message->kind]);

            return;
        }

        if ($response->failed()) {
            Log::error('meta.whatsapp.send_failed', [
                'status' => $response->status(),
                'kind' => $message->kind,
                'error' => $response->json('error.message'),
            ]);

            return;
        }

        $id = trim((string) $response->json('messages.0.id'));
        Log::info('meta.whatsapp.sent', ['id' => $id, 'kind' => $message->kind]);
        if ($message->fallback !== null) {
            WhatsAppOutboundReceipt::put($id, $message->to, $message->fallback);
        }
    }

    public function download(string $mediaId): ?array
    {
        try {
            $meta = $this->request()->get(sprintf('%s/%s/%s', self::HOST, $this->version(), $mediaId));
            $url = (string) $meta->json('url');
            if ($meta->failed() || $url === '') {
                Log::error('meta.whatsapp.media_failed', ['step' => 'lookup', 'status' => $meta->status()]);

                return null;
            }

            $file = $this->request()->timeout(30)->get($url);
        } catch (ConnectionException) {
            Log::error('meta.whatsapp.media_failed', ['step' => 'connection']);

            return null;
        }

        if ($file->failed()) {
            Log::error('meta.whatsapp.media_failed', ['step' => 'download', 'status' => $file->status()]);

            return null;
        }

        $mime = trim((string) ($meta->json('mime_type') ?? $file->header('Content-Type')));

        return ['contents' => $file->body(), 'mime' => $mime];
    }

    private function request(): PendingRequest
    {
        return Http::withToken((string) config('services.meta.access_token'))
            ->acceptJson()
            ->timeout(15);
    }

    private function version(): string
    {
        return (string) config('services.meta.graph_version', 'v27.0');
    }

    /** @return array<string, mixed> */
    private function payload(OutboundMessage $message): array
    {
        $base = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $message->to,
        ];

        return match ($message->kind) {
            OutboundMessage::BUTTONS => $base + [
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => ['text' => $message->body],
                    'action' => [
                        'buttons' => array_map(static fn (array $button): array => [
                            'type' => 'reply',
                            'reply' => ['id' => $button['id'], 'title' => $button['title']],
                        ], $message->options['buttons']),
                    ],
                ],
            ],
            OutboundMessage::LIST => $base + [
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'list',
                    'body' => ['text' => $message->body],
                    'action' => [
                        'button' => $message->options['button'],
                        'sections' => [['title' => 'Opciones', 'rows' => $message->options['rows']]],
                    ],
                ],
            ],
            OutboundMessage::LOCATION_REQUEST => $base + [
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'location_request_message',
                    'body' => ['text' => $message->body],
                    'action' => ['name' => 'send_location'],
                ],
            ],
            default => $base + [
                'type' => 'text',
                'text' => ['preview_url' => true, 'body' => $message->body],
            ],
        };
    }
}
