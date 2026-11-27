<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Informe del Observatorio — Zentra</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-6">
    <div class="max-w-md text-center">
        <h1 class="text-lg font-semibold text-white">Preparando el informe</h1>
        <p id="pptx-status" class="mt-2 text-sm text-slate-400">Se arma en segundo plano. Esta página lo descarga cuando está listo.</p>
    </div>
    <script>
        const statusUrl = @json($statusUrl);
        const fileUrl = @json($fileUrl);
        const label = document.getElementById('pptx-status');
        const tick = async () => {
            try {
                const res = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
                const data = await res.json();
                if (data.status === 'ready') {
                    window.location = fileUrl;
                    return;
                }
                if (data.status === 'failed') {
                    label.textContent = data.message || 'No se pudo armar el informe.';
                    return;
                }
            } catch (e) {}
            window.setTimeout(tick, 2000);
        };
        tick();
    </script>
</body>
</html>
