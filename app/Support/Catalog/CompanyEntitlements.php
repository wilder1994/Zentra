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

    public function accessSeats(): int
    {
        return (int) ($this->company->max_clients ?? 0);
    }

    public function supervisionSeats(): int
    {
        if (! $this->company->exists || ! $this->company->hasSupervisionPackage()) {
            return 0;
        }

        return (int) ($this->company->max_supervision_clients ?: $this->company->supervision_package_size ?: 0);
    }

    public function indexingSeats(): int
    {
        return (int) ($this->company->indexing_seats ?? 0);
    }

    public function observatorySeats(): int
    {
        return (int) ($this->company->observatory_seats ?? 0);
    }

    /** Bolsa compartida de instalaciones: la mayor cantidad entre los productos contratados. */
    public function installationCapacity(): int
    {
        return max($this->accessSeats(), $this->supervisionSeats(), $this->indexingSeats(), $this->observatorySeats());
    }

    public function hasAccess(): bool
    {
        return $this->accessSeats() > 0;
    }

    public function hasSupervision(): bool
    {
        return $this->company->exists && $this->company->hasSupervisionPackage();
    }

    public function hasIndexing(): bool
    {
        return $this->indexingSeats() > 0;
    }

    public function hasObservatory(): bool
    {
        return $this->observatorySeats() > 0;
    }

    public function accessMetal(): ?CommercialMetal
    {
        return $this->metalOf($this->accessSeats());
    }

    public function supervisionMetal(): ?CommercialMetal
    {
        if (! $this->hasSupervision()) {
            return null;
        }

        if ($this->company->hasUnlimitedSupervision()) {
            return CommercialMetal::Platino;
        }

        return $this->metalOf($this->supervisionSeats()) ?? CommercialMetal::Bronce;
    }

    public function indexingMetal(): ?CommercialMetal
    {
        return $this->metalOf($this->indexingSeats());
    }

    public function observatoryMetal(): ?CommercialMetal
    {
        return $this->metalOf($this->observatorySeats());
    }

    /** Al combinar productos de distinto plan rige el más alto. */
    public function metal(): ?CommercialMetal
    {
        return CommercialMetal::highest(
            $this->accessMetal(),
            $this->supervisionMetal(),
            $this->indexingMetal(),
            $this->observatoryMetal(),
        );
    }

    public function planEmployeeCap(): int
    {
        return $this->metal()?->employeeCap() ?? 0;
    }

    public function employeeCap(): int
    {
        $base = $this->planEmployeeCap();

        return $base > 0 ? $base + (int) ($this->company->extra_employees ?? 0) : 0;
    }

    public function postsPerInstallation(): int
    {
        $override = $this->company->posts_per_installation;
        if ($override !== null) {
            return (int) $override;
        }

        return $this->metal()?->postsPerInstallation() ?? 0;
    }

    public function employeesPerPost(): int
    {
        $override = $this->company->employees_per_post;
        if ($override !== null) {
            return (int) $override;
        }

        return $this->metal()?->employeesPerPost() ?? 0;
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

    private function metalOf(int $seats): ?CommercialMetal
    {
        return $seats > 0 ? CommercialMetal::fromSeats($seats) : null;
    }
}
