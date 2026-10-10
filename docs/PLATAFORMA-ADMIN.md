# Panel Plataforma — Súper Admin

Documentación del panel `/admin`: dashboard operativo, ciclo comercial, archivo de cartera y retención legal de datos.

**Última actualización:** 24 septiembre 2026

---

## Superficie y roles

| Prefijo | Rol | Permisos clave |
|---------|-----|----------------|
| `/admin` | `super-admin` | `platform.dashboard`, `platform.companies.view`, `platform.companies.manage` |

Layout: `resources/views/layouts/admin.blade.php` (acento **violet**).  
Shell: altura de viewport fija (`h-screen`); nav del sidebar con scroll interno; pie (nombre + Cerrar sesión) anclado abajo; flecha a media altura para ocultar/mostrar el menú.  
Módulo Documentos (definición + v1 implementado; fases futuras §12): [`MODULO-DOCUMENTOS.md`](MODULO-DOCUMENTOS.md).  
Guía visual: [`DISENO-UI-ZENTRA.md`](DISENO-UI-ZENTRA.md) §13.

### Sesión de soporte (entrar como empresa)

El súper admin no tiene `security_company_id`. El panel `/company` solo vale con `support.acting_company_id` en sesión (`POST /admin/companies/{company}/enter`, auditado).

Si la sesión web caduca en una URL de empresa, al volver a entrar **no** se restaura el soporte (no hay `platform.enter_company` silencioso). Laravel ignora `url.intended` hacia `/company/*` y manda a `/admin`. Si abre `/company/supervision` (u otra ruta de empresa que no sea portería) sin contexto: redirect a `/admin` con aviso, no 403. Cookie `support_last_company_id`: botón **Entrar de nuevo** en el layout plataforma.

Tests: `EnterCompanyAsSupportTest`.

### Ficha empresa — plan y cartera

**Cartera de clientes** (no conjuntos): mismo alto que Paquete y ciclo; la lista hace scroll interno. En móvil, tope de altura para no empujar el formulario. No pagina.

**Paquete y ciclo:** Accesos (cupo, mixto) + Supervisión en un solo formulario. Cuándo aplica: **Ya** (cupo al instante), **el día X**, **al corte**. Si la fecha es hoy o anterior, aplica igual que Ya. Pago en **Pagar factura** (no pide comprobante en este formulario).

- `POST /admin/companies/{company}/plan` (`admin.companies.plan.apply`), permiso `platform.companies.manage`.
- Servicio `ApplyAdminPlanChangeService`. Si no es inmediato, escribe `scheduled_*`; el cron de ciclo (`applyDueChanges`) lo aplica al vencer.
- **Cobrar y programar al corte** (`POST …/package/schedule`) sigue pidiendo comprobante.
- Tests: `AdminPlanChangeTest`.

### Acceso al sistema (suspender / archivar / reactivar)

En la ficha, bloque **Acceso al sistema** (abajo de Pagar / Cancelar membresía). No es lo mismo que cancelar la membresía.

Estado visible: **Activo** · **Suspendido** · **Archivado**. Confirmar siempre en modal del panel (no `confirm()` del navegador).

| Qué pulsar | Cuándo | Qué pasa |
|------------|--------|----------|
| **Suspender acceso** | Mora o corte inmediato | Apaga el sistema ya. Tokens API revocados. |
| **Archivar empresa** | No hay acuerdo | Suspende + saca de cartera (`cancelled` o `non_payment`). |
| **Reactivar acceso** | Ya pagaron / volvió el trato | Prende de nuevo. Si estaba archivada, desarchiva y restaura conjuntos `archived_company`. |

Rutas (`platform.companies.manage`): `POST …/cut` · `POST …/archive` · `POST …/reactivate-service`.

**Quién entra con el acceso suspendido o archivado**

- **Admin empresa:** entra al panel web, solo lectura, aviso **Servicio suspendido**.
- **Cualquier otro** (colaborador, portería, PWA/APK, API): no entra. *Este usuario no tiene acceso al sistema. Comuníquese con el administrador.*

