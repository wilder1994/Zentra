<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Enums\MetaConversationState;
use App\Enums\ObservatoryReporterRole;
use App\Models\Client;
use App\Models\Installation;
use App\Models\MetaConversation;
use App\Models\MetaWebhookMessage;
use App\Models\ObservatoryReportType;
use App\Services\Observatory\EnsureObservatoryReportTypesService;
use App\Services\Observatory\SearchObservatorySchoolsService;
use App\Support\Meta\WhatsAppLimits;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Conversación guiada del 310: colegio, tipo, qué pasó, fotos, quién es y anonimato.
 * Solo atiende al cliente fijado en META_OBSERVATORY_CLIENT_ID.
 */
final class MetaObservatoryBot
{
    private const CANCEL_WORDS = ['cancelar', 'salir', 'cancel'];

    private const MAX_PHOTOS = 3;

    private const MIN_BODY = 10;

    public function __construct(
        private readonly WhatsAppGateway $gateway,
        private readonly SearchObservatorySchoolsService $schools,
        private readonly SubmitMetaConversationReportService $submit,
        private readonly EnsureObservatoryReportTypesService $types,
    ) {}

    public function handle(MetaWebhookMessage $row): void
    {
        $client = $this->client();
        if (! $client instanceof Client) {
            return;
        }

        $in = MetaInboundMessage::fromRow($row);
        if ($in->from === '') {
            return;
        }

        $replies = DB::transaction(fn (): array => $this->process($row, $in, $client));

        foreach ($replies as $reply) {
            $this->gateway->send($reply);
        }
    }

    private function client(): ?Client
    {
        $id = (int) config('services.meta.observatory_client_id');
        if ($id <= 0 || trim((string) config('services.meta.access_token')) === '') {
            Log::error('meta.bot.unconfigured', [
                'client' => $id > 0,
                'token' => trim((string) config('services.meta.access_token')) !== '',
            ]);

            return null;
        }

        $client = Client::query()->withoutGlobalScopes()->whereNull('deleted_at')->whereKey($id)->first();
        if (! $client instanceof Client || ! $client->is_active || ! $client->has_observatory) {
            Log::error('meta.bot.client_disabled', ['client_id' => $id]);

            return null;
        }

        return $client;
    }

    /** @return list<OutboundMessage> */
    private function process(MetaWebhookMessage $row, MetaInboundMessage $in, Client $client): array
    {
        $claimed = MetaWebhookMessage::query()
            ->whereKey($row->id)
            ->whereNull('processed_at')
            ->update(['processed_at' => now()]);
        if ($claimed === 0) {
            return [];
        }

        [$conversation, $isNew] = $this->conversation($in);
        $row->forceFill(['conversation_id' => $conversation->id])->save();

        $conversation->forceFill(['last_message_at' => now()]);
        if ($conversation->profile_name === null && $in->name !== null) {
            $conversation->profile_name = $in->name;
        }

        if ($isNew) {
            $replies = $this->greeting($in);
        } elseif (in_array(mb_strtolower(trim((string) $in->text)), self::CANCEL_WORDS, true)) {
            $conversation->close(MetaConversationState::Cancelled);
            $replies = [OutboundMessage::text($in->from, 'Listo, cancelé el reporte. Escríbeme cuando quieras reportar algo.')];
        } else {
            $replies = match ($conversation->state) {
                MetaConversationState::School => $this->school($conversation, $in, $client),
                MetaConversationState::Type => $this->type($conversation, $in, $client),
                MetaConversationState::Description => $this->description($conversation, $in),
                MetaConversationState::Media => $this->media($conversation, $in),
                MetaConversationState::Role => $this->role($conversation, $in),
                MetaConversationState::Anonymous => $this->anonymous($conversation, $in, $client),
                default => [],
            };
        }

        $conversation->save();

        return $replies;
    }

