<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\MetaWebhookMessage;
use App\Services\Meta\OutboundMessage;
use App\Services\Meta\WhatsAppGateway;
use App\Services\Meta\WhatsAppOutboundReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Tests\Support\FakeWhatsAppGateway;
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
        $logged = [];
        Log::listen(function (MessageLogged $event) use (&$logged): void {
            $logged[] = $event;
        });
        $body = $this->payload('wamid.2', '1149223651039175', 'No guardar');

        $this->callWebhook($body)->assertOk();

        $this->assertSame(0, MetaWebhookMessage::query()->count());
        $encoded = json_encode($logged);
        $this->assertIsString($encoded);
        $this->assertStringContainsString('meta.webhook.skipped', $encoded);
        $this->assertStringContainsString('1149223651039175', $encoded);
        $this->assertStringNotContainsString('No guardar', $encoded);
        $this->assertStringNotContainsString('573001112233', $encoded);
    }

    public function test_a_failed_status_resends_the_question_as_text(): void
    {
        $gateway = new FakeWhatsAppGateway();
        $this->app->instance(WhatsAppGateway::class, $gateway);
        WhatsAppOutboundReceipt::put('wamid.out', '573001112233', '¿Quién eres? Responde Alumno, Padre o Vecino.');

        $body = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['phone_number_id' => self::PHONE],
                        'statuses' => [[
                            'id' => 'wamid.out',
                            'status' => 'failed',
                            'recipient_id' => '573001112233',
                            'errors' => [['code' => 131026, 'title' => 'Message undeliverable']],
                        ]],
                    ],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $this->callWebhook($body)->assertOk();

        $this->assertCount(1, $gateway->sent);
        $this->assertSame(OutboundMessage::TEXT, $gateway->sent[0]->kind);
        $this->assertSame('¿Quién eres? Responde Alumno, Padre o Vecino.', $gateway->sent[0]->body);
        $this->assertNull($gateway->sent[0]->fallback);
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
