@php
    use App\Models\StructureAppUser;
@endphp

<x-client-layout title="Editar usuario app">
    <form action="{{ route('client.app-users.update', $appUser) }}" method="POST" class="max-w-xl space-y-4 rounded-xl border border-slate-800 bg-slate-900 p-5">
        @csrf
        @method('PUT')
        <div>
            <a href="{{ route('client.app-users.show', $appUser) }}" class="text-sm text-slate-400 hover:text-white">← Volver</a>
            <h2 class="text-lg font-semibold text-white mt-2">{{ $appUser->member?->full_name ?? $appUser->username }}</h2>
            <p class="text-xs font-mono text-teal-300 mt-1">{{ $appUser->login_email }}</p>
        </div>

        <fieldset class="space-y-2">
            <legend class="text-xs text-slate-400 mb-1">Canal</legend>
            <label class="flex items-center gap-2 text-sm text-slate-300">
                <input type="radio" name="delivery_channel" value="{{ StructureAppUser::CHANNEL_ANDROID }}"
                       @checked(old('delivery_channel', $appUser->delivery_channel) === StructureAppUser::CHANNEL_ANDROID)
                       class="border-slate-600 bg-slate-950 text-teal-600">
                Android — APK
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-300">
                <input type="radio" name="delivery_channel" value="{{ StructureAppUser::CHANNEL_IOS_WEB }}"
                       @checked(old('delivery_channel', $appUser->delivery_channel) === StructureAppUser::CHANNEL_IOS_WEB)
                       class="border-slate-600 bg-slate-950 text-teal-600">
                iPhone — web
            </label>
        </fieldset>

        <label class="flex items-center gap-2 text-sm text-slate-300">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $appUser->is_active))
                   class="rounded border-slate-600 bg-slate-950 text-teal-600">
            Cuenta activa
        </label>

        <button type="submit" class="w-full rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-500">Guardar</button>
    </form>
</x-client-layout>
