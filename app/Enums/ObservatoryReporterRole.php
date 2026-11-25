<?php

declare(strict_types=1);

namespace App\Enums;

enum ObservatoryReporterRole: string
{
    case Alumno = 'alumno';
    case Padre = 'padre';
    case Vecino = 'vecino';
    case Rector = 'rector';
    case Apoyo = 'apoyo';
    case Supervisor = 'supervisor';
    case Vigilante = 'vigilante';
    case Integracion = 'integracion';

    public function label(): string
    {
        return match ($this) {
            self::Alumno => 'Alumno',
            self::Padre => 'Padre',
            self::Vecino => 'Vecino',
            self::Rector => 'Rector',
            self::Apoyo => 'Apoyo',
            self::Supervisor => 'Supervisor',
            self::Vigilante => 'Vigilante',
            self::Integracion => 'Sistema externo',
        };
    }

    public function isPublic(): bool
    {
        return in_array($this, [self::Alumno, self::Padre, self::Vecino], true);
    }

    /** @return array<string, string> */
    public static function publicOptions(): array
    {
        $out = [];
        foreach ([self::Alumno, self::Padre, self::Vecino] as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }

    public function allowedFor(ObservatoryReportSource $source): bool
    {
        return match ($source) {
            ObservatoryReportSource::Whatsapp, ObservatoryReportSource::Link => $this->isPublic(),
            ObservatoryReportSource::Panel => in_array($this, [self::Rector, self::Apoyo], true),
            ObservatoryReportSource::Api => in_array($this, [self::Rector, self::Apoyo, self::Integracion], true),
            ObservatoryReportSource::Campo => in_array($this, [self::Supervisor, self::Vigilante], true),
        };
    }
}
