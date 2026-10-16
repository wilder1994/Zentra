<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use JsonSerializable;
use Stringable;
use ValueError;

/**
 * Cupo de Supervisión: `sup_{puestos}`. El plan (metal) sale de la cantidad de puestos.
 * `sup_unlimited` (legado) se lee como 100. Las instancias se reutilizan, así `===` compara por valor.
 */
final class SupervisionPackageSku implements Castable, JsonSerializable, Stringable
{
    public const MAX_SEATS = 10_000;

    /** @var array<string, self> */
    private static array $pool = [];

    private function __construct(
        public readonly string $value,
        private readonly int $size,
    ) {}

    public static function from(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new ValueError('"'.$value.'" no es un cupo de Supervisión válido.');
    }

    public static function tryFrom(?string $value): ?self
    {
        if ($value === 'sup_unlimited') {
            return self::fromSize(100);
        }

        if ($value === null || ! preg_match('/^sup_(\d{1,5})$/', $value, $m)) {
            return null;
        }

        $size = (int) $m[1];
        if ($size < 1 || $size > self::MAX_SEATS) {
            return null;
        }

        return self::$pool['sup_'.$size] ??= new self('sup_'.$size, $size);
    }

    public static function fromSize(int $size): self
    {
        return self::from('sup_'.$size);
    }

    public function size(): int
    {
        return $this->size;
    }

    public function seatCap(): int
    {
        return $this->size;
    }

    public function metal(): CommercialMetal
    {
        return CommercialMetal::fromSeats($this->size);
    }

    public function label(): string
    {
        $posts = $this->size === 1 ? '1 puesto' : "{$this->size} puestos";

        return "{$this->metal()->label()} · {$posts} · Supervisión";
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
            public function get($model, string $key, mixed $value, array $attributes): ?SupervisionPackageSku
            {
                return $value === null || $value === '' ? null : SupervisionPackageSku::tryFrom((string) $value);
            }

            public function set($model, string $key, mixed $value, array $attributes): ?string
            {
                if ($value === null || $value === '') {
                    return null;
                }

                return $value instanceof SupervisionPackageSku ? $value->value : SupervisionPackageSku::from((string) $value)->value;
            }
        };
    }
}
