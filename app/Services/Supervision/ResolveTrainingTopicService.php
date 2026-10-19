<?php

declare(strict_types=1);

namespace App\Services\Supervision;

use App\Models\SupervisorTrainingTopic;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/** Tema de capacitación por id del catálogo o por nombre (lo crea si no existe, sin duplicar por tildes ni mayúsculas). */
final class ResolveTrainingTopicService
{
    public function byId(int $companyId, int $topicId): SupervisorTrainingTopic
    {
        $topic = SupervisorTrainingTopic::query()
            ->where('security_company_id', $companyId)
            ->whereKey($topicId)
            ->first();

        if ($topic === null) {
            throw ValidationException::withMessages(['topic' => 'El tema de capacitación no existe en esta empresa.']);
        }

        return $topic;
    }

    public function byName(int $companyId, string $name, ?User $creator, string $from = SupervisorTrainingTopic::FROM_APP): SupervisorTrainingTopic
    {
        $clean = SupervisorTrainingTopic::clean($name);
        $normalized = SupervisorTrainingTopic::normalize($clean);
        if (mb_strlen($normalized) < 3) {
            throw ValidationException::withMessages(['topic' => 'Escriba un tema de al menos 3 letras.']);
        }

        $existing = $this->findNormalized($companyId, $normalized);
        if ($existing !== null) {
            if (! $existing->is_active) {
                $existing->update(['is_active' => true]);
            }

            return $existing;
        }

        try {
            return SupervisorTrainingTopic::query()->create([
                'security_company_id' => $companyId,
                'name' => $clean,
                'normalized_name' => $normalized,
                'is_active' => true,
                'sort_order' => (int) SupervisorTrainingTopic::query()->where('security_company_id', $companyId)->max('sort_order') + 10,
                'created_by_user_id' => $creator?->id,
                'created_from' => $from,
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->findNormalized($companyId, $normalized)
                ?? throw ValidationException::withMessages(['topic' => 'No se pudo crear el tema.']);
        }
    }

    /** @param  array<string, mixed>  $payload */
    public function fromPayload(int $companyId, array $payload, ?User $creator): SupervisorTrainingTopic
    {
        if (! empty($payload['topic_id'])) {
            return $this->byId($companyId, (int) $payload['topic_id']);
        }

        return $this->byName($companyId, (string) ($payload['topic_name'] ?? ''), $creator);
    }

    private function findNormalized(int $companyId, string $normalized): ?SupervisorTrainingTopic
    {
        return SupervisorTrainingTopic::query()
            ->where('security_company_id', $companyId)
            ->where('normalized_name', $normalized)
            ->first();
    }
}
