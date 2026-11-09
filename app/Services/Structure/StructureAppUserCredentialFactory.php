<?php

declare(strict_types=1);

namespace App\Services\Structure;

use App\Models\StructureAppUser;
use App\Models\StructureMember;
use App\Repositories\StructureAppUserRepository;
use Illuminate\Support\Str;

final class StructureAppUserCredentialFactory
{
    public function __construct(
        private readonly StructureAppUserRepository $appUsers,
    ) {}

    public function usernameFor(StructureMember $member, int $clientId): string
    {
        $base = Str::lower(trim(
            Str::slug($member->first_name, '').'.'.Str::slug($member->last_name, ''),
            '.'
        ));
        if ($base === '' || $base === '.') {
            $base = 'usuario';
        }
        $base = Str::limit(Str::replace('-', '', $base), 60, '');

        $candidate = $base;
        $suffix = 2;
        while ($this->appUsers->usernameExists($clientId, $candidate)) {
            $candidate = Str::limit($base, 55, '').$suffix;
            $suffix++;
        }

        return $candidate;
    }

    public function plainPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $password = '';
        for ($i = 0; $i < 10; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }

    public function assertChannel(string $channel): string
    {
        return in_array($channel, [StructureAppUser::CHANNEL_ANDROID, StructureAppUser::CHANNEL_IOS_WEB], true)
            ? $channel
            : StructureAppUser::CHANNEL_ANDROID;
    }
}
