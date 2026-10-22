<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — Zentra</title>
    <style>
        @page { margin: 22mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; line-height: 1.5; color: #1e293b; }
        .cover { border-bottom: 2px solid #0f766e; padding-bottom: 8px; margin-bottom: 12px; }
        .brand { font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase; color: #0f766e; font-weight: bold; }
        h1 { font-size: 20px; margin: 4px 0 2px; color: #0f172a; }
        h2 { font-size: 13px; margin: 16px 0 6px; color: #0f766e; page-break-after: avoid; }
        p { margin: 4px 0 6px; }
        ul, ol { margin: 4px 0 8px 18px; padding: 0; }
        li { margin: 2px 0; }
        code { font-family: DejaVu Sans Mono, monospace; font-size: 9.5px; background: #f1f5f9; padding: 0 2px; }
        pre { font-family: DejaVu Sans Mono, monospace; font-size: 9px; background: #0f172a; color: #e2e8f0; padding: 8px 10px; border-radius: 4px; white-space: pre-wrap; page-break-inside: avoid; }
        table { width: 100%; border-collapse: collapse; margin: 6px 0 10px; page-break-inside: avoid; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; font-weight: bold; }
        .note { background: #fffbeb; border-left: 3px solid #f59e0b; padding: 5px 8px; }
        .lead { font-size: 11px; }
        .meta { color: #64748b; font-size: 9.5px; }
        .footer { margin-top: 18px; border-top: 1px solid #e2e8f0; padding-top: 6px; color: #94a3b8; font-size: 9px; }
    </style>
</head>
<body>
    <div class="cover">
        <div class="brand">Zentra · Integraciones API</div>
        <h1>{{ $title }}</h1>
        @include('modules.admin.companies.manuals.intro')
    </div>

    @include('modules.admin.companies.manuals.api-key-sections')
    @if ($type === 'integracion')
        @include('modules.admin.companies.manuals.swagger-sections')
    @endif

    <p class="footer">Generado el {{ now()->format('d/m/Y H:i') }} · Zentra. Si algo no funciona, contacta a soporte de Zentra.</p>
</body>
</html>
