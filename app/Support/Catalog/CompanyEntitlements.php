<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Enums\CommercialMetal;
use App\Enums\CommercialProduct;
use App\Models\SecurityCompany;

final class CompanyEntitlements
{
    public function __construct(private readonly SecurityCompany $company) {}

    public static function for(?SecurityCompany $company): self
    {
        return new self($company ?? new SecurityCompany);
    }

    public function hasAccess(): bool
    {
        return (int) ($this->company->max_clients ?? 0) > 0;
    }

    public function hasSupervision(): bool
    {
        return $this->company->exists && $this->company->hasSupervisionPackage();
    }

    public function hasIndexing(): bool
    {
        if ($this->company->exists && $this->company->has_indexing !== null) {
            return (bool) $this->company->has_indexing;
        }

        return $this->hasAccess() || $this->hasSupervision();
    }

    public function hasObservatory(): bool
    {
        if (! $this->hasAccess()) {
            return false;
        }

        if ($this->company->exists && $this->company->has_observatory !== null) {
            return (bool) $this->company->has_observatory;
        }

        return true;
    }

    public function accessMetal(): ?CommercialMetal
    {
        $seats = (int) ($this->company->max_clients ?? 0);

        return $seats > 0 ? CommercialMetal::fromSeats($seats) : null;
    }

    public function supervisionMetal(): ?CommercialMetal
    {
        if (! $this->hasSupervision()) {
            return null;
        }

        if ($this->company->hasUnlimitedSupervision()) {
            return CommercialMetal::Platino;
        }

        $seats = (int) ($this->company->max_supervision_clients ?: $this->company->supervision_package_size ?: 0);

        return $seats > 0 ? CommercialMetal::fromSeats($seats) : CommercialMetal::Bronce;
    }

    public function employeeCap(): int
    {
        $caps = [];
        $access = $this->accessMetal();
        $supervision = $this->supervisionMetal();
        if ($access) {
            $caps[] = $access->employeeCap();
        }
        if ($supervision) {
            $caps[] = $supervision->employeeCap();
        }
        if ($caps !== []) {
            return max($caps);
        }

        if ($this->hasIndexing() && ! $this->hasAccess() && ! $this->hasSupervision()) {
            return CommercialMetal::Bronce->employeeCap();
        }

        return 0;
    }

    /** @return list<string> */
    public function modules(): array
    {
        $catalog = CatalogSettings::current();
        $mods = [];

        if ($this->hasAccess()) {
            $mods = array_merge($mods, $catalog->modules(CommercialProduct::Access));
        }
        if ($this->hasSupervision()) {
            $mods = array_merge($mods, $catalog->modules(CommercialProduct::Supervision));
        }
        if ($this->hasIndexing()) {
            $mods = array_merge($mods, $catalog->modules(CommercialProduct::Indexing));
        }
        if ($this->hasObservatory()) {
            $mods = array_merge($mods, $catalog->modules(CommercialProduct::Observatory));
        }

        $unique = array_values(array_unique($mods));

        return $unique === [] && $this->company->exists
            ? $catalog->modules(CommercialProduct::Access)
            : $unique;
    }

    public function allows(string $module): bool
    {
        return in_array($module, $this->modules(), true);
    }
}
