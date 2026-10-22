<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>API Observatorio — {{ config('app.name') }}</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui.css">
    <style>
        :root { --bg: #020617; --card: #0b1222; --card-2: #0f172a; --line: #1e293b; --text: #e2e8f0; --muted: #94a3b8; --accent: #5eead4; }
        body { margin: 0; background: var(--bg); color: var(--text); font-family: Figtree, ui-sans-serif, system-ui; font-size: 15px; }
        code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.85em; background: var(--card-2); border: 1px solid var(--line); border-radius: 4px; padding: 0 0.3em; }

        .docs-head { border-bottom: 1px solid var(--line); background: linear-gradient(180deg, #0b1222 0%, var(--bg) 100%); }
        .docs-wrap { max-width: 68rem; margin: 0 auto; padding: 0 1.5rem; }
        .docs-head .docs-wrap { padding-top: 1.75rem; padding-bottom: 1.5rem; }
        .docs-brand { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: var(--accent); }
        .docs-head h1 { margin: 0.35rem 0 0.4rem; font-size: 1.75rem; font-weight: 700; }
        .docs-head p { margin: 0; max-width: 46rem; color: var(--muted); line-height: 1.6; }
        .docs-steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); gap: 0.75rem; margin-top: 1.25rem; }
        .docs-step { border: 1px solid var(--line); border-radius: 0.75rem; background: var(--card); padding: 0.85rem 1rem; }
        .docs-step b { display: inline-flex; width: 1.4rem; height: 1.4rem; align-items: center; justify-content: center; margin-right: 0.4rem; border-radius: 999px; background: rgb(94 234 212 / 0.15); color: var(--accent); font-size: 0.75rem; }
        .docs-step span { font-weight: 600; }
        .docs-step p { margin-top: 0.35rem; font-size: 0.85rem; }
        .docs-notes { display: flex; flex-wrap: wrap; gap: 0.5rem 1.25rem; margin-top: 1rem; font-size: 0.82rem; color: var(--muted); }
        .docs-notes a { color: var(--accent); }
        .docs-notes .warn { color: #fcd34d; }

        #swagger-ui { max-width: 68rem; margin: 0 auto; padding: 0.5rem 1.5rem 3rem; }
        .swagger-ui, .swagger-ui .opblock-tag, .swagger-ui .opblock .opblock-summary-description,
        .swagger-ui .btn, .swagger-ui select, .swagger-ui input, .swagger-ui textarea, .swagger-ui table { font-family: Figtree, ui-sans-serif, system-ui; }
        .swagger-ui .topbar, .swagger-ui .info .base-url, .swagger-ui .info hgroup.main a { display: none; }
        .swagger-ui .info { margin: 1.5rem 0 1rem; }
        .swagger-ui .info .title { color: var(--text); font-size: 1.15rem; font-weight: 700; }
        .swagger-ui .info .title small { background: #334155 !important; }
        .swagger-ui .info .title small.version-stamp { background: #0f766e !important; }
        .swagger-ui .info .description p, .swagger-ui .info li, .swagger-ui .info .renderedMarkdown p { color: #cbd5e1; font-size: 0.92rem; line-height: 1.65; margin: 0.35rem 0; }
        .swagger-ui .info code { color: var(--accent); background: var(--card-2); }
        .swagger-ui .scheme-container { background: var(--card); border: 1px solid var(--line); border-radius: 0.75rem; box-shadow: none; margin: 0 0 1.5rem; padding: 1rem 1.25rem; }
        .swagger-ui .scheme-container .schemes > label, .swagger-ui .servers-title { color: var(--muted) !important; font-size: 0.8rem; }
        .swagger-ui .btn.authorize { border-color: var(--accent); color: var(--accent); background: rgb(94 234 212 / 0.08); border-radius: 0.5rem; font-weight: 600; }
        .swagger-ui .btn.authorize svg { fill: var(--accent); }

        .swagger-ui .opblock-tag-section { margin-bottom: 1.25rem; }
        .swagger-ui .opblock-tag { border-bottom: 1px solid var(--line); color: var(--text); font-size: 1.15rem; padding: 0.6rem 0.25rem; }
        .swagger-ui .opblock-tag small { color: var(--muted) !important; font-size: 0.85rem; padding-left: 0.75rem; }
        .swagger-ui .opblock-tag svg { fill: var(--muted); }
        .swagger-ui .opblock { border-radius: 0.6rem; margin: 0.6rem 0; background: var(--card) !important; border-width: 1px; box-shadow: none; }
        .swagger-ui .opblock.opblock-get { border-color: rgb(96 165 250 / 0.45); }
        .swagger-ui .opblock.opblock-post { border-color: rgb(52 211 153 / 0.45); }
        .swagger-ui .opblock .opblock-summary { padding: 0.55rem 0.75rem; }
        .swagger-ui .opblock .opblock-summary-method { border-radius: 0.4rem; min-width: 4.5rem; font-size: 0.8rem; }
        .swagger-ui .opblock .opblock-summary-path, .swagger-ui .opblock .opblock-summary-path a { color: var(--text) !important; font-size: 0.92rem; }
        .swagger-ui .opblock .opblock-summary-description { color: #cbd5e1; font-size: 0.9rem; }
        .swagger-ui .opblock-summary-control svg, .swagger-ui .authorization__btn svg { fill: var(--muted); }
        .swagger-ui .opblock .opblock-section-header { background: var(--card-2); box-shadow: none; border-top: 1px solid var(--line); }
        .swagger-ui .opblock .opblock-section-header h4, .swagger-ui .opblock .opblock-section-header label,
        .swagger-ui .opblock .opblock-section-header > label span { color: var(--text) !important; }
        .swagger-ui .opblock-description-wrapper p, .swagger-ui .opblock-external-docs-wrapper p,
        .swagger-ui .opblock .opblock-body .renderedMarkdown p { color: #cbd5e1 !important; font-size: 0.9rem; }

        .swagger-ui table thead tr td, .swagger-ui table thead tr th, .swagger-ui .col_header { color: var(--muted) !important; border-bottom-color: var(--line); font-size: 0.78rem; }
        .swagger-ui .parameter__name { color: var(--text) !important; font-size: 0.9rem; font-weight: 600; }
        .swagger-ui .parameter__type, .swagger-ui .parameter__in { color: var(--muted) !important; }
        .swagger-ui .parameter__name.required:after, .swagger-ui .parameter__name.required span { color: #fca5a5 !important; }
        .swagger-ui .parameters-col_description, .swagger-ui .parameters-col_description p,
        .swagger-ui .response-col_status, .swagger-ui .response-col_description, .swagger-ui .response-col_description p,
        .swagger-ui .response-col_links, .swagger-ui .responses-inner h4, .swagger-ui .responses-inner h5,
        .swagger-ui .response-control-media-type__title, .swagger-ui .opblock-body .opblock-section .no-margin,
        .swagger-ui label, .swagger-ui .tab li, .swagger-ui .tab li button.tablinks, .swagger-ui .model-title, .swagger-ui .model,
        .swagger-ui section.models h4, .swagger-ui section.models h4 span { color: #cbd5e1 !important; }
        .swagger-ui .response-control-media-type__accept-message { color: #6ee7b7 !important; }
        .swagger-ui input[type=text], .swagger-ui textarea, .swagger-ui select { background: var(--bg); color: var(--text); border: 1px solid #334155; border-radius: 0.4rem; }
        .swagger-ui input[type=text]::placeholder { color: #64748b; }
        .swagger-ui .btn { border-radius: 0.45rem; color: var(--text); border-color: #475569; }
        .swagger-ui .btn.execute { background: #2563eb; border-color: #2563eb; color: #fff; font-weight: 600; }
        .swagger-ui .btn.cancel { color: #fca5a5; border-color: #f87171; }
        .swagger-ui .opblock-body pre.microlight, .swagger-ui .highlight-code > .microlight { background: var(--bg) !important; border: 1px solid var(--line); border-radius: 0.45rem; }
        .swagger-ui .responses-table .response .response-col_status { font-weight: 700; }
        .swagger-ui section.models { border: 1px solid var(--line); border-radius: 0.6rem; }
        .swagger-ui section.models .model-container { background: var(--card-2); }

        .swagger-ui .dialog-ux .modal-ux { background: var(--card); border: 1px solid var(--line); border-radius: 0.75rem; }
        .swagger-ui .dialog-ux .modal-ux-header { border-bottom-color: var(--line); }
        .swagger-ui .dialog-ux .modal-ux-header h3, .swagger-ui .dialog-ux .modal-ux-content h4, .swagger-ui .dialog-ux .modal-ux-content p,
        .swagger-ui .dialog-ux .modal-ux-content label, .swagger-ui .dialog-ux .modal-ux-content h6 { color: var(--text) !important; }
        .swagger-ui .dialog-ux .modal-ux-header .close-modal svg { fill: var(--muted); }
    </style>
</head>
<body>
    <header class="docs-head">
        <div class="docs-wrap">
            <div class="docs-brand">Zentra · Integraciones API</div>
            <h1>API del Observatorio</h1>
            <p>Endpoints para leer eventos, tablero y colegios, y para enviar reportes. El acceso es con la <strong>API key</strong> que entrega soporte de Zentra. Las respuestas llegan como datos (JSON), no como una página.</p>

            <div class="docs-steps">
                <div class="docs-step"><b>1</b><span>Autorizar</span><p>Pulsa <strong>Autorizar</strong>, pega la API key (sin la palabra «Bearer») y confirma.</p></div>
                <div class="docs-step"><b>2</b><span>Abrir un endpoint</span><p>Elige uno de la lista, por ejemplo «Listar eventos», y llena los filtros que necesites.</p></div>
                <div class="docs-step"><b>3</b><span>Ejecutar</span><p>Pulsa <strong>Ejecutar</strong> y mira el código (200, 401, 403…) y los datos que llegan.</p></div>
            </div>

            <div class="docs-notes">
                <span class="warn">Las pruebas son reales: «Crear reporte» crea un reporte de verdad.</span>
                <span>En el comando <code>curl</code> la key aparece como <code>TU_API_KEY</code>.</span>
                <span>Contrato OpenAPI: <a href="{{ $specUrl }}">{{ $specUrl }}</a></span>
            </div>
        </div>
    </header>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
    <script>
        const maskKey = (text) => String(text).replace(/(Authorization: Bearer )[^'"\s\\]+/gi, '$1TU_API_KEY');
        const snippets = (SwaggerUIBundle.plugins.RequestSnippets ? SwaggerUIBundle.plugins.RequestSnippets() : {}).fn || {};
        const MaskedSnippets = () => ({
            fn: Object.fromEntries(
                Object.entries(snippets)
                    .filter(([name, generator]) => name.startsWith('requestSnippetGenerator_') && typeof generator === 'function')
                    .map(([name, generator]) => [name, (...args) => maskKey(generator(...args))]),
            ),
        });

        const MaskedCurl = () => ({
            wrapComponents: {
                curl: (Original, system) => (props) => {
                    const request = props.request;
                    const masked = request && typeof request.hasIn === 'function' && request.hasIn(['headers', 'Authorization'])
                        ? request.setIn(['headers', 'Authorization'], 'Bearer TU_API_KEY')
                        : request;

                    return system.React.createElement(Original, { ...props, request: masked });
                },
            },
        });

        window.ui = SwaggerUIBundle({
            url: @json($specUrl),
            dom_id: '#swagger-ui',
            deepLinking: true,
            persistAuthorization: true,
            tryItOutEnabled: true,
            defaultModelsExpandDepth: 0,
            plugins: [MaskedSnippets, MaskedCurl],
        });

        // Etiquetas de Swagger UI en español (solo textos exactos de la interfaz, nunca datos).
        const labels = {
            'Authorize': 'Autorizar', 'Available authorizations': 'Autorizaciones disponibles', 'Authorized': 'Autorizado',
            'Logout': 'Quitar key', 'Close': 'Cerrar', 'Value:': 'API key:', 'Servers': 'Servidor',
            'Parameters': 'Parámetros', 'No parameters': 'Sin parámetros', 'Name': 'Nombre', 'Description': 'Descripción',
            'Request body': 'Cuerpo de la petición', 'Responses': 'Respuestas', 'Code': 'Código', 'Links': 'Enlaces', 'No links': '—',
            'Execute': 'Ejecutar', 'Cancel': 'Cancelar', 'Clear': 'Limpiar', 'Try it out': 'Probar', 'Download': 'Descargar',
            'Example Value': 'Ejemplo', 'Schema': 'Esquema', 'Media type': 'Tipo de contenido', 'Controls Accept header.': 'Define el encabezado Accept.',
            'Server response': 'Respuesta del servidor', 'Response body': 'Datos recibidos', 'Response headers': 'Encabezados',
            'Request URL': 'Dirección consultada', 'Details': 'Detalle', 'Schemas': 'Modelos de datos', 'Loading...': 'Cargando…',
            'Undocumented': 'Sin documentar', 'Error: Forbidden': 'Error: sin permiso', 'Error: Unauthorized': 'Error: key inválida',
        };
        const skip = new Set(['PRE', 'CODE', 'TEXTAREA', 'SCRIPT', 'STYLE']);
        const translate = (root) => {
            const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
            for (let node = walker.nextNode(); node; node = walker.nextNode()) {
                if (skip.has(node.parentElement?.tagName) || node.parentElement?.closest('pre, .microlight, .curl-command')) continue;
                const key = node.nodeValue.trim();
                if (labels[key] && labels[key] !== key) node.nodeValue = node.nodeValue.replace(key, labels[key]);
            }
        };
        const target = document.body;
        new MutationObserver(() => translate(target)).observe(target, { childList: true, subtree: true });
        translate(target);
    </script>
</body>
</html>
