import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

/**
 * Laravel's paginator labels arrive as HTML entities (e.g. "&laquo;
 * Previous"). Decode them to plain text instead of using v-html, which
 * Vue disallows on components like <Link>.
 */
export function paginationLabel(label: string): string {
    return label.replace(/&laquo;/g, '‹').replace(/&raquo;/g, '›');
}
