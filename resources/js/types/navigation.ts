import type { AlertCircleIcon } from '@hugeicons/core-free-icons';
import type { InertiaLinkProps } from '@inertiajs/vue3';

// Hugeicons' own published `IconArray` type is a mutable tuple array, but
// every icon it ships is actually declared `readonly` - so it never
// structurally matches. Deriving the type from a real icon constant instead
// sidesteps that mismatch entirely.
export type NavIcon = typeof AlertCircleIcon;

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: NavIcon;
    isActive?: boolean;
};
