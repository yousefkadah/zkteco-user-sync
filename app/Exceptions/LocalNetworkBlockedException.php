<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the OS refuses to let us put UDP probes on the local network, so
 * a discovery scan can't run at all.
 *
 * This exists to keep a *permission* failure distinguishable from "no devices
 * answered". Both otherwise look like an empty result, which makes the app
 * appear broken rather than un-permissioned — and leaves the user with nothing
 * to act on.
 *
 * The two cases we expect in the wild:
 *  - macOS 15 (Sequoia) and later gate local-network access behind TCC consent.
 *    The prompt appears on first access and, until granted, local networking
 *    fails. No entitlement exempts an app from this.
 *  - Linux sandboxes (snap/Flatpak) can deny the socket via AppArmor/seccomp
 *    when the needed interface isn't connected.
 */
class LocalNetworkBlockedException extends RuntimeException
{
    public function __construct(private readonly int $socketErrorCode = 0, string $message = '')
    {
        parent::__construct($message !== '' ? $message : 'The system blocked local network access.');
    }

    public function socketErrorCode(): int
    {
        return $this->socketErrorCode;
    }

    /**
     * A short, actionable instruction for the current platform. Shown verbatim
     * to the user, so it names the exact place to click.
     */
    public function hint(): string
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            return 'macOS is blocking local network access. Open System Settings → Privacy & Security → '
                .'Local Network and turn it on for this app, then scan again.';
        }

        // snapd sets SNAP for confined apps; Flatpak sets FLATPAK_ID.
        if (getenv('SNAP') !== false || getenv('FLATPAK_ID') !== false) {
            return 'The app sandbox is blocking local network access. Allow network access for this app '
                .'(for a snap: sudo snap connect '.($this->snapName() ?: '<app>').':network), then scan again.';
        }

        return 'The system blocked local network access. Check your firewall or security software, '
            .'then scan again.';
    }

    private function snapName(): ?string
    {
        $name = getenv('SNAP_NAME');

        return is_string($name) && $name !== '' ? $name : null;
    }
}
