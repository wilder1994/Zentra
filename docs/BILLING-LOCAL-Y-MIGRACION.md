# Billing local — simulador y migración a proveedores

Pagos y facturación en **desarrollo local** sin Wompi, PayU ni proveedor tecnológico DIAN.

**Última actualización:** agosto 2026

---

## Objetivo

Probar el flujo comercial completo en Laragon:

1. Aceptación clickwrap (súper admin en expediente).
2. Pago **manual** (súper admin) o **online simulado** (admin empresa o súper admin).
3. Factura demo en expediente + evidencias en timeline.

Sin credenciales externas ni webhooks públicos.

---

## Configuración `.env`

```env
BILLING_MODE=demo
BILLING_DEMO_PREFIX=DEMO
BILLING_GATEWAY_DRIVER=local
```

| Variable | Valor local | Notas |
|----------|-------------|--------|
| `BILLING_MODE` | `demo` | Sin API PT / CUFE real |
| `BILLING_GATEWAY_DRIVER` | `local` | Checkout interno Zentra |

---

## Rutas

| Actor | Ruta | Función |
|-------|------|---------|
| Admin empresa | `GET /company/billing` | Estado licencia y botón pagar |
| Admin empresa | `POST /company/billing/checkout` | Crea pago `pending` y redirige |
| Cualquier autorizado | `GET /billing/checkout/{payment}` | Pantalla Aprobar / Rechazar |
| Cualquier autorizado | `POST /billing/checkout/{payment}/approve` | Completa pago + factura demo |
| Cualquier autorizado | `POST /billing/checkout/{payment}/reject` | Marca pago fallido |
| Súper admin | `POST /admin/documents/expedientes/{company}/payments/manual` | Pago manual (existente) |
| Súper admin | `POST /admin/documents/expedientes/{company}/payments/local-checkout` | Mismo checkout simulado |

---

## Flujo de prueba manual

### Admin empresa (`empresa@sj-seguridad.test`)

1. Súper admin registra aceptación en expediente SJ Seguridad.
2. Login empresa → **Facturación** → **Iniciar pago online simulado**.
3. Checkout → **Aprobar pago**.
4. Ver mensaje de éxito en `/company/billing`.
5. Súper admin → expediente: timeline + factura demo.

### Rechazo

Checkout → **Rechazar** → pago `failed`, sin factura nueva.

### Sin aceptación

Botón pagar redirige con aviso; no se crea checkout.

---

## Tests

```bash
php artisan test --filter=LocalPaymentCheckoutTest
php artisan test --filter=PlatformDocumentsTest
```

---

## Componentes **permanentes** (conservar en go-live)

| Componente | Rol |
|------------|-----|
| `commercial_payments` | Registro de cobros |
| `RegisterCommercialPaymentService::executeManual()` | Cobros operativos |
| `finalizeCompletedPayment()` | Evidencia + factura |
| `platform_documents` | Expediente |
| Rutas expediente admin | Auditoría |

---

## Componentes **locales** (reemplazar al integrar proveedor)

| Hoy (local) | Reemplazo futuro |
|-------------|------------------|
| `BILLING_GATEWAY_DRIVER=local` | `wompi` / `payu` + keys |
| `initiateLocalCheckout()` | `initiateGatewayCheckout()` vía driver |
| `completeLocalCheckout()` / `failLocalCheckout()` | Webhook pasarela |
| Vista `modules/billing/checkout.blade.php` | Checkout widget / redirect proveedor |
| Rutas `billing/checkout/{payment}/approve\|reject` | `POST /webhooks/billing/{driver}` |
| Referencias `LOCAL-*` | ID transacción proveedor |
| `IssueDemoInvoiceService` | `IssueInvoiceService` + PT cuando `BILLING_MODE=live` |

**No eliminar** el pago manual ni la tabla `commercial_payments`.

---

## Checklist go-live

- [ ] Contrato pasarela + keys producción
- [ ] `BILLING_GATEWAY_DRIVER` ≠ `local`
- [ ] Implementar driver real (`WompiGateway`, etc.)
- [ ] Desactivar rutas approve/reject en producción (o solo en `local`)
- [ ] PT DIAN contratado → `BILLING_MODE=live`
- [ ] Documentar keys en `.env` producción (no commitear)

---

Ver también: [`MODULO-DOCUMENTOS.md`](MODULO-DOCUMENTOS.md) §12.
