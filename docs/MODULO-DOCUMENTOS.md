# Módulo Documentos — Panel Plataforma

Documentación de diseño del módulo `/admin/documents`: gobierno documental, expediente probatorio, aceptación contractual y facturación (fase demo → go-live con proveedor tecnológico).

**Última actualización:** agosto 2026  
**Estado:** implementado **v1.1** (hub, normoteca globales + contrato por SKU, versionado admin, TRD, expedientes con corpus congelado, clickwrap, pago manual, factura demo)  
**Mockups:** canvas en el IDE — `canvases/modulo-documentos-pantallas.canvas.tsx` (wireframes interactivos).

> Orientación jurídica-técnica para ingeniería de producto. No sustituye asesoría legal ni contable.

---

## 1. Propósito

**Documentos** no es un wiki estático. Es el sistema de:

- **Normoteca** — T&C, políticas, **protección de datos de menores** y procedimientos **globales**; **contrato de licencia por plan (SKU)**; versionado editable en admin.
- **TRD operativa** — series documentales, plazos, disposición final, base legal.
- **Expediente por suscriptor** — aceptación con corpus congelado (contenido + hash), facturas, actas de ciclo.
- **Evidencias** — registro inmutable de quién aceptó qué, cuándo, y qué ocurrió en cada transición del ciclo comercial.

**Audiencia v1:** solo **súper admin** (`/admin`).  
**Nav:** ítem **Documentos** en sidebar admin (después de Empresas).

No confundir con el **Documentos de personal** de la empresa (`/company/documents`): carpetas HV/cursos/parafiscales, etc. Las planillas PILA **no** van aquí. Ver [`EMPLEADOS-Y-CARGOS.md`](EMPLEADOS-Y-CARGOS.md). Esta Normoteca es solo plataforma.

---

## 2. Estado legal y facturación (arranque)

| Ítem | Estado hoy | Implicación en producto |
|------|------------|-------------------------|
| Registro empresarial / RUT operativo de Zentra | **Pendiente** | No hay facturación DIAN real en producción |
| Proveedor tecnológico (PT) de factura electrónica | **Pendiente** | Sin API ni portal contratado |
| Decisión de producto | **Facturación con PT** (no software propio DIAN en arranque) | Integración PT en fase 2 post go-live |

### Desarrollo y QA

- Cartera y usuarios: **seeders** (`TenantSeeder`, `DemoUsersSeeder`, `admin@control-acceso.test`).
- Facturas en expediente: **simuladas** (`demo` / `mock`) o adjuntos de prueba — sin CUFE DIAN válido.
- Pagos: **sandbox** de pasarela + **registro manual** por súper admin en dev/staging.
- Clickwrap y evidencias: flujo y persistencia **reales en BD** (rep. legal, hash, timestamp).
- Config prevista: `billing.mode = demo | live` (sin llamar PT en `demo`).

### Go-live comercial (pendiente)

1. Constitución / RUT de Zentra al día.  
2. Habilitación como facturador electrónico ante DIAN.  
3. Contrato con **proveedor tecnológico** (portal → API).  
4. Textos legales revisados por asesoría externa.  
5. `billing.mode = live`, pasarela producción, sin datos ficticios de facturación en producción.

---

## 3. Decisiones de producto cerradas

| Tema | Decisión |
|------|----------|
| Aceptación contractual | **Clickwrap reforzado** (sin firma digital ONAC por defecto) |
| Antes del pago | Obligatorio: **contrato del SKU** + T&C + políticas (+ procedimiento de ciclo de vida en corpus) |
| Contratos | **Uno por plan (SKU)**; documentos globales (T&C, privacidad, procedimiento) iguales para todos |
| Inmutabilidad | Al aceptar/comprar se congela el corpus (contenido + hash) en expediente; editar Normoteca **no** altera aceptaciones previas |
| Quién acepta | **Representante legal declarado** (nombre, cargo, tipo y número de documento) |
| Tipo de cliente | **Persona jurídica** y **persona natural** |
| Pagos | **Pasarela online** + **registro manual** por súper admin |
| Facturación | **Proveedor tecnológico**; v1 desarrollo con facturas **demo** |
| Evidencias de ciclo | Suspensión, archivo, retiro conjunto, purga → **acta/evidencia** en expediente |
| Retención | **Dual track:** censo operativo ~365d (1581); soportes mercantiles/contrato/FE **~10 años** (CCom/Ley 962); revisar `commercial_retention_years` actual (5) en go-live |
| Export PDF/HTML | **Fuera de alcance v1** (textos en BD + lectura en UI) |
| Permisos v1 | Solo **super-admin**; opcional futuro `platform.documents.view` |

