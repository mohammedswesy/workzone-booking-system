/**
 * App display timezone helpers. Storage is UTC; wall clocks use Asia/Gaza (or page.props.displayTimezone).
 */

export function displayTimezone(fallback = 'Asia/Gaza') {
    try {
        return window?.Laravel?.displayTimezone
            || document?.querySelector('meta[name="display-timezone"]')?.content
            || fallback;
    } catch {
        return fallback;
    }
}

export function formatInDisplayTz(value, locale = 'en', timeZone = displayTimezone()) {
    if (!value) return '—';
    try {
        return new Intl.DateTimeFormat(locale === 'ar' ? 'ar' : 'en', {
            timeZone,
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(value));
    } catch {
        return String(value);
    }
}

/** Format a UTC instant as datetime-local value in the app display timezone. */
export function toDatetimeLocalInDisplayTz(value, timeZone = displayTimezone()) {
    if (!value) return '';
    const d = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(d.getTime())) return '';

    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(d);

    const get = (type) => parts.find((p) => p.type === type)?.value || '00';
    return `${get('year')}-${get('month')}-${get('day')}T${get('hour')}:${get('minute')}`;
}

/** Current wall-clock datetime-local in the display timezone, offset by hours. */
export function nowDatetimeLocalInDisplayTz(addHours = 0, timeZone = displayTimezone()) {
    const d = new Date(Date.now() + addHours * 3600 * 1000);
    return toDatetimeLocalInDisplayTz(d, timeZone);
}
