<?php

declare(strict_types=1);

namespace App\Support\Api;

/**
 * Permisos de las API keys de integración, por módulo y endpoint.
 * Cada ruta de `routes/api.php` declara el suyo con `api.ability:<clave>`.
 */
final class ApiAbilities
{
    /** Solo sesión personal (app de campo, cerrar sesión). Una API key nunca entra. */
    public const HUMAN = 'human';

    public const LEVEL_NONE = 'none';

    public const LEVEL_READ = 'read';

    public const LEVEL_WRITE = 'write';

    public const LEVEL_CUSTOM = 'custom';

    /**
     * @return array<string, array{label: string, hint: string, endpoints: array<string, array{label: string, write: bool, routes: list<string>}>}>
     */
    public static function modules(): array
    {
        return [
            'observatory' => [
                'label' => 'Observatorio',
                'hint' => 'Eventos, tablero, colegios y nuevos reportes (Secretaría u otro sistema).',
                'endpoints' => [
                    'observatory.events.read' => [
                        'label' => 'Eventos: listar y ver',
                        'write' => false,
                        'routes' => ['GET /api/observatory/events', 'GET /api/observatory/events/{id}'],
                    ],
                    'observatory.board.read' => [
                        'label' => 'Tablero (cifras)',
                        'write' => false,
                        'routes' => ['GET /api/observatory/board'],
                    ],
                    'observatory.sites.read' => [
                        'label' => 'Colegios e instalaciones',
                        'write' => false,
                        'routes' => ['GET /api/observatory/sites'],
                    ],
                    'observatory.reports.write' => [
                        'label' => 'Crear reportes',
                        'write' => true,
                        'routes' => ['POST /api/observatory/reports'],
                    ],
                ],
            ],
            'access' => [
                'label' => 'Portería',
                'hint' => 'Preautorizaciones, correspondencia y visitantes del usuario (residente o anfitrión).',
                'endpoints' => [
                    'access.pre_authorizations.read' => [
                        'label' => 'Preautorizaciones: listar y ver',
                        'write' => false,
                        'routes' => ['GET /api/pre-authorizations', 'GET /api/pre-authorizations/{id}'],
                    ],
                    'access.pre_authorizations.write' => [
                        'label' => 'Preautorizaciones: crear y anular',
                        'write' => true,
                        'routes' => ['POST /api/pre-authorizations', 'DELETE /api/pre-authorizations/{id}'],
                    ],
                    'access.correspondence.read' => [
                        'label' => 'Correspondencia',
                        'write' => false,
                        'routes' => ['GET /api/correspondence', 'GET /api/correspondence/{id}'],
                    ],
                    'access.visitors.read' => [
                        'label' => 'Buscar visitantes',
                        'write' => false,
                        'routes' => ['GET /api/visitors/search'],
                    ],
                ],
            ],
        ];
    }

    /** @return list<string> */
    public static function all(): array
    {
        $keys = [];
        foreach (self::modules() as $module) {
            $keys = array_merge($keys, array_keys($module['endpoints']));
        }

        return $keys;
    }

    public static function label(string $ability): string
    {
        foreach (self::modules() as $module) {
            if (isset($module['endpoints'][$ability])) {
                return $module['label'].' · '.$module['endpoints'][$ability]['label'];
            }
        }

        return $ability;
    }

    /**
     * Convierte la elección del formulario (nivel por módulo + endpoints sueltos) en la lista final.
     *
     * @param  array<string, string>  $levels  módulo => none|read|write|custom
     * @param  list<string>  $custom
     * @return list<string>
     */
    public static function resolve(array $levels, array $custom): array
    {
        $abilities = [];
        foreach (self::modules() as $key => $module) {
            $level = $levels[$key] ?? self::LEVEL_NONE;
            foreach ($module['endpoints'] as $ability => $endpoint) {
                $granted = match ($level) {
                    self::LEVEL_READ => ! $endpoint['write'],
                    self::LEVEL_WRITE => true,
                    self::LEVEL_CUSTOM => in_array($ability, $custom, true),
                    default => false,
                };
                if ($granted) {
                    $abilities[] = $ability;
                }
            }
        }

        return $abilities;
    }

    /**
     * Nivel que mostrar en la tabla para un módulo según las abilities guardadas.
     *
     * @param  list<string>  $abilities
     */
    public static function levelFor(string $module, array $abilities): string
    {
        $endpoints = self::modules()[$module]['endpoints'] ?? [];
        $granted = array_values(array_intersect(array_keys($endpoints), $abilities));
        if ($granted === []) {
            return self::LEVEL_NONE;
        }
        if (count($granted) === count($endpoints)) {
            return self::LEVEL_WRITE;
        }
        $reads = array_keys(array_filter($endpoints, fn (array $e): bool => ! $e['write']));
        sort($reads);
        $sorted = $granted;
        sort($sorted);

        return $sorted === $reads ? self::LEVEL_READ : self::LEVEL_CUSTOM;
    }

    public static function levelLabel(string $level): string
    {
        return match ($level) {
            self::LEVEL_READ => 'Lectura',
            self::LEVEL_WRITE => 'Lectura y escritura',
            self::LEVEL_CUSTOM => 'Personalizado',
            default => 'Sin acceso',
        };
    }
}
