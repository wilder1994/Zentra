<?php

declare(strict_types=1);

namespace App\Enums;

enum SupervisorFieldModule: string
{
    case Inventory = 'inventory';
    case ControlBooks = 'control_books';
    case Folders = 'folders';
    case Weapons = 'weapons';
    case Recommendations = 'recommendations';
    case Uniform = 'uniform';
    case Trainings = 'trainings';
    case Alarms = 'alarms';
    case Supports = 'supports';
    case Documents = 'documents';

    public function label(): string
    {
        return match ($this) {
            self::Inventory => 'Inventario',
            self::ControlBooks => 'Libros de control',
            self::Folders => 'Carpetas',
            self::Weapons => 'Armamento',
            self::Recommendations => 'Recomendaciones',
            self::Uniform => 'Presentación personal',
            self::Trainings => 'Capacitación',
            self::Alarms => 'Alarmas',
            self::Supports => 'Apoyos',
            self::Documents => 'Documentos',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Inventory => 'Pase revista a cada elemento del puesto: tipo, estado y observación.',
            self::ControlBooks => 'Libros del puesto: tipo, novedad y observación.',
            self::Folders => 'Carpeta del puesto: completa o con faltantes.',
            self::Weapons => 'Revista del arma: identificación, novedad y aseo opcional con foto.',
            self::Recommendations => 'Hasta tres riesgos del puesto: probabilidad, impacto, consecuencia y evidencia.',
            self::Uniform => 'Obligatoria. Marque el estado de cada prenda del vigilante. Con novedad: observación y fotos.',
            self::Trainings => 'Una por revista para el vigilante de la revista. No se repite el mismo tema en el mes.',
            self::Alarms => 'Prueba o atención de alarma en el sitio: tipo, modalidad, resultado y GPS.',
            self::Supports => 'Apoyo operativo en un cliente: tipo, motivo y GPS.',
            self::Documents => 'Papeles que recogen o entregan en el turno. Sin cliente ni GPS.',
        };
    }

    public function requiresClient(): bool
    {
        return match ($this) {
            self::Alarms, self::Supports => true,
            default => false,
        };
    }

    public function requiresGps(): bool
    {
        return match ($this) {
            self::Alarms, self::Supports => true,
            default => false,
        };
    }

    public function hangsOffReview(): bool
    {
        return match ($this) {
            self::Inventory, self::ControlBooks, self::Folders, self::Weapons, self::Recommendations,
            self::Uniform, self::Trainings => true,
            default => false,
        };
    }

    /** Módulos que solo entiende la app desde APK 2.3 (catálogo con `app_build` >= 23). */
    public function needsAppBuild(): int
    {
        return match ($this) {
            self::Uniform, self::Trainings => 23,
            default => 0,
        };
    }
}
