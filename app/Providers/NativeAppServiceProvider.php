<?php

namespace App\Providers;

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
        $window = Window::open()
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
