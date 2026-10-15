<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'TRUSTA') }} - Auth</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|poppins:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Tema terang/gelap (diterapkan sebelum render agar tidak berkedip) -->
        @include('partials.theme-head')

        <!-- Scripts (Alpine.js sudah dibundel di app.js) -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="guest-body font-sans text-gray-900 antialiased bg-[#0F172A]">
        @include('partials.theme-toggle', ['floating' => true])
        {{ $slot }}
    </body>
</html>
