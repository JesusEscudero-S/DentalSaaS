{{-- Layout para pantallas sin sesión (login, recuperar contraseña). --}}
@props(['title' => null])

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('img/ui/logo-tooth.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-50 p-6">
    <x-ui.card class="w-full max-w-[420px] p-8 shadow-[0_12px_32px_rgba(15,23,42,0.08)]">
        <div class="flex items-center gap-2.5">
            <span class="flex size-8 items-center justify-center rounded-lg bg-primary-600">
                <img src="{{ asset('img/ui/logo-tooth.svg') }}" alt="" width="30" height="30">
            </span>
            <span class="text-[14px] text-slate-900">Dental <span class="text-primary-600">SaaS</span></span>
        </div>

        {{ $slot }}
    </x-ui.card>
</body>
</html>
