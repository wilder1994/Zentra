<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Enums\ClientAdminOrigin;

final readonly class CreateUserData
{
    /**
     * @param  list<int>  $clientIds
     * @param  list<int>  $installationIds
     */
    public function __construct(
        public string $name,
        public string $username,
        public ?string $email,
        public string $password,
        public string $role,
        public ?int $securityCompanyId,
        public array $clientIds = [],
        public bool $isActive = true,
        public ?string $jobTitle = null,
        public ?string $avatarPath = null,
        public ?int $employeeId = null,
        public bool $mustChangePassword = false,
        public ?ClientAdminOrigin $adminOrigin = null,
        public ?string $documentNumber = null,
        public array $installationIds = [],
        public string $installationPermission = 'admin',
        /** @var list<AccessGrantData> */
        public array $grants = [],
    ) {}
}