    /** @return array{0: MetaConversation, 1: bool} */
    private function conversation(MetaInboundMessage $in): array
    {
        $ttl = max(5, (int) config('services.meta.conversation_ttl_minutes', 120));

        $open = MetaConversation::query()->where('open_key', $in->from)->lockForUpdate()->first();
        if ($open instanceof MetaConversation && $open->last_message_at->lt(now()->subMinutes($ttl))) {
            $open->close(MetaConversationState::Cancelled);
            $open = null;
        }
        if ($open instanceof MetaConversation) {
            return [$open, false];
        }

        try {
            $created = MetaConversation::query()->create([
                'wa_id' => $in->from,
                'open_key' => $in->from,
                'profile_name' => $in->name,
                'state' => MetaConversationState::School,
                'last_message_at' => now(),
            ]);

            return [$created, true];
        } catch (UniqueConstraintViolationException) {
            return [MetaConversation::query()->where('open_key', $in->from)->lockForUpdate()->firstOrFail(), false];
        }
    }

    /** @return list<OutboundMessage> */
    private function greeting(MetaInboundMessage $in): array
    {
        return [OutboundMessage::locationRequest($in->from, WhatsAppLimits::clip(
            "Hola, soy el Observatorio SJ. Tu reporte se registra según nuestra política de privacidad: ".route('privacy')
            ."\n\n¿En qué colegio pasó? Escribe parte del nombre o su código DANE, o toca el botón para enviar tu ubicación."
            ."\n\nPuedes escribir «cancelar» cuando quieras salir.",
            WhatsAppLimits::BODY,
        ))];
    }

    /** @return list<OutboundMessage> */
    private function school(MetaConversation $conversation, MetaInboundMessage $in, Client $client): array
    {
        if ($in->replyId !== null && str_starts_with($in->replyId, 'school:')) {
            return $this->pickSchool($conversation, $in, $client);
        }

        if ($in->hasLocation()) {
            $near = $this->schools->nearest($client, (float) $in->latitude, (float) $in->longitude);

            return $this->schoolChoices($conversation, $in->from, $near, false, true);
        }

        $text = trim((string) $in->text);
        if ($text === '') {
            return [OutboundMessage::locationRequest($in->from, 'Escribe parte del nombre del colegio o su código DANE, o toca el botón para enviar tu ubicación.')];
        }

        $found = $this->schools->byText($client, $text);

        return $this->schoolChoices($conversation, $in->from, $found['schools'], $found['has_more'], false);
    }

    /** @return list<OutboundMessage> */
    private function pickSchool(MetaConversation $conversation, MetaInboundMessage $in, Client $client): array
    {
        $id = (int) substr((string) $in->replyId, strlen('school:'));
        $offered = array_map('intval', $conversation->candidates ?? []);
        $school = in_array($id, $offered, true) ? $this->schools->find($client, $id) : null;

        if (! $school instanceof Installation) {
            return [OutboundMessage::locationRequest($in->from, 'Ese colegio ya no está disponible. Escribe otra vez el nombre o toca el botón para enviar tu ubicación.')];
        }

        $conversation->forceFill([
            'installation_id' => $school->id,
            'candidates' => null,
            'state' => MetaConversationState::Type,
        ]);

        return $this->typeChoices($conversation, $in->from, $client, 0);
    }

    /**
     * @param  Collection<int, Installation>  $schools
     * @return list<OutboundMessage>
     */
    private function schoolChoices(MetaConversation $conversation, string $to, Collection $schools, bool $more, bool $withDistance): array
    {
        if ($schools->isEmpty()) {
            return [OutboundMessage::locationRequest($to, 'No encontré ese colegio. Escribe otra parte del nombre o su código DANE, o toca el botón para enviar tu ubicación.')];
        }

        $conversation->candidates = $schools->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        $rows = $schools->map(function (Installation $school) use ($withDistance): array {
            [$title, $rest] = WhatsAppLimits::split($this->shortName($school->name), WhatsAppLimits::ROW_TITLE);
            $description = $rest !== '' ? $rest : (filled($school->dane_code) ? 'DANE '.$school->dane_code : '');
            if ($withDistance) {
                $description = trim($this->distance((float) $school->getAttribute('distance_km')).' · '.$description, ' ·');
            }

            return ['id' => 'school:'.$school->id, 'title' => $title, 'description' => $description];
        })->values()->all();

        $body = $more
            ? 'Hay más coincidencias; te muestro las primeras. Si no es ninguna, escribe más del nombre.'
            : 'Elige tu colegio. Si no está, escribe otro nombre o toca el botón para enviar tu ubicación.';

        return [OutboundMessage::list($to, $body, 'Ver colegios', $rows)];
    }

