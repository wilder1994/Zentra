<?php

declare(strict_types=1);

namespace App\Services\Observatory;

final class BuildObservatoryOpenApiSpec
{
    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $server = rtrim((string) config('app.url'), '/');

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Zentra Observatorio API',
                'version' => '1.0.0',
                'description' => implode("\n\n", [
                    'Eventos y riesgos escolares del **Observatorio**. No incluye censo, portería ni facturación.',
                    'Alcance: el token ve solo los colegios del cliente (Secretaría) o de la empresa. El admin de instalaciones ve solo sus instalaciones.',
                    'Autenticación: `POST /api/auth/login` → `Authorization: Bearer {token}`.',
                    'Un reporte nuevo se une al folio abierto del mismo colegio y tipo si el último de ese tipo tiene menos de 1 hora.',
                ]),
            ],
            'servers' => [
                ['url' => $server, 'description' => 'Zentra'],
            ],
            'tags' => [
                ['name' => 'Sesión', 'description' => 'Token Sanctum'],
                ['name' => 'Observatorio', 'description' => 'Eventos, reportes y riesgos'],
            ],
            'paths' => [
                '/api/auth/login' => [
                    'post' => [
                        'tags' => ['Sesión'],
                        'summary' => 'Obtener token',
                        'operationId' => 'observatoryLogin',
                        'security' => [],
                        'requestBody' => $this->jsonBody([
                            'email' => ['type' => 'string', 'format' => 'email', 'example' => 'admin@palmasdelingenio.test'],
                            'password' => ['type' => 'string', 'format' => 'password'],
                            'device_name' => ['type' => 'string', 'example' => 'secretaria-gis'],
                        ], ['email', 'password']),
                        'responses' => [
                            '200' => $this->jsonResponse('Token emitido', [
                                'token' => ['type' => 'string'],
                                'user' => ['type' => 'object'],
                            ]),
                            '422' => $this->error(422, 'Credenciales inválidas'),
                        ],
                    ],
                ],
                '/api/observatory/events' => [
                    'get' => [
                        'tags' => ['Observatorio'],
                        'summary' => 'Listar eventos',
                        'operationId' => 'listObservatoryEvents',
                        'parameters' => [
                            $this->query('status', 'nuevo | en_atencion | cerrado'),
                            $this->query('from', 'Fecha inicial (Y-m-d)'),
                            $this->query('to', 'Fecha final (Y-m-d)'),
                            $this->query('installation_id', 'Filtrar por colegio', 'integer'),
                            $this->query('q', 'Buscar instalación, DANE o título'),
                            $this->query('client_id', 'Solo empresa: acotar a un cliente', 'integer'),
                            $this->query('page', 'Página', 'integer'),
                        ],
                        'responses' => [
                            '200' => $this->jsonResponse('Listado paginado', [
                                'data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Event']],
                                'meta' => ['$ref' => '#/components/schemas/PageMeta'],
                            ]),
                            '401' => $this->error(401, 'Sin token'),
                            '403' => $this->error(403, 'Fuera de alcance'),
                        ],
                    ],
                ],
                '/api/observatory/events/{id}' => [
                    'get' => [
                        'tags' => ['Observatorio'],
                        'summary' => 'Ver folio',
                        'operationId' => 'showObservatoryEvent',
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                        ],
                        'responses' => [
                            '200' => $this->jsonResponse('Evento con reportes', [
                                'event' => ['$ref' => '#/components/schemas/EventDetail'],
                            ]),
                            '404' => $this->error(404, 'No encontrado'),
                        ],
                    ],
                ],
                '/api/observatory/board' => [
                    'get' => [
                        'tags' => ['Observatorio'],
                        'summary' => 'Tablero y riesgos',
                        'operationId' => 'observatoryBoard',
                        'parameters' => [
                            $this->query('from', 'Fecha inicial (Y-m-d)'),
                            $this->query('to', 'Fecha final (Y-m-d)'),
                            $this->query('client_id', 'Solo empresa: acotar a un cliente', 'integer'),
                        ],
                        'responses' => [
                            '200' => $this->jsonResponse('KPIs, tendencia, tipos, canales y ranking', [
                                'board' => ['$ref' => '#/components/schemas/Board'],
                            ]),
                        ],
                    ],
                ],
                '/api/observatory/sites' => [
                    'get' => [
                        'tags' => ['Observatorio'],
                        'summary' => 'Colegios del alcance',
                        'operationId' => 'observatorySites',
                        'parameters' => [
                            $this->query('q', 'Nombre o DANE'),
                            $this->query('client_id', 'Solo empresa: acotar a un cliente', 'integer'),
                        ],
                        'responses' => [
                            '200' => $this->jsonResponse('Colegios activos', [
                                'sites' => [
                                    'type' => 'array',
                                    'items' => ['$ref' => '#/components/schemas/Site'],
                                ],
                                'kinds' => ['type' => 'object', 'additionalProperties' => ['type' => 'string']],
                            ]),
                        ],
                    ],
                ],
                '/api/observatory/reports' => [
                    'post' => [
                        'tags' => ['Observatorio'],
                        'summary' => 'Crear reporte',
                        'operationId' => 'createObservatoryReport',
                        'description' => 'Secretaría (`client-admin`): canal Integración. Rector o apoyo: mismo origen que el panel. La empresa solo lee.',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/NewReport'],
                                ],
                                'multipart/form-data' => [
                                    'schema' => [
                                        'allOf' => [
                                            ['$ref' => '#/components/schemas/NewReport'],
                                            ['type' => 'object', 'properties' => [
                                                'photo' => ['type' => 'string', 'format' => 'binary'],
                                            ]],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => $this->jsonResponse('Reporte creado o unido a un folio abierto', [
                                'report' => ['$ref' => '#/components/schemas/CreatedReport'],
                            ]),
                            '403' => $this->error(403, 'La empresa no escribe por API'),
                            '422' => $this->error(422, 'Validación'),
                        ],
                    ],
                ],
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'Sanctum',
                    ],
                ],
                'schemas' => [
                    'Event' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer', 'example' => 12],
                            'folio' => ['type' => 'string', 'example' => 'EV-000012'],
                            'status' => ['type' => 'string', 'enum' => ['nuevo', 'en_atencion', 'cerrado']],
                            'status_label' => ['type' => 'string'],
                            'title' => ['type' => 'string'],
                            'opened_at' => ['type' => 'string', 'format' => 'date-time'],
                            'closed_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                            'reports_count' => ['type' => 'integer'],
                            'installation' => ['$ref' => '#/components/schemas/Site'],
                            'client' => ['$ref' => '#/components/schemas/Client'],
                        ],
                    ],
                    'EventDetail' => [
                        'allOf' => [
                            ['$ref' => '#/components/schemas/Event'],
                            ['type' => 'object', 'properties' => [
                                'reports' => [
                                    'type' => 'array',
                                    'items' => ['$ref' => '#/components/schemas/Report'],
                                ],
                            ]],
                        ],
                    ],
                    'Report' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'kind' => ['type' => 'string'],
                            'kind_label' => ['type' => 'string'],
                            'level' => ['type' => 'integer'],
                            'color' => ['type' => 'string'],
                            'source' => ['type' => 'string', 'enum' => ['comunidad', 'panel', 'campo', 'porteria', 'api']],
                            'source_label' => ['type' => 'string'],
                            'reporter_role' => ['type' => 'string'],
                            'reporter_role_label' => ['type' => 'string'],
                            'body' => ['type' => 'string'],
                            'is_anonymous' => ['type' => 'boolean'],
                            'reporter_name' => ['type' => 'string', 'nullable' => true],
                            'reporter_phone' => ['type' => 'string', 'nullable' => true],
                            'photo_url' => ['type' => 'string', 'nullable' => true],
                            'latitude' => ['type' => 'number', 'nullable' => true],
                            'longitude' => ['type' => 'number', 'nullable' => true],
                            'created_at' => ['type' => 'string', 'format' => 'date-time'],
                        ],
                    ],
                    'NewReport' => [
                        'type' => 'object',
                        'required' => ['installation_id', 'kind', 'body'],
                        'properties' => [
                            'installation_id' => ['type' => 'integer'],
                            'kind' => ['type' => 'string', 'description' => 'Slug del tipo configurado por el cliente'],
                            'body' => ['type' => 'string', 'minLength' => 10, 'maxLength' => 2000],
                            'is_anonymous' => ['type' => 'boolean', 'default' => false],
                            'reporter_name' => ['type' => 'string'],
                            'reporter_phone' => ['type' => 'string'],
                            'latitude' => ['type' => 'number'],
                            'longitude' => ['type' => 'number'],
                        ],
                    ],
                    'CreatedReport' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'event_id' => ['type' => 'integer'],
                            'folio' => ['type' => 'string'],
                            'merged' => ['type' => 'boolean'],
                        ],
                    ],
                    'Site' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                            'dane_code' => ['type' => 'string', 'nullable' => true],
                            'city' => ['type' => 'string', 'nullable' => true],
                            'latitude' => ['type' => 'number', 'nullable' => true],
                            'longitude' => ['type' => 'number', 'nullable' => true],
                        ],
                    ],
                    'Client' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                            'slug' => ['type' => 'string'],
                        ],
                    ],
                    'Board' => [
                        'type' => 'object',
                        'properties' => [
                            'total' => ['type' => 'integer'],
                            'nuevo' => ['type' => 'integer'],
                            'en_atencion' => ['type' => 'integer'],
                            'cerrado' => ['type' => 'integer'],
                            'closed_rate' => ['type' => 'integer', 'description' => 'Porcentaje de eventos cerrados (0 si no hay)'],
                            'load_rate' => ['type' => 'integer', 'description' => 'Atención: (nuevos + en trámite × 0,4) / total × 100. Rojo = cola, verde = cerrados'],
                            'top' => ['type' => 'array', 'items' => ['type' => 'object']],
                            'trend' => ['$ref' => '#/components/schemas/Series'],
                            'kinds' => ['$ref' => '#/components/schemas/Series'],
                            'sources' => ['$ref' => '#/components/schemas/Series'],
                        ],
                    ],
                    'Series' => [
                        'type' => 'object',
                        'properties' => [
                            'labels' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'values' => ['type' => 'array', 'items' => ['type' => 'integer']],
                        ],
                    ],
                    'PageMeta' => [
                        'type' => 'object',
                        'properties' => [
                            'current_page' => ['type' => 'integer'],
                            'last_page' => ['type' => 'integer'],
                            'per_page' => ['type' => 'integer'],
                            'total' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
            'security' => [
                ['bearerAuth' => []],
            ],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @param  list<string>  $required
     * @return array<string, mixed>
     */
    private function jsonBody(array $properties, array $required = []): array
    {
        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => $required,
                        'properties' => $properties,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function jsonResponse(string $description, array $properties): array
    {
        return [
            'description' => $description,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => $properties,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function query(string $name, string $description, string $type = 'string'): array
    {
        return [
            'name' => $name,
            'in' => 'query',
            'required' => false,
            'description' => $description,
            'schema' => ['type' => $type],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function error(int $status, string $description): array
    {
        return [
            'description' => $description,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'message' => ['type' => 'string'],
                            'errors' => ['type' => 'object'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
