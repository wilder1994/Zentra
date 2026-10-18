# Hosting VPS (Zentra)

**Última actualización:** 17 octubre 2026

Revistas por puesto (17 oct 2026): migrate `2026_10_17_200000` (`supervisor_posts.reviews_per_day`, default 1: no cambia el cumplimiento de los puestos actuales). Filtros en modal (Historial/Resumen/Fichas) y tema a la derecha. Pull + `migrate --force` + `npm ci && npm run build` + caches. Sin seeder.

Líneas de servicio temporal/archivo (16 oct 2026): migrate `2026_10_16_163000` (columnas de archivo por línea + `observatory_events.archived_at` + flags `archived_by_service_line`). Pull + `migrate --force` + `npm ci && npm run build` + caches. La purga de líneas archivadas corre con `data:purge-retention` (`service_line_retention_days`). Sin seeder.

Disco (15 oct 2026): 13 GB de 96 GB. Se borró `/root/zentra-migracion` (respaldo de `controla`, 1,8 GB). Lo grande: sistema ~4,8 GB, swap 2 GB, `storage/app` 1,8 GB (1,6 GB son recortes de planilla PILA por empleado, ver `EMPLEADOS-Y-CARGOS.md`), respaldos CloudPanel 3 × 475 MB, MySQL 1,3 GB (BD 124 MB + redo 1,1 GB fijo).

MySQL / Percona — resiliencia post-reboot (16 oct 2026): tras un reinicio del VPS, `mysql.service` quedó `inactive` con `Dependency failed` (`Requires=local-fs.target remote-fs.target` del unit Percona). El sitio respondía **500** (`SQLSTATE[HY000] [2002] Connection refused`). Arranque manual: `systemctl start mysql`. Para que no se repita, en el VPS quedó:

| Pieza | Ruta | Qué hace |
|-------|------|----------|
| Drop-in systemd | `/etc/systemd/system/mysql.service.d/zentra-resilience.conf` | Suaviza dependencias + `RestartSec=5` (el unit Percona sigue listando fs; la red de seguridad es el ensure) |
| Script ensure | `/usr/local/sbin/zentra-mysql-ensure.sh` | Si MySQL no está activo o no escucha `:3306`, hace `systemctl start mysql`; log `/var/log/zentra-mysql-ensure.log` |
| Timer systemd | `zentra-mysql-ensure.timer` (+ `.service`) | Arranca a los ~45 s del boot y cada 2 min |
| Cron | `/etc/cron.d/zentra-mysql-ensure` | Respaldo: cada 2 min + `@reboot` (sleep 45) |

Comprobar: `systemctl is-active mysql`, `systemctl list-timers zentra-mysql-ensure.timer`, `ss -lntp \| grep 3306`, `tail /var/log/zentra-mysql-ensure.log`. Tras editar drop-in/units: `systemctl daemon-reload && systemctl enable --now zentra-mysql-ensure.timer`.

Comercial Super Admin (15 oct 2026): hub `/admin/comercial` — propuesta PDF, cotización PDF (plan personalizado + total pactado) y presentación PPTX 16:9 (11 diapositivas, paleta clara, capturas panel empresa con stitch de scroll en Supervisión/Observatorio). Capturas en `resources/images/commercial/deck/`. Migrates del mismo corte: `2026_10_15_*` (blocklist ids, plantilla turno guarda), `2026_10_18_*` (`scheduled_amount*`, sync cupos Supervisión por puesto). Pull + `composer install` + `migrate --force` + `npm ci && npm run build` + caches. Sin seeder nuevo (permiso `platform.companies.view`). Comprobar enlace `public/images` → `../resources/images` si falta. Detalle: [`COMERCIAL-DOCUMENTOS.md`](COMERCIAL-DOCUMENTOS.md).

Georreferencia de instalaciones (14 oct 2026): migrate `2026_10_17_110000` (tabla `installation_geo_requests` + permiso `company.geo.approve`, lo crea la migración). El supervisor envía desde la app (turno abierto, GPS ±30 m, solo con red); la empresa aprueba en Supervisión → Georreferenciaciones. PWA `zentra-sup-v53`. APK v2.1. Pull + `migrate --force` + `npm run build` + caches. Sin seeder.