    /** @return list<OutboundMessage> */
    private function type(MetaConversation $conversation, MetaInboundMessage $in, Client $client): array
    {
        $reply = (string) $in->replyId;

        if (str_starts_with($reply, 'types_more:')) {
            return $this->typeChoices($conversation, $in->from, $client, (int) substr($reply, strlen('types_more:')));
        }

        if (str_starts_with($reply, 'type:')) {
            $type = ObservatoryReportType::query()
                ->where('client_id', $client->id)
                ->where('is_active', true)
                ->whereKey((int) substr($reply, strlen('type:')))
                ->first();

            if ($type instanceof ObservatoryReportType) {
                $conversation->forceFill(['report_type_id' => $type->id, 'state' => MetaConversationState::Description]);

                return [OutboundMessage::text($in->from, 'Cuéntame qué pasó, con el mayor detalle posible (mínimo '.self::MIN_BODY.' letras).')];
            }
        }

        return $this->typeChoices($conversation, $in->from, $client, 0);
    }

    /** @return list<OutboundMessage> */
    private function typeChoices(MetaConversation $conversation, string $to, Client $client, int $page): array
    {
        $this->types->execute($client);
        $types = ObservatoryReportType::catalogFor($client->id);

        if ($types === []) {
            $conversation->close(MetaConversationState::Cancelled);

            return [OutboundMessage::text($to, 'Por ahora no hay tipos de situación disponibles. Intenta más tarde.')];
        }

        $rows = array_map(static fn (array $type): array => [
            'id' => 'type:'.$type['id'],
            'title' => $type['name'],
        ], $types);

        if (count($rows) > WhatsAppLimits::LIST_ROWS) {
            $size = WhatsAppLimits::LIST_ROWS - 1;
            $pages = (int) ceil(count($rows) / $size);
            $page = max(0, $page) % $pages;
            $rows = array_slice($rows, $page * $size, $size);
            $rows[] = ['id' => 'types_more:'.(($page + 1) % $pages), 'title' => 'Ver más tipos'];
        }

        $school = $conversation->installation_id !== null
            ? $this->schools->find($client, (int) $conversation->installation_id)
            : null;
        $intro = $school instanceof Installation
            ? 'Colegio: '.WhatsAppLimits::clip($this->shortName($school->name), 200)."\n\n"
            : '';

        return [OutboundMessage::list($to, $intro.'¿Qué tipo de situación es?', 'Ver tipos', $rows)];
    }

    /** @return list<OutboundMessage> */
    private function description(MetaConversation $conversation, MetaInboundMessage $in): array
    {
        $text = trim((string) $in->text);
        if (mb_strlen($text) < self::MIN_BODY) {
            return [OutboundMessage::text($in->from, 'Cuéntame un poco más: necesito al menos '.self::MIN_BODY.' letras para entender qué pasó.')];
        }

        $conversation->forceFill(['body' => mb_substr($text, 0, 2000), 'state' => MetaConversationState::Media]);

        return [OutboundMessage::buttons(
            $in->from,
            'Si tienes fotos del hecho, envíalas ahora (hasta '.self::MAX_PHOTOS.'). Si no, toca «Sin fotos».',
            [['id' => 'media:skip', 'title' => 'Sin fotos']],
        )];
    }

