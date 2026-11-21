<?php

declare(strict_types=1);

namespace Tests\Feature\Observatory;

use App\Enums\MetaConversationState;
use App\Models\Client;
use App\Models\Installation;
use App\Models\MetaConversation;
use App\Models\MetaWebhookMessage;
use App\Models\ObservatoryReport;
use App\Models\ObservatoryReportType;
use App\Services\Meta\MetaObservatoryBot;
use App\Services\Meta\OutboundMessage;
use App\Services\Meta\WhatsAppGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\FakeWhatsAppGateway;
use Tests\TestCase;

final class MetaObservatoryBotTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '1390897910781326';

    private const SENDER = '573001112233';

    private FakeWhatsAppGateway $gateway;

    private Client $client;

    private Installation $santa;

    private Installation $peru;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seedWithPilot();

        $this->client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $this->client->update(['has_observatory' => true, 'is_active' => true]);
        Installation::query()
            ->where('client_id', $this->client->id)
            ->update(['installation_profile_id' => $this->installationProfileId('conjunto')]);

        $this->santa = $this->school('SEM - I.E. SANTA LIBRADA SEDE PRINCIPAL', '176001000101', 3.4516, -76.5320);
        $this->peru = $this->school('SEM - I.E. REPUBLICA DEL PERU', '176001000102', 3.4000, -76.4000);

        config([
            'services.meta.phone_number_id' => self::PHONE,
            'services.meta.access_token' => 'token',
            'services.meta.observatory_client_id' => $this->client->id,
        ]);

        $this->gateway = new FakeWhatsAppGateway();
        $this->app->instance(WhatsAppGateway::class, $this->gateway);
    }

    public function test_full_conversation_creates_an_identified_report_with_photo(): void
    {
        $this->say('hola');
        $this->assertSame(OutboundMessage::LOCATION_REQUEST, $this->gateway->last()->kind);
        $this->assertStringContainsString('/privacidad', $this->gateway->last()->body);

        $this->say('santa librada');
        $this->assertSame(OutboundMessage::LIST, $this->gateway->last()->kind);
        $this->assertSame(['school:'.$this->santa->id], $this->gateway->rowIds());

        $this->reply('school:'.$this->santa->id);
        $this->assertContains('Riña', $this->gateway->rowTitles());
        $hurto = $this->typeId('hurto');

        $this->reply('type:'.$hurto);
        $this->assertStringContainsString('Cuéntame qué pasó', $this->gateway->last()->body);

        $this->say('corto');
        $this->assertStringContainsString('un poco más', $this->gateway->last()->body);

        $this->say('Vieron a alguien saltando el muro del colegio.');
        $this->assertSame(OutboundMessage::BUTTONS, $this->gateway->last()->kind);

        $this->image('media-1');
        $this->assertStringContainsString('foto 1 de 3', $this->gateway->last()->body);

        $this->reply('media:done');
        $this->assertCount(3, $this->gateway->last()->options['buttons']);

        $this->reply('role:padre');
        $this->assertStringContainsString('ocultos', $this->gateway->last()->body);

        $this->reply('anon:no');
        $this->assertStringContainsString('folio EV-', $this->gateway->last()->body);

        $report = ObservatoryReport::query()->firstOrFail();
        $this->assertSame('whatsapp', $report->source->value);
        $this->assertSame('padre', $report->reporter_role->value);
        $this->assertSame('hurto', $report->kind);
        $this->assertFalse($report->is_anonymous);
        $this->assertSame('Ana', $report->reporter_name);
        $this->assertSame(self::SENDER, $report->reporter_phone);
        $this->assertSame((int) $this->santa->id, (int) $report->installation_id);
        $this->assertCount(1, $report->photo_paths ?? []);
        Storage::disk('public')->assertExists($report->photo_paths[0]);

        $conversation = MetaConversation::query()->firstOrFail();
        $this->assertSame(MetaConversationState::Done, $conversation->state);
        $this->assertNull($conversation->open_key);
        $this->assertSame((int) $report->id, (int) $conversation->report_id);
    }

    public function test_anonymous_report_erases_the_phone_everywhere(): void
    {
        $this->completeFlow(anonymous: true);

        $report = ObservatoryReport::query()->firstOrFail();
        $this->assertTrue($report->is_anonymous);
        $this->assertNull($report->reporter_name);
        $this->assertNull($report->reporter_phone);
        $this->assertStringContainsString('no quedan guardados', $this->gateway->last()->body);

        $this->assertSame(0, MetaWebhookMessage::query()->whereNotNull('sender')->count());
        $this->assertSame(0, MetaWebhookMessage::query()->whereNotNull('sender_name')->count());
        foreach (MetaWebhookMessage::query()->get() as $message) {
            $this->assertArrayNotHasKey('from', $message->payload);
        }

        $conversation = MetaConversation::query()->firstOrFail();
        $this->assertStringStartsWith('anon-', $conversation->wa_id);
        $this->assertNull($conversation->profile_name);
    }

    public function test_location_offers_the_nearest_schools_first(): void
    {
        $this->say('hola');
        $this->location(3.4517, -76.5321);

        $this->assertSame(
            ['school:'.$this->santa->id, 'school:'.$this->peru->id],
            $this->gateway->rowIds(),
        );
        $this->assertStringStartsWith('A ', $this->gateway->last()->options['rows'][0]['description']);
    }

    public function test_types_come_from_the_client_catalog_and_paginate(): void
    {
        $this->say('hola');
        $this->say('santa');
        $this->reply('school:'.$this->santa->id);
        $this->assertCount(4, $this->gateway->rowIds());

        foreach (range(1, 10) as $n) {
            ObservatoryReportType::query()->create([
                'client_id' => $this->client->id,
                'name' => 'Tipo nuevo '.$n,
                'slug' => 'tipo-nuevo-'.$n,
                'level' => 1,
                'color' => '#112233',
                'is_active' => true,
                'sort_order' => 10 + $n,
            ]);
        }

        $this->say('x');
        $this->assertCount(10, $this->gateway->rowIds());
        $this->assertSame('Ver más tipos', $this->gateway->rowTitles()[9]);
        $this->assertSame('types_more:1', $this->gateway->rowIds()[9]);

        $this->reply('types_more:1');
        $this->assertContains('Tipo nuevo 10', $this->gateway->rowTitles());
    }

    public function test_it_does_nothing_without_token_or_when_the_client_has_no_observatory(): void
    {
        config(['services.meta.access_token' => '']);
        $row = $this->say('hola');
        $this->assertSame([], $this->gateway->sent);
        $this->assertNull($row->fresh()?->processed_at);

        config(['services.meta.access_token' => 'token']);
        $this->client->update(['has_observatory' => false]);
        $this->say('hola');
        $this->assertSame([], $this->gateway->sent);
        $this->assertSame(0, MetaConversation::query()->count());
    }

    public function test_cancel_closes_the_conversation_and_next_message_starts_over(): void
    {
        $this->say('hola');
        $this->say('Cancelar');
        $this->assertStringContainsString('cancelé', $this->gateway->last()->body);
        $this->assertSame(MetaConversationState::Cancelled, MetaConversation::query()->firstOrFail()->state);

        $this->say('hola');
        $this->assertSame(OutboundMessage::LOCATION_REQUEST, $this->gateway->last()->kind);
        $this->assertSame(2, MetaConversation::query()->count());
    }

    public function test_an_expired_conversation_starts_over(): void
    {
        $this->say('hola');
        MetaConversation::query()->update(['last_message_at' => now()->subHours(5)]);

        $this->say('santa');
        $this->assertSame(OutboundMessage::LOCATION_REQUEST, $this->gateway->last()->kind);
        $this->assertSame(2, MetaConversation::query()->count());
    }

    public function test_a_message_is_answered_only_once(): void
    {
        $row = $this->say('hola');
        $count = count($this->gateway->sent);

        app(MetaObservatoryBot::class)->handle($row->fresh());

        $this->assertSame($count, count($this->gateway->sent));
    }

    private function completeFlow(bool $anonymous): void
    {
        $this->say('hola');
        $this->say('santa librada');
        $this->reply('school:'.$this->santa->id);
        $this->reply('type:'.$this->typeId('hurto'));
        $this->say('Vieron a alguien saltando el muro del colegio.');
        $this->reply('media:skip');
        $this->reply('role:alumno');
        $this->reply($anonymous ? 'anon:yes' : 'anon:no');
    }

    private function typeId(string $slug): int
    {
        return (int) ObservatoryReportType::query()
            ->where('client_id', $this->client->id)
            ->where('slug', $slug)
            ->value('id');
    }

    private function school(string $name, string $dane, float $lat, float $lng): Installation
    {
        return Installation::query()->create([
            'client_id' => $this->client->id,
            'name' => $name,
            'installation_profile_id' => $this->installationProfileId(),
            'dane_code' => $dane,
            'is_client_site' => false,
            'is_active' => true,
            'city' => 'Cali',
            'latitude' => $lat,
            'longitude' => $lng,
        ]);
    }

    private function say(string $text): MetaWebhookMessage
    {
        return $this->inbound('text', ['text' => ['body' => $text]], $text);
    }

    private function reply(string $id): MetaWebhookMessage
    {
        return $this->inbound('interactive', ['interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => $id, 'title' => $id]]]);
    }

    private function location(float $lat, float $lng): MetaWebhookMessage
    {
        return $this->inbound('location', ['location' => ['latitude' => $lat, 'longitude' => $lng]]);
    }

    private function image(string $mediaId): MetaWebhookMessage
    {
        return $this->inbound('image', ['image' => ['id' => $mediaId, 'mime_type' => 'image/jpeg']]);
    }

    /** @param  array<string, mixed>  $item */
    private function inbound(string $type, array $item, ?string $body = null): MetaWebhookMessage
    {
        $id = 'wamid.'.Str::uuid();
        $row = MetaWebhookMessage::query()->create([
            'message_id' => $id,
            'phone_number_id' => self::PHONE,
            'sender' => self::SENDER,
            'sender_name' => 'Ana',
            'type' => $type,
            'body' => $body,
            'payload' => ['from' => self::SENDER, 'id' => $id, 'type' => $type] + $item,
        ]);

        app(MetaObservatoryBot::class)->handle($row);

        return $row;
    }
}
