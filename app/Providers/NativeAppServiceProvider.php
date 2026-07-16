<?php

namespace App\Providers;

use Illuminate\Support\Facades\Artisan;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        // Keep the app's schema current on every launch. NativePHP only runs
        // migrations when nativephp.sqlite is first created, so users updating
        // from an older version (whose database already exists) would otherwise
        // miss new tables — e.g. the Fullness connector's fullness_connections.
        // Idempotent: already-run migrations are skipped.
        try {
            Artisan::call('native:migrate', ['--force' => true]);
        } catch (\Throwable $exception) {
            report($exception);
        }

        $window = Window::open()
            // Land on the bundle-free splash rather than '/'. NativePHP creates the
            // window hidden and only reveals it on Electron's `did-finish-load`,
            // which waits for every subresource — so landing on the app meant no
            // window appeared until the entire React bundle had downloaded, making
            // startup look frozen. The splash finishes loading immediately, so the
            // window shows the logo + progress right away and then hands off to '/'.
            ->url(url('/splash'))
            ->title(config('app.name', 'ZKTeco User Sync'))
            ->width(1280)
            ->height(860)
            ->minWidth(1024)
            ->minHeight(640)
            ->backgroundColor('#0b0d10'); // matches the dark window; avoids a white load flash

        // macOS gets the bespoke frameless chrome with the native traffic lights
        // inset into our custom titlebar. Windows/Linux keep the native OS frame
        // so the user never loses min/max/close (safe cross-platform default).
        if (PHP_OS_FAMILY === 'Darwin') {
            $window->titleBarHiddenInset()
                ->trafficLightPosition(16, 14); // vertically centered in the 44px titlebar band
        }
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
            'memory_limit' => '512M',
            'max_execution_time' => '0',
            'default_socket_timeout' => '30',
        ];
    }
}
