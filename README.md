# Zentra

Plataforma SaaS B2B de **control de accesos y vigilancia** para empresas de seguridad privada y conjuntos residenciales en Colombia. Construida sobre **Laravel 11** (Laragon).

**Repositorio:** [github.com/wilder1994/Zentra](https://github.com/wilder1994/Zentra) (`origin`)

---

## Estado del proyecto

| Fase | Nombre | Estado |
|------|--------|--------|
| **0** | Fundación multi-tenant | ✅ Implementada |
| **1** | Estructura / censo | ✅ Implementada |
| **Limpieza** | Panel plataforma + residuos Breeze | ✅ Implementada |
| **Landing** | Vista pública `/` (welcome) | ✅ Implementada |
| **Contratación** | Planes `/planes` + wizard guest `/contratar` | ✅ Implementada |
| **Billing local** | Checkout simulado empresa + expediente | ✅ Implementada |
| **Auth** | Login `/login` (AuthLayout) | ✅ Implementada |
| **2** | Operación portería (MVP) — Hub operaciones, lista bloqueo, salida masiva | ✅ Implementada |
| **3** | BI + vigilancia — Reportes mejorados con exportación | ✅ Implementada |
| **4** | API REST (Sanctum) + Portal Residente web | ✅ Implementada |
| **Comercial** | Planes Bronce/Plata/Oro/Platino por cantidad + facturación mensual/anual | ✅ Implementada |
| **UI Empresa** | Design system `x-ui.*`, Command Center, header tabs colgantes, toasts flash | ✅ Implementada (v4) |
| **Ops empresa** | Turnos (`guard_shifts`), revistas (`supervisor_reviews`), KPIs pánico/bloqueo/revista | ✅ Implementada (v1) |
| **Ops portería+** | Minuta supervisión + evidencias, turnos, zonas comunes, auditoría, reportes imprimibles | ✅ Implementada (v1) |
| **UI Plataforma** | Dashboard analítico + empresa expediente (Resumen/Perfil/Docs/Historial) + soporte | ✅ Implementada (v3) |
| **Membresía** | Pago manual/online, cancelar al fin de periodo, reactivar, cambio de plan diferido | ✅ Implementada (v1) |
| **Expediente conjunto** | Cartera → Ver: KPIs, charts, Operar portería/cliente + banner «Volver al expediente» | ✅ Implementada (v2) |
| **Ajustes plataforma** | Catálogo `identity_document_types` (CRUD) + puntos de acceso | ✅ Implementada (v2) |
| **Cliente comercial** | Alta empresa: party_type, documento, contactos, representante; `structure_type_id` fijo; slug/login auto | ✅ Implementada (v1) |
| **Ciclo comercial** | Paquetes + acceso: gracia 5d → suspensión → archivo `non_payment` → purga | ✅ Implementada |
| **Documentos** | Normoteca (globales + contrato por SKU), versionado, expediente congelado, clickwrap, pago manual, factura demo | ✅ Implementada (v1.1) |
| **Usuarios** | CRUD scoped; Vigilante / Supervisor de vigilancia (código revista); foto y cargo | ✅ Implementada |
| **Perfiles** | Empresa/cliente: dirección, ciudad/depto y geo; `service_started_at` (sin cobro al cliente en Zentra) | ✅ Implementada |
| **Empleados** | Ficha SJ-SIG (4 bloques + foto) + Excel WM ampliado (preview → alta o **actualización** por documento). Sidebar propio; Ajustes = cargos/tipos + catálogos de Supervisión | ✅ Implementada |
| **Documentos (personal)** | Indexador de carpetas (HV, contratación, certificados, cursos, afiliaciones, otros). Cliente: flag Accesos + puestos del cliente. **Ver** = preview; **Gestionar** = descargar/indexar. Distinto de Normoteca | ✅ Implementada |
| **Supervisión campo** | PWA captura (8 módulos, rito de turno, catálogos). Mapa En vivo/Historial, cierre automático, cola offline por usuario. Fuente de verdad: Zentra | ✅ Implementada |
| **Árbol del cliente** | Una tarjeta **Instalaciones y puestos**; otra **Puertas** (solo Accesos). Modalidad + vigilantes en el puesto | ✅ Implementada |

Documentación detallada: [`docs/INFORME-VISION-PRODUCTO-W-CODEX.md`](docs/INFORME-VISION-PRODUCTO-W-CODEX.md) · [`docs/PLAN-INICIO-PROYECTO-ZENTRA.md`](docs/PLAN-INICIO-PROYECTO-ZENTRA.md) · [`docs/REFERENCIA-PLATAFORMA-CONTROL-ACCESOS.md`](docs/REFERENCIA-PLATAFORMA-CONTROL-ACCESOS.md) · [`docs/MODELO-COMERCIAL-PAQUETES.md`](docs/MODELO-COMERCIAL-PAQUETES.md) · [**Paquetes Accesos y Supervisión**](docs/PAQUETES-ACCESOS-Y-SUPERVISION.md) · [**Supervisión de campo**](docs/SUPERVISION-CAMPO.md) · [**Portería**](docs/PORTERIA.md) · [**Observatorio**](docs/OBSERVATORIO.md) · [**Landing y contratación**](docs/LANDING-Y-CONTRATACION.md) · [**Usuarios y perfiles**](docs/USUARIOS-Y-PERFILES.md) · [**Empleados y cargos**](docs/EMPLEADOS-Y-CARGOS.md) · [**Clientes y estructura**](docs/CLIENTES-Y-ESTRUCTURA.md) · [**Billing local**](docs/BILLING-LOCAL-Y-MIGRACION.md) · [**Diseño UI**](docs/DISENO-UI-ZENTRA.md) · [**Panel Plataforma**](docs/PLATAFORMA-ADMIN.md) · [**Módulo Documentos**](docs/MODULO-DOCUMENTOS.md) (v1.1 normoteca por SKU + inmutabilidad; fases futuras §12) · [**Hosting VPS**](docs/HOSTING-VPS.md)

---

## Superficies de producto

| Panel | Prefijo | Rol(es) | Descripción |
|-------|---------|---------|-------------|
| **Plataforma** | `/admin` | `super-admin` | Dashboard, **Descargas**, precios, empresas, documentos, **Ajustes** (tipos de documento) |
| **Empresa** | `/company` | `company-admin` | Command Center (**Mi empresa**), cartera, **Empleados**, **Documentos**, **Mis datos**, **Ajustes** (cargos/tipos + zonas/turnos/preoperacional), usuarios, billing, **Observatorio**, Supervisión, **Descargas** |
| **Cliente** | `/client` | `client-admin`, `client-installation-admin` | Censo: nodos (`structures`, tipo heredado del cliente), personas, vehículos, mascotas, autorizaciones. **Observatorio**. Admin instalaciones: solo sus instalaciones (cierran estados, unen folios, sacan reportes); Ajustes solo ver |
| **Portería** | `/access` | `guardia` (Vigilante), `supervisor` (Supervisor de vigilancia), `client-admin` | Ops diarias + **accesos** (puertas de una instalación del cliente) |
| **Residente** | `/resident` | `resident`, `anfitrion` | Portal web: pre-autorizaciones y correspondencia |
| **API** | `/api` | Token-based | Sanctum: auth, pre-autorizaciones, correspondencia, **Supervisión de campo**, **Observatorio** (`/docs/observatory`) |
| **PWA campo** | `field-app/` · `zentra_supervision.test` | `supervisor` | Captura; login usuario o correo legado; API inferida. Offline: cola por supervisor (flush en segundo plano, no bloquea al de turno). Caché SW `zentra-sup-v35` |

Tras el login, cada rol es redirigido a su **home** vía `ResolveUserHomeRoute` → ruta `/home`.

**Roles Spatie (`config/access.php`):** `super-admin`, `company-admin`, `client-admin`, **`client-installation-admin`**, `guardia`, **`supervisor`**, `resident`, más alias legacy (`admin-accesos`, `anfitrion`). El seeder **elimina** roles que no estén en ese archivo; mantener siempre `supervisor` (demo + revistas empresa). Tras cambios de roles en producción: `php artisan db:seed --class=RoleAndPermissionSeeder` (sync; no vacía tablas).

---

## Requisitos

- PHP 8.2+
- Composer
- MySQL 8+
- Node.js 18+ (assets Vite)
- [Laragon](https://laragon.org/) (recomendado) o entorno equivalente

---

## Instalación

```bash
git clone https://github.com/wilder1994/Zentra.git
cd Zentra
composer install
cp .env.example .env   # o copiar .env manualmente
php artisan key:generate
```

Configurar en `.env`:

```env
APP_URL=http://zentra.test
DB_DATABASE=zentra
DB_USERNAME=root
DB_PASSWORD=
SESSION_DRIVER=file

# Mapa del dashboard plataforma y supervisión (opcional)
GOOGLE_MAPS_API_KEY=
GOOGLE_MAPS_SERVER_API_KEY=
GOOGLE_MAPS_DEFAULT_LAT=4.5709
GOOGLE_MAPS_DEFAULT_LNG=-74.2973
GOOGLE_MAPS_DEFAULT_ZOOM=6

# Ciclo de acceso / cobranza (paquetes cupo×modalidad×ciclo siguen independientes)
SUBSCRIPTION_GRACE_DAYS=5
SUBSCRIPTION_REMINDER_DAYS=5
SUBSCRIPTION_ARCHIVE_AFTER_SUSPENDED_DAYS=90

# Facturación plataforma (demo = facturas sin PT DIAN)
BILLING_MODE=demo
BILLING_DEMO_PREFIX=DEMO
BILLING_GATEWAY_DRIVER=local
BILLING_SIGNUP_INTENT_TTL_HOURS=24
BILLING_ALLOW_PUBLIC_REGISTER=false
```
```bash
# Desarrollo limpio (baseline unificado ~30 migraciones; borra datos):
php artisan migrate:fresh --seed

# Solo aplicar migraciones pendientes (si ya hay BD):
php artisan migrate
php artisan db:seed
npm install && npm run build
```

> **Baseline ago 2026:** el historial de `add_*` se consolidó en creates. Clonar o reset local → `migrate:fresh --seed`. Detalle: [`docs/PLATAFORMA-ADMIN.md`](docs/PLATAFORMA-ADMIN.md) § Migraciones · glosario operativo [`docs/USUARIOS-Y-PERFILES.md`](docs/USUARIOS-Y-PERFILES.md).

### Assets estáticos (imágenes)

Las imágenes del producto viven en `resources/images/`. Para servirlas con `asset('images/...')`, crear un enlace en Laragon (Windows):

```powershell
cd C:\laragon\www\Zentra
New-Item -ItemType Junction -Path "public\images" -Target "C:\laragon\www\Zentra\resources\images" -Force
```

### Node.js en Laragon

Si `npm` no se reconoce en la terminal, agregar al PATH de Windows:

```
C:\laragon\bin\nodejs\node-v18
```

Luego: `npm run build` o `npm run dev`.

### Google Maps (dashboard `/admin` + selector de dirección)

El mapa de **Distribución geográfica** y el picker de `x-ui.geo-address-fields` usan Google Maps. Variables en `.env` (plantilla en `.env.example`):

```env
GOOGLE_MAPS_API_KEY=
GOOGLE_MAPS_SERVER_API_KEY=
GOOGLE_MAPS_DEFAULT_LAT=4.5709
GOOGLE_MAPS_DEFAULT_LNG=-74.2973
GOOGLE_MAPS_DEFAULT_ZOOM=6
```

**Configurar en Google Cloud Console:**

1. [APIs y servicios → Biblioteca](https://console.cloud.google.com/apis/library) → habilitar **Maps JavaScript API**, **Places API**, **Geocoding API** y **Roads API** (ruta de historial).
2. [Credenciales](https://console.cloud.google.com/apis/credentials) → **Crear credenciales → Clave de API**.
3. Restringir la clave **del mapa** (navegador):
   - **Aplicación:** referentes HTTP → `http://zentra.test/*` y `http://localhost/*`
   - **API:** Maps JavaScript API, Places API, Geocoding API, Roads API
4. Pegar esa clave en `GOOGLE_MAPS_API_KEY` y ejecutar `php artisan config:clear`.
5. **Ruta de historial (Roads):** la llamada sale del servidor Laravel, no del navegador. Una clave solo con sitios web suele fallar. Crea **otra clave** (restricción de IP: `127.0.0.1` en local, o ninguna en pruebas) limitada a **Roads API** y pégala en `GOOGLE_MAPS_SERVER_API_KEY`. Presupuesto y cuota diaria en Cloud. En vivo **no** usa Roads.

Sin clave, el dashboard muestra un aviso en el contenedor del mapa; el formulario geo sigue permitiendo captura manual.  
Icono del botón mapa: `resources/images/ui/map-pin.png`. Icono GPS del supervisor: `resources/images/ui/supervisor-moto.png`. Ambos se copian a `public/images/ui/` en local (`/public/images` está en `.gitignore`).  
Guía detallada: [`docs/PLATAFORMA-ADMIN.md`](docs/PLATAFORMA-ADMIN.md) § Mapa geográfico · [`docs/USUARIOS-Y-PERFILES.md`](docs/USUARIOS-Y-PERFILES.md) § Ubicación.

> **Importante:** No ejecutar `migrate:fresh` ni `db:wipe` en entornos con datos reales sin autorización explícita.

---

## Credenciales y seed (tras `db:seed`)

### Seed mínimo (por defecto)

| Qué | Contenido |
|-----|-----------|
| Roles/permisos | Todos los de `config/access.php` |
| Documentos | Normoteca (globales + contratos por SKU) + TRD |
| Usuario | Solo súper admin |

| Rol | Email | Contraseña | Home |
|-----|-------|------------|------|
| Súper Admin | `admin@control-acceso.test` | `Admin123!` | `/admin/dashboard` |

**Se siembran en mínimo:** roles, normoteca+TRD, tipos de documento de identidad (CC/CE/NIT/PA), súper admin.

**No se siembran en mínimo:** empresas, clientes, tipos de estructura, instalaciones, accesos, puestos de Supervisión, censo ni otros usuarios. Se crean desde la UI (precios: al abrir la tabla se generan defaults editables). Tipos de estructura: Ajustes empresa (`/company/structure-types`). El piloto SJ crea solo los 4 tipos que usa Palmas.

Orden en `DatabaseSeeder`:

1. `RoleAndPermissionSeeder`
2. `PlatformDocumentsSeeder`
3. `IdentityDocumentTypeSeeder` — CC, CE, NIT, Pasaporte
4. `DemoUsersSeeder` (solo súper admin)

### Datos piloto opcionales (tests / demos)

```bash
php artisan db:seed --class=PilotDemoSeeder
```

Incluye: catálogo `structure_types`, empresa SJ Seguridad (`900123456-1`), clientes Palmas/Torres, árbol de sitio (instalación propia del cliente; Palmas: 4 puertas + 2 puestos; Torres: 1 puerta + 1 puesto), censo Torre A, y usuarios:

| Rol | Email | Contraseña |
|-----|-------|------------|
| Admin Empresa | `empresa@sj-seguridad.test` | `Empresa123!` |
| Admin Cliente | `admin@palmasdelingenio.test` | `Cliente123!` |
| Vigilante | `guardia@control-acceso.test` | `Guardia123!` |
| Residente | `anfitrion@control-acceso.test` | `Anfitrion123!` |

Supervisor de vigilancia: no se siembra. Alta real en **Usuarios** desde un empleado (`nombre.apellido.####`).

```bash
php artisan db:seed --class=PlatformDocumentsSeeder  # solo normoteca + TRD
php artisan db:seed --class=DemoUsersSeeder           # solo súper admin
```

---

## Landing y contratación pública

Documentación completa: [`docs/LANDING-Y-CONTRATACION.md`](docs/LANDING-Y-CONTRATACION.md)

### Welcome (`/`)

Vista `resources/views/welcome.blade.php` · `WelcomeController` redirige autenticados a `/home`.

| Elemento | Detalle |
|----------|---------|
| Layout | `min-h-screen` con scroll; pie al fondo |
| Hero | `flex-1`, grid 40/60 (texto / imagen dashboard) |
| Título | Accesos, supervisión y observatorio |
| Tarjeta `@guest` | Precio mínimo mensual (`CatalogPricer`), pills SaaS + anual, CTA → `/planes` |
| Cards | Accesos, Supervisión, Observatorio |
| Marca | WCodex · Innovative Software Solutions |
| Header | Logo + «Iniciar sesión» |

| Asset | Ruta |
|-------|------|
| Logo | `resources/images/branding/logo-zentra.png` |
| Favicon | `public/favicon.ico` (único; `partials.favicon`) |
| Fondo | `resources/images/welcome/hero-background.png` |
| Hero dashboard | `resources/images/welcome/hero-dashboard.png` |

### Rutas guest (signup)

| Ruta | Función |
|------|---------|
| `GET /planes` | «Arma tu plan»: tarjeta por plan con los 4 productos |
| `GET /planes/carrito` | Carrito, Mensual/Anual, descuentos, Contratar |
| `GET /contratar?access=&hardware=&supervision=&indexing=&observatory=&cycle=` | Inicia intent de contratación |
| `GET/POST /contratar/datos/{token}` | Datos empresa + contraseña + dirección/ciudad/depto/geo |
| `GET/POST /contratar/legal/{token}` | Clickwrap: contrato del SKU + T&C + privacidad (texto completo) |
| `GET /contratar/resumen/{token}` | Resumen antes de pagar |
| `POST /contratar/pagar/{token}` | Crea checkout simulado |
| `GET /contratar/checkout/{token}` | Aprobar / Rechazar pago |

**Regla:** `users` y `security_companies` solo se crean si el pago se **aprueba**. `BILLING_ALLOW_PUBLIC_REGISTER=false` desactiva `/register` de Breeze.

Layout wizard: `layouts/public.blade.php` · Rutas: `routes/modules/public.php`

### Usuarios y perfiles

Documentación: [`docs/USUARIOS-Y-PERFILES.md`](docs/USUARIOS-Y-PERFILES.md). En empresa, todos los usuarios se crean igual: empleado (nombre + cédula) → `nombre.apellido.####` + clave.

| Panel | Rutas clave |
|-------|-------------|
| Plataforma | `/admin/users`, `/admin/companies/{id}/profile` |
| Empresa | `/company/users`, `/company/settings` (Mis datos), `/company/employees` |
| Cliente | `/client/users` (lista; no crea). Accesos (personas del censo) en `/client/app-users` |

Tras desplegar permisos nuevos: `php artisan db:seed --class=RoleAndPermissionSeeder`

---

## Login (`/login`)

Vista de autenticación con layout dedicado `AuthLayout` (`resources/views/layouts/auth.blade.php`).

| Elemento | Detalle |
|----------|---------|
| Fondo | `hero-background.png` con opacidad reducida + overlay oscuro |
| Formulario | Card glass centrado, tema cyan/slate |
| Textos | Español (B2B, sin registro público) |
| Logo | `logo-zentra.png` sobre el formulario |
| Volver | Enlace a `/` |

Componente: `app/View/Components/AuthLayout.php` · Vista: `resources/views/auth/login.blade.php`

Otras rutas auth (recuperar contraseña, etc.) siguen usando `GuestLayout` de Breeze hasta migrarlas.

---

## Fase 0 — Multi-tenant (implementado)

### Base de datos

- `security_companies` — empresas de seguridad + **paquete comercial** (`package_sku`, `package_size`, `package_modality`, `max_clients`, `package_price_monthly`)
- `clients` — clientes comerciales (`party_type`, documento, `structure_type_id`, `login_suffix` auto; legacy `plan_tier`/`max_structures` no limitan portafolio)
- `installations` — sitio físico del cliente (puede ser el mismo cliente)
- `supervisor_posts` — puestos de Supervisión de campo (nunca un `location`)
- `client_user_assignments` — asignación usuario ↔ cliente
- `client_id` en tablas operativas (`locations` cuelga de `installation_id`, buildings, residents, vehicles, etc.)

### Paquetes comerciales (empresa)

Planes **Bronce 1–5 / Plata 6–15 / Oro 16–40 / Platino 41+** por cantidad libre. Accesos, Supervisión y Observatorio se cobran **por instalación**; Indexación **por empleado** ($1.000; Bronce 1–1.000 / Plata 1.001–2.000 / Oro 2.001–3.000 / Platino 3.001+). Cada producto tiene su plan: unidad × cantidad × (1 − % del plan); al cruzar un rango todas cambian de precio. Combinar da 30% a cada producto después del de mayor valor; el anual (−17%) va encadenado. Cada plan limita puestos por instalación, empleados por puesto y empleados totales; el súper admin amplía y la empresa solicita ampliación. Disponibilidad por plan y Hardware, precio pactado, incremento anual e historial. Menú **Planes** en `/admin/pricing`. [`docs/MODELO-COMERCIAL-PAQUETES.md`](docs/MODELO-COMERCIAL-PAQUETES.md) · [`docs/PAQUETES-ACCESOS-Y-SUPERVISION.md`](docs/PAQUETES-ACCESOS-Y-SUPERVISION.md).

Motor: `App\Services\Pricing\PlanQuoter` + `CatalogPricer` (`config/catalog.php`, `pricing_settings.catalog`); JS `resources/js/plan-picker.js` y carrito `resources/js/plan-cart.js`. Recálculo diferido: `CompanyRepricingService`. Anual: ×12 × (1 − 17%).

### Arquitectura

- `TenantContext` + `ClientScope` + trait `BelongsToClient`
- Middleware: `tenancy.access`, `tenant.unscoped`, `company`, `client.admin`, `platform.admin`
- Capas: Controllers → Services → Repositories → Models
- Policies Spatie + permisos en `config/access.php`
- Helper: `App\Support\Tenancy\CompanyPackage` · `AssignCompanyPackageService`

### Panel Plataforma (`/admin`)

Documentación completa: [`docs/PLATAFORMA-ADMIN.md`](docs/PLATAFORMA-ADMIN.md)

| Ruta | Función |
|------|---------|
| `GET /admin/dashboard` | Dashboard analítico: mapa, KPIs, cartera, paquetes, TOP facturación, tendencia MRR |
| `GET /admin/descargas` | **Descargas**: APK de Supervisión + web de respaldo |
| `GET /admin/companies` | Listado empresas + KPIs (riesgo, totales empresas/conjuntos) |
| `GET/POST /admin/companies/create` | Alta empresa (datos fiscales, paquete, ubicación geo) |
| `POST /admin/companies/{id}/archive` | Archivar empresa (cascada a clientes) |
| `POST /admin/companies/{id}/clients/{client}/release` | Retirar conjunto y liberar cupo |
| `GET /admin/pricing` | Planes Bronce–Platino (tablas, chips, ayuda, incremento anual) |
| `PUT /admin/pricing` | Guardar unidades, %, módulos, disponibilidad y alcance (nuevas / actuales) |
| `GET /admin/companies/{id}` | **Resumen** empresa: KPIs, cartera de clientes, aplicar plan Accesos+Supervisión (ya / fecha / al corte), Pagar / Cancelar / Cobrar y programar al corte / Reactivar |
| `GET /admin/companies/{id}/historial` | Historial comercial (solo lectura): pagos, facturas, timeline |
| `POST /admin/companies/{id}/payments/manual` | Pago manual (ref + soporte PDF/imagen + intent) |
| `POST /admin/companies/{id}/membership/cancel` | Cancelar membresía (acceso hasta fin de periodo) |
| `POST /admin/companies/{id}/membership/undo-cancel` | Deshacer cancelación sin pago (si aún al día) |
| `POST /admin/companies/{id}/plan` | Súper admin: aplicar Accesos + Supervisión ya, en fecha o al corte (sin comprobante) |
| `POST /admin/companies/{id}/package/schedule` | Programar cambio de plan (pago ahora, aplica al corte) |
| `POST /admin/companies/{id}/enter` | Entrar como empresa (soporte, sesión + banner + audit) |
| `POST /admin/support/exit` | Salir del modo soporte → expediente empresa |
| `GET /admin/settings/document-types` | **Ajustes**: tipos de documento de identidad |
| `POST/PUT/DELETE /admin/settings/document-types/{id}` | CRUD + reordenar |
| `PUT /admin/companies/{id}/package` | Asignar SKU comercial y facturación (legacy; preferir programar cambio) |
| `GET /admin/companies/{id}/profile` | Perfil legal, contacto y ubicación |
| `PUT /admin/companies/{id}/profile` | Guardar perfil empresa |
| `GET /admin/users` | Usuarios globales |
| `GET/PUT /admin/users/{id}/edit` | Crear/editar usuario (cualquier rol) |
| `GET /admin/documents` | Hub documental (KPIs, accesos normoteca/TRD/expedientes) |
| `GET /admin/documents/normativa` | Normoteca — globales + contratos por SKU |
| `GET/PUT /admin/documents/normativa/{id}/edit` | Editar y publicar nueva versión (no altera expedientes aceptados) |
| `GET /admin/documents/trd` | Tabla de retención documental |
| `GET /admin/documents/expedientes` | Listado expedientes por suscriptor |
| `GET /admin/documents/expedientes/{company}` | Expediente: timeline, documentos, corpus congelado, pago |
| `POST /admin/documents/expedientes/{company}/acceptance` | Registrar clickwrap + rep. legal (snapshot con contenido) |
| `POST /admin/documents/expedientes/{company}/payments/manual` | Pago manual + factura demo |
| `POST /admin/documents/expedientes/{company}/payments/local-checkout` | Checkout simulado (sin proveedor) |

**Pagos locales (sin proveedor):** `BILLING_GATEWAY_DRIVER=local` abre checkout interno (`/billing/checkout/{payment}`) con Aprobar/Rechazar. Manual súper admin + online simulado convergen en `commercial_payments`. Guía: [`docs/BILLING-LOCAL-Y-MIGRACION.md`](docs/BILLING-LOCAL-Y-MIGRACION.md).

**Membresía (v1):** en Resumen se gestionan intents `renew` / `anticipate` / `reactivate` / `plan_change`. Cancelación = `cancel_at_period_end` (sigue operativa hasta `package_ends_at`); al vencer, lifecycle suspende **sin gracia**. **Suspender acceso** (ficha admin, bloque Acceso al sistema) apaga de inmediato a todos salvo el admin empresa (solo lectura + «Servicio suspendido»). No es Cancelar membresía. Historial es solo lectura (los pagos manuales del súper admin sí aparecen).

**Módulo Documentos (v1.1):** normoteca con **contrato por plan (SKU)** y documentos globales (T&C, privacidad, **protección de menores**, procedimiento); versionado desde admin; al aceptar se congela contenido + hash en expediente (**inmutable** ante cambios posteriores de Normoteca); clickwrap, pago manual y factura demo (`BILLING_MODE=demo`). El aviso de menores en Personas / alta de cliente y admins lee la versión vigente. Sin export PDF/HTML en v1. Detalle: [`docs/MODULO-DOCUMENTOS.md`](docs/MODULO-DOCUMENTOS.md).

**Ciclo comercial (acceso):** gracia 5 días → suspensión (bloqueo) → archivo por falta de pago tras N días (`SUBSCRIPTION_ARCHIVE_AFTER_SUSPENDED_DAYS`, default 90) → retención → purga. Job `subscriptions:process-lifecycle` (diario 02:00). Canceladas al fin de periodo: sin gracia al vencer.

**Paquetes:** cupo × modalidad × ciclo mensual/anual **no se eliminan**; son independientes del motor de acceso. Reactivar = reasignar paquete (`AssignCompanyPackageService`). Ya no existe «cartera por recuperar» (`archive_reason`: `cancelled` \| `non_payment`).

**Retención legal:** purga datos operativos tras 365 días y anonimización comercial tras 5 años (`data:purge-retention`, mensual día 1 03:00). Config: `config/retention.php`.

**Cupo:** solo `lifecycle = active` consume slot (`operationalClientsCount()`).

Config acceso: `config/subscription.php` · detalle: [`docs/PLATAFORMA-ADMIN.md`](docs/PLATAFORMA-ADMIN.md).

### Panel Empresa (`/company`)

Sidebar: **Mi empresa** (dashboard) · Facturación · Clientes · **Instalaciones** · **Observatorio** · Supervisión · **Atención de pánicos** · **Descargas** · **Empleados** · **Documentos** · Usuarios · **Mis datos** (perfil) · **Ajustes** (Cargos | Tipos | Estructuras | Zonas | Turnos | Preoperacional | Documentos | Libros | Tipos de arma | Marcas | Riesgos | Alarmas | Apoyos).

| Ruta | Función |
|------|---------|
| `GET /company/dashboard` | **Mi empresa** — Command Center (3 filas): mapa satélite, cartera/alertas, fuerza laboral, accesos, turnos, revistas mes/semana |
| `GET /company/clients` | Cartera de **clientes** (acción única: **Ver**; vacío: «Aún no tienes clientes creados en la cartera») |
| `GET /company/installations` | Directorio de instalaciones + tablero SIG (servicios, novedades de puesto, revistas) si no hay búsqueda |
| `GET /company/ops/alerts.json` | Poll de pánico/Observatorio (overlay; no incluye a quien disparó) |
| `POST /company/ops/panic` | Pánico por usuario de empresa (mismo canal que portería, cliente y APK) |
| `GET /company/panics` | Atención de pánicos: fichas abiertas/cerradas (`ops.panic.attend`) |
| `GET /company/dispositivos` | Cambio de dispositivo de supervisores: Aceptar/Rechazar (`company.devices.approve` o admin de empresa) |
| `POST /company/panics/claim` | Tomar un pánico y abrir la ficha |
| `GET /company/panics/{id}/ficha` | Carta imprimible / PDF |
| `GET /company/observatory/events` | Observatorio: **Tablero** (filtro cliente; pines / calor instalación / calor riesgo; puntaje; líneas por tipo; leyenda/chips agrupados por slug) y **Eventos**. Tipos: solo lectura. API: `/docs/observatory` |
| `GET /company/observatory/tablero.pptx` | Observatorio: export PPTX del tablero (mismos filtros; solo cifras) |
| `GET /company/clients/{id}` | Ficha: **Cliente** (ficha + tarjetas) \| **Resumen** (KPIs/charts de portería, si `has_access`) |
| `POST /company/clients` | Alta de ficha (sin bloqueo por cupo; asientos al marcar líneas). **No** crea instalaciones, accesos ni puestos |
| `POST/PUT/DELETE /company/clients/{id}/installations` | CRUD instalaciones (catálogo compartido) |
| `POST/PUT/DELETE /company/clients/{id}/locations` | CRUD puertas de una instalación (tarjeta Accesos) |
| `POST/PUT/DELETE /company/clients/{id}/posts` | CRUD puestos compartidos (Accesos y Supervisión) |
| `GET /company/clients/template` | Formato Excel de clientes |
| `POST /company/clients/import/*` | Carga masiva: preview → aceptar |
| `GET /company/supervision` | En vivo: motos + ruta al clic + Ver ficha en curso; Historial: turnos cerrados, una ruta en calle, Ver ficha guardada; Resumen; Fichas de campo |
| `GET /company/supervision/live.json` | Feed En vivo (turnos abiertos + revistas); no llama Roads |
| `GET /company/supervision/turnos/{shift}/ruta` | Historial: Snap to Roads en turno cerrado (cache `snapped_route`) |
| `GET /company/supervision/fichas/{kind}/{id}` | Ficha HTML carta. `kind=shift`: ficha de turno (borrador si está abierto; `sheet_snapshot` al cerrar) |
| `GET /company/supervision/informe.pptx` | Informe ejecutivo PPTX (mismo filtro; solo cifras). Compositor + párrafos + GRACIAS + DeepSeek + chatbot/PQRS: pendiente, [`docs/SUPERVISION-CAMPO.md`](docs/SUPERVISION-CAMPO.md) §§ Informe PPTX y Chatbot y PQRS |
| `GET /company/descargas` | **Descargas**: APK (`/company/descargas/zentra-supervision.apk`) + web PWA de respaldo |
| `GET /company/billing` | **Facturación** unificada: membresía Accesos + Supervisión, historial, pago online |
| `POST /company/billing/supervision` | Contratar / cambiar Supervisión (self-serve, aplica al corte) |
| `POST /company/billing/checkout` | Checkout online (intent renew/anticipate/reactivate) |
| `POST /company/billing/membership/cancel` | Cancelar membresía (acceso hasta corte) |
| `POST /company/billing/membership/undo-cancel` | Deshacer cancelación sin pago |
| `POST /company/billing/package/schedule` | Programar cambio de plan (cobra online, aplica al corte) |
| `GET /company/users` | Usuarios (`status=active` \| `inactive`; pestañas en header; internos y externos) |
| `GET/PUT /company/users/{id}/edit` | Crear/editar usuario scoped (foto `avatar`, cliente + instalaciones) |
| `GET /company/settings` | **Mis datos**: perfil, ubicación, logo y encabezado de fichas (sin pestañas) |
| `PUT /company/settings` | Guardar perfil, logo y texto de encabezado |
| `GET /company/employees` | **Empleados**: listado (Ficha), Formato Excel, carga masiva (preview → alta o actualización por documento) |
| `GET /company/documents` | **Documentos** de personal: carpetas + indexador PDF. Preview con Ver; Descargar con Gestionar |
| `GET /client/documents` | Carpetas del personal asignado (Accesos + `show_personnel_folders`). Ver = preview; Gestionar = descargar |
| `GET /company/employees/template` | Descarga plantilla (hojas Empleados + Instrucciones; A–Z WM + extras SJ-SIG) |
| `GET/POST /company/employees/{id}/photo` | Foto de la ficha (JPG/PNG/WebP, disco local) |
| `GET /company/job-titles` | **Ajustes → Cargos**: catálogo por empresa |
| `GET /company/collaborator-types` | **Ajustes → Tipos**: catálogo por empresa |
| `GET /company/structure-types` | **Ajustes → Estructuras**: tipos de sitio por empresa |
| `GET /company/supervision-zones` | **Ajustes → Zonas**: rutas de Supervisión (no Accesos) |
| `GET /company/supervision-shifts` | **Ajustes → Turnos**: plantillas nombre + horario |
| `GET /company/supervision-preop` | **Ajustes → Preoperacional**: EPP y vehículo |
| `GET /company/supervision-document-types` | **Ajustes → Documentos**: tipos que el supervisor recoge o entrega en el turno |
| `GET /company/supervision-control-book-types` | **Ajustes → Libros**: tipos de libro de control del puesto |
| `GET /company/supervision-weapon-types` | **Ajustes → Tipos de arma**: pistola, escopeta, revólver, etc. |
| `GET /company/supervision-weapon-brands` | **Ajustes → Marcas**: marcas del arma en revista |
| `GET /company/supervision-risk-types` | **Ajustes → Riesgos**: tipos de la recomendación (físico, químico, eléctrico, etc.) |
| `GET /company/supervision-alarm-types` | **Ajustes → Alarmas**: tipos para prueba o atención (pánico, incendio, etc.) |
| `GET /company/supervision-support-types` | **Ajustes → Apoyos**: tipos de apoyo operativo |

Detalle empleados: [`docs/EMPLEADOS-Y-CARGOS.md`](docs/EMPLEADOS-Y-CARGOS.md). Supervisión de campo: [`docs/SUPERVISION-CAMPO.md`](docs/SUPERVISION-CAMPO.md).

`/company/clients/select` redirige a `/company/porteria` (vista eliminada).

#### Facturación empresa (`/company/billing`)

Misma fuente que el historial plataforma (`commercial_payments`, facturas, evidencias). La empresa **no** registra consignación manual: solo checkout online; si el súper admin registra un pago manual, aparece en su historial.

Acciones: anticipar/renovar/reactivar online · cancelar · deshacer cancelación · programar cambio de plan (pago online diferido).

#### Expediente de conjunto (`/company/clients/{id}`)

Pestañas: **Cliente** (ficha + Operar portería / Operar cliente si Accesos, Editar, **Instalaciones y puestos**, **Puertas** si Accesos) · **Resumen** (si `has_access`: KPIs de portería). Árboles: `?vista=sitio` y `?vista=puertas`. El sitio no lista revistas (van a `/company/supervision`). Un puesto puede tener varios vigilantes; un empleado solo un puesto.

El Excel de clientes **solo** carga la ficha. Instalaciones, puestos y puertas se crean a mano aquí. Detalle: [`docs/CLIENTES-Y-ESTRUCTURA.md`](docs/CLIENTES-Y-ESTRUCTURA.md).

| Concepto UI | Fuente |
|-------------|--------|
| Instalación | `installations` (compartida Accesos/Supervisión; geo obligatoria; `is_client_site` copia nombre + pin del cliente) |
| Puerta | `locations` (`type = access_point`) de una instalación. Solo Accesos. **No** es el puesto |
| Puesto | `supervisor_posts` de una instalación (catálogo único, 8/12/24 h + vigilantes). Visible en Accesos y Supervisión. La PWA lista solo estos |
| Unidades | `structures` desglosadas por catálogo `structure_types` (apto, casa, torre, bodega…) |
| Personas (censo) | `structure_members` por `MemberType` (propietario, familiar…) |
| Usuarios app | `structure_app_users` activos (no confundir con `residents` legacy) |
| Parque vehicular | `vehicles.is_visitor_vehicle` + `access_logs` (adentro = sin `exit_time`) |

**Retorno al expediente (v2):** al operar portería o cliente se activa `CompanyOperateContext` (sesión). Los layouts `access` y `client` muestran un banner ámbar (mismo estilo que el modo soporte del súper admin) con **Volver al expediente** → `POST /company/operate/exit`. Composer: `OperateReturnLayoutComposer`.

#### Alta comercial de cliente

Ver [`docs/CLIENTES-Y-ESTRUCTURA.md`](docs/CLIENTES-Y-ESTRUCTURA.md).

| Campo UI | Notas |
|----------|--------|
| Tipo de cliente | `party_type` (jurídica / natural) |
| Documento | Catálogo `identity_document_types` + `tax_id` |
| Tipo de estructura | Obligatorio; queda en `clients.structure_type_id` |
| Slug / sufijo login | No en formulario; auto en `CreateClientService` |

#### Catálogo de estructuras y puntos de acceso

| Concepto | Dónde | Detalle |
|----------|-------|---------|
| Tipos de estructura | `/company/structure-types` | Por empresa. Nombre + activo + unidad ocupable; código interno automático; no borrar si hay clientes o nodos |
| Tipos de documento | `/admin/settings/document-types` | CC/CE/NIT…; alta cliente + clickwrap legal |
| Tipo fijo del cliente | Alta/edición `/company/clients` | Solo tipos de **esa** empresa. Los nodos nuevos heredan ese tipo |
| Instalaciones | Tarjetas Accesos / Supervisión de la ficha | Catálogo compartido + mapa; «mismo cliente» copia nombre y ubicación |
| Accesos (puertas) | Tarjeta Puertas o `/access/locations` | `locations.installation_id` obligatorio |
| Puestos | Tarjeta Instalaciones y puestos | `supervisor_posts`; modalidad 8/12/24 h + empleados; la app no usa puertas |
| Seed mínimo | `IdentityDocumentTypeSeeder` | Documentos de identidad |
| Seed opcional | `PilotDemoSeeder` | empresa/clientes + 4 tipos de estructura del piloto + árbol Palmas/Torres + censo |

#### Command Center (`/company/dashboard`)

Dashboard operativo multi-conjunto para `company-admin`. Capas: `DashboardController` → `CompanyDashboardService` → `CompanyDashboardAnalytics` + `ClientRepository`.

Layout de **3 filas**. Ancho: rail `.company-shell-rail` en el layout empresa (header + flash + main alineados; **sin** `max-w-7xl`). Padding responsive; tope suave ~1600px (`2xl`) / ~1760px (`≥1920px`).

| Breakpoint | Comportamiento |
|------------|----------------|
| `<768px` | Filas apiladas; mapa ~320px; paneles altura auto/fija móvil |
| `768–1023` | Side cartera+alertas en 2 cols; fila 2 en 2+1; fila 3 en 2 cols |
| `≥1024` | Grid desktop: mapa `1.75fr` + side; filas 2 (3 cols) y 3 (2 cols); alturas fijas |
| `≥1280` / `≥1536` | Más peso al mapa (`2.05fr` → `2.25fr`); paneles más altos; buscador más ancho |

| Fila | Paneles |
|------|---------|
| 1 | **Mapa de conjuntos** + **Cartera de clientes** + **Alertas y registros (hoy)** |
| 2 | **Fuerza laboral** \| **Accesos por conjunto (hoy)** \| **Turnos abiertos** |
| 3 | **Revistas mensuales** (8 meses) \| **Revistas de supervisión (7 días)** |

| Bloque | Contenido |
|--------|-----------|
| Cartera | Plan activo, activos, archivados, disponibles (cupo − activos); enlace facturación |
| Alertas hoy | Novedades, correspondencia pendiente, pánicos abiertos, bloqueos activos |
| Fuerza laboral | Vigilantes, en turno, supervisores, sin asignación |
| Accesos | Chart.js: vehículos vs visitantes peatonales por conjunto (hoy) |
| Turnos | Tabla `guard_shifts` abiertos + última revista |
| Revistas | Series meta / realizados / pendientes (mes y semana) — sin duplicar KPIs de cumplimiento |

**Mapa (Google Maps):**

- Requiere `GOOGLE_MAPS_API_KEY` (mismo config que `/admin`)
- Default **satélite**; toggle cabecera **Satélite \| Terreno** (`MapTypeId.TERRAIN`)
- Buscador de conjunto en la cabecera del panel (fuera del canvas)
- Pins con nombre; **sin auto-abrir** el primero
- Clic → nube custom (no InfoWindow nativo): revistas, turno, accesos, Ver / Operar
- Zoom con rueda (`scrollwheel` + `gestureHandling: 'greedy'`) y controles `+`/`−`
- Fallback SVG si no hay API key

**Modelos / migraciones ops (v1):**

- `clients.service_hours`, `clients.revista_target_per_day` — meta de revistas por día
- `guard_logs.resolved_at` — cierre de pánico / novedad
- `guard_shifts` — turnos de vigilante (inicio/fin, puesto, notas)
- `supervisor_reviews` — revistas de supervisor (geo, observaciones, vínculo a turno)
- `Blocklist`: scopes `active()`, `vehicles()`, `persons()` + tipado visitante/vehículo/residente

Analytics: `revista_monthly`, `revista_week`, `access_by_client`, `open_shifts_table`, `portfolio`.  
Test: `tests/Feature/Company/CompanyDashboardTest.php`.

### Diseño UI — Panel Empresa (`/company`)

Sistema visual unificado para el shell y formularios del panel empresa. **Guía completa:** [`docs/DISENO-UI-ZENTRA.md`](docs/DISENO-UI-ZENTRA.md)

| Elemento | Detalle |
|----------|---------|
| Layout | `layouts/company.blade.php` — shell `h-screen`; sidebar plegable en desktop (flecha); en móvil hamburguesa + drawer; rail `.company-shell-rail`; slots `subtitle` / `actions` / `headerTabs` (pestañas colgantes bajo el borde del header) |
| Dashboard | Command Center v3: grid responsive, mapa satélite/terreno |
| Componentes | `x-ui.button`, `x-ui.label`, `x-ui.input`, `x-ui.field-error`, `x-ui.geo-address-fields`, `x-ui.flash-toasts` |
| Tabs | `.admin-header-tab` — contorno `slate-800` (= borde del header) para sensación de “colgar” de la barra |
| Analytics | `CompanyDashboardService` + `CompanyDashboardAnalytics` · expediente conjunto: `BuildClientExpedienteService` |
| Contexto | `CompanyLayoutComposer` → `companyContext` + `supportMode`; `OperateReturnLayoutComposer` → banner en access/client |
| Vistas | `company/dashboard` (Mi empresa), `company/clients/*`, `company/installations/*`, `company/billing`, `company/downloads`, `company/users/*`, `company/settings` (Mis datos), `company/employees/*`, `company/job-titles`, `company/collaborator-types`, `company/structure-types` |

Variantes de botón: `primary` (indigo), `secondary`, `success` (emerald), `platform` (violet en `/admin`). Tamaños: `sm`, `md`.

**Pendiente migración:** `company/clients/show` y paneles conjunto/portería con acentos propios (teal/indigo).

### Diseño UI — Panel Plataforma (`/admin`)

| Elemento | Detalle |
|----------|---------|
| Layout | `resources/views/layouts/admin.blade.php` — shell `h-screen`; sidebar violet plegable en desktop; hamburguesa en móvil; pie (nombre + Cerrar sesión) siempre visible; nav con scroll interno |
| Dashboard | Mapa geográfico (Google Maps), KPIs, estado de cartera (6 segmentos), modalidad/cupo/ciclo, TOP 5 facturación, KPIs comerciales, tendencia MRR (Chart.js) |
| Empresas | 3 KPIs: Riesgo (suspendidas/archivadas/eliminadas), Total empresas, Total conjuntos; tabla de cartera |
| Analytics | `PlatformDashboardAnalytics` — agregación de métricas y marcadores del mapa |
| Geolocalización | `address`, `city`, `department`, `latitude`/`longitude` en empresas, conjuntos e intents; picker Places (`geo-address-picker.js`) |
| Acceso/cobranza | `config/subscription.php` + `billing_day`; lifecycle sin «recovery» |
| Mapa | Toggle Empresa / Clientes; requiere `GOOGLE_MAPS_API_KEY` en `.env` |
| Componentes | Mismos `x-ui.*` con `variant="platform"` y `accent="platform"` en inputs |
| Vistas migradas | `admin/dashboard`, `admin/companies/*`, `admin/users/*`, `admin/pricing/edit`, `admin/documents/*` |
| Shell UI | Mismo patrón viewport-fixed en `layouts/company`, `client`, `access` (en access el nav largo scrollea; el pie no) |

---

## Fase 1 — Estructura / censo (implementado)

### Modelo unificado `structures`

Árbol autoreferencial bajo la **instalación** (`structures.installation_id`): elige instalación → nodos (Torre A, Salón B…) → subnodos. El **tipo** del cliente (`clients.structure_type_id`) se fija en el alta comercial; los nodos creados desde UI **heredan** ese tipo. Las personas se asignan a un nodo.

Detalle de dominio y glosario: [`docs/CLIENTES-Y-ESTRUCTURA.md`](docs/CLIENTES-Y-ESTRUCTURA.md).

Tablas relacionadas:

- `structure_members` — censo: tipo de documento, fecha de nacimiento, `access_code` (QR de adultos); **requieren** `structure_id`
- `structure_pets` — mascotas
- `visitor_pre_authorizations` — pre-autorizaciones con `qr_auth_token`
- `structure_app_users` — acceso de personas del censo (`usuario@login_suffix`)
- `vehicles.structure_id` — vehículos vinculados a unidad

### Panel Cliente (`/client`)

| Ruta | Módulo |
|------|--------|
| `/client/dashboard` | Resumen SIG: servicios hoy, novedades de puesto, revistas, tabla de servicios, afiliación, gráfica de servicios por mes |
| `/client/installations` | Directorio de instalaciones + tablero SIG compacto (sin búsqueda). `/client/structures` redirige |
| `/client/ops/alerts.json` | Poll de pánico/Observatorio (overlay) |
| `/client/ops/panic` | Pánico por usuario (mismo canal; overlay en empresa, no el emisor) |
| `/client/observatory/events` | Observatorio (módulo opt-in). Tablero + Eventos + **Tipos**. **Nuevo reporte**: hasta 3 fotos (cámara/miniatura, opcional). Rector cierra; apoyo reporta |
| `/client/observatory/tablero.pptx` | Observatorio: export PPTX del tablero (mismos filtros; solo cifras) |
| `/client/members` | Personas: tipo de documento + fecha de nacimiento; menores (Ley 1581) sin export; QR solo adultos |
| `/client/pets` | Directorio de mascotas por unidad |
| `/client/vehicles` | Directorio vehicular |
| `/client/authorizations` | Pre-autorizaciones |
| `/client/authorizations/import` | Import Excel (`maatwebsite/excel`) |
| `/client/app-users` | Accesos — personas del censo (`structure_app_users`) |
| `/client/users` | Lista admins externos. **No crea** (alta en `/company/users` o `/admin/users`) |

### Mascotas (`/client/pets`) — CRUD completo

- **Rutas**: `index`, `create`, `store`, `show` (sigue el patrón de Members)
- **Especies**: Perro, Gato, Ave, Otro/exótico (enum `PetSpecies`)
- **Marcador de peligrosidad**: `is_potentially_dangerous` con badge rojo
- **Filtros**: búsqueda por nombre/raza, filtro por unidad
- **Vista de estructura**: lista de mascotas embebida en la vista `structures.show`

### Exportar listado asamblea (`/client/members/export`)

Descarga un Excel del censo para juntas. **Excluye menores de 18 años** (Ley 1581 art. 7).

- **Clase**: `MembersAssemblyExport` (maatwebsite/excel)
- **Columnas**: Nombre completo, Documento, Tipo, Nodo, Teléfono, Email, Acceso APP
- **Ordenado**: por apellido y nombre
- Botón `Exportar` en Personas

### Servicios clave

- `StructureRepository` — árbol, contadores censo
- `MigrateLegacyStructuresService` — buildings/housing_units → structures
- `SeedPilotStructuresService` — datos piloto
- `ImportAuthorizationsService` — Excel columnas: `visitante`, `estructura`, `fecha`

---

## Limpieza arquitectónica (implementado)

- Eliminado dashboard genérico Breeze (`/dashboard` + `dashboard.blade.php`)
- `/dashboard` redirige a `/home` (resolver por rol)
- Panel **Plataforma** `/admin/dashboard` para Súper Admin
- `ResolveUserHomeRoute` centraliza redirects post-login
- Permisos explícitos: `platform.dashboard`, `platform.companies.*`

---

## Módulo Portería (`/access`) — línea base

Consola del vigilante. Detalle: [`docs/PORTERIA.md`](docs/PORTERIA.md). Layout `layouts.access`. Pánico: `POST /access/ops/panic`. Ingreso y salida: pestaña Movimiento (busca → Ingresa/Sale; foto en ficha) y pestaña Registros. Menú: Resumen, Ingreso y salida, Personas, Vehículos, Mascotas, Correspondencia, Autorizaciones, Reservas, Minutas, Lista de bloqueo, Instalaciones, Visitantes, Turnos. El vigilante: una puerta se asigna sola; si hay varias las elige al abrir turno. Operar portería no pide puerta.

Las pantallas legacy (`buildings`/`residents`, `/access/operations`) redirigen o quedan fuera del menú.

### Lista de Bloqueo (`/access/blocklist`)

Permite denegar acceso a personas o vehículos desde la portería:

- **Tabla `blocklist`**: polimórfica (`blockable_type`/`blockable_id`) para visitantes, vehículos y residentes
- **CRUD completo**: crear con búsqueda por tipo, listar activos, remover bloqueo
- **Scopes**: `active()`, `vehicles()`, `persons()` — tipado unificado (string legacy + FQCN)
- **Expiración opcional**: se puede establecer fecha de expiración del bloqueo
- **Permiso**: `access.manage.blocklist` (asignado a guardia, supervisor, client-admin)
- **Consumo empresa**: el Command Center agrega bloqueos activos por cartera de conjuntos

### Salida masiva

Botón `Salida Masiva` en la vista de Ingreso/Salida que marca como `completed` todos los registros activos del día:

- Método `bulkExit()` en `AccessLogController`
- Confirmación antes de ejecutar
- Útil para cierre de turno o jornada

### Reportes (`/access/reports`)

- **Nuevos filtros**: tipo de acceso (visitante, vehicular, residente) además de fecha, estado y ubicación
- **Exportación a Excel**: botón `Exportar Excel` que descarga los resultados filtrados como `.xlsx`
- **Vista imprimible**: `resources/views/modules/access/reports/printable.blade.php`
- **Nuevas estadísticas**: total ingresos, dentro, hoy, visitantes, promedio de estadía
- Clase `AccessLogsExport` con `FromQuery`, `WithMapping`, `WithHeadings`, `ShouldAutoSize`

### Ops portería+ (ago 2026) — supervisión, turnos, zonas, auditoría

Integrado en `main` / `creawilder` (rama Manuel + Command Center). Roles en `config/access.php` (incl. **`supervisor`** — no eliminar: `RoleAndPermissionSeeder` borra roles ausentes del config).

| Módulo | Rutas / piezas clave | Notas |
|--------|----------------------|--------|
| **Supervisión (minuta)** | `/access/supervision/*`, códigos `/access/supervision/codes` | Unlock por código, evidencias (`supervision_attachments`), firma en minuta |
| **Turnos portería** | `/access/turnos` | Apertura/cierre; puerta obligatoria; `EnsureOpenShift` + `EnsurePorteriaDoor` |
| **Pánico** | `POST /access/ops/panic`, `GET /access/ops/alerts.json` | Mismo overlay que cliente/supervisor. Sin minuta `is_panic`. [`PORTERIA.md`](docs/PORTERIA.md) |
| **Reservas** | Panel cliente `/client/bookings` (calendario, aprobación, zonas con nombre libre y su configuración); portería `/access/zones` (Hoy, Próximas, Historial: dar uso / finalizar) y `/access/zones/calendar` (consulta y solicitud por aprobar) | `common_zones` por instalación (nombre único por instalación) + `common_zone_bookings`; solicitudes de portería con `origin = porteria`; invitados → autorización tipo Evento; permisos `client.bookings.manage` / `access.manage.zones` |
| **Radio de revistas** | Súper admin `/admin/settings` → tarjeta «Radio de revistas» | Global + tope en `supervision_review_settings`; excepciones por empresa, cliente o instalación en `supervision_review_radii`; se aplica instalación → cliente → empresa → global, nunca por encima del tope |
| **Auditoría** | `/access/audit` | `audit_logs` + `AuditLogger` |
| **Geo / recurrencia** | Locations con geo; pre-autorizaciones con recurrencia | `GeoService`, `RecurrenceService` |
| **Notificaciones** | Portal residente | `notifications` + `CorrespondenciaRecibida` / `AlertaOperativa` |

Migraciones batch (tras pull): `2026_08_07_0100*` (supervisión) + `2026_08_14_1000*` (zonas, audit, geo, recurrencia, notifications).  
Tras permisos nuevos: `php artisan db:seed --class=RoleAndPermissionSeeder`.

---

## Tests

Los tests usan una **base de datos aislada** (`zentra_test`), configurada en `phpunit.xml`. No tocan la BD de desarrollo (`zentra` en `.env`).

Crear la BD de test una sola vez (Laragon / MySQL):

```sql
CREATE DATABASE IF NOT EXISTS zentra_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
php artisan test
```

Suites relevantes:

- `tests/Feature/Tenancy/TenantIsolationTest.php`
- `tests/Feature/Structure/StructureModuleTest.php`
- `tests/Feature/Platform/PlatformDashboardTest.php`
- `tests/Feature/Platform/PlatformCompaniesIndexTest.php`
- `tests/Feature/Company/CompanyDashboardTest.php`
- `tests/Feature/Company/CompanyBillingTest.php`
- `tests/Feature/Company/CompanyClientExpedienteTest.php` (operar + volver al expediente)
- `tests/Feature/Company/CompanyClientSiteTreeTest.php` (instalaciones, accesos, puestos)
- `tests/Feature/Api/SupervisorShiftApiTest.php`
- `tests/Feature/Api/SupervisorFieldLogApiTest.php`
- `tests/Feature/Company/CompanySupervisionCatalogTest.php`
- `tests/Feature/Company/CompanyStructureTypeTest.php`
- `tests/Feature/Observatory/ObservatoryReportFlowTest.php`
- `tests/Feature/Observatory/ObservatoryApiTest.php`
- `tests/Feature/Billing/LocalPaymentCheckoutTest.php`
- `tests/Feature/Public/PublicSignupFlowTest.php`
- `tests/Feature/User/ScopedUserManagementTest.php`
- `tests/Feature/Platform/PlatformDocumentsTest.php`
- `tests/Feature/Platform/EnterCompanyAsSupportTest.php`
- `tests/Unit/Platform/DataRetentionPurgeTest.php`
- `tests/Unit/Platform/SubscriptionLifecycleTest.php`
- `tests/Feature/Auth/LoginCsrfTest.php`
- `tests/Unit/Pricing/PriceCalculatorTest.php`

> Los tests usan `RefreshDatabase` y **recrean** `zentra_test` en cada ejecución. Nunca ejecutar la suite completa contra la BD de desarrollo.

---

## Estructura de carpetas (nuevas)

### Portal Residente (`/resident`) — Fase 4

Panel web para residentes con pre-autorizaciones y seguimiento de correspondencia:

| Ruta | Función |
|------|---------|
| `/resident/dashboard` | Resumen: pre-autorizaciones activas y correspondencia pendiente |
| `/resident/pre-authorizations` | Listado y cancelación de pre-autorizaciones |
| `/resident/pre-authorizations/create` | Crear nueva autorización de visita (nombre, documento, fecha, ubicación) |
| `/resident/correspondence` | Historial de correspondencia recibida y entregada |
| `/resident/correspondence/{id}` | Detalle de paquete o encomienda |

- Layout dedicado: `layouts/resident.blade.php` (tema oscuro, navegación propia)
- Componente Blade: `ResidentLayout`
- Roles: `resident`, `anfitrion`
- Redirección post-login: `ResolveUserHomeRoute` → `/resident/dashboard`

## API REST (`/api`) — Sanctum

API autenticada con tokens Laravel Sanctum para consumo desde app móvil futura.

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/api/auth/login` | POST | Login con email+password, devuelve token |
| `/api/auth/me` | GET | Datos del usuario autenticado |
| `/api/auth/logout` | POST | Revoca token actual |
| `/api/pre-authorizations` | GET | Lista de pre-autorizaciones del usuario |
| `/api/pre-authorizations` | POST | Crear pre-autorización |
| `/api/pre-authorizations/{id}` | GET | Detalle de pre-autorización |
| `/api/pre-authorizations/{id}` | DELETE | Cancelar pre-autorización |
| `/api/correspondence` | GET | Lista de correspondencia del usuario |
| `/api/correspondence/{id}` | GET | Detalle de correspondencia |
| `/api/visitors/search` | GET | Buscar visitantes por nombre/documento |
| `/api/observatory/events` | GET | Observatorio: listar eventos del alcance (filtro estado/fecha/colegio) |
| `/api/observatory/events/{id}` | GET | Observatorio: folio + reportes |
| `/api/observatory/board` | GET | Observatorio: KPIs, tendencia, tipos, canales, ranking |
| `/api/observatory/sites` | GET | Observatorio: colegios del alcance |
| `/api/observatory/reports` | POST | Observatorio: crear reporte (Secretaría = Integración; rector/apoyo = panel). Empresa solo lee |
| `/api/observatory/openapi.json` | GET | Contrato OpenAPI 3 (público) |
| `/docs/observatory` | GET | Documentación Swagger UI |
| `/api/supervision/login` | POST | Login supervisor (paquete Supervisión) |
| `/api/supervision/intake` | GET | Catálogos de turno, zona, EPP, flota |
| `/api/supervision/shifts/*` | GET/POST | Abrir, ping GPS, cerrar turno (multipart fotos) |
| `/api/supervision/catalog` | GET | Contrato de 8 módulos de campo |
| `/api/supervision/posts` | GET | Puestos `supervisor_posts` del cliente (no `locations`) |
| `/api/supervision/reviews` | POST | Revista en puesto de Supervisión (no llena minuta Accesos) |
| `/api/supervision/panic` | POST | Pánico del supervisor (turno abierto; GPS opcional). El teléfono emisor no oye |
| `/api/supervision/observatory/sites` | GET | Colegios de la empresa para el Observatorio |
| `/api/supervision/observatory/reports` | POST | Reporte Observatorio desde la PWA (supervisor) |
| `/api/supervision/offline-pack` | GET | Snapshot offline: sitios, puestos, vigilantes, catálogo |
| `/api/supervision/logs` | POST | Inventario, libros, carpetas, armamento, recomendaciones, etc. `client_event_id` opcional |
| `/api/supervision/sheets` | GET | Fichas del supervisor autenticado |
| `/api/supervision/sheets/{kind}/{id}` | GET | Carta HTML de la ficha (imprimir / PDF) |
| `/api/supervision/recommendations` | GET | Recomendaciones de riesgo (registro inmutable; sin ciclo ni PATCH) |

```
app/
├── Domain/Pricing|Structure|Tenant/  # DTOs (PriceQuote, CreateClientData, etc.)
├── Enums/                          # BillingCycle, CompanyPackageSku, PackageModality, etc.
├── Exports/                        # AccessLogsExport, MembersAssemblyExport
├── Http/Controllers/
│   ├── Api/                        # Sanctum API
│   ├── Platform/                   # Dashboard, Pricing, Company, Document (súper admin)
│   ├── Company/                    # Admin Empresa
│   ├── Client/                     # Admin Cliente
│   ├── Resident/                   # Portal Residente
│   ├── Public/                     # Welcome, planes, signup guest
│   ├── Billing/                    # Checkout local (pasarela simulada)
│   └── Access/                     # Portería
├── Models/PricingSettings.php      # Unitarios editables por súper admin
├── Repositories/
├── Services/Pricing/               # PriceCalculator, UpdatePlatformPricingService
├── Services/Platform/              # Dashboard analytics, archivo, retiro, lifecycle, purga, documentos
├── Services/Company/               # CompanyDashboardService, CompanyDashboardAnalytics
├── Services/Access/                # TurnoService, ZoneBookingService, AuditLogger, GeoService, RecurrenceService, BlocklistGuard
├── Services/Public/                # CompletePublicSignupService
├── Services/User/                  # ManageScopedUserService
├── Services/Auth/                  # UserScopeResolver
├── Policies/UserPolicy.php         # SecurityCompanyPolicy
├── Services/Tenant/                # AssignCompanyPackageService, CreateClientService, EnterPorteriaService
├── Models/GuardShift.php           # Turnos de vigilante
├── Models/SupervisorReview.php     # Revistas de supervisor
├── Policies/
├── View/Components/
├── View/Composers/CompanyLayoutComposer.php
└── Support/Tenancy/CompanyPackage.php

resources/views/components/ui/     # x-ui.* (button, label, input, field-error)
resources/views/components/company/ # x-company.kpi-stat

config/billing.php                  # BILLING_MODE demo|live, prefijo factura demo

routes/modules/
├── public.php                    # /planes, /contratar (guest)
├── billing.php                   # /billing/checkout/{payment}
├── admin.php
├── company.php
├── client.php
├── access.php
└── resident.php

routes/api.php                   # Sanctum endpoints
```

---

## Comandos útiles

```bash
php artisan migrate:fresh --seed            # reset desarrollo (baseline unificado; requiere OK explícito)
php artisan migrate                         # aplicar migraciones pendientes
php artisan subscriptions:process-lifecycle # gracia 5d → suspensión → archivo non_payment (diario 02:00)
php artisan supervision:auto-close-shifts   # fin de plantilla + 30 min; sin plantilla, 3 h idle (cada 5 min)
php artisan schedule:work                   # corre el scheduler en local (Windows no tiene cron)
php artisan data:purge-retention            # purga censo post-retención (también programado mensual)
php artisan db:seed                         # datos demo (aditivo, todos los seeders)
php artisan db:seed --class=RoleAndPermissionSeeder  # sincronizar permisos tras cambios en config/access.php
php artisan db:seed --class=DemoUsersSeeder # solo usuarios demo
php artisan db:seed --class=TenantSeeder    # solo empresa y clientes
php artisan db:seed --class=PlatformDocumentsSeeder  # normoteca + TRD
php artisan config:clear
php artisan route:list --path=planes          # landing y contratación pública
php artisan route:list --path=contratar
php artisan route:list --path=admin         # rutas plataforma (pricing, empresas)
php artisan route:list --path=company       # rutas admin empresa
php artisan route:list --path=api           # ver rutas API
php artisan test                            # usa zentra_test, no zentra
npm run build                               # compilar assets Vite para producción
npm run dev                                 # Vite en desarrollo
```

### Seguridad de base de datos

**Prohibido** en BD de desarrollo sin autorización explícita:

- `migrate:fresh`, `migrate:refresh`, `db:wipe`
- Ejecutar `php artisan test` sin `zentra_test` configurada en `phpunit.xml`

Regla del proyecto para el agente IA: `.cursor/rules/database-safety.mdc`

### Git — remoto único

Único repositorio: `https://github.com/wilder1994/Zentra.git` (`origin`).

```bash
git push origin main
git push origin main:creawilder
```

---

## Licencia

Proyecto privado — uso interno Creawilder / Zentra.
