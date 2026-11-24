<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Enums\MetaConversationState;
use App\Enums\ObservatoryReportSource;
use App\Models\Client;
use App\Models\MetaConversation;
use App\Models\MetaWebhookMessage;
use App\Models\ObservatoryReport;
use App\Models\ObservatoryReportType;
use App\Services\Observatory\SubmitObservatoryReportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/** Convierte una conversación completa de WhatsApp en un reporte del observatorio. */
final class SubmitMetaConversationReportService
{
    public function __construct(
        private readonly WhatsAppGateway $gateway,
        private readonly SubmitObservatoryReportService $reports,
    ) {}

    public function execute(MetaConversation $conversation, Client $client): ObservatoryReport
    {
        $type = ObservatoryReportType::query()
            ->where('client_id', $client->id)
            ->whereKey((int) $conversation->report_type_id)
            ->first();

        $tmp = [];
        $files = $this->photos($conversation, $tmp);
        $video = $this->video($conversation, $tmp);

        try {
            $report = $this->reports->execute($client, [
                'installation_id' => (int) $conversation->installation_id,
                'kind' => $type?->slug ?? '',
                'body' => (string) $conversation->body,
                'is_anonymous' => (bool) $conversation->is_anonymous,
                'source' => ObservatoryReportSource::Whatsapp,
                'reporter_role' => (string) $conversation->reporter_role,
                'reporter_name' => $conversation->profile_name,
                'reporter_phone' => $conversation->wa_id,
                'photos' => $files,
                'video' => $video,
            ]);
        } finally {
            foreach ($tmp as $path) {
                @unlink($path);
            }
        }

        $this->finish($conversation, $report);

        return $report;
    }

    /**
     * @param  list<string>  $tmp  rutas temporales a borrar al terminar
     * @return list<UploadedFile>
     */
    private function photos(MetaConversation $conversation, array &$tmp): array
    {
        $files = [];
        foreach ($conversation->mediaItems() as $item) {
            $media = $this->gateway->download($item['id']);
            if ($media === null || ! str_starts_with($media['mime'], 'image/')) {
                continue;
            }
            if (count($files) >= 2) {
                break;
            }

            $path = tempnam(sys_get_temp_dir(), 'wa');
            if ($path === false) {
                continue;
            }
            file_put_contents($path, $media['contents']);
            $tmp[] = $path;
            $files[] = new UploadedFile($path, 'whatsapp.jpg', $media['mime'], null, true);
        }

        return $files;
    }

    /** @param  list<string>  $tmp */
    private function video(MetaConversation $conversation, array &$tmp): ?UploadedFile
    {
        foreach ($conversation->mediaItems() as $item) {
            if (! str_starts_with($item['mime'], 'video/')) {
                continue;
            }
            $media = $this->gateway->download($item['id']);
            if ($media === null || ! str_starts_with($media['mime'], 'video/')) {
                return null;
            }
            $path = tempnam(sys_get_temp_dir(), 'wa-video');
            if ($path === false) {
                return null;
            }
            file_put_contents($path, $media['contents']);
            $tmp[] = $path;

            return new UploadedFile($path, 'whatsapp.mp4', $media['mime'], null, true);
        }

        return null;
    }

    /** Cierra la conversación; si fue anónima, borra el número del buzón y de la conversación. */
    private function finish(MetaConversation $conversation, ObservatoryReport $report): void
    {
        $attributes = ['media' => null, 'candidates' => null, 'report_id' => $report->id];

        if ($conversation->is_anonymous) {
            MetaWebhookMessage::query()
                ->where('conversation_id', $conversation->id)
                ->get()
                ->each(function (MetaWebhookMessage $message): void {
                    $payload = is_array($message->payload) ? $message->payload : [];
                    unset($payload['from'], $payload['from_user_id']);
                    $message->forceFill(['sender' => null, 'sender_name' => null, 'payload' => $payload])->save();
                });

            $attributes['wa_id'] = 'anon-'.Str::lower(Str::random(16));
            $attributes['profile_name'] = null;
        }

        $conversation->forceFill($attributes);
        $conversation->close(MetaConversationState::Done);
    }
}