**Cancelar membresía** sigue siendo contrato: opera hasta `package_ends_at`. No apaga el sistema.

El cron de mora usa el mismo corte (`SuspendCompanyService`).

Tests: `CutCompanyServiceTest`.

---

## Dashboard (`GET /admin/dashboard`)

Pantalla analítica de la plataforma. Métricas comerciales, distribución geográfica y tendencias de facturación.

### Layout (v2 — julio 2026)

| Bloque | Contenido |
|--------|-----------|
| **Fila superior** | Mapa (Google Maps) + 4 KPIs + donut estado de cartera |
| **Paquetes** | Modalidad (manual/hardware), cupo contratado (1/5/10/50/100), ciclo (mensual/anual) |
| **Facturación** | TOP 5 empresas por MRR, KPIs comerciales, nuevos vs retenidos |
| **Tendencia** | Línea MRR (12 meses) + conjuntos activos (eje dual) |

Gráficas: **Chart.js 4** (CDN). Mapa: **Google Maps JavaScript API** con toggle **Empresa / Clientes**.

### KPIs principales

| Métrica | Fuente |
|---------|--------|
| Empresas activas | Empresas no archivadas |
| Conjuntos operativos | `clients` con `lifecycle = active` |
| MRR estimado | Suma mensualizada de paquetes (`package_price_monthly` o anual ÷ 12) |
| Tasa retención | % empresas en bucket «Al día» |

### Estado de cartera

Donut / leyenda con estados alineados al ciclo de acceso:

| Segmento | Criterio |
|----------|----------|
| Al día | Bucket `current` |
| Por vencer | Bucket `due_soon` |
| Vencidos | Gracia / vencido **con acceso** (`grace` / overdue, no suspended) |
| Suspendidas | Acceso bloqueado (`subscription_status = suspended`) |
| Archivadas | `archived_at` not null |
| Eliminadas | Soft-delete (`onlyTrashed`) |

Motor de buckets: `CompanySubscriptionState` · datos: `PlatformDashboardAnalytics::portfolioStatus`.

### Mapa geográfico

| Capa | Marcadores |
|------|------------|
| **Empresa** | `security_companies` (coordenadas propias o promedio de conjuntos) |
| **Clientes** | `clients` con `latitude`/`longitude` |

Configuración: `config/google-maps.php`

```env
GOOGLE_MAPS_API_KEY=
GOOGLE_MAPS_SERVER_API_KEY=
GOOGLE_MAPS_DEFAULT_LAT=4.5709
GOOGLE_MAPS_DEFAULT_LNG=-74.2973
GOOGLE_MAPS_DEFAULT_ZOOM=6
```

Sin API key se muestra aviso en el contenedor del mapa. Historial de Supervisión (Snap to Roads) usa `GOOGLE_MAPS_SERVER_API_KEY` (clave servidor, no sitios web); detalle en el README.

#### Alta de clave en Google Cloud