    /** @return list<OutboundMessage> */
    private function media(MetaConversation $conversation, MetaInboundMessage $in): array
    {
        $items = $conversation->mediaItems();

        if ($in->type === 'image' && $in->mediaId !== null && str_starts_with((string) $in->mediaMime, 'image/')) {
            if (count($items) < self::MAX_PHOTOS) {
                $items[] = ['id' => $in->mediaId, 'mime' => (string) $in->mediaMime];
                $conversation->media = $items;
            }
            if (count($items) >= self::MAX_PHOTOS) {
                return $this->askRole($conversation, $in->from, 'Recibí las '.self::MAX_PHOTOS.' fotos.');
            }

            return [OutboundMessage::buttons(
                $in->from,
                'Recibí la foto '.count($items).' de '.self::MAX_PHOTOS.'. Envía otra o toca «Listo».',
                [['id' => 'media:done', 'title' => 'Listo']],
            )];
        }

        $text = mb_strtolower(trim((string) $in->text));
        if (in_array($in->replyId, ['media:skip', 'media:done'], true) || in_array($text, ['listo', 'sin fotos'], true)) {
            return $this->askRole($conversation, $in->from, null);
        }

        $label = $items === [] ? 'Sin fotos' : 'Listo';
        $hint = in_array($in->type, ['video', 'audio', 'document', 'sticker'], true)
            ? 'Por ahora solo recibo fotos. Envía una foto o toca «'.$label.'».'
            : 'Envía una foto o toca «'.$label.'» para continuar.';

        return [OutboundMessage::buttons($in->from, $hint, [['id' => 'media:done', 'title' => $label]])];
    }

    /** @return list<OutboundMessage> */
    private function askRole(MetaConversation $conversation, string $to, ?string $intro): array
    {
        $conversation->state = MetaConversationState::Role;

        $buttons = [];
        foreach (ObservatoryReporterRole::publicOptions() as $value => $label) {
            $buttons[] = ['id' => 'role:'.$value, 'title' => $label];
        }

        return [OutboundMessage::buttons($to, trim(($intro ?? '').' ¿Quién eres?'), $buttons)];
    }

    /** @return list<OutboundMessage> */
    private function role(MetaConversation $conversation, MetaInboundMessage $in): array
    {
        $role = str_starts_with((string) $in->replyId, 'role:')
            ? ObservatoryReporterRole::tryFrom(substr((string) $in->replyId, strlen('role:')))
            : null;

        if ($role === null || ! $role->isPublic()) {
            return $this->askRole($conversation, $in->from, null);
        }

        $conversation->forceFill(['reporter_role' => $role->value, 'state' => MetaConversationState::Anonymous]);

        return $this->askAnonymous($in->from);
    }

    /** @return list<OutboundMessage> */
    private function askAnonymous(string $to): array
    {
        return [OutboundMessage::buttons(
            $to,
            '¿Quieres que tu nombre y teléfono queden ocultos? Solo se mostrará la denuncia.',
            [['id' => 'anon:yes', 'title' => 'Sí, anónimo'], ['id' => 'anon:no', 'title' => 'No, con mi nombre']],
        )];
    }

    /** @return list<OutboundMessage> */
    private function anonymous(MetaConversation $conversation, MetaInboundMessage $in, Client $client): array
    {
        $choice = match ($in->replyId) {
            'anon:yes' => true,
            'anon:no' => false,
            default => null,
        };
        if ($choice === null) {
            return $this->askAnonymous($in->from);
        }

        $conversation->is_anonymous = $choice;

        try {
            $report = $this->submit->execute($conversation, $client);
        } catch (ValidationException) {
            $conversation->close(MetaConversationState::Cancelled);

            return [OutboundMessage::text($in->from, 'No pude registrar el reporte. Escríbeme de nuevo para empezar otra vez.')];
        }

        $report->loadMissing('event', 'installation');
        $text = 'Listo, registré tu reporte';
        if ($report->installation instanceof Installation) {
            $text .= ' en '.WhatsAppLimits::clip($this->shortName($report->installation->name), 120);
        }
        if ($report->event !== null) {
            $text .= ' con el folio '.$report->event->folio();
        }
        $text .= '. Gracias por avisar.';
        if ($choice) {
            $text .= ' Tu nombre y teléfono no quedan guardados.';
        }

        return [OutboundMessage::text($in->from, $text)];
    }

    /** Quita el prefijo administrativo («SEM - I.E.») que ocupa el poco espacio del título. */
    private function shortName(string $name): string
    {
        $name = (string) preg_replace('/^\s*(?:SEM|EM)\s*[-–—]\s*/iu', '', $name);
        $name = (string) preg_replace('/^\s*I\.?\s?E\.?\s+/iu', '', $name);

        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    private function distance(float $km): string
    {
        if ($km < 1) {
            return 'A '.(max(1, (int) round($km * 100)) * 10).' m';
        }

        return 'A '.number_format($km, 1, ',', '').' km';
    }
}
