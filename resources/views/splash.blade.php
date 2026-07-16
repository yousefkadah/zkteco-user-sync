<!DOCTYPE html>
{{--
    The window's landing page. Deliberately carries NO @vite bundle: NativePHP
    reveals the window on Electron's `did-finish-load`, which waits for every
    subresource, so landing on the full app meant the window stayed hidden until
    the entire React bundle had downloaded — the app looked like it took ages to
    open, with nothing on screen. This page finishes loading almost immediately,
    so the window appears with the logo + progress, then hands off to the app.
--}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'ZKTeco User Sync') }}</title>
    <link rel="icon" type="image/png" href="/icon.png">
    <style>html, body { margin: 0; height: 100%; background: #0b0d10; }</style>
    <noscript><meta http-equiv="refresh" content="0;url={{ url('/') }}"></noscript>
</head>
<body>
    @include('partials.splash')

    <script>
        // Hand off to the app once this page has painted. The short delay lets
        // Electron's did-finish-load fire and reveal the window FIRST, so the
        // splash is visible while the app bundle loads; navigating immediately
        // would race that and leave the window hidden again.
        addEventListener('load', function () {
            setTimeout(function () {
                // Forward NativePHP's _windowId (it appends one to the window's
                // URL) so the app loads exactly as it would have on a direct '/'
                // load and Window::current() still resolves.
                var id = new URLSearchParams(location.search).get('_windowId');
                var target = @json(url('/')) + (id ? '?_windowId=' + encodeURIComponent(id) : '');

                location.replace(target);
            }, 150);
        });
    </script>
</body>
</html>
