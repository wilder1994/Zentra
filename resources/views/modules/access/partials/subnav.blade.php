@if(request()->routeIs('access.operations'))
<div class="py-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        @can('access.dashboard')
        <a href="{{ route('access.dashboard') }}" class="group relative bg-slate-900 rounded-xl border border-slate-800 hover:border-indigo-700 p-4 transition-all duration-200">
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0 w-10 h-10 bg-indigo-900/50 rounded-lg flex items-center justify-center group-hover:bg-indigo-800/50 transition-colors">
                    <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-white group-hover:text-indigo-300">Dashboard</p>
                    <p class="text-xs text-slate-500">Resumen general del sistema</p>
                </div>
            </div>
        </a>
        @endcan

        @can('access.register.entry')
        <a href="{{ route('access.logs.index') }}" class="group relative bg-slate-900 rounded-xl border border-slate-800 hover:border-emerald-700 p-4 transition-all duration-200">
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0 w-10 h-10 bg-emerald-900/50 rounded-lg flex items-center justify-center group-hover:bg-emerald-800/50 transition-colors">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-white group-hover:text-emerald-300">Ingreso/Salida</p>
                    <p class="text-xs text-slate-500">Registrar entrada y salida de visitantes</p>
                </div>
            </div>
        </a>
        @endcan

        @can('access.manage.visitors')
        <a href="{{ route('access.visitors.index') }}" class="group relative bg-slate-900 rounded-xl border border-slate-800 hover:border-blue-700 p-4 transition-all duration-200">
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0 w-10 h-10 bg-blue-900/50 rounded-lg flex items-center justify-center group-hover:bg-blue-800/50 transition-colors">
                    <svg class="w-5 h-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-white group-hover:text-blue-300">Visitantes</p>
                    <p class="text-xs text-slate-500">Administrar visitantes registrados</p>
                </div>
            </div>
        </a>
        @endcan

        @can('access.manage.residents')
        <a href="{{ route('access.residents.index') }}" class="group relative bg-slate-900 rounded-xl border border-slate-800 hover:border-teal-700 p-4 transition-all duration-200">
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0 w-10 h-10 bg-teal-900/50 rounded-lg flex items-center justify-center group-hover:bg-teal-800/50 transition-colors">
                    <svg class="w-5 h-5 text-teal-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-white group-hover:text-teal-300">Residentes</p>
                    <p class="text-xs text-slate-500">Personas que viven en la instalación</p>
                </div>
            </div>
        </a>
        @endcan

        @can('access.manage.housing_units')
        <a href="{{ route('access.housing_units.index') }}" class="group relative bg-slate-900 rounded-xl border border-slate-800 hover:border-orange-700 p-4 transition-all duration-200">
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0 w-10 h-10 bg-orange-900/50 rounded-lg flex items-center justify-center group-hover:bg-orange-800/50 transition-colors">
                    <svg class="w-5 h-5 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-white group-hover:text-orange-300">Apartamentos</p>
                    <p class="text-xs text-slate-500">Unidades de vivienda de la instalación</p>
                </div>
            </div>
        </a>
        @endcan

        @can('access.manage.buildings')
        <a href="{{ route('access.buildings.index') }}" class="group relative bg-slate-900 rounded-xl border border-slate-800 hover:border-slate-600 p-4 transition-all duration-200">
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0 w-10 h-10 bg-slate-800 rounded-lg flex items-center justify-center group-hover:bg-slate-700 transition-colors">
                    <svg class="w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-white group-hover:text-slate-300">Torres/Bloques</p>
                    <p class="text-xs text-slate-500">Edificios de la instalación residencial</p>
                </div>
            </div>
        </a>
        @endcan

        @can('access.manage.vehicles')
        <a href="{{ route('access.vehicles.index') }}" class="group relative bg-slate-900 rounded-xl border border-slate-800 hover:border-cyan-700 p-4 transition-all duration-200">
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0 w-10 h-10 bg-cyan-900/50 rounded-lg flex items-center justify-center group-hover:bg-cyan-800/50 transition-colors">
                    <svg class="w-5 h-5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10m0 0V21h14v-5m0 0h3l2-4-2-4h-3l-2 4h-1"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-white group-hover:text-cyan-300">Vehículos</p>
                    <p class="text-xs text-slate-500">Registro de vehículos del sistema</p>
                </div>
            </div>
        </a>
        @endcan

        @can('access.manage.vehicle_access')
        <a href="{{ route('access.vehicle_access.index') }}" class="group relative bg-slate-900 rounded-xl border border-slate-800 hover:border-amber-700 p-4 transition-all duration-200">
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0 w-10 h-10 bg-amber-900/50 rounded-lg flex items-center justify-center group-hover:bg-amber-800/50 transition-colors">
                    <svg class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-white group-hover:text-amber-300">Control Vehicular</p>
                    <p class="text-xs text-slate-500">Ingreso/salida de vehículos de residentes</p>
                </div>
            </div>
        </a>
        @endcan

        @can('access.manage.pre_authorizations')
        <a href="{{ route('access.pre_authorizations.index') }}" class="group relative bg-slate-900 rounded-xl border border-slate-800 hover:border-purple-700 p-4 transition-all duration-200">
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0 w-10 h-10 bg-purple-900/50 rounded-lg flex items-center justify-center group-hover:bg-purple-800/50 transition-colors">
                    <svg class="w-5 h-5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-white group-hover:text-purple-300">Pre-Autorizaciones</p>
                    <p class="text-xs text-slate-500">Autorizaciones anticipadas de ingreso</p>
                </div>
            </div>
        </a>
        @endcan

        @can('access.manage.correspondence')
        <a href="{{ route('access.correspondence.index') }}" class="group relative bg-slate-900 rounded-xl border border-slate-800 hover:border-pink-700 p-4 transition-all duration-200">
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0 w-10 h-10 bg-pink-900/50 rounded-lg flex items-center justify-center group-hover:bg-pink-800/50 transition-colors">
                    <svg class="w-5 h-5 text-pink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-white group-hover:text-pink-300">Correspondencia</p>
                    <p class="text-xs text-slate-500">Paquetes y correspondencia recibida</p>
                </div>
            </div>
        </a>
        @endcan

        @can('access.manage.guard_logs')
        <a href="{{ route('access.guard_logs.index') }}" class="group relative bg-slate-900 rounded-xl border border-slate-800 hover:border-yellow-700 p-4 transition-all duration-200">
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0 w-10 h-10 bg-yellow-900/50 rounded-lg flex items-center justify-center group-hover:bg-yellow-800/50 transition-colors">
                    <svg class="w-5 h-5 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-white group-hover:text-yellow-300">Minutas</p>
                    <p class="text-xs text-slate-500">Novedades y reportes del guardia</p>
                </div>
            </div>
        </a>
        @endcan

        @can('access.manage.locations')
        <a href="{{ route('access.locations.index') }}" class="group relative bg-slate-900 rounded-xl border border-slate-800 hover:border-sky-700 p-4 transition-all duration-200">
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0 w-10 h-10 bg-sky-900/50 rounded-lg flex items-center justify-center group-hover:bg-sky-800/50 transition-colors">
                    <svg class="w-5 h-5 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-white group-hover:text-sky-300">Puntos de acceso</p>
                    <p class="text-xs text-slate-500">Puertas y porterías de la instalación</p>
                </div>
            </div>
        </a>
        @endcan

        @can('access.manage.blocklist')
        <a href="{{ route('access.blocklist.index') }}" class="group relative bg-slate-900 rounded-xl border border-slate-800 hover:border-red-700 p-4 transition-all duration-200">
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0 w-10 h-10 bg-red-900/50 rounded-lg flex items-center justify-center group-hover:bg-red-800/50 transition-colors">
                    <svg class="w-5 h-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-white group-hover:text-red-300">Lista Bloqueo</p>
                    <p class="text-xs text-slate-500">Ingresos denegados y restricciones</p>
                </div>
            </div>
        </a>
        @endcan

        @can('access.manage.turnos')
        <a href="{{ route('access.turnos.index') }}" class="group relative bg-slate-900 rounded-xl border border-slate-800 hover:border-lime-700 p-4 transition-all duration-200">
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0 w-10 h-10 bg-lime-900/50 rounded-lg flex items-center justify-center group-hover:bg-lime-800/50 transition-colors">
                    <svg class="w-5 h-5 text-lime-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-white group-hover:text-lime-300">Turnos</p>
                    <p class="text-xs text-slate-500">Planillas y rotación de guardias</p>
                </div>
            </div>
        </a>
        @endcan

        @can('access.manage.zones')
        <a href="{{ route('access.zones.index') }}" class="group relative bg-slate-900 rounded-xl border border-slate-800 hover:border-violet-700 p-4 transition-all duration-200">
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0 w-10 h-10 bg-violet-900/50 rounded-lg flex items-center justify-center group-hover:bg-violet-800/50 transition-colors">
                    <svg class="w-5 h-5 text-violet-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-white group-hover:text-violet-300">Zonas</p>
                    <p class="text-xs text-slate-500">Áreas y sectores de vigilancia</p>
                </div>
            </div>
        </a>
        @endcan

        @can('access.view.audit')
        <a href="{{ route('access.audit.index') }}" class="group relative bg-slate-900 rounded-xl border border-slate-800 hover:border-stone-600 p-4 transition-all duration-200">
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0 w-10 h-10 bg-stone-800 rounded-lg flex items-center justify-center group-hover:bg-stone-700 transition-colors">
                    <svg class="w-5 h-5 text-stone-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-white group-hover:text-stone-300">Auditoría</p>
                    <p class="text-xs text-slate-500">Traza de acciones del sistema</p>
                </div>
            </div>
        </a>
        @endcan

        @can('access.view.reports')
        <a href="{{ route('access.reports.index') }}" class="group relative bg-slate-900 rounded-xl border border-slate-800 hover:border-rose-700 p-4 transition-all duration-200">
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0 w-10 h-10 bg-rose-900/50 rounded-lg flex items-center justify-center group-hover:bg-rose-800/50 transition-colors">
                    <svg class="w-5 h-5 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-white group-hover:text-rose-300">Reportes</p>
                    <p class="text-xs text-slate-500">Estadísticas e informes del sistema</p>
                </div>
            </div>
        </a>
        @endcan
    </div>
</div>
@else
<div class="py-4">
    <a href="{{ route('access.operations') }}" class="inline-flex items-center gap-2 text-sm text-indigo-400 hover:text-indigo-300 font-medium">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Volver al menú principal
    </a>
</div>
@endif
