<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\MetaWebhookMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MetaWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'app-secret';

    private const TOKEN = 'verify-token';

    private const PHONE = '1308879390781326';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.meta.app_secret' => self::SECRET,
            'services.meta.webhook_verify_token' => self::TOKEN,
            'services.meta.phone_number_id' => self::PHONE,
        ]);
    }

    public function test_meta_verification_returns_the_challenge(): void
    {
        $this->get('/api/webhooks/meta?hub.mode=subscribe&hub.verify_token=verify-token&hub.challenge=12345')
            ->assertOk()
            ->assertSee('12345', false);
    }

    public function test_meta_verification_rejects_a_wrong_token(): void
    {
        $this->get('/api/webhooks/meta?hub.mode=subscribe&hub.verify_token=no&hub.challenge=12345')
            ->assertForbidden();
    }

    public function test_meta_webhook_stores_a_signed_message_once(): void
    {
        $body = $this->payload('wamid.1', self::PHONE, 'Hay una riña en la salida');

        $this->callWebhook($body)->assertOk();
        $this->callWebhook($body)->assertOk();

        $this->assertSame(1, MetaWebhookMessage::query()->count());
        $this->assertDatabaseHas('meta_webhook_messages', [
            'message_id' => 'wamid.1',
            'phone_number_id' => self::PHONE,
            'body' => 'Hay una riña en la salida',
        ]);
    }

    public function test_meta_webhook_ignores_another_phone_number(): void
    {
        $body = $this->payload('wamid.2', '1149223651039175', 'No guardar');

        $this->callWebhook($body)->assertOk();

        $this->assertSame(0, MetaWebhookMessage::query()->count());
    }

    public function test_meta_webhook_rejects_a_bad_signature(): void
    {
        $this->call('POST', '/api/webhooks/meta', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=deadbeef',
            'CONTENT_TYPE' => 'application/json',
        ], $this->payload('wamid.3', self::PHONE, 'Hola'))
            ->assertForbidden();

        $this->assertSame(0, MetaWebhookMessage::query()->count());
    }

    private function callWebhook(string $body): \Illuminate\Testing\TestResponse
    {
        return $this->call('POST', '/api/webhooks/meta', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, self::SECRET),
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    private function payload(string $messageId, string $phoneNumberId, string $text): string
    {
        return json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['phone_number_id' => $phoneNumberId],
                        'messages' => [[
                            'from' => '573001112233',
                            'id' => $messageId,
                            'timestamp' => '1710000000',
                            'type' => 'text',
                            'text' => ['body' => $text],
                        ]],
                    ],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);
    }
};