---

## 4. Marco normativo de referencia

| Capa | Norma | Uso en Zentra |
|------|--------|-----------------|
| Datos personales | Ley 1581/2012, Dec. 1377/2013 | Autorización, supresión, encargado/responsable, evidencia ARCO |
| Mensajes de datos | Ley 527/1999, Dec. 2364/2012 | Clickwrap, contratos electrónicos, integridad |
| Conservación mercantil | CCom art. 60, Ley 962 art. 28 | Contratos y FE ~10 años |
| Factura electrónica | Sistema FE DIAN (Res. 000165/2023 y compilaciones) | Emisión vía PT en go-live |
| Archivo / TRD | Ley 594/2000 (metodología TRD como buena práctica) | Series y disposición documental |
| Consumo B2C | Ley 1480/2011 | Referencia; Zentra B2B prioriza CCom + 527 |

---

## 5. Mapa de pantallas

Rutas previstas (prefijo `/admin`):

| # | Pantalla | Ruta prevista | Función |
|---|----------|---------------|---------|
| 1 | Hub Documentos | `GET /admin/documents` | Tablero cumplimiento + accesos rápidos |
| 2 | Normoteca | `GET /admin/documents/normativa` | Corpus legal versionado (globales + contrato por SKU); `…/normativa/{id}/edit` publica nueva versión |
| 3 | TRD | `GET /admin/documents/trd` | Tabla de retención documental |
| 4 | Expedientes | `GET /admin/documents/expedientes` | Listado suscriptores (PJ/PN) |
| 5 | Expediente detalle | `GET /admin/documents/expedientes/{company}` | Timeline + documentos + evidencias |
| 6 | Alta comercial | `GET /admin/companies/create` (o wizard) | Tipo PJ/PN + datos fiscales |
| 7 | Paquete y ciclo | Paso wizard / `companies/{id}` | SKU, mensual/anual |
| 8 | Aceptación legal | Paso wizard | Clickwrap + rep. legal |
| 9 | Pago | Paso wizard | Pasarela o pendiente manual |
| 10 | Factura (demo/live) | En expediente | PDF/XML/CUFE según modo |
| 11 | Registro pago manual | Modal / `POST` admin | Súper admin confirma transferencia |
| 12 | Acta ciclo | Auto en expediente | Suspensión, archivo, release, purga |

Ver wireframes interactivos en el canvas de pantallas.

---

## 6. Flujo comercial end-to-end

```
Alta suscriptor (PJ o PN)
  → Datos fiscales mínimos (nombre, documento/NIT, email facturación, dirección)
  → Selección paquete + ciclo (mensual/anual)
  → Pantalla aceptación:
       · Representante legal (nombre, cargo, documento)
       · Checkbox / aceptación explícita: Contrato · T&C · Políticas
       · Evidencia: user_id, IP, user-agent, hash versión documentos, timestamp
  → Pago:
       · Online (pasarela sandbox/live)
       · O pendiente → súper admin registra pago manual
  → Activación servicio (paquete + acceso)
  → Factura:
       · demo: registro simulado o adjunto en expediente
       · live: emisión PT → CUFE → guardar en expediente
  → Ciclo operativo:
       · gracia → suspensión → archivo → retención → purga
       · cada hito genera evidencia en expediente
```

**Regla:** sin aceptación completa **no** se habilita el pago.

---

## 6.1 Normoteca — modelo de datos y servicios (v1.1)

| Concepto | Detalle |
|----------|---------|
| Tabla | `legal_corpus_versions` |
| `package_sku` | `null` = documento global; valor SKU = contrato de ese plan |
| Unicidad | `(type, package_sku, version)` |
| Vigente | `superseded_at IS NULL` |
| Snapshot | Al aceptar: `type`, `version`, `title`, `package_sku`, **`content`**, `content_hash` |
| Hash aceptación | SHA-256 del JSON del snapshot + representante + documento |
| Inmutabilidad | Editar Normoteca crea versión nueva; el `corpus_snapshot` del expediente **no** se reescribe |

