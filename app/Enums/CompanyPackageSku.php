<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use JsonSerializable;
use Stringable;
use ValueError;

/**
 * Cupo de Accesos: `pack_{instalaciones}_{manual|hardware}`. El plan (metal) sale de la cantidad.
 * `pack_0_manual` = empresa solo con Supervisión. Las instancias se reutilizan, así `===` compara por valor.
 */
final class CompanyPackageSku implements Castable, JsonSerializable, Stringable
{
    public const MAX_SEATS = 10_000;

    /** @var array<string, self> */
    private static array $pool = [];

    private function __construct(
        public readonly string $value,
        private readonly int $size,
        private readonly PackageModality $modality,
    ) {}

    public static function from(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new ValueError('"'.$value.'" no es un cupo de Accesos válido.');
    }

    public static function tryFrom(?string $value): ?self
    {
        if ($value === null || ! preg_match('/^pack_(\d{1,5})_(manual|hardware)$/', $value, $m)) {
            return null;
        }

        $size = (int) $m[1];
        if ($size > self::MAX_SEATS) {
            return null;
        }

        $key = "pack_{$size}_{$m[2]}";

        return self::$pool[$key] ??= new self($key, $size, PackageModality::from($m[2]));
    }

    public static function fromParts(int $size, PackageModality $modality): self
    {
        $stored = $modality === PackageModality::Mixed ? PackageModality::Manual : $modality;

        return self::from('pack_'.max(0, $size).'_'.$stored->value);
    }

    public function size(): int
    {
        return $this->size;
    }

    public function modality(): PackageModality
    {
        return $this->modality;
    }

    public function hasAccess(): bool
    {
        return $this->size > 0;
    }

    public function metal(): ?CommercialMetal
    {
        return CommercialMetal::forSeats($this->size);
    }

    public function label(): string
    {
        if ($this->size === 0) {
            return 'Sin Accesos';
        }

        $sites = $this->size === 1 ? '1 instalación' : "{$this->size} instalaciones";

        return "{$this->metal()?->label()} · {$sites} · {$this->modality->label()}";
    }

    /** Clave del contrato legal: un contrato por plan y modalidad. */
    public function contractKey(): string
    {
        return ($this->metal() ?? CommercialMetal::Bronce)->value.'_'.$this->modality->value;
    }

    public function allowsSupervision(): bool
    {
        return true;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            public function get($model, string $key, mixed $value, array $attributes): ?CompanyPackageSku
            {
                return $value === null || $value === '' ? null : CompanyPackageSku::tryFrom((string) $value);
            }

            public function set($model, string $key, mixed $value, array $attributes): ?string
            {
                if ($value === null || $value === '') {
                    return null;
                }

                return $value instanceof CompanyPackageSku ? $value->value : CompanyPackageSku::from((string) $value)->value;
            }
        };
    }
}
