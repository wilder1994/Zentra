# Modelo comercial — Planes empresa (Zentra)

Pricing B2B para empresas de seguridad. Vigente desde el 12 oct 2026.

---

## Resumen

| Quién compra | Qué compra | Qué limita |
|--------------|------------|------------|
| Empresa de seguridad | Accesos y/o Supervisión (independientes), Indexación, Observatorio | Instalaciones activas (`max_clients`), clientes supervisados (`max_supervision_clients`) y empleados (3k–12k) |

Unidad comercial = **instalación activa** (Accesos) o **cliente supervisado** (Supervisión). Se compra cualquier cantidad (1 … 10.000); no hay packs fijos.

---

## Planes y fórmula

| Plan | Cantidad | Empleados | Dto. por defecto |
|------|----------|-----------|------------------|
| Bronce | 1–5 | 3.000 | 5% |
| Plata | 6–15 | 6.000 | 8% |
| Oro | 16–40 | 9.000 | 10% |
| Platino | 41–100 (más de 100 a precio Platino) | 12.000 | 12% |

```
plan             = rango que contiene la cantidad total
precio_unidad    = unidad × (1 − descuento del plan)
mensual          = cantidad × precio_unidad
anual            = mensual × 12 × (1 − tenancy.pricing.annual_discount)
```

- Al cruzar un rango **todas** las instalaciones toman el nuevo precio (no solo las nuevas).
- Ejemplo (unidad 80.000): 5 → Bronce 76.000 c/u = 380.000; 8 → Plata 73.600 c/u = 588.800.
- Hardware: unidad propia (`units.access_hardware`, 150.000 por defecto) con el mismo descuento del plan de Accesos.
- Supervisión e Indexación usan la misma lógica con su unidad. Observatorio: un precio según el plan de Accesos.
- Accesos y Supervisión son **independientes**: se puede contratar solo Supervisión.

Motor: `App\Support\Catalog\CatalogPricer` (matrices) y `App\Services\Pricing\PriceCalculator` (cotizaciones). Selector en vistas: `resources/js/plan-picker.js` + `<x-plans.seat-fields>` (misma fórmula en JS).

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
| Observatorio | Observatorio (solo si hay Accesos) |

## Dónde se ve

- `/admin/pricing` — menú **Planes**: tablas por producto, chips, ayuda «?», Editar planes, Incremento anual.
- Ficha empresa — plan, valor por instalación, precio pactado, historial, cambio con selector por cantidad.
- Alta de empresa, cambio programado y reactivación — selector por cantidad con plan y precio en vivo.
- Dashboard — «Empresas por plan» (Accesos y Supervisión).
- Empresa `/company/billing` — plan, instalaciones, valor por instalación, total, próxima facturación, nuevo valor programado; «Cambiar plan» aplica al corte.
- Público `/` y `/planes` — precio por instalación, calculadora y enlace al registro (`/contratar?access=&hardware=&supervision=&cycle=`).

Contratos: uno por plan y modalidad (`legal_corpus_versions.package_sku` = `{metal}_{modalidad}`).

## Rutas

| Método | Ruta | Permiso |
|--------|------|---------|
| GET/PUT | `/admin/pricing` | `platform.companies.view` / `manage` |
| POST | `/admin/pricing/increase/preview` | `manage` |
| POST | `/admin/pricing/increase` | `manage` |
| PUT | `/admin/companies/{company}/price-lock` | `manage` |
| GET | `/planes` | público |

## Tests (BD `zentra_test`)

```bash
php artisan test --filter=CatalogPricerTest
php artisan test --filter=PriceCalculatorTest
php artisan test --filter=PlansPagesTest
```

## Deploy

```bash
php artisan migrate --force
npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Sin `migrate:fresh`. La migración `2026_10_12_120000_align_commercial_plans` agrega columnas e historial, convierte Supervisión ilimitada y re-indexa contratos; **no** cambia precios de empresas actuales. Ver [`HOSTING-VPS.md`](HOSTING-VPS.md).

## Changelog

| Fecha | Cambio |
|-------|--------|
| 2026-10-12 | Planes por cantidad libre, Accesos/Supervisión independientes, disponibilidad, precio pactado, incremento anual, historial |
| 2026-09-29 | Metales, 4 productos, módulos por plan, indexación add-on, observatorio por metal de Accesos |
| 2026-07-20 | Modelo inicial cupo + unitarios |