**Carga vigente para un plan:** `LegalCorpusVersion::currentForPackage($sku)` → globales (T&C, privacidad, menores, procedimiento) + contrato del SKU. El aviso operativo de menores (`MinorPersonalData::notice()`) lee la versión vigente de `minors_data_policy`.

| Servicio | Rol |
|----------|-----|
| `BuildLegalCorpusSnapshotService` | Arma snapshot con contenido completo + hash |
| `PublishLegalCorpusVersionService` | Archiva vigente y publica `n.m+1` |
| `RecordSubscriptionAcceptanceService` | Clickwrap admin → snapshot + `platform_documents` (metadata `immutable`) |
| Signup `storeLegal` / `CompletePublicSignupService` | Mismo snapshot en intent → aceptación al aprobar pago |

**UI admin:** `GET /admin/documents/normativa` · `GET/PUT …/normativa/{corpus}` (editar / publicar).  
**Seed:** `PlatformDocumentsSeeder` + `database/seeders/Support/LegalCorpusDraftContent.php` (borrador; revisión legal externa pendiente).  
**Fuera de alcance v1.1:** export PDF / impresión / HTML descargable.

---

## 7. Expediente — tipos documentales

| Tipo | Origen | Retención orientativa |
|------|--------|------------------------|
| Contrato de licencia (SKU) | Clickwrap + **texto congelado** en snapshot / metadata | 10 años mercantil |
| T&C + políticas aceptadas | Versión normoteca + contenido + hash en snapshot | 10 años |
| Evidencia de aceptación | BD append-only | Vinculada al contrato |
| Factura electrónica | PT (live) o demo | 10 años + CUFE/XML |
| Acta suspensión | Job / acción admin | TRD ciclo comercial |
| Acta archivo | `ArchiveCompanyService` | TRD |
| Acta retiro conjunto | `ReleaseClientService` | TRD |
| Acta purga | `ProcessDataRetentionPurgeService` | TRD + inventario tablas |

---

## 8. TRD inicial (propuesta)

| Serie | Subserie | Retención | Disposición |
|-------|----------|-----------|-------------|
| Comercial | Contrato + aceptación | 10 años | Conservación |
| Comercial | Factura FE | 10 años | Conservación |
| Comercial | Actas ciclo | 10 años | Conservación |
| Operativo tenant | Censo / logs / visitantes | 365 días post baja | Purga certificada |
| Normativa interna | Políticas publicadas | Vigencia + histórico | Conservación histórica |

---

## 9. Fases de implementación

| Fase | Entrega | Estado |
|------|---------|--------|
| **0** | Doc + canvas + `config/billing.php` demo | ✅ |
| **1** | Nav + hub + normoteca + TRD + expedientes (seeds) | ✅ |
| **1.1** | Contrato por SKU + versionado admin + snapshot con contenido inmutable | ✅ |
| **2** | Clickwrap + rep. legal + evidencia | ✅ |
| **3** | Pago manual admin + factura demo | ✅ |
| **4** | Pasarela sandbox real | 🔲 Fase futura — ver §12 |
| **5** | Wizard alta PJ/PN en creación empresas | 🔲 Fase futura — ver §12 |
| **6** | Pantalla disposición final documental | 🔲 Fase futura — ver §12 |
| **7** | Go-live legal + PT portal (FE manual/adjunto) | 🔲 Pendiente legal |
| **8** | API PT DIAN desde Zentra (FE automática) | 🔲 Fase futura — ver §12 |

---

## 10. Relación con código existente

| Componente actual | Relación con Documentos |
|-------------------|-------------------------|
| `LegalCorpusVersion` / `package_sku` | Contrato por plan + globales |
| `BuildLegalCorpusSnapshotService` | Snapshot con contenido al aceptar |
| `PublishLegalCorpusVersionService` | Nueva versión Normoteca |
| `ProcessSubscriptionLifecycleService` | Fuente de actas suspensión/archivo |
| `ReleaseClientService` | Acta retiro conjunto |
| `ProcessDataRetentionPurgeService` | Acta purga |
| `config/subscription.php` | Procedimiento documentado en normoteca |
| `config/retention.php` | Alinear con TRD (5 vs 10 años comercial) |
| `TenantSeeder` | Datos demo para expedientes en dev |
| `layouts/admin.blade.php` | Shell violet, sidebar fijo viewport |

