# Comercial Super Admin — Propuesta, Cotización y Presentación

**Última actualización:** 15 octubre 2026

Hub del súper admin para generar documentos de venta sin salir del panel. Nav **Comercial** → `/admin/comercial` (permiso `platform.companies.view`).

No reemplaza el catálogo de precios (`/admin/pricing`) ni el ciclo de membresía de la ficha empresa. Complementa el modelo de planes documentado en [`MODELO-COMERCIAL-PAQUETES.md`](MODELO-COMERCIAL-PAQUETES.md).

---

## Hub

| Ruta | Función |
|------|---------|
| `GET /admin/comercial` | Tres tarjetas: Propuesta, Cotización, Presentación |
| `GET /admin/comercial/propuesta` | Formulario PDF propuesta |
| `POST /admin/comercial/propuesta/pdf` | Descarga PDF |
| `GET /admin/comercial/cotizacion` | Formulario PDF cotización + plan personalizado |
| `POST /admin/comercial/cotizacion/pdf` | Descarga PDF |
| `GET /admin/comercial/presentacion` | Formulario PPTX |
| `POST /admin/comercial/presentacion/pptx` | Descarga PowerPoint 16:9 |

Controlador: `App\Http\Controllers\Platform\CommercialController`.  
Vistas: `resources/views/modules/admin/commercial/`.  
Config / textos por defecto: `config/commercial.php` + `App\Support\Commercial\CommercialCopyDefaults`.  
Tests: `tests/Feature/Platform/CommercialDocumentsTest`.

---

## Propuesta comercial (PDF)

Brochure del catálogo vigente (Bronce / Plata / Oro / Platino) con textos editables.

| Campo | Uso |
|-------|-----|
| Fecha, ciudad, dirigido a | Cabecera |
| Asunto, introducción, destacado, conclusiones | Cuerpo |
| Presentador (nombre, cargo, teléfono, correo) | Pie de contacto (texto; sin imagen de firma) |

- Motor: FPDF (`BuildCommercialBrochurePdfService`, base tipográfica `CommercialPdfDocument`).
- Marcas: Zentra · W Codex (`config/commercial.brand`).
- Los precios salen del catálogo en BD (`CatalogSettings` / pricing), no se inventan en el formulario.

---

## Cotización comercial (PDF)

Cotización a medida: el usuario arma un plan con el mismo `planPicker` que `/planes` y fija un **total comercial** (puede diferir del catálogo por alianza).

| Campo | Uso |
|-------|-----|
| Productos / cantidades / ciclo | `planPicker` → precio de catálogo de referencia |
| Total comercial | Editable; es el valor que aparece como pacto en el PDF |
| Nota | Opcional, debajo del total |
| Textos de documento | Igual patrón que la propuesta |

- Motor: `BuildCommercialQuotePdfService`.
- JS: `resources/js/commercial-quote-form.js` (registrado en Vite/`app.js`).

---

## Presentación comercial (PPTX)

PowerPoint **16:9**, **11 diapositivas**, paleta clara. Sin tablas de planes ni gráficas de precio (eso va en PDF).

### Paleta y estilo

| Token | Hex (ARGB sin `#`) | Uso |
|-------|--------------------|-----|
| Lienzo | `F8FAFC` | Fondo diapositiva |
| Tinta | oscuro slate | Títulos y cuerpo |
| Acento violeta | marca W Codex | Barra superior, énfasis |
| Esmeralda | acentos secundarios / etiquetas | Subtítulos de captura |

- Tarjetas: AutoShape `roundRect` + post-proceso OOXML `adj` (~9000) para esquinas suaves y sombra.
- Portada y cierre «Gracias»: panel frosted (GD) sobre captura hero; sin velo oscuro pre-bakeado.
- Capturas: recorte redondeado con GD antes de embeber (`clipImageToRoundedBox`).
- Librería: `phpoffice/phppresentation`.

### Orden de diapositivas

1. **Portada** — hero dashboard + panel frosted (W Codex, Zentra, asunto, dirigido a, ciudad · fecha).
2. **Galería** — «Una plataforma. Toda su operación.» + mosaico de capturas del panel empresa.
3. **Accesos** — admin de instalaciones + panel del vigilante (portería).
4. **Supervisión en campo** — captura con scroll (mapa / en vivo); copy operativo.
5. **Observatorio** — captura con scroll del tablero; copy de folios y riesgo.
6. **Indexación de empleados** — maestro de personal.
7. **Beneficios (destacados)** — control de usuarios + georreferencia (capturas).
8. **Beneficios / por qué** — textos editables del formulario.
9. **Módulos extra** — refuerzo de valor (sin precios).
10. **Cierre / siguientes pasos** — presentador en texto + next steps.
11. **Gracias** — panel frosted + cierre.

### Capturas (`config/commercial.deck_shots`)

Archivos en `resources/images/commercial/deck/` (vía junction `public/images` → `resources/images` en local; en VPS el mismo enlace o fallback a `resource_path` en `BuildCommercialDeckPptxService::shotPath`).

| Clave | Archivo | Origen |
|-------|---------|--------|
| `hero_cover` | `hero-cover.png` | Welcome / hero dashboard |
| `co_dashboard` | `co-dashboard.png` | Mi empresa |
| `co_access_admin` | `co-access-admin.png` | Accesos (admin instalaciones) |
| `co_access_guard` | `co-access-guard.png` | Portería vigilante |
| `co_supervision` | `co-supervision.png` | Supervisión (stitch con scroll del main) |
| `co_observatory` | `co-observatory.png` | Observatorio (stitch con scroll) |
| `co_employees` | `co-employees.png` | Empleados |
| `co_users` | `co-users.png` | Usuarios |
| `co_georef` | `co-georef.png` | Georreferenciaciones |

Servicio: `BuildCommercialDeckPptxService`. Formulario: `resources/views/modules/admin/commercial/deck.blade.php`.

### Campos del formulario PPTX

Fecha, ciudad, dirigido a, asunto, contexto, solución, por qué, siguientes pasos, cierre, datos del presentador (texto).

---

## Dependencias Composer

- `setasign/fpdf` — PDFs
- `phpoffice/phppresentation` — PPTX

GD de PHP para frosted panel y recorte redondeado de capturas.

---

## Despliegue

Pull + `composer install` + `npm ci && npm run build` + `migrate --force` (si hay migraciones del mismo corte) + `config:cache` + `route:cache` + `view:cache`. Sin seeder específico de Comercial (usa permiso existente `platform.companies.view`).

Verificar en VPS:

1. `https://zentra.wcodex.cloud/admin/comercial` (login súper admin) muestra las tres tarjetas.
2. Generar PPTX desde Presentación: descarga `.pptx`, ~11 diapositivas, capturas visibles.
3. Enlace `public/images` → `resources/images` (o que `resource_path('images/…')` resuelva los PNG del deck).

Detalle de comandos: [`HOSTING-VPS.md`](HOSTING-VPS.md).
