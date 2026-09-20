import { format, parseISO } from 'date-fns';

/**
 * Formats any Date or date string to 'yyyy-MM-dd' (e.g. 2026-09-20)
 */
export function formatDate(value: Date | string | number | null | undefined): string {
    if (!value) return '—';
    try {
        const date = typeof value === 'string' ? (value.includes('T') ? parseISO(value) : new Date(value)) : new Date(value);
        if (isNaN(date.getTime())) return String(value);
        return format(date, 'yyyy-MM-dd');
    } catch {
        return String(value);
    }
}

/**
 * Formats any Date or date string to 'yyyy-MM-dd HH:mm:ss' (e.g. 2026-09-20 15:13:10)
 */
export function formatDateTime(value: Date | string | number | null | undefined): string {
    if (!value) return '—';
    try {
        const date = typeof value === 'string' ? (value.includes('T') ? parseISO(value) : new Date(value)) : new Date(value);
        if (isNaN(date.getTime())) return String(value);
        return format(date, 'yyyy-MM-dd HH:mm:ss');
    } catch {
        return String(value);
    }
}

/**
 * Formats any Date or date string to 24-hour time 'HH:mm:ss' (e.g. 15:13:10)
 */
export function formatTime(value: Date | string | number | null | undefined): string {
    if (!value) return '—';
    try {
        // If it's already a plain time string like '15:13:10' or '09:00'
        if (typeof value === 'string' && /^\d{2}:\d{2}(:\d{2})?$/.test(value.trim())) {
            const parts = value.trim().split(':');
            const h = parts[0].padStart(2, '0');
            const m = parts[1].padStart(2, '0');
            const s = parts[2] ? parts[2].padStart(2, '0') : '00';
            return `${h}:${m}:${s}`;
        }

        const date = typeof value === 'string' ? (value.includes('T') ? parseISO(value) : new Date(value)) : new Date(value);
        if (isNaN(date.getTime())) return String(value);
        return format(date, 'HH:mm:ss');
    } catch {
        return String(value);
    }
}

/**
 * Formats any Date or date string to 24-hour short time 'HH:mm' (e.g. 15:13)
 */
export function formatShortTime(value: Date | string | number | null | undefined): string {
    if (!value) return '—';
    try {
        if (typeof value === 'string' && /^\d{2}:\d{2}(:\d{2})?$/.test(value.trim())) {
            const parts = value.trim().split(':');
            return `${parts[0].padStart(2, '0')}:${parts[1].padStart(2, '0')}`;
        }
        const date = typeof value === 'string' ? (value.includes('T') ? parseISO(value) : new Date(value)) : new Date(value);
        if (isNaN(date.getTime())) return String(value);
        return format(date, 'HH:mm');
    } catch {
        return String(value);
    }
}
