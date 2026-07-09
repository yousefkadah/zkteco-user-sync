import { Monitor, Upload } from 'lucide-react';
import type { ComponentType } from 'react';

export interface NavItem {
    label: string;
    href: string;
    icon: ComponentType<{ className?: string }>;
    isActive: (path: string) => boolean;
}

export const NAVIGATION: NavItem[] = [
    {
        label: 'Imports',
        href: '/',
        icon: Upload,
        isActive: (path) => path === '/' || path.startsWith('/import'),
    },
    {
        label: 'Devices',
        href: '/devices',
        icon: Monitor,
        isActive: (path) => path.startsWith('/devices'),
    },
];
