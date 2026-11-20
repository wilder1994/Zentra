<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Models\MetaWebhookMessage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class ReceiveMetaWebhookService
{
    public function challenge(Request $request): ?string
    {
        $expected = (string) config('services.meta.webhook_verify_token');
        $mode = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        if ($expected === '' || $mode !== 'subscribe' || ! is_string($challenge) || ! hash_equals($expected, $token)) {
            return null;
        }

        return $challenge;
    }

    public function signatureIsValid(Request $request): bool
    {
        $secret = (string) config('services.meta.app_secret');
        $header = (string) $request->header('X-Hub-Signature-256', '');
        if ($secret === '' || $header === '') {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $header);
    }

    public function store(string $raw): int
    {
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            $this->skip(['reason' => 'invalid_json']);

            return 0;
        }

        $allowed = (string) config('services.meta.phone_number_id');
        if ($allowed === '') {
            $this->skip(['reason' => 'phone_unconfigured']);

            return 0;
        }

        $saved = 0;
        foreach ($this->messages($decoded) as $message) {
            if ($message['phone_number_id'] !== $allowed || $message['message_id'] === '') {
                continue;
            }

            $row = MetaWebhookMessage::query()->firstOrCreate(
                ['message_id' => $message['message_id']],
                [
                    'phone_number_id' => $message['phone_number_id'],
                    'sender' => $message['sender'],
                    'type' => $message['type'],
                    'body' => $message['body'],
                    'payload' => $message['payload'],
                    'sent_at' => $message['sent_at'],
                ],
            );
            if ($row->wasRecentlyCreated) {
                $saved++;
            }
        }

        if ($saved === 0) {
            $this->skip($this->trace($decoded));
        }

        return $saved;
    }

    /** @param  array<string, mixed>  $context */
    private function skip(array $context): void
    {
        Log::error('meta.webhook.skipped', $context);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array{fields: list<string>, phone_number_ids: list<string>, messages: int, statuses: int}
     */
    private function trace(array $decoded): array
    {
        $fields = [];
        $phones = [];
        $messages = 0;
        $statuses = 0;
        $entries = is_array($decoded['entry'] ?? null) ? $decoded['entry'] : [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $changes = is_array($entry['changes'] ?? null) ? $entry['changes'] : [];
            foreach ($changes as $change) {
                if (! is_array($change)) {
                    continue;
                }
                $field = trim((string) ($change['field'] ?? ''));
                if ($field !== '') {
                    $fields[] = $field;
                }
                $value = is_array($change['value'] ?? null) ? $change['value'] : [];
                $metadata = is_array($value['metadata'] ?? null) ? $value['metadata'] : [];
                $phone = trim((string) ($metadata['phone_number_id'] ?? ''));
                if ($phone !== '') {
                    $phones[] = $phone;
                }
                $messages += is_array($value['messages'] ?? null) ? count($value['messages']) : 0;
                $statuses += is_array($value['statuses'] ?? null) ? count($value['statuses']) : 0;
            }
        }

        return [
            'fields' => array_values(array_unique($fields)),
            'phone_number_ids' => array_values(array_unique($phones)),
            'messages' => $messages,
            'statuses' => $statuses,
        ];
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return list<array{message_id: string, phone_number_id: string, sender: ?string, type: ?string, body: ?string, payload: array<string, mixed>, sent_at: ?Carbon}>
     */
    private function messages(array $decoded): array
    {
        $entries = is_array($decoded['entry'] ?? null) ? $decoded['entry'] : [];
        $messages = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $changes = is_array($entry['changes'] ?? null) ? $entry['changes'] : [];
            foreach ($changes as $change) {
                if (! is_array($change)) {
                    continue;
                }
                $value = is_array($change['value'] ?? null) ? $change['value'] : [];
                $metadata = is_array($value['metadata'] ?? null) ? $value['metadata'] : [];
                $phoneNumberId = trim((string) ($metadata['phone_number_id'] ?? ''));
                $items = is_array($value['messages'] ?? null) ? $value['messages'] : [];
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $type = trim((string) ($item['type'] ?? ''));
                    $messages[] = [
                        'message_id' => trim((string) ($item['id'] ?? '')),
                        'phone_number_id' => $phoneNumberId,
                        'sender' => $this->nullableString($item['from'] ?? null),
                        'type' => $type !== '' ? $type : null,
                        'body' => $this->body($item, $type),
                        'payload' => $item,
                        'sent_at' => $this->sentAt($item['timestamp'] ?? null),
                    ];
                }
            }
        }

        return $messages;
    }

    /** @param  array<string, mixed>  $item */
    private function body(array $item, string $type): ?string
    {
        $text = $item['text']['body'] ?? null;
        if ($type === 'text' && is_string($text)) {
            $text = trim($text);

            return $text !== '' ? $text : null;
        }

        $caption = is_array($item[$type] ?? null) ? ($item[$type]['caption'] ?? null) : null;
        if (! is_string($caption)) {
            return null;
        }
        $caption = trim($caption);

        return $caption !== '' ? $caption : null;
    }

    private function sentAt(mixed $timestamp): ?Carbon
    {
        if (! is_numeric($timestamp)) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $timestamp);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value !== '' ? $value : null;
    }
};
