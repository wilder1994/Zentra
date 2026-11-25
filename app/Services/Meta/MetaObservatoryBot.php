<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Enums\MetaConversationState;
use App\Enums\ObservatoryReporterRole;
use App\Models\Client;
use App\Models\Installation;
use App\Models\MetaConversation;
use App\Models\MetaWebhookMessage;
use App\Models\ObservatoryReporterMemory;
use App\Models\ObservatoryReportType;
use App\Services\Observatory\EnsureObservatoryReportTypesService;
use App\Services\Observatory\RememberObservatoryReporterService;
use App\Services\Observatory\SearchObservatorySchoolsService;
use App\Support\Meta\WhatsAppLimits;
use App\Support\Observatory\ObservatoryMediaLimits;
use App\Support\Observatory\ObservatoryVideoDuration;
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

    private const MAX_PHOTOS = ObservatoryMediaLimits::MAX_PHOTOS;

    private const MIN_BODY = ObservatoryMediaLimits::MIN_BODY;

    public function __construct(
        private readonly WhatsAppGateway $gateway,
        private readonly SearchObservatorySchoolsService $schools,
        private readonly SubmitMetaConversationReportService $submit,
        private readonly EnsureObservatoryReportTypesService $types,
        private readonly RememberObservatoryReporterService $memory,
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
                MetaConversationState::Role => $this->role($conversation, $in),
                MetaConversationState::Remember => $this->remember($conversation, $in),
                MetaConversationState::Age => $this->age($conversation, $in),
                MetaConversationState::Consent => $this->consent($conversation, $in),
                MetaConversationState::School => $this->school($conversation, $in, $client),
                MetaConversationState::Type => $this->type($conversation, $in, $client),
                MetaConversationState::Description => $this->description($conversation, $in),
                MetaConversationState::Media => $this->media($conversation, $in),
                MetaConversationState::Anonymous => $this->anonymous($conversation, $in),
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
                'state' => MetaConversationState::Role,
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
        return $this->askWho($in->from);
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
        if (mb_strlen($text) < self::MIN_BODY) {
            return [OutboundMessage::locationRequest($in->from, 'Escribe el colegio y qué pasó. Con una palabra basta, por ejemplo: Santa Librada, hurto.')];
        }

        return $this->parseReport($conversation, $in->from, $client, $text);
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
        ]);

        return $this->afterSchool($conversation, $in->from, $client);
    }

    /** @return list<OutboundMessage> */
    private function parseReport(MetaConversation $conversation, string $to, Client $client, string $text): array
    {
        $type = $this->detectType($client, $text);
        $query = $this->withoutType($text, $type);
        $found = $this->schools->byText($client, $query !== '' ? $query : $text);
        if ($found['schools']->isEmpty()) {
            $found = $this->shrinkSchools($client, $query !== '' ? $query : $text);
        }

        $conversation->body = mb_substr($text, 0, 2000);
        if ($type instanceof ObservatoryReportType) {
            $conversation->report_type_id = $type->id;
        }

        if ($found['schools']->isEmpty()) {
            return [OutboundMessage::locationRequest($to, 'No encontré el colegio en ese mensaje. Escribe el nombre y qué pasó, o envía tu ubicación.')];
        }

        if ($found['schools']->count() > 1) {
            return $this->schoolChoices($conversation, $to, $found['schools'], $found['has_more'], false);
        }

        /** @var Installation $school */
        $school = $found['schools']->first();
        $conversation->installation_id = $school->id;
        if ($this->onlySchool($text, $school)) {
            $conversation->forceFill(['body' => null, 'report_type_id' => null, 'state' => MetaConversationState::Description]);

            return [OutboundMessage::text($to, 'Colegio: '.WhatsAppLimits::clip($this->shortName($school->name), 80).'. ¿Qué pasó? Puedes escribir una palabra, por ejemplo hurto.')];
        }

        return $this->afterSchool($conversation, $to, $client);
    }

    /** @return list<OutboundMessage> */
    private function afterSchool(MetaConversation $conversation, string $to, Client $client): array
    {
        if (! filled($conversation->body)) {
            $conversation->state = MetaConversationState::Description;

            return [OutboundMessage::text($to, '¿Qué pasó? Puedes escribir una palabra, por ejemplo hurto.')];
        }

        if ($conversation->report_type_id === null) {
            $type = $this->detectType($client, (string) $conversation->body);
            if ($type instanceof ObservatoryReportType) {
                $conversation->report_type_id = $type->id;
            }
        }

        if ($conversation->report_type_id === null) {
            $conversation->state = MetaConversationState::Type;

            return $this->typeChoices($conversation, $to, $client, 0);
        }

        return $this->askMedia($conversation, $to);
    }

    private function detectType(Client $client, string $text): ?ObservatoryReportType
    {
        $this->types->execute($client);
        $haystack = mb_strtolower($text);
        $match = null;
        $length = 0;
        foreach (ObservatoryReportType::query()->where('security_company_id', $client->security_company_id)->where('is_active', true)->get() as $type) {
            $name = mb_strtolower($type->name);
            if ($name !== '' && preg_match('/(^|[^\p{L}])'.preg_quote($name, '/').'([^\p{L}]|$)/u', $haystack) === 1 && mb_strlen($name) > $length) {
                $match = $type;
                $length = mb_strlen($name);
            }
        }

        return $match;
    }

    private function withoutType(string $text, ?ObservatoryReportType $type): string
    {
        if (! $type instanceof ObservatoryReportType) {
            return trim($text);
        }

        $clean = preg_replace('/(^|[^\p{L}])'.preg_quote($type->name, '/').'([^\p{L}]|$)/iu', ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', (string) $clean));
    }

    /** @return array{schools: \Illuminate\Support\Collection<int, Installation>, has_more: bool} */
    private function shrinkSchools(Client $client, string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        while (count($words) > 1) {
            array_pop($words);
            $found = $this->schools->byText($client, implode(' ', $words));
            if ($found['schools']->isNotEmpty()) {
                return $found;
            }
        }

        return ['schools' => collect(), 'has_more' => false];
    }

    private function onlySchool(string $text, Installation $school): bool
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $name = mb_strtolower($school->name);
        foreach ($words as $word) {
            if (mb_strlen($word) < 2) {
                continue;
            }
            if (! str_contains($name, $word)) {
                return false;
            }
        }

        return true;
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
                ->where('security_company_id', $client->security_company_id)
                ->where('is_active', true)
                ->whereKey((int) substr($reply, strlen('type:')))
                ->first();

            if ($type instanceof ObservatoryReportType) {
                $conversation->report_type_id = $type->id;
                if (filled($conversation->body)) {
                    return $this->askMedia($conversation, $in->from);
                }
                $conversation->state = MetaConversationState::Description;

                return [OutboundMessage::text($in->from, '¿Qué pasó? Puedes escribir una palabra, por ejemplo hurto.')];
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

            return [OutboundMessage::text($to, 'Por ahora no hay riesgos endógenos disponibles. Intenta más tarde.')];
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
            $rows[] = ['id' => 'types_more:'.(($page + 1) % $pages), 'title' => 'Ver más'];
        }

        $school = $conversation->installation_id !== null
            ? $this->schools->find($client, (int) $conversation->installation_id)
            : null;
        $intro = $school instanceof Installation
            ? 'Colegio: '.WhatsAppLimits::clip($this->shortName($school->name), 200)."\n\n"
            : '';

        return [OutboundMessage::list($to, $intro.'¿Cuál es el riesgo endógeno?', 'Ver catálogo', $rows)];
    }

    /** @return list<OutboundMessage> */
    private function description(MetaConversation $conversation, MetaInboundMessage $in): array
    {
        $text = trim((string) $in->text);
        if (mb_strlen($text) < self::MIN_BODY) {
            return [OutboundMessage::text($in->from, 'Escribe qué pasó. Con una palabra basta, por ejemplo hurto.')];
        }

        $conversation->body = mb_substr($text, 0, 2000);
        $clientId = (int) config('services.meta.observatory_client_id');
        $client = Client::query()->withoutGlobalScopes()->whereKey($clientId)->first();
        if ($client instanceof Client && $conversation->report_type_id === null) {
            $type = $this->detectType($client, $text);
            if ($type instanceof ObservatoryReportType) {
                $conversation->report_type_id = $type->id;
            }
        }
        if ($conversation->report_type_id === null && $client instanceof Client) {
            $conversation->state = MetaConversationState::Type;

            return $this->typeChoices($conversation, $in->from, $client, 0);
        }

        return $this->askMedia($conversation, $in->from);
    }

    /** @return list<OutboundMessage> */
    private function askMedia(MetaConversation $conversation, string $to): array
    {
        $conversation->state = MetaConversationState::Media;

        return [OutboundMessage::buttons(
            $to,
            'Si tienes evidencia, envía hasta '.self::MAX_PHOTOS.' fotos y 1 video de '.ObservatoryMediaLimits::VIDEO_SECONDS.' segundos. Si no, toca «Listo».',
            [['id' => 'media:done', 'title' => 'Listo']],
        )];
    }

    /** @return list<OutboundMessage> */
    private function media(MetaConversation $conversation, MetaInboundMessage $in): array
    {
        $items = $conversation->media ?? [];
        $images = $this->countKind($items, 'image/');
        $hasVideo = $this->countKind($items, 'video/') > 0;

        if ($in->type === 'image' && $in->mediaId !== null && str_starts_with((string) $in->mediaMime, 'image/')) {
            if ($images < self::MAX_PHOTOS) {
                $items[] = ['id' => $in->mediaId, 'mime' => (string) $in->mediaMime];
                $conversation->media = $items;
                $images++;
            }
            if ($images >= self::MAX_PHOTOS && $hasVideo) {
                return $this->askWho($conversation, $in->from, 'Recibí las fotos y el video.');
            }

            return [OutboundMessage::buttons(
                $in->from,
                'Foto '.$images.' de '.self::MAX_PHOTOS.($hasVideo ? ' y el video' : '').'. Puedes enviar '.($hasVideo ? 'otra foto o' : 'un video de '.ObservatoryMediaLimits::VIDEO_SECONDS.' s o').' tocar «Listo».',
                [['id' => 'media:done', 'title' => 'Listo']],
            )];
        }

        if ($in->type === 'video' && $in->mediaId !== null) {
            $reason = $hasVideo ? 'Ya tengo un video.' : $this->rejectVideo($in->mediaId);
            if ($reason !== null) {
                return [OutboundMessage::buttons($in->from, $reason, [['id' => 'media:done', 'title' => 'Listo']])];
            }
            $items[] = ['id' => $in->mediaId, 'mime' => (string) ($in->mediaMime ?: 'video/mp4')];
            $conversation->media = $items;
            if ($images >= self::MAX_PHOTOS) {
                return $this->askWho($conversation, $in->from, 'Recibí el video.');
            }

            return [OutboundMessage::buttons(
                $in->from,
                'Recibí el video. Puedes enviar hasta '.self::MAX_PHOTOS.' fotos o tocar «Listo».',
                [['id' => 'media:done', 'title' => 'Listo']],
            )];
        }

        $text = mb_strtolower(trim((string) $in->text));
        if (in_array($in->replyId, ['media:skip', 'media:done'], true) || in_array($text, ['listo', 'sin fotos'], true)) {
            return $this->afterMedia($conversation, $in);
        }

        return [OutboundMessage::buttons(
            $in->from,
            'Envía una foto, un video de hasta '.ObservatoryMediaLimits::VIDEO_SECONDS.' segundos, o toca «Listo».',
            [['id' => 'media:done', 'title' => 'Listo']],
        )];
    }

    /** @param  list<array<string, mixed>>  $items */
    private function countKind(array $items, string $prefix): int
    {
        $count = 0;
        foreach ($items as $item) {
            if (is_array($item) && str_starts_with((string) ($item['mime'] ?? ''), $prefix)) {
                $count++;
            }
        }

        return $count;
    }

    private function rejectVideo(string $mediaId): ?string
    {
        $media = $this->gateway->download($mediaId);
        if ($media === null || ! str_starts_with($media['mime'], 'video/')) {
            return 'No pude recibir ese video. Envíe un mp4 de hasta '.ObservatoryMediaLimits::VIDEO_SECONDS.' segundos.';
        }
        if (strlen($media['contents']) > ObservatoryMediaLimits::VIDEO_KB * 1024) {
            return 'El video puede pesar hasta 20 MB.';
        }

        $path = tempnam(sys_get_temp_dir(), 'wa-video');
        if ($path === false) {
            return 'No pude revisar el video. Intenta con otro más corto.';
        }
        file_put_contents($path, $media['contents']);
        $seconds = ObservatoryVideoDuration::seconds($path);
        @unlink($path);
        if ($seconds === null || $seconds > ObservatoryMediaLimits::VIDEO_SECONDS + 0.4) {
            return 'El video puede durar hasta '.ObservatoryMediaLimits::VIDEO_SECONDS.' segundos.';
        }

        return null;
    }

    /** @return list<OutboundMessage> */
    private function askWho(string $to): array
    {
        return [OutboundMessage::buttons($to, '¿Quién eres? «cancelar» sale.', [
            ['id' => 'who:alumno', 'title' => 'Alumno'],
            ['id' => 'who:padre', 'title' => 'Padre'],
            ['id' => 'who:vecino', 'title' => 'Vecino'],
        ])];
    }

    /** @return list<OutboundMessage> */
    private function role(MetaConversation $conversation, MetaInboundMessage $in): array
    {
        $reply = (string) $in->replyId;
        if (preg_match('/^who:(alumno|padre|vecino)$/', $reply, $match) === 1) {
            $conversation->reporter_role = $match[1];
            if ($match[1] === ObservatoryReporterRole::Padre->value) {
                return $this->askSchool($conversation, $in->from);
            }

            return $this->afterRole($conversation, $in->from, $match[1]);
        }

        return $this->askWho($in->from);
    }

    /** @return list<OutboundMessage> */
    private function afterRole(MetaConversation $conversation, string $to, string $role): array
    {
        $client = $this->client();
        $saved = $client instanceof Client
            ? $this->memory->recall((int) $client->id, $to, $role)
            : null;
        if ($saved instanceof ObservatoryReporterMemory) {
            $conversation->is_adult = $saved->is_adult;
            if ($saved->is_adult === false) {
                $conversation->is_anonymous = false;
            }

            return $this->askSchool($conversation, $to);
        }

        return $this->askRemember($conversation, $to);
    }

    /** @return list<OutboundMessage> */
    private function askRemember(MetaConversation $conversation, string $to): array
    {
        $conversation->state = MetaConversationState::Remember;

        return [OutboundMessage::buttons($to, '¿Recordamos tu edad y autorización en este teléfono?', [
            ['id' => 'remember:yes', 'title' => 'Sí, recordar'],
            ['id' => 'remember:no', 'title' => 'No recordar'],
        ])];
    }

    /** @return list<OutboundMessage> */
    private function remember(MetaConversation $conversation, MetaInboundMessage $in): array
    {
        if ($in->replyId === 'remember:yes') {
            $conversation->remember_preference = true;

            return $this->askAge($conversation, $in->from);
        }
        if ($in->replyId === 'remember:no') {
            $conversation->remember_preference = false;

            return [
                OutboundMessage::text($in->from, RememberObservatoryReporterService::DECLINE_NOTICE),
                ...$this->askAge($conversation, $in->from),
            ];
        }

        return $this->askRemember($conversation, $in->from);
    }

    /** @return list<OutboundMessage> */
    private function askAge(MetaConversation $conversation, string $to): array
    {
        $conversation->state = MetaConversationState::Age;

        return [OutboundMessage::buttons($to, '¿Eres mayor de edad?', [
            ['id' => 'age:yes', 'title' => 'Sí'],
            ['id' => 'age:no', 'title' => 'No'],
        ])];
    }

    /** @return list<OutboundMessage> */
    private function age(MetaConversation $conversation, MetaInboundMessage $in): array
    {
        if ($in->replyId === 'age:yes') {
            $conversation->is_adult = true;

            return $this->askConsent($conversation, $in->from, true);
        }
        if ($in->replyId === 'age:no') {
            $conversation->is_adult = false;
            $conversation->is_anonymous = false;

            return $this->askConsent($conversation, $in->from, false);
        }

        return $this->askAge($conversation, $in->from);
    }

    /** @return list<OutboundMessage> */
    private function askConsent(MetaConversation $conversation, string $to, bool $adult): array
    {
        $conversation->state = MetaConversationState::Consent;
        $notice = $adult
            ? 'Autorizo el tratamiento de mis datos personales para registrar este reporte de forma previa, expresa e informada conforme a la Ley 1581 de 2012. Política de tratamiento: se usan tu nombre, el teléfono si lo das, el texto y la hora, solo para registrar la alerta. No se venden ni se usan para publicidad. Puedes pedir conocer, rectificar o suprimir tus datos.'
            : 'Los datos de niños, niñas y adolescentes tienen protección especial. La Ley 1581 de 2012 (artículo 7) prohíbe su tratamiento, salvo los datos públicos, y exige respetar sus derechos prevalentes. Solo se tratan por su interés superior y con autorización del representante legal.';

        return [OutboundMessage::text($to, $notice.' Responde acepto para continuar.')];
    }

    /** @return list<OutboundMessage> */
    private function consent(MetaConversation $conversation, MetaInboundMessage $in): array
    {
        if (mb_strtolower(trim((string) $in->text)) === 'acepto') {
            $this->storeMemory($conversation, $in->from);

            return $this->askSchool($conversation, $in->from);
        }

        return [OutboundMessage::text($in->from, 'Responde acepto para continuar.')];
    }

    private function storeMemory(MetaConversation $conversation, string $phone): void
    {
        if ($conversation->remember_preference !== true || $conversation->is_adult === null || $conversation->reporter_role === null) {
            return;
        }

        $client = $this->client();
        if (! $client instanceof Client) {
            return;
        }

        $this->memory->remember((int) $client->id, $phone, (string) $conversation->reporter_role, (bool) $conversation->is_adult);
    }

    /** @return list<OutboundMessage> */
    private function askSchool(MetaConversation $conversation, string $to): array
    {
        $conversation->state = MetaConversationState::School;

        return [OutboundMessage::locationRequest($to, '¿En qué colegio y qué pasó? Escribe el colegio y el hecho, por ejemplo: Santa Librada, hurto. O envía tu ubicación.')];
    }

    /** @return list<OutboundMessage> */
    private function afterMedia(MetaConversation $conversation, MetaInboundMessage $in): array
    {
        if ($conversation->is_adult === false) {
            return $this->finish($conversation, $in, false);
        }

        return $this->askAnonymous($conversation, $in->from);
    }

    /** @return list<OutboundMessage> */
    private function askAnonymous(MetaConversation $conversation, string $to): array
    {
        $conversation->state = MetaConversationState::Anonymous;

        return [OutboundMessage::buttons($to, '¿Quieres reportar en anónimo?', [
            ['id' => 'anon:no', 'title' => 'Con mi nombre'],
            ['id' => 'anon:yes', 'title' => 'Anónimo'],
        ])];
    }

    /** @return list<OutboundMessage> */
    private function anonymous(MetaConversation $conversation, MetaInboundMessage $in): array
    {
        $choice = match ($in->replyId) {
            'anon:yes' => true,
            'anon:no' => false,
            default => null,
        };
        if ($choice === null) {
            return $this->askAnonymous($conversation, $in->from);
        }

        $conversation->is_anonymous = $choice;

        return $this->finish($conversation, $in, $choice);
    }

    /** @return list<OutboundMessage> */
    private function finish(MetaConversation $conversation, MetaInboundMessage $in, bool $anonymous): array
    {
        $client = $this->client();
        if (! $client instanceof Client) {
            return [OutboundMessage::text($in->from, 'No pude registrar el reporte. Escríbeme de nuevo para empezar otra vez.')];
        }

        try {
            $report = $this->submit->execute($conversation, $client);
        } catch (ValidationException $e) {
            $conversation->close(MetaConversationState::Cancelled);
            $message = $e->errors()['report'][0] ?? 'No pude registrar el reporte. Escríbeme de nuevo para empezar otra vez.';

            return [OutboundMessage::text($in->from, $message)];
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
        if ($anonymous) {
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