Minuta libro (14 oct 2026): migrate `2026_10_17_100000` (`guard_logs.corrects_guard_log_id`). Pull + `migrate --force` + `npm run build` + caches.

Radio de revistas (14 oct 2026): migrate `2026_10_16_100000` (`supervision_review_settings`, `supervision_review_radii`). El radio ya no es fijo: súper admin → Ajustes → «Radio de revistas» (global 80 m, tope 300 m, excepciones por empresa/cliente/instalación). Pull + `migrate --force` + `npm run build` + caches.

Reservas en portería + zonas simples (14 oct 2026): portería tiene pestaña Calendario (`/access/zones/calendar`) y solicita reservas que quedan por aprobar; Hoy muestra también las pendientes. Zonas sin campo tipo (nombre libre, único por instalación) y se pueden eliminar si no tienen reservas. Corrige `GET /api/supervision/shift-photo/start-selfie` (500 por tipo de respuesta). Pull + `npm run build` + caches. Sin migrate ni APK.

Migración a Zentra (10 oct 2026): el sitio CloudPanel `controla.wcodex.cloud` se reemplazó por `zentra.wcodex.cloud` (usuario `wcodex-zentra`, BD `zentra`). Reverb: `zentra-reverb.service` (`127.0.0.1:6001`). Solo existen los sitios `decowandy` y `zentra`. Respaldos del sitio viejo en `/root/zentra-migracion/`.

Cambio de dispositivo (11 oct 2026): migrate `2026_10_11_150000` (`users.single_session_device`, `users.mobile_device_*`, tabla `device_change_requests`) + `RoleAndPermissionSeeder` (`company.devices.approve`). Panel web: una pregunta por equipo (cookie `zentra_device`). APK/PWA: celular atado al supervisor; otro celular pide aprobación (10 min). PWA `zentra-sup-v51`. APK v1.9. Pull + `migrate --force` + seeder de roles + `npm run build` + `config:cache` + `route:cache` + `view:cache`. Artisan como `wcodex-zentra`.

GPS limpio + offline correcto (11 oct 2026): ruta nueva `POST /api/supervision/shifts/pings` (lote APK). `recorded_at` de captura en revistas, logs y pings; lo capturado sin red entra al turno cerrado por el sistema (72 h). Cola PWA: rechazados visibles (no se borran). PWA `zentra-sup-v52`. APK v2.0 (GPS primero, ≤ 50 m, cola offline). Pull + `npm run build` + `config:cache` + `route:cache` + `view:cache`. Sin migrate.

Revista 80 m + historial limpio (11 oct 2026): radio de revista `SupervisorReviewGeofence::RADIUS_METERS` = 80. Historial con turno elegido solo pinta ruta, revistas y eventos del turno. Pull + `npm run build` + `view:cache`. Sin migrate ni APK.

Paradas en historial + km GPS (11 oct 2026): `/company/supervision/turnos/{shift}/ruta` devuelve `stops`, `start`, `end`, `km`; el historial pinta paradas (≥ 2 min en 75 m). Cierre por el sistema guarda `km_traveled` por GPS si no hay km de odómetro. Turnos viejos: `php artisan supervision:backfill-gps-km`. Pull + `npm run build` + `view:cache` + `config:cache` + `route:cache` + backfill. Sin migrate ni APK.

Planes por instalación + carrito (13 oct 2026): migrate `2026_10_13_100000` (cupos, archivo unificado, `quota_requests`), `2026_10_13_110000` (precios Indexación/Observatorio) y `2026_10_13_120000` (`site_permission` → `installation_permission`). Ruta pública `/o/{slug}/sedes` pasa a `/o/{slug}/instalaciones` (sin redirección). Pull + `migrate --force` + `npm run build` + `config:cache` + `route:cache` + `view:cache`. Sin APK ni seeder. Después, `2026_10_13_130000_indexing_per_employee`: Indexación por empleado (cantidad = empleados activos; precio vigente sin cambio hasta el recálculo).

