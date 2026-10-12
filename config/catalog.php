<?php

declare(strict_types=1);

return [
    'module_options' => [
        'dashboard' => 'Mi empresa',
        'billing' => 'Facturación',
        'clients' => 'Clientes',
        'installations' => 'Instalaciones',
        'observatory' => 'Observatorio',
        'supervision' => 'Supervisión',
        'panics' => 'Atención de pánicos',
        'downloads' => 'Descargas',
        'employees' => 'Empleados',
        'documents' => 'Documentos',
        'users' => 'Usuarios',
        'profile' => 'Mis datos',
        'settings' => 'Ajustes',
    ],

    'default_modules' => [
        'access' => ['dashboard', 'billing', 'clients', 'installations', 'panics', 'employees', 'users', 'profile', 'settings'],
        'supervision' => ['billing', 'clients', 'installations', 'supervision', 'panics', 'downloads', 'employees', 'users', 'profile', 'settings'],
        'indexing' => ['billing', 'employees', 'documents', 'users', 'profile', 'settings'],
        'observatory' => ['observatory'],
    ],

    'default_units' => [
        'access' => 80_000,
        'access_hardware' => 150_000,
        'supervision' => 80_000,
        'indexing' => 40_000,
        'indexing_addon' => 15_000,
    ],

    'default_discounts' => [
        'access' => ['bronce' => 0.05, 'plata' => 0.08, 'oro' => 0.10, 'platino' => 0.12],
        'supervision' => ['bronce' => 0.05, 'plata' => 0.08, 'oro' => 0.10, 'platino' => 0.12],
        'indexing' => ['bronce' => 0.05, 'plata' => 0.08, 'oro' => 0.10, 'platino' => 0.12],
    ],

    // Planes y modalidad Hardware visibles para venta (público, registro, facturación empresa).
    'default_availability' => [
        'bronce' => true,
        'plata' => true,
        'oro' => true,
        'platino' => true,
        'hardware' => false,
    ],

    'default_observatory' => [
        'bronce' => 180_000,
        'plata' => 150_000,
        'oro' => 110_000,
        'platino' => 80_000,
    ],
];
