<?php

declare(strict_types=1);

namespace App\Domain\Structure\Data;

final readonly class CreateStructureAppUserData
{
    public function __construct(
        public int $clientId,
        public int $memberId,
        public string $deliveryChannel,
        public ?int $actorUserId,
    ) {}
}
