<x-access-layout title="Supervisión">
    <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl bg-indigo-900/60 flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.739-2.753 8.554m0 0H14.19m-4.943 0h-1.51A4.044 4.044 0 013.693 15.5V15.5a4.044 4.044 0 011.844-4.055m0 0A12.53 12.53 0 0112 6.55a.95.95 0 00.909-.691l.501-1.2a.889.889 0 011.086-.5l2.65.86a.9.9 0 01.6 1.11l-.62 1.86a.913.913 0 01-.95.63A37.786 37.786 0 0115 9.6m-4.056-4.85A18.515 18.515 0 0012 2.333c2.727 4.681 2.242 6.313 2.242 6.313M19 21h2m-8 0h2"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-indigo-300">Acceso restringido</p>
                        <h2 class="text-lg font-bold text-white">Autenticación de Supervisor</h2>
                    </div>
                </div>
            </div>

            <div class="px-6 py-6">
                <p class="text-sm text-slate-400 mb-5">
                    Para entrar al módulo de <strong class="text-slate-200">Supervisión</strong> debes ingresar el
                    <strong class="text-slate-200">código único</strong> asignado a tu supervisor. Cada código es
                    personal e intransferible.
                </p>

                @if ($errors->any())
                <div class="mb-4 rounded-lg bg-red-900/40 border border-red-700 text-red-200 px-4 py-3 text-sm">
                    {{ $errors->first('code') }}
                </div>
                @endif

                <form method="POST" action="{{ route('access.supervision.unlock.submit') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-slate-300">Código único de supervisor</label>
                        <x-ui.password-wrap
                            name="code"
                            required
                            autofocus
                            autocomplete="off"
                            inputmode="text"
                            placeholder="••••••••"
                            class="mt-1 block w-full rounded-lg border border-slate-700 bg-slate-950 py-3 pr-10 pl-4 text-center text-lg tracking-widest text-white placeholder-slate-600 focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    <button
                        type="submit"
                        class="w-full inline-flex items-center justify-center px-4 py-3 bg-indigo-600 border border-transparent rounded-lg font-semibold text-sm text-white uppercase tracking-widest hover:bg-indigo-500 transition-colors shadow-sm"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Ingresar a Supervisión
                    </button>
                </form>

                <div class="mt-6 flex items-center justify-center">
                    <a href="{{ route('access.operations') }}" class="text-xs text-slate-500 hover:text-slate-300 transition-colors">
                        ← Volver al menú principal
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-access-layout>