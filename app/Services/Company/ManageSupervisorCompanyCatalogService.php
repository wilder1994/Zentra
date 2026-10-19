<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Enums\SupervisorChecklistKind;
use App\Models\SupervisorAlarmType;
use App\Models\SupervisorChecklistItem;
use App\Models\SupervisorControlBookType;
use App\Models\SupervisorDocumentType;
use App\Models\SupervisorPostModality;
use App\Models\SupervisorShiftTemplate;
use App\Models\SupervisorSupportType;
use App\Models\SupervisorWeaponBrand;
use App\Models\SupervisorWeaponType;
use App\Models\SupervisorRiskType;
use App\Models\SupervisorZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class ManageSupervisorCompanyCatalogService
{
    public function __construct(
        private readonly SeedSupervisorIntakeDefaultsService $defaults,
    ) {}

    public function ensureDefaults(int $companyId): void
    {
        $this->defaults->execute($companyId);
    }

    /** @param array{name: string, is_active?: bool} $data */
    public function createZone(int $companyId, array $data): SupervisorZone
    {
        $name = trim($data['name']);
        $this->assertUnique(SupervisorZone::class, $companyId, $name);

        return SupervisorZone::query()->create([
            'security_company_id' => $companyId,
            'name' => $name,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => $this->nextOrder(SupervisorZone::class, $companyId),
        ]);
    }

    /** @param array{name?: string, is_active?: bool} $data */
    public function updateZone(SupervisorZone $zone, array $data): SupervisorZone
    {
        return $this->updateNamed($zone, $data);
    }

    public function deleteZone(SupervisorZone $zone): void
    {
        $zone->delete();
    }

    /** @param array{name: string, is_active?: bool} $data */
    public function createDocumentType(int $companyId, array $data): SupervisorDocumentType
    {
        $name = trim($data['name']);
        $this->assertUnique(SupervisorDocumentType::class, $companyId, $name);

        return SupervisorDocumentType::query()->create([
            'security_company_id' => $companyId,
            'name' => $name,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => $this->nextOrder(SupervisorDocumentType::class, $companyId),
        ]);
    }

    /** @param array{name?: string, is_active?: bool} $data */
    public function updateDocumentType(SupervisorDocumentType $type, array $data): SupervisorDocumentType
    {
        return $this->updateNamed($type, $data);
    }

    public function deleteDocumentType(SupervisorDocumentType $type): void
    {
        $type->delete();
    }

    /** @param array{name: string, is_active?: bool} $data */
    public function createControlBookType(int $companyId, array $data): SupervisorControlBookType
    {
        $name = trim($data['name']);
        $this->assertUnique(SupervisorControlBookType::class, $companyId, $name);

        return SupervisorControlBookType::query()->create([
            'security_company_id' => $companyId,
            'name' => $name,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => $this->nextOrder(SupervisorControlBookType::class, $companyId),
        ]);
    }

    /** @param array{name?: string, is_active?: bool} $data */
    public function updateControlBookType(SupervisorControlBookType $type, array $data): SupervisorControlBookType
    {
        return $this->updateNamed($type, $data);
    }

    public function deleteControlBookType(SupervisorControlBookType $type): void
    {
        $type->delete();
    }

    /** @param array{name: string, is_active?: bool} $data */
    public function createWeaponType(int $companyId, array $data): SupervisorWeaponType
    {
        $name = trim($data['name']);
        $this->assertUnique(SupervisorWeaponType::class, $companyId, $name);

        return SupervisorWeaponType::query()->create([
            'security_company_id' => $companyId,
            'name' => $name,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => $this->nextOrder(SupervisorWeaponType::class, $companyId),
        ]);
    }

    /** @param array{name?: string, is_active?: bool} $data */
    public function updateWeaponType(SupervisorWeaponType $type, array $data): SupervisorWeaponType
    {
        return $this->updateNamed($type, $data);
    }

    public function deleteWeaponType(SupervisorWeaponType $type): void
    {
        $type->delete();
    }

    /** @param array{name: string, is_active?: bool} $data */
    public function createWeaponBrand(int $companyId, array $data): SupervisorWeaponBrand
    {
        $name = trim($data['name']);
        $this->assertUnique(SupervisorWeaponBrand::class, $companyId, $name);

        return SupervisorWeaponBrand::query()->create([
            'security_company_id' => $companyId,
            'name' => $name,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => $this->nextOrder(SupervisorWeaponBrand::class, $companyId),
        ]);
    }

    /** @param array{name?: string, is_active?: bool} $data */
    public function updateWeaponBrand(SupervisorWeaponBrand $brand, array $data): SupervisorWeaponBrand
    {
        return $this->updateNamed($brand, $data);
    }

    public function deleteWeaponBrand(SupervisorWeaponBrand $brand): void
    {
        $brand->delete();
    }

    /** @param array{name: string, is_active?: bool} $data */
    public function createRiskType(int $companyId, array $data): SupervisorRiskType
    {
        $name = trim($data['name']);
        $this->assertUnique(SupervisorRiskType::class, $companyId, $name);

        return SupervisorRiskType::query()->create([
            'security_company_id' => $companyId,
            'name' => $name,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => $this->nextOrder(SupervisorRiskType::class, $companyId),
        ]);
    }

    /** @param array{name?: string, is_active?: bool} $data */
    public function updateRiskType(SupervisorRiskType $type, array $data): SupervisorRiskType
    {
        return $this->updateNamed($type, $data);
    }

    public function deleteRiskType(SupervisorRiskType $type): void
    {
        $type->delete();
    }

    /** @param array{name: string, is_active?: bool} $data */
    public function createAlarmType(int $companyId, array $data): SupervisorAlarmType
    {
        $name = trim($data['name']);
        $this->assertUnique(SupervisorAlarmType::class, $companyId, $name);

        return SupervisorAlarmType::query()->create([
            'security_company_id' => $companyId,
            'name' => $name,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => $this->nextOrder(SupervisorAlarmType::class, $companyId),
        ]);
    }

    /** @param array{name?: string, is_active?: bool} $data */
    public function updateAlarmType(SupervisorAlarmType $type, array $data): SupervisorAlarmType
    {
        return $this->updateNamed($type, $data);
    }

    public function deleteAlarmType(SupervisorAlarmType $type): void
    {
        $type->delete();
    }

    /** @param array{name: string, is_active?: bool} $data */
    public function createSupportType(int $companyId, array $data): SupervisorSupportType
    {
        $name = trim($data['name']);
        $this->assertUnique(SupervisorSupportType::class, $companyId, $name);

        return SupervisorSupportType::query()->create([
            'security_company_id' => $companyId,
            'name' => $name,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => $this->nextOrder(SupervisorSupportType::class, $companyId),
        ]);
    }

    /** @param array{name?: string, is_active?: bool} $data */
    public function updateSupportType(SupervisorSupportType $type, array $data): SupervisorSupportType
    {
        return $this->updateNamed($type, $data);
    }

    public function deleteSupportType(SupervisorSupportType $type): void
    {
        $type->delete();
    }

    /** @param array{hours: int, name?: ?string, is_active?: bool} $data */
    public function createPostModality(int $companyId, array $data): SupervisorPostModality
    {
        $hours = (int) $data['hours'];
        $this->assertUniqueHours($companyId, $hours);

        return SupervisorPostModality::query()->create([
            'security_company_id' => $companyId,
            'hours' => $hours,
            'name' => filled($data['name'] ?? null) ? trim((string) $data['name']) : null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => $this->nextOrder(SupervisorPostModality::class, $companyId),
        ]);
    }

    /** @param array{hours?: int, name?: ?string, is_active?: bool} $data */
    public function updatePostModality(SupervisorPostModality $modality, array $data): SupervisorPostModality
    {
        if (isset($data['hours'])) {
            $hours = (int) $data['hours'];
            $this->assertUniqueHours((int) $modality->security_company_id, $hours, $modality->id);
            $modality->hours = $hours;
        }
        if (array_key_exists('name', $data)) {
            $modality->name = filled($data['name']) ? trim((string) $data['name']) : null;
        }
        if (array_key_exists('is_active', $data)) {
            $modality->is_active = (bool) $data['is_active'];
        }
        $modality->save();

        return $modality->refresh();
    }

    public function deletePostModality(SupervisorPostModality $modality): void
    {
        $modality->delete();
    }

    /**
     * @param  array{name: string, starts_at: string, ends_at: string, is_active?: bool}  $data
     */
    public function createTemplate(int $companyId, array $data): SupervisorShiftTemplate
    {
        $name = trim($data['name']);
        $this->assertUnique(SupervisorShiftTemplate::class, $companyId, $name);

        return SupervisorShiftTemplate::query()->create([
            'security_company_id' => $companyId,
            'name' => $name,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => $this->nextOrder(SupervisorShiftTemplate::class, $companyId),
        ]);
    }

    /**
     * @param  array{name?: string, starts_at?: string, ends_at?: string, is_active?: bool}  $data
     */
    public function updateTemplate(SupervisorShiftTemplate $template, array $data): SupervisorShiftTemplate
    {
        if (isset($data['name'])) {
            $name = trim((string) $data['name']);
            $this->assertUnique(SupervisorShiftTemplate::class, (int) $template->security_company_id, $name, $template->id);
            $template->name = $name;
        }
        if (isset($data['starts_at'])) {
            $template->starts_at = $data['starts_at'];
        }
        if (isset($data['ends_at'])) {
            $template->ends_at = $data['ends_at'];
        }
        if (array_key_exists('is_active', $data)) {
            $template->is_active = (bool) $data['is_active'];
        }
        $template->save();

        return $template->refresh();
    }

    public function deleteTemplate(SupervisorShiftTemplate $template): void
    {
        $template->delete();
    }

    /**
     * @param  array{name: string, is_active?: bool}  $data
     */
    public function createItem(int $companyId, SupervisorChecklistKind $kind, array $data): SupervisorChecklistItem
    {
        $name = trim($data['name']);
        $key = $this->uniqueItemKey($companyId, $kind, $this->defaults->nextKey($name));

        return SupervisorChecklistItem::query()->create([
            'security_company_id' => $companyId,
            'kind' => $kind,
            'item_key' => $key,
            'name' => $name,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => $this->nextItemOrder($companyId, $kind),
        ]);
    }

    /** @param array{name?: string, is_active?: bool} $data */
    public function updateItem(SupervisorChecklistItem $item, array $data): SupervisorChecklistItem
    {
        if (isset($data['name'])) {
            $item->name = trim((string) $data['name']);
        }
        if (array_key_exists('is_active', $data)) {
            $item->is_active = (bool) $data['is_active'];
        }
        $item->save();

        return $item->refresh();
    }

    public function deleteItem(SupervisorChecklistItem $item): void
    {
        $item->delete();
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function nextOrder(string $model, int $companyId): int
    {
        $max = (int) $model::query()->where('security_company_id', $companyId)->max('sort_order');

        return $max + 10;
    }

    private function nextItemOrder(int $companyId, SupervisorChecklistKind $kind): int
    {
        $max = (int) SupervisorChecklistItem::query()
            ->where('security_company_id', $companyId)
            ->where('kind', $kind)
            ->max('sort_order');

        return $max + 10;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function assertUnique(string $model, int $companyId, string $name, ?int $ignoreId = null): void
    {
        $exists = $model::query()
            ->where('security_company_id', $companyId)
            ->where('name', $name)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'Ya existe un ítem con ese nombre en este catálogo.',
            ]);
        }
    }

    private function assertUniqueHours(int $companyId, int $hours, ?int $ignoreId = null): void
    {
        $exists = SupervisorPostModality::query()
            ->where('security_company_id', $companyId)
            ->where('hours', $hours)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'hours' => 'Ya existe una modalidad con esas horas.',
            ]);
        }
    }

    /** @param array{name?: string, is_active?: bool} $data */
    private function updateNamed(SupervisorZone|SupervisorDocumentType|SupervisorControlBookType|SupervisorWeaponType|SupervisorWeaponBrand|SupervisorRiskType|SupervisorAlarmType|SupervisorSupportType $row, array $data): SupervisorZone|SupervisorDocumentType|SupervisorControlBookType|SupervisorWeaponType|SupervisorWeaponBrand|SupervisorRiskType|SupervisorAlarmType|SupervisorSupportType
    {
        if (isset($data['name'])) {
            $name = trim((string) $data['name']);
            $this->assertUnique($row::class, (int) $row->security_company_id, $name, $row->id);
            $row->name = $name;
        }
        if (array_key_exists('is_active', $data)) {
            $row->is_active = (bool) $data['is_active'];
        }
        $row->save();

        return $row->refresh();
    }

    private function uniqueItemKey(int $companyId, SupervisorChecklistKind $kind, string $base): string
    {
        $key = $base;
        $i = 2;
        while (
            SupervisorChecklistItem::query()
                ->where('security_company_id', $companyId)
                ->where('kind', $kind)
                ->where('item_key', $key)
                ->exists()
        ) {
            $key = $base.'_'.$i;
            $i++;
        }

        return $key;
    }
}
