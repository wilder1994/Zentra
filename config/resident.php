<?php

declare(strict_types=1);

return [
    /** URL pública de la app web de residentes (iPhone / respaldo). */
    'web_url' => env('RESIDENT_APP_URL', ''),

    /** Máximo de usuarios app activos por unidad (estructura). */
    'max_app_users_per_structure' => (int) env('RESIDENT_MAX_APP_USERS_PER_STRUCTURE', 4),
];
