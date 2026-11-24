<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Models\SupervisorShift;
use Carbon\CarbonInterface;

/** Las dos distancias que pueden mandar un turno a revisión. */
final readonly class SupervisorOdometerComparison
{
    public function __construct(
        public SupervisorShift $shift,
        public ?int $tripKm,
        public bool $tripOverLimit,
        public ?int $previousKm,
        public ?CarbonInterface $previousEndedAt,
        public ?int $gapKm,
        public bool $gapOverLimit,
    ) {}

    public function flagged(): bool
    {
        return $this->tripOverLimit || $this->gapOverLimit;
    }
}
