@php
    use App\Support\Supervision\SheetImage;

    $mode = $mode ?? 'page';
    $pdf = $mode === 'pdf';
    $pdfUrl = $pdfUrl ?? null;
    $isShift = $sheet->kind === \App\Enums\SupervisorFieldSheetKind::Shift;
    $heroSize = $isShift ? [176, 235] : [210, 280];
    $badgeClass = $sheet->isDraft ? 'draft' : ($sheet->hasNovelty ? 'nov' : 'ok');
    $badgeText = $sheet->isDraft ? 'En curso' : ($sheet->hasNovelty ? 'Con novedad' : 'Sin novedad');
    $recordedLabel = \Carbon\CarbonImmutable::instance($sheet->recordedAt)->timezone((string) config('app.timezone'))->format('d/m/Y H:i');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $sheet->folio }} — {{ $sheet->kind->documentTitle() }}</title>
    @unless ($pdf)
        @include('partials.favicon')
    @endunless
    <style>
        @page { size: letter; margin: 12mm 12mm 14mm; }
        * { box-sizing: border-box; }
        body { font-family: {!! $pdf ? "'DejaVu Sans', sans-serif" : "'Segoe UI', system-ui, sans-serif" !!}; color: #0f172a; margin: 0; font-size: {{ $pdf ? '10px' : '12px' }}; line-height: 1.4; background: {{ $pdf ? '#fff' : '#eef2f6' }}; }
        .sheet { background: #fff; max-width: 860px; margin: 0 auto; padding: {{ $pdf ? '0' : '26px 30px 30px' }}; }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; }
        .muted { color: #64748b; }
        .head { border-bottom: 2px solid #0f172a; }
        .head .hcell { padding: 0 0 12px; }
        .logo { width: 64px; height: 64px; border: 1px solid #e2e8f0; border-radius: 6px; }
        .brand h1 { font-size: 15px; margin: 0 0 2px; }
        .brand p { margin: 0; font-size: 10px; }
        .folio { text-align: right; white-space: nowrap; }
        .folio strong { display: block; font-size: 13px; }
        .badge { display: inline-block; margin-top: 4px; padding: 2px 9px; border-radius: 999px; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: .04em; }
        .badge.ok { background: #dcfce7; color: #166534; }
        .badge.nov { background: #fee2e2; color: #991b1b; }
        .badge.draft { background: #fef3c7; color: #92400e; }
        .badge.plain { background: #e2e8f0; color: #334155; }
        .doc-title { font-size: 13px; font-weight: bold; margin: 12px 0 4px; }
        .intro { font-size: 9.5px; color: #334155; text-align: justify; margin: 0 0 12px; }
        .hero { margin-bottom: 12px; }
        .hero .photo-cell { padding-right: 14px; white-space: nowrap; }
        .portrait { display: inline-block; margin-right: 8px; text-align: center; vertical-align: top; }
        .portrait:last-child { margin-right: 0; }
        .portrait img, .ph { display: block; border-radius: 8px; border: 1px solid #cbd5e1; }
        .ph { background: #f1f5f9; border-style: dashed; color: #64748b; font-size: 10px; font-weight: bold; text-align: center; }
        .cap { font-size: 9px; color: #64748b; margin-top: 3px; }
        .kv td { padding: 4px 0; border-bottom: 1px solid #eef2f6; }
        .kv .k { width: 34%; color: #64748b; font-size: 9.5px; text-transform: uppercase; letter-spacing: .03em; padding-right: 8px; }
        .kv .v { font-weight: bold; }
        .kv .strong .v { font-size: 13px; color: #0f766e; }
        .notes { border: 1px solid #cbd5e1; background: #f8fafc; border-radius: 8px; padding: 8px 10px; margin-bottom: 10px; }
        .summary { margin: 0 0 12px; }
        .chip { display: inline-block; margin: 0 4px 4px 0; padding: 3px 9px; border-radius: 999px; font-size: 9.5px; font-weight: bold; border: 1px solid #cbd5e1; color: #334155; background: #f8fafc; }
        .chip.nov { border-color: #fca5a5; background: #fef2f2; color: #991b1b; }
        .block { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; margin-bottom: 10px; }
        .tbl tr, .photos .ph-item, .portrait, .block-head, .grid tr, .block.route, .block.odo { page-break-inside: avoid; }
        .block-head td { padding-bottom: 6px; border-bottom: 1px solid #e2e8f0; }
        .block-head h2 { margin: 0; font-size: 12px; }
        .block-head .badge { margin-top: 0; }
        .block-body { padding-top: 8px; }
        .grid td { width: 50%; padding: 3px 10px 3px 0; }
        .grid .k { display: block; color: #64748b; font-size: 9px; text-transform: uppercase; letter-spacing: .03em; }
        .grid .v { font-weight: bold; }
        .tbl th { background: #f1f5f9; color: #334155; font-size: 9px; text-transform: uppercase; letter-spacing: .03em; text-align: left; padding: 5px 7px; border: 1px solid #e2e8f0; }
        .tbl td { padding: 5px 7px; border: 1px solid #e2e8f0; }
        .tbl .c { text-align: center; width: 13%; }
        .tbl tr.nov td { background: #fef2f2; }
        .tbl tr.nov td.c { color: #b91c1c; font-weight: bold; }
        .tbl tr.warn td { background: #fffbeb; }
        .note { margin-top: 8px; border-left: 3px solid #f59e0b; background: #fffbeb; padding: 6px 9px; border-radius: 4px; color: #78350f; }
        .photos { margin-top: 8px; }
        .photos .ph-item { display: inline-block; margin: 0 8px 6px 0; text-align: center; vertical-align: top; }
        .photos img { display: block; width: 120px; height: 120px; border-radius: 6px; border: 1px solid #cbd5e1; }
        .route img { display: block; width: 100%; border-radius: 8px; border: 1px solid #cbd5e1; }
        .route .ph { width: 100%; height: 140px; line-height: 140px; }
        .route-stats { margin-top: 8px; }
        .route-stats td { text-align: center; padding: 6px 4px; border: 1px solid #e2e8f0; background: #f8fafc; }
        .route-stats .k { display: block; color: #64748b; font-size: 8.5px; text-transform: uppercase; }
        .route-stats .v { font-weight: bold; font-size: 12px; }
        .legend { margin-top: 6px; font-size: 9px; color: #64748b; }
        .legend b { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin: 0 3px 0 8px; vertical-align: middle; }
        .signs { margin-top: 26px; }
        .signs td { padding: 0 18px; text-align: center; }
        .signs .line { border-top: 1px solid #0f172a; padding-top: 4px; font-size: 10px; margin-top: 38px; }
        .footer { margin-top: 18px; padding-top: 8px; border-top: 1px solid #cbd5e1; font-size: 9px; color: #64748b; }
        .footer td:nth-child(2) { text-align: center; }
        .footer td:last-child { text-align: right; }
        .zoom { cursor: zoom-in; }
        .toolbar { position: sticky; top: 0; z-index: 5; background: #0f172a; padding: 10px 16px; text-align: right; }
        .toolbar a, .toolbar button { display: inline-block; margin-left: 8px; padding: 8px 14px; border-radius: 8px; border: 0; font: inherit; font-weight: 700; font-size: 12px; text-decoration: none; cursor: pointer; background: #1e293b; color: #e2e8f0; }
        .toolbar .primary { background: #0f766e; color: #fff; }
        .lightbox { position: fixed; inset: 0; z-index: 50; background: rgba(2, 6, 23, .88); display: none; align-items: center; justify-content: center; padding: 24px; cursor: zoom-out; }
        .lightbox.open { display: flex; }
        .lightbox img { width: 100%; height: 100%; object-fit: contain; filter: drop-shadow(0 20px 50px rgba(0, 0, 0, .5)); }
        .lightbox button { position: absolute; top: 14px; right: 14px; width: 40px; height: 40px; border-radius: 999px; border: 0; background: rgba(255, 255, 255, .15); color: #fff; font-size: 22px; cursor: pointer; }
        @media print {
            body { background: #fff; }
            .toolbar, .lightbox { display: none !important; }
            .sheet { padding: 0; max-width: none; }
        }
        @media (max-width: 640px) {
            .sheet { padding: 16px 14px 22px; }
            .hero .photo-cell, .hero .info-cell { display: block; width: 100% !important; padding-right: 0; text-align: center; }
            .hero .info-cell { text-align: left; }
            .hero .info-cell { margin-top: 10px; }
            .odo .photo-cell, .odo .info-cell { display: block; width: 100%; padding-right: 0; }
            .odo .info-cell { margin-top: 8px; }
        }
    </style>
</head>
<body>
    @if ($mode === 'page')
        <div class="toolbar">
            @if ($pdfUrl)
                <a class="primary" href="{{ $pdfUrl }}">Descargar PDF</a>
            @endif
            <button type="button" onclick="window.print()">Imprimir</button>
        </div>
    @endif

    <div class="sheet">
        <table class="head">
            <tr>
                <td class="brand hcell">
                    <table>
                        <tr>
                            @if ($sheet->companyLogoSrc)
                                <td style="width:76px"><img class="logo" src="{{ $sheet->companyLogoSrc }}" alt="Logo"></td>
                            @endif
                            <td>
                                <h1>{{ $sheet->companyName }}</h1>
                                @if ($sheet->companyLegalName !== $sheet->companyName)
                                    <p>{{ $sheet->companyLegalName }}</p>
                                @endif
                                @if ($sheet->companyTaxId)
                                    <p class="muted">NIT {{ $sheet->companyTaxId }}</p>
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="folio hcell">
                    <strong>{{ $sheet->folio }}</strong>
                    <span class="muted">{{ $recordedLabel }}</span><br>
                    <span class="badge {{ $badgeClass }}">{{ $badgeText }}</span>
                </td>
            </tr>
        </table>

        <p class="doc-title">{{ $sheet->kind->documentTitle() }}</p>
        <p class="intro">{{ $sheet->intro }}</p>

        @if ($sheet->hero)
            <table class="hero">
                <tr>
                    <td class="photo-cell" style="width:{{ count($sheet->hero['photos']) * ($heroSize[0] + 8) }}px">
                        @foreach ($sheet->hero['photos'] as $photo)
                            <div class="portrait">
                                @if ($photo['src'])
                                    <img class="zoom" src="{{ SheetImage::cover($photo['src'], $heroSize[0] * 2, $heroSize[1] * 2) }}" alt="{{ $photo['label'] }}" style="width:{{ $heroSize[0] }}px;height:{{ $heroSize[1] }}px">
                                @else
                                    <div class="ph" style="width:{{ $heroSize[0] }}px;height:{{ $heroSize[1] }}px;line-height:{{ $heroSize[1] }}px">{{ $photo['pending'] ?? 'Sin foto' }}</div>
                                @endif
                                <div class="cap">{{ $photo['label'] }}</div>
                            </div>
                        @endforeach
                    </td>
                    <td class="info-cell">
                        <table class="kv">
                            @foreach ($sheet->hero['fields'] as $field)
                                <tr>
                                    <td class="k">{{ $field['label'] }}</td>
                                    <td class="v">{{ $field['value'] }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>
            </table>
        @else
            <table class="kv" style="margin-bottom:12px">
                @foreach (array_filter([
                    'Cliente' => $sheet->clientName,
                    'Supervisor' => $sheet->supervisorName.($sheet->username ? ' ('.$sheet->username.')' : ''),
                    'Zona / turno' => trim(($sheet->zoneName ?? '—').($sheet->shiftLabel ? ' · '.$sheet->shiftLabel : '')),
                    'GPS' => $sheet->latitude !== null && $sheet->longitude !== null ? number_format($sheet->latitude, 6).', '.number_format($sheet->longitude, 6) : null,
                ]) as $label => $value)
                    <tr><td class="k">{{ $label }}</td><td class="v">{{ $value }}</td></tr>
                @endforeach
            </table>
        @endif

        @if ($sheet->notes)
            <div class="notes"><strong>Observaciones.</strong> {{ $sheet->notes }}</div>
        @endif

        @if ($sheet->moduleSummary !== [])
            <div class="summary">
                <div class="muted" style="font-size:9px;text-transform:uppercase;letter-spacing:.03em;margin-bottom:4px">Módulos de la revista</div>
                @foreach ($sheet->moduleSummary as $item)
                    <span class="chip {{ $item['novelty'] ? 'nov' : '' }}">{{ $item['label'] }} · {{ $item['label'] === 'Capacitación' ? 'Registrada' : ($item['novelty'] ? 'Con novedad' : 'Sin novedad') }}</span>
                @endforeach
            </div>
        @endif

        @if ($sheet->odometer)
            <div class="block odo">
                <table class="block-head"><tr><td><h2>Odómetro</h2></td></tr></table>
                <div class="block-body">
                    <table>
                        <tr>
                            <td class="photo-cell" style="width:388px;padding-right:14px;white-space:nowrap">
                                @foreach ($sheet->odometer['photos'] as $photo)
                                    <div class="portrait">
                                        @if ($photo['src'])
                                            <img class="zoom" src="{{ SheetImage::cover($photo['src'], 480, 360) }}" alt="{{ $photo['label'] }}" style="width:180px;height:135px">
                                        @else
                                            <div class="ph" style="width:180px;height:135px;line-height:135px">{{ $photo['pending'] ?? 'Sin foto' }}</div>
                                        @endif
                                        <div class="cap">{{ $photo['label'] }}</div>
                                    </div>
                                @endforeach
                            </td>
                            <td class="info-cell">
                                <table class="kv">
                                    @foreach ($sheet->odometer['fields'] as $field)
                                        <tr class="{{ ! empty($field['strong']) ? 'strong' : '' }}">
                                            <td class="k">{{ $field['label'] }}</td>
                                            <td class="v">{{ $field['value'] }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        @endif

        @if ($sheet->routeMap)
            <div class="block route">
                <table class="block-head"><tr><td><h2>Recorrido del turno</h2></td></tr></table>
                <div class="block-body">
                    @if ($sheet->routeMap['src'])
                        <img class="zoom" src="{{ $sheet->routeMap['src'] }}" alt="Recorrido del turno">
                        <div class="legend">
                            <b style="background:#22c55e"></b>Inicio
                            <b style="background:#ef4444"></b>Cierre
                            <b style="background:#6366f1"></b>Revistas (en orden)
                            <b style="background:#f59e0b;border-radius:2px;height:4px"></b>Recorrido
                        </div>
                    @else
                        <div class="ph">Mapa no disponible por el momento</div>
                    @endif
                    <table class="route-stats">
                        <tr>
                            @foreach ($sheet->routeMap['fields'] as $field)
                                <td><span class="k">{{ $field['label'] }}</span><span class="v">{{ $field['value'] }}</span></td>
                            @endforeach
                        </tr>
                    </table>
                    @if ($sheet->routeMap['note'])
                        <p class="legend" style="margin:6px 0 0">{{ $sheet->routeMap['note'] }}</p>
                    @endif
                </div>
            </div>
        @endif

        @foreach ($sheet->sections as $section)
            <div class="block">
                <table class="block-head">
                    <tr>
                        <td><h2>{{ $section['title'] }}</h2></td>
                        @if (array_key_exists('novelty', $section) && $section['title'] !== 'Capacitación')
                            <td style="text-align:right"><span class="badge {{ $section['novelty'] ? 'nov' : 'plain' }}">{{ $section['novelty'] ? 'Con novedad' : 'Sin novedad' }}</span></td>
                        @endif
                    </tr>
                </table>
                <div class="block-body">
                    @if (! empty($section['fields']))
                        <table class="grid">
                            @foreach (array_chunk($section['fields'], 2) as $pair)
                                <tr>
                                    @foreach ($pair as $field)
                                        <td><span class="k">{{ $field['label'] }}</span><span class="v">{{ $field['value'] }}</span></td>
                                    @endforeach
                                    @if (count($pair) === 1)
                                        <td></td>
                                    @endif
                                </tr>
                            @endforeach
                        </table>
                    @endif

                    @if (! empty($section['table']))
                        @php($center = $section['table']['center'] ?? [])
                        <table class="tbl">
                            <tr>
                                @foreach ($section['table']['head'] as $i => $head)
                                    <th class="{{ in_array($i, $center, true) ? 'c' : '' }}">{{ $head }}</th>
                                @endforeach
                            </tr>
                            @foreach ($section['table']['rows'] as $row)
                                <tr class="{{ $row['tone'] ?? '' }}">
                                    @foreach ($row['cells'] as $i => $cell)
                                        <td class="{{ in_array($i, $center, true) ? 'c' : '' }}">{!! nl2br(e($cell)) !!}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </table>
                    @endif

                    @foreach ($section['rows'] as $row)
                        <p style="margin:0 0 3px">{{ $row }}</p>
                    @endforeach

                    @if (! empty($section['note']))
                        <div class="note">{{ $section['note'] }}</div>
                    @endif

                    @if (($section['photos'] ?? []) !== [])
                        <div class="photos">
                            @foreach ($section['photos'] as $photo)
                                <div class="ph-item">
                                    <img class="zoom" src="{{ SheetImage::cover($photo['src'], 400, 400) }}" alt="{{ $photo['label'] }}">
                                    <div class="cap">{{ $photo['label'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endforeach

        @if ($sheet->signatures !== [])
            <table class="signs">
                <tr>
                    @foreach ($sheet->signatures as $who)
                        <td><div class="line">Firma {{ match (mb_strtolower($who)) { 'supervisor' => 'del supervisor', 'vigilante' => 'del vigilante', default => 'de '.mb_strtolower($who) } }}</div></td>
                    @endforeach
                </tr>
            </table>
        @endif

        <table class="footer">
            <tr>
                <td>{{ $sheet->folio }} · {{ $sheet->kind->label() }}</td>
                <td>{{ $sheet->supervisorName }} · {{ $recordedLabel }}</td>
                <td>{{ $sheet->companyName }} · Registro de supervisión de campo</td>
            </tr>
        </table>
    </div>

    @unless ($pdf)
        <div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Foto ampliada">
            <button type="button" aria-label="Cerrar">×</button>
            <img alt="">
        </div>
        <script>
            (() => {
                const box = document.getElementById('lightbox');
                const img = box.querySelector('img');
                const close = () => { box.classList.remove('open'); img.removeAttribute('src'); };
                document.querySelectorAll('img.zoom').forEach((el) => {
                    el.addEventListener('click', () => {
                        img.src = el.src;
                        img.alt = el.alt;
                        box.classList.add('open');
                    });
                });
                box.addEventListener('click', close);
                document.addEventListener('keydown', (ev) => {
                    if (ev.key !== 'Escape' || ! box.classList.contains('open')) return;
                    ev.stopImmediatePropagation();
                    close();
                });
                window.addEventListener('message', (ev) => { if (ev.data === 'zentra-sheet-close-zoom') close(); });
            })();
        </script>
    @endunless
</body>
</html>
