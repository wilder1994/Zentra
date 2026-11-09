# Zentra Residentes (APK)

WebView Capacitor que apunta a `/r/` (misma UI web de residentes).

## Build local

```bash
cd resident-app
npm install
npx cap add android   # solo la primera vez
npx cap sync android
# JAVA_HOME = JDK 21
npm run apk
npm run publish-apk   # copia a public/downloads/zentra-residentes.apk
```

En producción, cambia `server.url` en `capacitor.config.json` al dominio real (https).

Push: registra el token con `POST /api/resident/devices` tras el login Sanctum (`POST /api/resident/login`).
