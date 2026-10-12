<?php

declare(strict_types=1);

namespace App\Domain\User;

final readonly class UpdateUserData
{
    /**
     * @param  list<int>|null  $clientIds
     * @param  list<int>|null  $installationIds
     */
    public function __construct(
        public string $name,
        public ?string $email,
        public ?string $password,
        public ?string $role,
        public ?array $clientIds,
        public bool $isActive,
        public ?string $jobTitle = null,
        public ?string $avatarPath = null,
        public bool $regenerateSupervisorCode = false,
        public ?string $documentNumber = null,
        public ?array $installationIds = null,
        public ?string $installationPermission = null,
        /** @var list<AccessGrantData>|null */
        public ?array $grants = null,
    ) {}
}
