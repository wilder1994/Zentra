<?php

namespace App\Services\Access;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public function record(?Model $model, string $action, ?array $old = null, ?array $new = null): AuditLog
    {
        $user = auth()->user();
        $webUser = $user instanceof User ? $user : null;
        $clientId = $webUser?->primary_client_id
            ?? app(TenantContext::class)->clientId();

        $targetType = $model?->getMorphClass() ?? null;
        $targetId = $model?->getKey() ?? null;

        if ($model !== null && $old === null) {
            $old = $this->sanitize($model->getOriginal());
        }

        if ($model !== null && $new === null) {
            $new = $this->sanitize($model->getAttributes());
        }

        return AuditLog::create([
            'client_id' => $clientId,
            'user_id' => $webUser?->id,
            'action' => $action,
            'auditable_type' => $targetType,
            'auditable_id' => $targetId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500),
        ]);
    }

    public function created(Model $model): void
    {
        $this->record($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->record($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted');
    }

    public function action(string $action, ?Model $model = null, ?array $old = null, ?array $new = null): AuditLog
    {
        return $this->record($model, $action, $old, $new);
    }

    private function sanitize(array $values): array
    {
        $blocked = ['client_id', 'created_at', 'updated_at', 'password', 'qr_code', 'ip_address', 'user_agent'];

        return collect($values)
            ->except($blocked)
            ->filter(fn ($value) => ! is_null($value))
            ->toArray();
    }
}
