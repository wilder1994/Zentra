# Modelo comercial — Planes empresa (Zentra)

Pricing B2B para empresas de seguridad. Vigente desde el 12 oct 2026.

---

## Resumen

| Quién compra | Qué compra | Qué limita |
|--------------|------------|------------|
| Empresa de seguridad | Accesos, Supervisión, Indexación y Observatorio (cualquiera, solos o combinados) | Instalaciones por producto, puestos por instalación, empleados por puesto y empleados totales |

Los **4 productos se cobran por instalación**. Cada uno tiene su cantidad (`max_clients`, `max_supervision_clients`, `indexing_seats`, `observatory_seats`) y su propio plan según esa cantidad. Se compra cualquier cantidad (1 … 10.000); no hay packs fijos.

---

## Planes y límites

| Plan | Instalaciones | Puestos por instalación | Empleados por puesto | Empleados totales | Dto. por defecto |
|------|---------------|-------------------------|----------------------|-------------------|------------------|
| Bronce | 1–5 | 2 | 3 | 1.000 | 5% |
| Plata | 6–15 | 5 | 5 | 2.000 | 8% |
| Oro | 16–40 | 7 | 7 | 3.000 | 10% |
| Platino | 41+ (más de 100 a precio Platino) | 10 | 10 | 5.000 | 12% |

Los límites de la empresa salen del **plan más alto** entre sus productos (`CommercialMetal`, `QuotaOverview`). Al cruzar un rango **todas** las instalaciones de ese producto toman el nuevo precio.

## Fórmula

```
precio_producto  = unidad × instalaciones × (1 − descuento de su plan)
combinar         = los productos se ordenan de mayor a menor valor;
                   el primero va completo, cada uno de los siguientes × (1 − combo_discount)
mensual          = suma de productos tras combinar
anual            = mensual × 12 × (1 − tenancy.pricing.annual_discount)   (encadenado, 17%)
```

- Unidades por defecto (`config/catalog.php`): Accesos 80.000, Hardware 150.000, Supervisión 80.000, Indexación 40.000, Observatorio 40.000. `combo_discount` = 30%. Todo editable en `/admin/pricing`.
- Ejemplo: Accesos 10 (Plata, 736.000) + Supervisión 10 (Plata, 736.000 − 30% = 515.200).
- Hardware: unidad propia con el mismo descuento del plan de Accesos.
- Observatorio se puede contratar solo. Indexación ya no es complemento: tiene cantidad y precio propios.

Motor PHP: `App\Domain\Pricing\Data\PlanSelection` (cantidades), `App\Services\Pricing\PlanQuoter` (cotización con combinar y anual), `CompanyPlanPricer` (columnas de precio de la empresa) y `CatalogPricer` (matrices). JS: `resources/js/plan-picker.js` (`quotePlan`, misma fórmula) y `resources/js/plan-cart.js`.

## Cupos y ampliaciones

- Crear instalación, puesto o asignar empleado pide cupo libre del producto y del plan. El mensaje dice qué falta y remite a archivar o ampliar en Facturación, donde está **Solicitar ampliación**.
- **Ampliaciones del súper admin** (ficha empresa, bloque Cupos): empleados extra de la empresa (`extra_employees`), puestos por instalación y empleados por puesto (por empresa, por instalación `max_posts` o por puesto `max_employees`), con nota.
- **Solicitar ampliación** (empresa → súper admin): `POST /company/billing/quota-requests` crea un `quota_request`; el súper admin aprueba o rechaza desde la ficha.
- La migración `2026_10_13_100000` deja lo que hoy excede los nuevos límites como cupo adicional: ninguna empresa se bloquea.

## Archivo unificado

Clientes, instalaciones, puestos y empleados se archivan con el mismo flujo. Archivar un padre archiva a sus hijos en cascada (`archived_with_parent`). Restaurar pide cupo libre; los hijos que se archivaron con el padre vuelven con él.

SKU almacenado: `pack_{n}_{manual|hardware|mixed}` (`CompanyPackageSku`) y `sup_{n}` (`SupervisionPackageSku`), ambos objetos valor con tamaño libre. Supervisión ilimitada ya no existe (se migró a `sup_100`).

## Disponibilidad

Cada plan y la modalidad Hardware tienen chip **Disponible / No disponible** (`pricing_settings.catalog.availability`). Hardware arranca no disponible.

- Lo no disponible se oculta en `/`, `/planes`, registro público y facturación de empresa.
- El súper admin lo sigue viendo y puede asignarlo.
- Una empresa que ya tiene un plan no disponible lo conserva hasta que cambie.

## Precios de empresas actuales

Cada empresa guarda el precio contratado (`package_price_monthly`, `unit_price_snapshot`, equivalentes de Supervisión). Cambiar el catálogo no lo toca salvo que se ordene:

| Herramienta | Dónde | Efecto |
|-------------|-------|--------|
| Guardar planes · «Solo para nuevas» (defecto) | `/admin/pricing` | Solo nuevas empresas |
| Guardar planes · «Aplicar a las actuales» | `/admin/pricing` | `pending_reprice_at` = próxima facturación; aviso en Facturación de la empresa |
| Incremento anual (% + fecha, con vista previa) | `/admin/pricing` | Sube el catálogo y programa a las actuales desde su primera facturación tras la fecha |
| Precio pactado (fecha fin y nota opcionales) | Ficha empresa | Excluye a la empresa de cambios e incrementos |