1. Proyecto en [Google Cloud Console](https://console.cloud.google.com/).
2. **APIs y servicios → Biblioteca** → habilitar **Maps JavaScript API** (dashboard) y **Roads API** (historial de Supervisión).
3. **APIs y servicios → Credenciales → Crear credenciales → Clave de API**.
4. Editar la clave del **navegador**:
   - **Restricciones de aplicación:** Sitios web → agregar `http://zentra.test/*` y `http://localhost/*` (ajustar dominio en producción).
   - **Restricciones de API:** Maps JavaScript API (y Places/Geocoding si usas el picker de dirección).
5. Copiar esa clave a `.env` → `GOOGLE_MAPS_API_KEY=...`. Para Roads, otra clave (IP o sin sitios web) en `GOOGLE_MAPS_SERVER_API_KEY`.
6. En el proyecto: `php artisan config:clear` y recargar `/admin`.

**Facturación:** Google exige cuenta de facturación activa en el proyecto (incluye crédito gratuito mensual).

| Error en consola del navegador | Causa habitual |
|--------------------------------|----------------|
| `RefererNotAllowedMapError` | Falta el referente HTTP del dominio local |
| `ApiNotActivatedMapError` | Maps JavaScript API no habilitada |
| `BillingNotEnabledMapError` | Facturación no vinculada al proyecto |

**Datos demo:** `TenantSeeder` asigna coordenadas en Cali a SJ Seguridad y sus conjuntos piloto.

### TOP empresas por facturación

Tabla con altura fija (alineada a la columna derecha del grid). Scroll interno cuando hay más filas. Cabecera sticky.

### Acciones de cartera (desde otras rutas)

Las acciones de archivo y retiro de conjunto siguen disponibles en el detalle de empresa:

| Acción | Ruta | Efecto |
|--------|------|--------|
| Archivar empresa (baja) | `POST /admin/companies/{company}/archive` | Suspende servicio, cascada `lifecycle = archived_company` en clientes |
| Retirar conjunto | `POST /admin/companies/{company}/clients/{client}/release` | `lifecycle = released`, libera cupo, datos en retención |
| Gestionar paquete | `GET /admin/companies/{company}` | Asignación de SKU y ciclo |

---

## Ciclo comercial de licencia

Los **paquetes** (cupo × modalidad × ciclo mensual/anual) se mantienen. El ciclo de **acceso/cobranza** es independiente:

```
Paquete activo → vencimiento → gracia (5 días) → suspensión (bloqueo)
→ (configurable, default 90 días) → archivo por falta de pago
→ retención legal → purga / eliminación
```

**No existe** el concepto «cartera por recuperar». Reactivar = asignar/pagar de nuevo el paquete (`AssignCompanyPackageService`).

### Estados (`SubscriptionStatus`)

`active` · `grace` · `expired` (legacy) · `suspended`

### Campos empresa (`security_companies`)

| Campo | Uso |
|-------|-----|
| `billing_cycle` | Mensual / anual del **paquete** |
| `billing_day` | Día de corte (1–28); se setea al asignar paquete |
| `package_ends_at` | Fin de vigencia contratada |
| `grace_ends_at` | Fin de gracia post-corte (`config/subscription.php` → `grace_days`) |
| `suspended_at` | Inicio de bloqueo por falta de pago |
| `archived_at` | Archivo comercial |
| `archive_reason` | `cancelled` (baja voluntaria) \| `non_payment` (falta de pago) |

Config:

```env
SUBSCRIPTION_GRACE_DAYS=5
SUBSCRIPTION_REMINDER_DAYS=5
SUBSCRIPTION_ARCHIVE_AFTER_SUSPENDED_DAYS=90
```

Pendiente de producto: facturas/prorrateo de 1ª cuota, recordatorios por email/WhatsApp, pasarela de pago.

### Vista Empresas (`GET /admin/companies`)

Cabecera con **3 KPIs** (sin bloque de título textual):

| KPI | Contenido |
|-----|-----------|
| **Riesgo comercial** | Suspendidas + Archivadas + Eliminadas (soft-delete) |
| **Total empresas** | Total · Activas (`is_active`) · Archivadas |
| **Total conjuntos** | Total · Operativos (`lifecycle=active`) · Archivados |

Datos: `SecurityCompanyRepository::companiesIndexKpis()` (cartera completa, no solo la página).

### Campos conjunto (`clients`)

| Campo | Uso |
|-------|-----|
| `lifecycle` | `active` · `released` · `archived_company` |
| `released_at` | Retiro de conjunto (empresa activa) |
| `archived_at` | Archivo en cascada por empresa |
| `tenant_data_purged_at` | Purga operativa completada |

### Cupo operativo

Solo cuentan clientes con `lifecycle = active`:

```php
SecurityCompany::operationalClientsCount()
```

`released` y `archived_company` **no consumen cupo**.

### Job automático

```bash
php artisan subscriptions:process-lifecycle
```

Programado: **diario a las 02:00** (`routes/console.php`).

Servicio: `App\Services\Platform\ProcessSubscriptionLifecycleService`

---

## Retención y purga legal de datos

> Orientación de diseño alineada a Ley 1581 de 2012 (Colombia). No constituye asesoría legal.

### Política implementada

| Tipo de dato | Retención | Acción |
|--------------|-----------|--------|
| **Operativo** (censo, visitantes, logs, estructuras) | 365 días tras retiro/archivo | Purga tablas tenant + anonimización del registro `clients` |
| **Comercial** (metadatos empresa archivada) | 5 años tras `archived_at` | Anonimización de PII en `security_companies` |

Configuración: `config/retention.php`

```env
RETENTION_CENSUS_DAYS=365
RETENTION_COMMERCIAL_YEARS=5
```

### Flujo de datos del conjunto

```
operativo (active)
  → retirado/archivado (released | archived_company)
  → retención read-only (sin portería)
  → purga (tenant_data_purged_at)
```

Tras la purga se conserva el registro `clients` con nombre anonimizado (`Conjunto purgado #ID`) para trazabilidad comercial; se eliminan filas en tablas con `client_id`.

### Tablas purgadas

Definidas en `config/retention.php` → `purge_tables`: supervisor_field_logs, supervisor_recommendations, supervisor_shift_reviews, supervisor_posts, access_logs, correspondence, pre_authorizations, visitor_pre_authorizations, guard_logs, blocklist, structure_*, visitors, residents, housing_units, buildings, locations, installations, client_user_assignments.

### Job automático

```bash
php artisan data:purge-retention
```

Programado: **día 1 de cada mes a las 03:00**.

Servicios:
- `ProcessDataRetentionPurgeService` — orquesta elegibles
- `PurgeClientTenantDataService` — borra datos tenant y anonimiza conjunto

---

## Rutas completas

| Método | Ruta | Función |
|--------|------|---------|
| GET | `/admin/dashboard` | Dashboard analítico (mapa, KPIs, cartera, facturación) |
| GET | `/admin/descargas` | App de Supervisión (APK + web de respaldo) |
| POST | `/admin/companies/{company}/archive` | Archivar empresa |
| POST | `/admin/companies/{company}/clients/{client}/release` | Retirar conjunto |
| GET | `/admin/pricing` | Tabla de precios |
| PUT | `/admin/pricing` | Guardar unitarios |
| GET | `/admin/companies` | Listado empresas |
| GET/POST | `/admin/companies/create` | Alta empresa (paquete + geo) |
| GET | `/admin/companies/{company}` | Detalle y cambio de paquete |
| PUT | `/admin/companies/{company}/package` | Asignar SKU y ciclo |
| GET | `/admin/documents` | Hub documental (KPIs) |
| GET | `/admin/documents/normativa` | Normoteca (globales incl. menores + contratos por SKU) |
| GET/PUT | `/admin/documents/normativa/{corpus}` | Editar / publicar nueva versión |
| GET | `/admin/documents/trd` | Tabla de retención documental |
| POST | `/admin/documents/expedientes/{company}/acceptance` | Aceptación clickwrap |
| POST | `/admin/documents/expedientes/{company}/payments/manual` | Pago manual + factura demo |
| GET | `/admin/documents/expedientes` | Listado expedientes |
| GET | `/admin/documents/expedientes/{company}` | Detalle expediente (corpus congelado) |
| GET | `/admin/settings/document-types` | Ajustes: tipos de documento de identidad |

Permisos: `platform.documents.view`, `platform.documents.manage`, `platform.settings.manage` (solo `super-admin` en v1).

### Ajustes — tipos de documento

Sidebar **Ajustes** de plataforma: tipos de documento de identidad (`/admin/settings/document-types`). CC, CE, NIT, PA… Usado en alta de cliente y aceptación legal.

Los **tipos de estructura** son catálogo **por empresa** (`/company/structure-types`). Ver [`CLIENTES-Y-ESTRUCTURA.md`](CLIENTES-Y-ESTRUCTURA.md).

**Tipo fijo del cliente:** al crear/editar cliente en `/company/clients` se elige `structure_type_id` de esa empresa. Los nodos nuevos en la ficha `/client/installations/{id}` **heredan** ese tipo (ya no se elige por nodo). El `code` del nodo lo genera el backend (slug; no se pide ni se muestra en el alta).

Dominio completo: [`CLIENTES-Y-ESTRUCTURA.md`](CLIENTES-Y-ESTRUCTURA.md).

**Separación de conceptos:**

| Concepto | Tabla | Quién define |
|----------|-------|--------------|
| Tipo del sitio / cliente | `structure_types` → `clients.structure_type_id` | Empresa (Ajustes → Estructuras) + alta cliente |
| Nodos del censo | `structures` (`parent_id`) | Ficha de instalación `/client/installations/{id}` |
| Instalación | `installations` | Pestaña Accesos y/o Supervisión de la ficha empresa |
| Puntos de acceso / puertas | `locations` (`access_point`) bajo instalación | Pestaña **Accesos** |
| Puesto de Supervisión | `supervisor_posts` | Pestaña **Supervisión** |

Detalle: [`CLIENTES-Y-ESTRUCTURA.md`](CLIENTES-Y-ESTRUCTURA.md).

Archivo: `routes/modules/admin.php`

---

## Servicios y enums

```
app/Services/Platform/
├── PlatformDashboardService.php      # Orquesta datos del dashboard
├── PlatformDashboardAnalytics.php    # KPIs, gráficas, marcadores mapa, TOP facturación
├── ManageIdentityDocumentTypeService.php # CRUD tipos de documento identidad
├── ArchiveCompanyService.php         # Archivo en cascada
├── ReleaseClientService.php          # Retiro de conjunto
├── ProcessSubscriptionLifecycleService.php
├── SuspendCompanyService.php
├── ProcessDataRetentionPurgeService.php
├── PurgeClientTenantDataService.php
├── PlatformDocumentsHubService.php
├── BuildLegalCorpusSnapshotService.php
├── PublishLegalCorpusVersionService.php
├── RecordSubscriptionAcceptanceService.php
├── RegisterCommercialPaymentService.php
├── IssueDemoInvoiceService.php
├── CreateCompanyService.php
└── RecordLifecycleEvidenceService.php

config/billing.php                    # BILLING_MODE, prefijo demo
config/subscription.php               # gracia, recordatorio, archivo post-suspensión
config/google-maps.php                # API key y centro por defecto (Colombia)

app/Enums/
├── ArchiveReason.php                 # cancelled, non_payment
├── ClientLifecycle.php               # active, released, archived_company
├── CompanyAlertBucket.php            # current, due_soon, overdue, archived
└── SubscriptionStatus.php            # incluye Suspended

app/Support/Tenancy/CompanySubscriptionState.php
```

---

## Migraciones (baseline unificado — ago 2026)

Historial squasheado: creates con schema final (sin cascada de `add_*`). En local tras pull:

```bash
php artisan migrate:fresh --seed
```

> Solo en desarrollo. Requiere autorización explícita si hay datos reales.

Tablas clave incluyen de origen: geo (`city`/`department`), `service_started_at`, `supervisor_code` / `job_title` / `avatar_path`, paquete/ciclo en empresas, normoteca con `package_sku`, pagos con gateway fields.
---

## Tests

```bash
php artisan test --filter=EnterCompanyAsSupportTest
php artisan test --filter=PlatformCompaniesIndexTest
php artisan test --filter=CreateCompanyTest
php artisan test --filter=SubscriptionLifecycleTest
php artisan test --filter=PlatformDocumentsTest
php artisan test --filter=DataRetentionPurgeTest
```

- `tests/Feature/Platform/EnterCompanyAsSupportTest.php`
- `tests/Feature/Platform/PlatformDashboardTest.php`
- `tests/Feature/Platform/PlatformCompaniesIndexTest.php`
- `tests/Feature/Platform/PlatformDocumentsTest.php`
- `tests/Unit/Platform/SubscriptionLifecycleTest.php`
- `tests/Unit/Platform/DataRetentionPurgeTest.php`

---

## Pendiente producto / legal

- Política de Tratamiento de Datos (PTD) publicada
- Contrato de encargo de tratamiento con empresas
- Exportación de datos al responsable antes de purga (opcional)
- Log de auditoría de acciones admin (archivar/retirar)
