/**
 * Client-side coordinate parsing / normalization for venue map forms.
 * Never fetches short links or calls external geocoding services.
 */

const ARABIC_INDIC = '٠١٢٣٤٥٦٧٨٩';
const EASTERN_ARABIC_INDIC = '۰۱۲۳۴۵۶۷۸۹';

/**
 * @param {unknown} value
 * @returns {number|null}
 */
export function roundCoord(value) {
    if (value === null || value === undefined || value === '') return null;
    const n = Number(value);
    if (!Number.isFinite(n)) return null;
    return Number(n.toFixed(7));
}

/**
 * Normalize Arabic-Indic digits and Arabic decimal separators to ASCII.
 * @param {unknown} raw
 * @returns {string}
 */
export function normalizeNumericString(raw) {
    let s = String(raw ?? '');
    s = s.replace(/[٠-٩]/g, (c) => String(ARABIC_INDIC.indexOf(c)));
    s = s.replace(/[۰-۹]/g, (c) => String(EASTERN_ARABIC_INDIC.indexOf(c)));
    // Arabic decimal separator (U+066B) and Arabic comma used as decimal
    s = s.replace(/\u066B/g, '.');
    return s.trim();
}

/**
 * @param {string} raw
 * @returns {{ lat: number, lng: number }|null}
 */
export function parseGoogleMapsUrl(raw) {
    const text = String(raw ?? '');

    // Prefer place marker payloads over viewport @lat,lng (often 0,0 or map center).
    let m = text.match(/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/);
    if (m) {
        return { lat: roundCoord(m[1]), lng: roundCoord(m[2]) };
    }

    m = text.match(/[?&]q=(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/i);
    if (m) {
        return { lat: roundCoord(m[1]), lng: roundCoord(m[2]) };
    }

    m = text.match(/@(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/);
    if (m) {
        return { lat: roundCoord(m[1]), lng: roundCoord(m[2]) };
    }

    return null;
}

/**
 * @param {unknown} raw
 * @returns {boolean}
 */
export function looksLikeMapsUrl(raw) {
    const text = String(raw ?? '');
    return /https?:\/\//i.test(text)
        || /maps\.google\./i.test(text)
        || /google\.com\/maps/i.test(text)
        || /maps\.app\.goo\.gl/i.test(text)
        || /goo\.gl\/maps/i.test(text);
}

/**
 * Parse pasted text into coordinates.
 * @param {unknown} raw
 * @returns {{
 *   kind: 'pair'|'single'|'url_unparsed'|'empty'|'invalid',
 *   lat?: number|null,
 *   lng?: number|null,
 *   value?: number|null
 * }}
 */
export function parseCoordinatePaste(raw) {
    const original = String(raw ?? '').trim();
    if (original === '') {
        return { kind: 'empty' };
    }

    const fromUrl = parseGoogleMapsUrl(original) || parseGoogleMapsUrl(normalizeNumericString(original));
    if (fromUrl?.lat != null && fromUrl?.lng != null) {
        return { kind: 'pair', lat: fromUrl.lat, lng: fromUrl.lng };
    }

    if (looksLikeMapsUrl(original)) {
        return { kind: 'url_unparsed' };
    }

    // Normalize digits; keep separators. Convert Arabic list comma ، to ASCII comma.
    let normalized = normalizeNumericString(original).replace(/\u060C/g, ',');

    // Pair: "31.5, 34.4" / "31.5;34.4" / "31.5 34.4"
    let m = normalized.match(/^(-?\d+(?:\.\d+)?)\s*[,;\s]\s*(-?\d+(?:\.\d+)?)$/);
    if (m) {
        return { kind: 'pair', lat: roundCoord(m[1]), lng: roundCoord(m[2]) };
    }

    // Single number (may use Arabic decimal already normalized)
    m = normalized.match(/^(-?\d+(?:\.\d+)?)$/);
    if (m) {
        return { kind: 'single', value: roundCoord(m[1]) };
    }

    return { kind: 'invalid' };
}

/**
 * True when values likely have lat/lng swapped (lat out of range but plausible as lng).
 * @param {unknown} lat
 * @param {unknown} lng
 * @returns {boolean}
 */
export function looksSwapped(lat, lng) {
    const a = Number(lat);
    const b = Number(lng);
    if (!Number.isFinite(a) || !Number.isFinite(b)) return false;
    return Math.abs(a) > 90 && Math.abs(a) <= 180 && Math.abs(b) <= 90;
}

/**
 * @param {unknown} lat
 * @param {unknown} lng
 * @returns {boolean}
 */
export function coordsComplete(lat, lng) {
    return lat !== null && lat !== undefined && lat !== ''
        && lng !== null && lng !== undefined && lng !== '';
}
