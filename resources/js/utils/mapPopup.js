/**
 * Map marker popup helpers (locale-aware price + distance + strings).
 */

/**
 * @param {string} locale vue-i18n locale ('en' | 'ar')
 * @returns {string}
 */
export function mapNumberLocale(locale) {
    return String(locale || 'en').startsWith('ar') ? 'ar' : 'en-US';
}

/**
 * @param {number|string|null|undefined} amount
 * @param {string} locale
 * @param {string} [currency='USD']
 * @returns {string}
 */
export function formatMapPrice(amount, locale, currency = 'USD') {
    if (amount == null || amount === '') return '';
    const n = Number(amount);
    if (!Number.isFinite(n)) return '';

    return new Intl.NumberFormat(mapNumberLocale(locale), {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(n);
}

/**
 * @param {number|string|null|undefined} km
 * @param {string} locale
 * @returns {string}
 */
export function formatDistanceKm(km, locale) {
    if (km == null || km === '') return '';
    const n = Number(km);
    if (!Number.isFinite(n)) return '';

    const formatted = new Intl.NumberFormat(mapNumberLocale(locale), {
        maximumFractionDigits: n < 10 ? 1 : 0,
        minimumFractionDigits: 0,
    }).format(n);

    return formatted;
}

/**
 * @param {{ name?: string, from_price?: number|null, distance_km?: number|null, url?: string }} marker
 * @param {{
 *   fromPrice: (p: { price: string }) => string,
 *   details: string,
 *   distance?: (p: { distance: string }) => string,
 * }} labels
 * @param {string} locale
 * @returns {string}
 */
export function buildMarkerPopupHtml(marker, labels, locale) {
    const name = escapeHtml(marker?.name || '');
    const price = formatMapPrice(marker?.from_price, locale);
    const priceLine = price
        ? `${escapeHtml(labels.fromPrice({ price }))}<br/>`
        : '';
    const distNum = formatDistanceKm(marker?.distance_km, locale);
    const distLine = distNum && labels.distance
        ? `${escapeHtml(labels.distance({ distance: distNum }))}<br/>`
        : '';
    const details = escapeHtml(labels.details);
    const url = escapeHtml(marker?.url || '#');

    return `<div style="min-width:140px" dir="auto">
                <strong>${name}</strong><br/>
                ${priceLine}
                ${distLine}
                <a href="${url}">${details}</a>
             </div>`;
}

export function escapeHtml(s) {
    return String(s)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

/**
 * Fit leaflet map to marker bounds.
 * @param {import('leaflet').Map} map
 * @param {Array<[number, number]>} bounds
 * @param {{ padding?: [number, number], maxZoom?: number, singleZoom?: number }} [opts]
 */
export function fitMapToMarkers(map, bounds, opts = {}) {
    if (!map || !bounds?.length) return;
    const padding = opts.padding || [40, 40];
    const maxZoom = opts.maxZoom ?? 15;
    const singleZoom = opts.singleZoom ?? 14;

    if (bounds.length === 1) {
        map.setView(bounds[0], Math.min(singleZoom, maxZoom));
        return;
    }

    map.fitBounds(bounds, { padding, maxZoom });
}
