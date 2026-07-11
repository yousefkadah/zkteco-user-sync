import { House, Monitor, Upload } from 'lucide-react';
import type { ComponentType } from 'react';

export interface NavItem {
    label: string;
    href: string;
    icon: ComponentType<{ className?: string }>;
    isActive: (path: string) => boolean;
}

export const NAVIGATION: NavItem[] = [
    {
        label: 'Home',
        href: '/',
        icon: House,
        isActive: (path) => path === '/',
    },
    {
        label: 'Imports',
        href: '/import',
        icon: Upload,
        isActive: (path) => path.startsWith('/import'),
    },
    {
        label: 'Devices',
        href: '/devices',
        icon: Monitor,
        isActive: (path) => path.startsWith('/devices'),
    },
];