El recálculo se aplica en `ProcessSubscriptionLifecycleService` (diario) y antes de calcular un cobro (`RegisterCommercialPaymentService`). Cada cambio queda en `company_price_changes` (historial en la ficha). Servicio: `CompanyRepricingService`.

## Productos y menús

Defaults en `config/catalog.php`. Editables en `/admin/pricing`.

| Producto | Menú por defecto |
|----------|------------------|
| Accesos | Mi empresa, Facturación, Clientes, Instalaciones, Pánicos, Empleados, Usuarios, Mis datos, Ajustes |
| Supervisión | Facturación, Clientes, Instalaciones, Supervisión, Pánicos, Descargas, Empleados, Usuarios, Mis datos, Ajustes |
| Indexación | Facturación, Empleados, Documentos, Usuarios, Mis datos, Ajustes |
| Observatorio | Mi empresa, Facturación, Clientes, Instalaciones, Observatorio, Usuarios, Mis datos, Ajustes |

## Dónde se ve

- `/admin/pricing` — menú **Planes**: tablas por producto, chips, ayuda «?», Editar planes, Incremento anual.
- Ficha empresa — plan, valor por instalación, precio pactado, historial, cambio con selector por cantidad.
- Alta de empresa, cambio programado y reactivación — selector por cantidad con plan y precio en vivo.
- Dashboard — «Empresas por plan» (Accesos y Supervisión).
- Empresa `/company/billing` — plan, instalaciones, valor por instalación, total, próxima facturación, nuevo valor programado; «Cambiar plan» aplica al corte.
- Público `/` — tarjetas por plan con límites.
- Público `/planes` — **Arma tu plan**: una tarjeta por plan con Accesos (y Hardware si está disponible), Supervisión, Indexación y Observatorio. Cada fila: casilla, precio unitario, +/− con cantidad limitada al rango del plan (Platino admite escribir más de 100), texto «Obtienes» y **Agregar al carrito**.
- Público `/planes/carrito` — una línea por producto (`localStorage` `zentra.plan-cart.v1`). Agregar el mismo producto reemplaza la línea con aviso; si la cantidad cruza rango, la línea cambia de plan. Selector Mensual/Anual; total sin descuento, descuentos por plan, descuento por combinar, anual, total y ahorro. **Contratar** abre el registro (`/contratar?access=&hardware=&supervision=&indexing=&observatory=&cycle=`).

Contratos: uno por plan y modalidad (`legal_corpus_versions.package_sku` = `{metal}_{modalidad}`).

## Rutas

| Método | Ruta | Permiso |
|--------|------|---------|
| GET/PUT | `/admin/pricing` | `platform.companies.view` / `manage` |
| POST | `/admin/pricing/increase/preview` | `manage` |
| POST | `/admin/pricing/increase` | `manage` |
| PUT | `/admin/companies/{company}/price-lock` | `manage` |
| GET | `/planes` | público |
| GET | `/planes/carrito` | público |
| POST | `/company/billing/quota-requests` | empresa |
| PUT | `/admin/companies/{company}/quotas` · `/quotas/installation` · `/quotas/post` | `manage` |
| POST | `/admin/companies/{company}/quota-requests/{quotaRequest}/approve` · `/reject` | `manage` |

## Tests (BD `zentra_test`)

```bash
php artisan test --filter=CatalogPricerTest
php artisan test --filter=PriceCalculatorTest
php artisan test --filter=PlansPagesTest
php artisan test --filter=PlanQuoterTest
php artisan test --filter=PlanQuotaArchiveTest
```

## Deploy

```bash
php artisan migrate --force
npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Sin `migrate:fresh`. La migración `2026_10_12_120000_align_commercial_plans` agrega columnas e historial, convierte Supervisión ilimitada y re-indexa contratos; **no** cambia precios de empresas actuales. Ver [`HOSTING-VPS.md`](HOSTING-VPS.md).

Migraciones del 13 oct 2026:

- `2026_10_13_100000_add_plan_quotas_and_unified_archive` — cupos, cantidades de Indexación/Observatorio (desde lo que ya tenían), archivo unificado, `quota_requests`.
- `2026_10_13_110000_add_indexing_observatory_pricing` — precios de Indexación y Observatorio. Las empresas actuales **no** pagan esos productos hasta su próximo recálculo de precio.
- `2026_10_13_120000_rename_site_permission_to_installation_permission` — renombra la columna.

## Changelog

| Fecha | Cambio |
|-------|--------|
| 2026-10-13 | 4 productos por instalación, descuento por combinar (30%), anual encadenado, tarjetas «Arma tu plan» y carrito, límites por plan, ampliaciones y archivo unificado; «sede» pasa a «instalación» |
| 2026-10-12 | Planes por cantidad libre, Accesos/Supervisión independientes, disponibilidad, precio pactado, incremento anual, historial |
| 2026-09-29 | Metales, 4 productos, módulos por plan, indexación add-on, observatorio por metal de Accesos |
| 2026-07-20 | Modelo inicial cupo + unitarios |
