<?php

declare(strict_types=1);

return [
    /*
    | demo: facturas simuladas, sin llamadas a proveedor tecnológico DIAN.
    | live: emisión real vía PT (pendiente go-live legal).
    */
    'mode' => env('BILLING_MODE', 'demo'),

    'currency' => 'COP',

    'demo_invoice_prefix' => env('BILLING_DEMO_PREFIX', 'DEMO'),

    /*
    | Driver de pasarela: local = checkout simulado en Zentra (sin proveedor externo).
    | Futuro: wompi, payu, etc.
    */
    'gateway' => [
        'driver' => env('BILLING_GATEWAY_DRIVER', 'local'),
    ],

    'signup_intent_ttl_hours' => (int) env('BILLING_SIGNUP_INTENT_TTL_HOURS', 24),

    'allow_public_register' => env('BILLING_ALLOW_PUBLIC_REGISTER', false),
];
