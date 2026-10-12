<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Data;

use App\Enums\PartyType;

final readonly class CreateClientData
{
    public function __construct(
        public int $securityCompanyId,
        public string $name,
        public PartyType $partyType,
        public ?string $legalName,
        public ?string $documentType,
        public ?string $taxId,
        public ?string $email,
        public ?string $phone,
        public ?string $representativeName,
        public ?string $representativeEmail,
        public int $structureTypeId,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $department = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public bool $isActive = true,
        public bool $hasAccess = false,
        public bool $hasSupervision = false,
        public bool $showPersonnelFolders = false,
        public ?string $serviceStartedAt = null,
        public bool $hasObservatory = false,
    ) {}
}
