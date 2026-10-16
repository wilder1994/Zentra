<x-admin-layout title="Propuesta comercial">
    <div class="max-w-3xl space-y-4">
        <x-ui.button variant="secondary" :href="route('admin.commercial.index')" size="sm">← Comercial</x-ui.button>

        <p class="text-sm text-slate-400">
            Genera un PDF con los planes vigentes del catálogo. Edita los textos antes de descargar. Marcas: Zentra · W Codex.
        </p>

        <form
            method="POST"
            action="{{ route('admin.commercial.brochure.pdf') }}"
            class="space-y-4 rounded-lg border border-slate-800 bg-slate-900/80 p-4"
        >
            @csrf
            @include('modules.admin.commercial.partials.document-fields', ['defaults' => $defaults])

            <div class="flex items-center gap-3 pt-2">
                <x-ui.button type="submit" variant="platform">Descargar PDF</x-ui.button>
                <a href="{{ route('admin.commercial.index') }}" class="text-sm text-slate-400 hover:text-white">Cancelar</a>
            </div>
        </form>
    </div>
</x-admin-layout>
