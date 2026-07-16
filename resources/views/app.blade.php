<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name', 'ZKTeco User Sync') }}</title>
    <link rel="icon" type="image/png" href="/icon.png">
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="min-h-full antialiased">
    @inertia

    {{-- Continues the /splash page's visual while the bundle loads and React
         mounts; app.tsx fades it out. Identical markup, so the hand-off from
         /splash to here is invisible. --}}
    @include('partials.splash')
</body>
</html>
