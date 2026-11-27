<?php

declare(strict_types=1);

namespace App\Domain\Supervision\Data;

final readonly class SupervisionQueryFilter
{
    public function __construct(
        public ?string $from = null,
        public ?string $to = null,
        public ?int $zoneId = null,
        public ?int $supervisorId = null,
        public ?string $sheetKind = null,
        public ?int $clientId = null,
        public ?bool $hasNovelty = null,
    ) {}

    public function withDates(?string $from, ?string $to): self
    {
        return new self(
            from: $from,
            to: $to,
            zoneId: $this->zoneId,
            supervisorId: $this->supervisorId,
            sheetKind: $this->sheetKind,
            clientId: $this->clientId,
            hasNovelty: $this->hasNovelty,
        );
    }

    public function withoutSheetKind(): self
    {
        return new self(
            from: $this->from,
            to: $this->to,
            zoneId: $this->zoneId,
            supervisorId: $this->supervisorId,
            sheetKind: null,
            clientId: $this->clientId,
            hasNovelty: $this->hasNovelty,
        );
    }
}