Cumplimiento por puesto (12 oct 2026): migrate `2026_10_12_010000` (`supervisor_posts.service_days` máscara Lun..Dom, `serves_holidays`). Un puesto activo espera ≥ 1 revista por día operativo (06:00–06:00) en sus días de servicio; festivos de Colombia calculados en `ColombiaHolidays`. `BuildPostComplianceService` alimenta mapa en vivo (Clientes/Instalaciones, interruptor Pendientes, pin con número, aviso de instalaciones sin ubicación), tira del día, Resumen, PPTX y panel Mi empresa. Pull + `migrate --force` + `npm run build` + `config:cache` + `route:cache` + `view:cache`. Sin APK.

Catálogo comercial (29 sep 2026): migrate `2026_09_29_010000` (`pricing_settings.catalog`, `has_indexing`, `has_observatory`). Pull + `migrate --force` + `npm run build` + `view:cache` + `route:cache`. Artisan como `wcodex-zentra`.

Sitio público: [https://zentra.wcodex.cloud](https://zentra.wcodex.cloud)

| Dato | Valor |
|------|--------|
| Proveedor | Hostinger VPS KVM 2, São Paulo, Ubuntu 24.04 + CloudPanel |
| IP | `82.25.66.93` |
| Usuario del sitio | `wcodex-zentra` |
| Código | `/home/wcodex-zentra/htdocs/zentra.wcodex.cloud` |
| Document root | `…/public` |
| PHP | 8.3 |
| Repo | `https://github.com/wilder1994/Zentra.git` rama `main` |

Flujo: push local a `origin` (`wilder1994/Zentra`, `main`). El VPS solo hace `git pull` + composer/npm/migrate. No FTP ni ZIP. No `migrate:fresh` ni `db:wipe` en producción.

Si el commit añade roles o permisos (`config/access.php`), tras migrate corre `php artisan db:seed --class=RoleAndPermissionSeeder` (sync Spatie; no vacía datos). El resto de seeders no se corre salvo petición explícita.

Este deploy **no** hace `migrate:fresh`. Las `add_*` unificadas en `create_*` solo aplican en instalación limpia. En el VPS ya corrido, `migrate` solo aplica pendientes (p. ej. `user_module_grants`). Backup selectivo de empleados/PDFs: carpeta `zentra-YYYYMMDD` en el home del sitio; borrar **después** de restaurar y verificar, no en este pull.

Observatorio: migrate `2026_09_13_120000` (tipos + módulo). Tras ese migrate: `RoleAndPermissionSeeder` (permiso `observatory.events.update`). Intake `/o/{slug}` (solo colegios). API `/api/observatory/*`. Comunas: `resources/data/cali-comunas.geojson`, el mapa las pide en `/geo/cali-comunas.geojson` (relativo). **No** cargar `libraries=visualization`: Maps JS 3.65 quitó HeatmapLayer. PPTX: `GET /company/observatory/tablero.pptx` y `/client/observatory/tablero.pptx` (mismo permiso `observatory.view`; `route:cache` + `view:cache`). Cortes UX: `npm run build` + `view:cache`. Sin migrate ni seeder.

Avisos en español (13 sep 2026): `lang/es/*`, toast centrado, PWA `zentra-sup-v41`.

Colaborador + Clientes (13 sep 2026): `ClientController` / `ClientPolicy`. Corte PHP: pull + `view:cache` + `config:cache`. Sin migrate, seeder ni `npm run build`.

Puestos + mapas (14 sep 2026): varios puestos por instalación; pines cliente/instalación en Mi empresa y Supervisión. Pull + `view:cache` + `config:cache` + `route:cache`. Sin migrate ni seeder.

App de campo (14 sep 2026): PWA en `https://zentra.wcodex.cloud/campo/` (no hay DNS `zentra_supervision.wcodex.cloud`). APK v1.1 en `public/downloads/zentra-supervision.apk` (primer ingreso: usuario + clave). En VPS: `SUPERVISION_PWA_URL=https://zentra.wcodex.cloud/campo/` + pull + `route:cache` + `view:cache` + `config:cache`. Sin migrate ni seeder.

Observatorio Compartir link (14 sep 2026): modal con scroll y buscador si hay muchos clientes. Pull + `npm run build` + `view:cache`. Sin migrate ni seeder.

Observatorio campo (14 sep 2026): mapa pin + 1–3 fotos en PWA/APK. Migrate `2026_09_14_120000` (`photo_paths`). Pull + `migrate --force` + `view:cache` + `route:cache`. Copiar PWA `public/campo` (va en el git) y APK v1.2. Sin seeder. `npm run build` por el Blade de la ficha.

Landing welcome (14 sep 2026): scroll (`min-h-screen`), H1 Accesos/supervisión/observatorio, 3 cards, marca WCodex. Pull + `npm run build` + `view:cache`. Sin migrate ni seeder.

Observatorio fotos link + tipos empresa (14 sep 2026): `/o/{slug}` y panel rector con cámara/miniaturas (hasta 3, opcional). Leyenda empresa por slug. Pull + `npm run build` + `view:cache`. Sin migrate ni seeder.

Menú móvil paneles (14 sep 2026): hamburguesa + drawer en cliente/empresa/plataforma/portería. Pull + `npm run build` + `view:cache`. Sin migrate ni seeder.

Parafiscales planilla (14 sep 2026): recorte = copia del xlsx original (fondo/logo) + filas del cotizante; una carga sustituye la anterior. Overlay con %. Sin migrate. Pull + `view:cache` + `route:cache`.

Plan súper admin (14 sep 2026): ficha empresa aplica Accesos+Supervisión ya / fecha / al corte; cartera de clientes con scroll. Sin migrate ni seeder. Pull + `npm run build` + `route:cache` + `view:cache` + `config:cache`.

Tablero SIG + pánico (14 sep 2026): migrate `operational_alerts`. Resumen cliente e instalaciones (mapa, puestos, novedades, afiliación, gráfica). Pánico sidebar/APK. Overlay poll. Pull + `migrate --force` + `npm run build` + `view:cache` + `route:cache`. Copiar PWA `public/campo` (`zentra-sup-v44`). APK v1.3 en `public/downloads/zentra-supervision.apk`. Sin seeder. Artisan como `wcodex-zentra`.

APK v1.4 (14 sep 2026): GPS de turno no arranca hasta conceder ubicación + notificaciones (ya no cierra la app al reentrar con turno abierto). PWA `zentra-sup-v45`. Pull + `view:cache`. El APK va en git (`public/downloads/zentra-supervision.apk`). Sin migrate.

Alpine sidebar + overlay (14 sep 2026): `app.js` debe importar `panelSidebar` y `opsLiveAlerts` (sin el primero el tablero Observatorio no pinta mapa ni gráficas). Pull + `npm run build` + `view:cache`. Sin migrate.

Atención de pánicos (14 sep 2026): migrate `panic_attentions` + `RoleAndPermissionSeeder` (`ops.panic.attend`). Overlay pánico en bucle hasta Enterado/Atender. Pull + `migrate --force` + seeder de roles + `npm run build` + `view:cache` + `route:cache`. Artisan como `wcodex-zentra`.

Traza GPS En vivo (15 sep 2026): `BuildSupervisorTrailService` filtra precisión > 150 m, saltos > ~130 km/h y picos aislados. Sin migrate, seeder ni `npm`. Pull + `view:cache` + `route:cache`. Artisan como `wcodex-zentra`.

Permisos + overlay (15 sep 2026): catálogo de matriz (dashboard, instalaciones, pánicos, SIG, etc.) para **admin y colaborador**. Pánico y Observatorio independientes. Overlay palpitante; Observatorio también en bucle. Sin migrate de tablas. Pull + `RoleAndPermissionSeeder` (crea `company.installations.*`, `ops.sig.view` y rellena grants de `company-admin`) + `npm run build` + `view:cache` + `route:cache`. Artisan como `wcodex-zentra`.

Matriz cliente/instalaciones (15 sep 2026): catálogo partido (nodos, personas, vehículos, etc.). Empleados/documentos recortados a puestos. Migrate `2026_09_15_150000` + `RoleAndPermissionSeeder` + `npm run build` + `view:cache` + `route:cache`. Artisan como `wcodex-zentra`.

Ficha folio Observatorio (15 sep 2026): grilla 2×2; fila 1 mapa+datos `h-[28rem]`, acciones fijas al pie. Pull + `npm run build` + `view:cache`. Sin migrate ni seeder. Artisan como `wcodex-zentra`.

Carga de folios (15 sep 2026): medidor verde→rojo (`load_rate`). Título **Atención de folios**; leyenda Cerrados (verde, izq.) · En trámite · Total (rojo, der.). Pull + `npm run build` + `view:cache`. Sin migrate. Artisan como `wcodex-zentra`.

Pánico inmediato (15 sep 2026): clic dispara el aviso (panel y APK, sin confirmar). **Enterado** silencia solo a ese usuario; **Atender** quita overlay y sonido a todos. PWA `zentra-sup-v46`. APK v1.5 en `public/downloads/zentra-supervision.apk`. Pull + `npm run build` + `view:cache` + `route:cache`. Sin migrate ni seeder. Artisan como `wcodex-zentra`.

Selfie de perfil APK/PWA (16 sep 2026): círculo del turno con la foto de inicio (`data:` + `GET /api/supervision/shift-photo/start-selfie`). PWA `zentra-sup-v47`. APK v1.6. Pull + `view:cache` + `route:cache`. Sin migrate ni seeder. Artisan como `wcodex-zentra`.

Modalidades de puesto (15 sep 2026): Ajustes → Modalidades (`supervisor_post_modalities`, horas 1–24; semilla 8/12/24). El puesto elige del catálogo. Pull + `migrate --force` + `view:cache` + `route:cache`. Sin seeder ni `npm`. Artisan como `wcodex-zentra`.

Mapa Supervisión + ficha de turno (16 sep 2026): En vivo sin filtros; ruta GPS al clic; Historial solo cerrados (una ruta Roads). Ficha `kind=shift` se congela en `supervisor_shifts.sheet_snapshot` al cierre. Pull + `migrate --force` + `view:cache` + `route:cache`. Sin seeder ni `npm`. Artisan como `wcodex-zentra`.

Portería consola vigilante (17 sep 2026): pánico `POST /access/ops/panic`. Puerta/turno solo el `guardia`; Operar portería entra al Resumen. Ingreso unificado. Pull + `migrate --force` si falta `2026_09_17_150000` + `view:cache` + `route:cache`. Sin seeder ni `npm` si el HTML ya está. Artisan como `wcodex-zentra`.

Ingreso/salida dos pestañas (17 sep 2026): Movimiento (busca → Ingresa/Sale) + Registros (filtros). Migrate `2026_09_17_180000` (`destination_*`, `authorized_member_id`). Pull + `migrate --force` + `npm run build` + `view:cache` + `route:cache`. Sin seeder. Artisan como `wcodex-zentra`.

Resumen servicios vs revistas (21 sep 2026): puesto = servicio. Novedades de servicio = cambios de puesto + observaciones. Revistas en contenedor aparte. Pull + `npm run build` + `view:cache` + `route:cache`. Sin migrate ni seeder. Artisan como `wcodex-zentra`.

Preview planilla parafiscal (21 sep 2026): LibreOffice convierte el recorte `.xlsx` a PDF (cache `*.xlsx.preview.pdf`). Apt una vez: `apt-get install -y --no-install-recommends libreoffice-calc fonts-liberation`. Usuario `wcodex-zentra` debe poder ejecutar `/usr/bin/soffice`. Sin `soffice` no hay 500: tabla HTML + aviso. Pull + `view:cache` + `config:cache`. Sin migrate ni `npm`. Artisan como `wcodex-zentra`.

Documentos Ver vs Gestionar (21 sep 2026): `company.documents.view` solo preview. **Descargar** (PDF/xlsx) exige `company.documents.manage`. Pull + `npm run build` + `route:cache` + `view:cache`. Sin migrate ni seeder. Artisan como `wcodex-zentra`.

Cupo por instalación + revista 40 m + una sesión (28 sep 2026): migrate `2026_09_28_210000` (`users.single_session_token`, geo opcional en `supervisor_posts`). PWA `zentra-sup-v48`. APK v1.7 en `public/downloads/zentra-supervision.apk`. Pull + `migrate --force` + `npm run build` + `view:cache` + `route:cache`. Artisan como `wcodex-zentra`.

Favicon único (28 sep 2026): solo `public/favicon.ico`. `partials.favicon` añade `?v=` con `filemtime` para evitar caché. Pull + `view:cache`. Artisan como `wcodex-zentra`.

Suspender acceso (24 sep 2026): ficha empresa, bloque **Acceso al sistema** (Suspender / Reactivar / Archivar). Confirmación en modal del panel. Admin empresa solo lectura + banner. Resto bloqueado. Sin migrate ni seeder. Pull + `view:cache` + `route:cache`. Artisan como `wcodex-zentra`.

Tableros en vivo (15 sep 2026): Observatorio + SIG + **Mi empresa**. JSON al cambio (Reverb) y sondeo cada 12 s si el socket no está conectado. Crear folio también refresca (alerta Observatorio). Toast de quién cambió. Mapa de conjuntos sigue de un pintado. Pull + `npm run build` + `view:cache` + `route:cache` + `config:cache`.

Para WebSocket (si no, el sondeo basta):

1. En `.env` del VPS: `BROADCAST_CONNECTION=reverb`, `REVERB_APP_ID` / `KEY` / `SECRET` (valores propios), `REVERB_HOST=zentra.wcodex.cloud`, `REVERB_PORT=443`, `REVERB_SCHEME=https`, `REVERB_SERVER_HOST=127.0.0.1`, `REVERB_SERVER_PORT=6001` (8080 es Varnish/PHP), `REVERB_BROADCAST_HOST=127.0.0.1`, `REVERB_BROADCAST_PORT=6001`, `REVERB_BROADCAST_SCHEME=http`. Las `VITE_REVERB_*` apuntan al dominio público (`443` / `https`); `npm run build` **después** de fijarlas.
2. Proceso: `php8.3 artisan reverb:start --no-interaction` como `wcodex-zentra` (systemd o Supervisor). No hace falta `queue:work` (`ShouldBroadcastNow`).
3. Nginx (directivas extra CloudPanel), luego `nginx -t && systemctl reload nginx`:

```nginx
location /app {
    proxy_http_version 1.1;
    proxy_set_header Host $http_host;
    proxy_set_header Scheme $scheme;
    proxy_set_header SERVER_PORT $server_port;
    proxy_set_header REMOTE_ADDR $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_pass http://127.0.0.1:6001;
}
```

**Artisan cache siempre como `wcodex-zentra`.** Si `view:cache` corre como root, Gestionar ficha da 500 (`Permission denied` al escribir `storage/framework/views`). Tras el cache: `chown -R wcodex-zentra:wcodex-zentra "$SITE"`.

APK Supervisión: el VPS no lo fabrica. Va en el repo (`public/downloads/zentra-supervision.apk`). Tras pull: `route:cache` + `view:cache`. Descargas: `/company/descargas` y `/admin/descargas`.

```bash
SITE=/home/wcodex-zentra/htdocs/zentra.wcodex.cloud
cd "$SITE"
sudo -u wcodex-zentra git pull --ff-only origin main
sudo -u wcodex-zentra -H bash -lc "cd '$SITE' && php8.3 /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction"
sudo -u wcodex-zentra -H bash -lc "cd '$SITE' && npm ci && npm run build"
sudo -u wcodex-zentra -H bash -lc "cd '$SITE' && php8.3 artisan migrate --force --no-interaction"
sudo -u wcodex-zentra -H bash -lc "cd '$SITE' && php8.3 artisan db:seed --class=RoleAndPermissionSeeder --force --no-interaction"
sudo -u wcodex-zentra -H bash -lc "cd '$SITE' && php8.3 artisan config:cache && php8.3 artisan route:cache && php8.3 artisan view:cache"
chown -R wcodex-zentra:wcodex-zentra "$SITE"
```

Cron del sitio: `* * * * * php8.3 artisan schedule:run`.

El indexador de Documentos no depende del MIME de `.mjs` (el worker se carga como blob). Si otro módulo ES falla en consola con `application/octet-stream`, en el vhost de CloudPanel (directivas extra de Nginx):

```nginx
types { application/javascript mjs; }
```

Luego `nginx -t && systemctl reload nginx`.
