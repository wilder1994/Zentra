<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\DeviceChangeStatus;
use App\Models\DeviceChangeRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class BindSupervisorDevice
{
    public const TTL_MINUTES = 10;

    public const PERMISSION = 'company.devices.approve';

    /**
     * Null when the device may log in; otherwise the 423 payload for the app.
     *
     * @return array{code: string, message: string, expires_at?: string}|null
     */
    public function check(User $user, string $deviceId, ?string $label, ?float $lat, ?float $lng): ?array
    {
        $bound = (string) ($user->mobile_device_id ?? '');

        if ($bound === '') {
            $user->forceFill([
                'mobile_device_id' => $deviceId,
                'mobile_device_label' => $label,
                'mobile_device_bound_at' => now(),
            ])->save();

            return null;
        }

        if (hash_equals($bound, $deviceId)) {
            if ($label !== null && $label !== $user->mobile_device_label) {
                $user->forceFill(['mobile_device_label' => $label])->save();
            }

            return null;
        }

        $this->expireStale($user->id);

        $pending = DeviceChangeRequest::query()
            ->where('user_id', $user->id)
            ->open()
            ->latest('id')
            ->first();

        if ($pending !== null) {
            if ($pending->device_id !== $deviceId) {
                return [
                    'code' => 'device_change_busy',
                    'message' => 'Ya hay una solicitud de cambio de celular en espera. Intenta de nuevo cuando venza.',
                    'expires_at' => $pending->expires_at->toIso8601String(),
                ];
            }

            return $this->payload($pending, 'device_change_pending', 'Solicitud en espera. La central debe aprobar el cambio de celular.');
        }

        $request = DeviceChangeRequest::query()->create([
            'user_id' => $user->id,
            'security_company_id' => $user->security_company_id,
            'device_id' => $deviceId,
            'device_label' => $label,
            'previous_device_id' => $bound,
            'previous_device_label' => $user->mobile_device_label,
            'latitude' => $lat,
            'longitude' => $lng,
            'status' => DeviceChangeStatus::Pendiente->value,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        return $this->payload($request, 'device_change_requested', 'Este celular no es el registrado. Se envió la solicitud a la central; espera la aprobación.');
    }

    public function approve(DeviceChangeRequest $request, User $actor): void
    {
        DB::transaction(function () use ($request, $actor): void {
            $row = DeviceChangeRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->assertOpen($row);

            $user = User::query()->lockForUpdate()->findOrFail($row->user_id);
            $user->forceFill([
                'mobile_device_id' => $row->device_id,
                'mobile_device_label' => $row->device_label,
                'mobile_device_bound_at' => now(),
            ])->save();
            $user->tokens()->where('is_integration', false)->delete();

            $row->forceFill([
                'status' => DeviceChangeStatus::Aprobada->value,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
            ])->save();
        });
    }

    public function reject(DeviceChangeRequest $request, User $actor): void
    {
        DB::transaction(function () use ($request, $actor): void {
            $row = DeviceChangeRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->assertOpen($row);

            $row->forceFill([
                'status' => DeviceChangeStatus::Rechazada->value,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
            ])->save();
        });
    }

    public function canApprove(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->hasAnyRole(['super-admin', 'company-admin']) || $user->can(self::PERMISSION);
    }

    public function expireStale(?int $userId = null, ?int $companyId = null): void
    {
        DeviceChangeRequest::query()
            ->where('status', DeviceChangeStatus::Pendiente->value)
            ->where('expires_at', '<=', now())
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($companyId, fn ($q) => $q->where('security_company_id', $companyId))
            ->update(['status' => DeviceChangeStatus::Vencida->value, 'updated_at' => now()]);
    }

    private function assertOpen(DeviceChangeRequest $row): void
    {
        if ($row->status !== DeviceChangeStatus::Pendiente) {
            throw new ConflictHttpException('La solicitud ya fue atendida.');
        }
        if (! $row->expires_at->isFuture()) {
            throw new ConflictHttpException('La solicitud venció. El supervisor debe pedirla de nuevo.');
        }
    }

    /** @return array{code: string, message: string, expires_at: string} */
    private function payload(DeviceChangeRequest $request, string $code, string $message): array
    {
        return [
            'code' => $code,
            'message' => $message,
            'expires_at' => $request->expires_at->toIso8601String(),
        ];
    }
}
