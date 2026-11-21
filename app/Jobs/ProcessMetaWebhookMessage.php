<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MetaWebhookMessage;
use App\Services\Meta\MetaObservatoryBot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Una sola vez por mensaje: si falla no se reintenta, para no contestar dos veces al ciudadano. */
final class ProcessMetaWebhookMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $messageId) {}

    public function handle(MetaObservatoryBot $bot): void
    {
        $message = MetaWebhookMessage::query()->find($this->messageId);
        if ($message instanceof MetaWebhookMessage) {
            $bot->handle($message);
        }
    }
}
