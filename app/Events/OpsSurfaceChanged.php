<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class OpsSurfaceChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $companyId,
        public ?int $clientId,
        public string $kind,
        public string $summary,
        public ?string $actor,
        public ?array $point = null,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [];
        if ($this->companyId > 0) {
            $channels[] = new PrivateChannel('ops.company.'.$this->companyId);
        }
        if ($this->clientId !== null && $this->clientId > 0) {
            $channels[] = new PrivateChannel('ops.client.'.$this->clientId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'kind' => $this->kind,
            'summary' => $this->summary,
            'actor' => $this->actor,
            'point' => $this->point,
        ];
    }
}