---

## 11. Checklist go-live (legal y operativo)

- [ ] Revisión legal externa de textos (contrato, T&C, políticas, DPA).
- [ ] Elección de PT (cotizar 2 opciones).
- [ ] Pasarela (Wompi / PayU / etc.) y modo sandbox.
- [ ] Constitución / RUT Zentra y habilitación FE DIAN.
- [ ] `billing.mode = live` en producción.

---

## 12. Fases futuras (pendientes de implementación)

Detalle técnico de lo que **no** está en v1 y cómo integrarlo sin romper el expediente actual.

### 12.1 Pasarela sandbox real

**Objetivo:** cobro online en flujo comercial (wizard o expediente) con dinero ficticio en sandbox, dejando el registro manual como respaldo operativo.

| Aspecto | Diseño propuesto |
|---------|------------------|
| Proveedores candidatos | Wompi, PayU, ePayco (evaluar fees, sandbox Colombia, webhook HTTPS) |
| Config | `config/billing.php`: `gateway.driver`, `gateway.public_key`, `gateway.private_key`, `gateway.sandbox` |
| Modelo | Extender `commercial_payments`: `gateway_transaction_id`, `gateway_status`, `gateway_payload` (JSON) |
| Enum | `PaymentMethod::Gateway` ya existe; `PaymentStatus` → `pending`, `completed`, `failed`, `refunded` |
| Flujo | 1) Aceptación completa → 2) Crear intento `pending` → 3) Redirect/checkout JS → 4) Webhook confirma → 5) `RegisterCommercialPaymentService::executeGateway()` → 6) `IssueDemoInvoiceService` o `IssueLiveInvoiceService` según `billing.mode` |
| Rutas nuevas | `POST /admin/documents/expedientes/{company}/payments/gateway` (iniciar), `POST /webhooks/billing/{driver}` (sin auth; firma HMAC) |
| Seguridad | Validar firma webhook; idempotencia por `gateway_transaction_id`; no activar paquete sin pago `completed` |
| UI | Botón «Pagar con pasarela» en expediente; en wizard paso Pago con iframe/redirect |
| Tests | Feature con mock HTTP client; nunca llamar API real en CI |

**Criterio de aceptación:** pago sandbox completado genera `commercial_payment`, evidencia `payment_recorded`, factura en expediente y mantiene bloqueo si aceptación incompleta.

---

### 12.2 API proveedor tecnológico DIAN (factura electrónica live)

**Objetivo:** emisión automática post-pago cuando `BILLING_MODE=live`, con CUFE, XML/PDF en expediente y retención 10 años.

| Aspecto | Diseño propuesto |
|---------|------------------|
| Prerrequisitos legales | RUT Zentra, resolución FE DIAN, contrato PT, textos legales firmados |
| Abstracción | `App\Contracts\Billing\ElectronicInvoiceIssuer` → `issue(CommercialPayment, SecurityCompany): IssuedInvoice` |
| Implementación | `App\Services\Platform\Billing\PtElectronicInvoiceIssuer` (adaptador por PT elegido) |
| Config | `billing.pt.driver`, `billing.pt.api_url`, `billing.pt.api_key`, `billing.pt.test_mode` |
| Modelo | `platform_documents`: campos `cufe`, `xml_path`, `pdf_path` (ya previstos en migración) |
| Servicio | Reemplazar/extender `IssueDemoInvoiceService` → `IssueInvoiceService` que elige demo vs live |
| Flujo live | Pago confirmado → llamada PT → guardar CUFE + adjuntos en storage (`storage/app/expedientes/{company_id}/`) → documento tipo `invoice` → evidencia `invoice_issued` |
| Errores | Cola `RetryFailedInvoiceJob`; estado pago `completed` pero factura `pending_emission` si PT falla; alerta súper admin |
| Demo vs live | `config('billing.mode') === 'demo'` → sin llamada PT; prefijo `BILLING_DEMO_PREFIX` |
| Tests | Contract tests con fixture XML/CUFE fake; stub del issuer en tests |

**Criterio de aceptación:** en `live`, cada pago completado produce factura con CUFE almacenado, visible en expediente, retenida según TRD serie Comercial / Factura FE.

---

### 12.3 Wizard alta PJ/PN en creación de empresas

**Objetivo:** unificar alta comercial en `/admin/companies/create` con tipo de parte, datos fiscales y encadenamiento a aceptación + pago (no solo expediente posterior).

| Aspecto | Diseño propuesto |
|---------|------------------|
| Campo existente | `security_companies.party_type` (`legal_entity` \| `natural_person`) — ya en BD |
| Wizard (pasos) | 1) Tipo PJ/PN → 2) Datos fiscales → 3) Paquete + ciclo → 4) Aceptación clickwrap → 5) Pago (manual/gateway) → 6) Confirmación |
| PJ (jurídica) | `legal_name`, `trade_name`, NIT, rep. legal (nombre, cargo, CC/NIT), email facturación, dirección |
| PN (natural) | Nombre completo, CC, email, dirección; `legal_name` = nombre; `trade_name` opcional |
| Validación | `StoreCompanyWizardRequest` por paso; reglas distintas según `party_type` |
| Controller | `CompanyWizardController` o extender `CompanyController` con sesión/wizard state |
| Integración Documentos | Paso 4 → `RecordSubscriptionAcceptanceService`; paso 5 → pago; redirect a expediente |
| UI | Reutilizar estilos `companies/create` + canvas pantallas 6–9 |
| Rutas | `GET/POST admin/companies/create/wizard/{step}` |

**Criterio de aceptación:** nueva empresa creada desde wizard con expediente completo (aceptación + pago opcional) sin visitar expediente manualmente.

---

### 12.4 Pantalla disposición final documental

**Objetivo:** operar la TRD: ver series próximas a vencer, ejecutar o simular disposición (conservación / purga certificada) con acta y evidencia.

| Aspecto | Diseño propuesto |
|---------|------------------|
| Ruta | `GET /admin/documents/disposicion` |
| Permiso | `platform.documents.manage` (solo súper admin) |
| Datos | Agregar por serie TRD: documentos con `retention_until` ≤ hoy + N días (alerta) |
| Vista | Tabla: serie, subserie, documento, suscriptor, `retention_until`, disposición TRD, acción |
| Acciones | **Conservar** (marcar archivado, sin borrar), **Purga certificada** (solo operativo tenant; enlace a `ProcessDataRetentionPurgeService`) |
| Acta | `PlatformDocument` tipo `act` + `LifecycleEvidenceEvent` con inventario tablas / hashes |
| Job | `ScanDocumentDispositionAlertsJob` — notificación súper admin (email/in-app futuro) |
| Relación TRD | `document_retention_series` ya seedada; vincular `platform_documents.retention_until` al cálculo |
| No incluye v1 | Purga automática de facturas/contratos (solo conservación); purga operativa censo según Ley 1581 |

**Criterio de aceptación:** súper admin ve alertas de retención, ejecuta disposición con acta en expediente y evidencia append-only.

---

### 12.5 Orden recomendado de desarrollo

```
1. Wizard PJ/PN (desbloquea flujo comercial completo en alta)
2. Pasarela sandbox (pagos online en wizard y expediente)
3. Pantalla disposición final (operación TRD sin dependencia externa)
4. Go-live legal + PT portal manual (adjunto CUFE en expediente)
5. API PT automática (emisión post-pago en live)
```

### 12.6 Archivos a crear/modificar (referencia)

| Fase | Archivos principales |
|------|----------------------|
| Pasarela | `config/billing.php`, `RegisterCommercialPaymentService`, `WebhookBillingController`, migración payments gateway columns |
| API PT | `ElectronicInvoiceIssuer` contract, `PtElectronicInvoiceIssuer`, `IssueInvoiceService`, jobs retry |
| Wizard | `CompanyWizardController`, requests por paso, vistas `companies/wizard/*`, rutas `admin.php` |
| Disposición | `DocumentDispositionController`, `ScanDocumentDispositionAlertsJob`, vista `documents/disposicion.blade.php` |

---

*Documento de definición e implementación v1. Fases §12 pendientes de autorización de desarrollo.*
