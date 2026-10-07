/**
 * Browser geolocation helper for "Near me".
 * Never sends coordinates to third parties.
 */

/**
 * @param {GeolocationPositionError|Error|{ code?: number|string }} err
 * @returns {'denied'|'unavailable'|'timeout'|'unsupported'|'unknown'}
 */
export function mapGeolocationError(err) {
    if (!err) return 'unknown';
    const code = err.code;
    // GeolocationPositionError codes
    if (code === 1 || code === 'PERMISSION_DENIED' || code === 'denied') return 'denied';
    if (code === 2 || code === 'POSITION_UNAVAILABLE' || code === 'unavailable') return 'unavailable';
    if (code === 3 || code === 'TIMEOUT' || code === 'timeout') return 'timeout';
    if (code === 'unsupported') return 'unsupported';
    return 'unknown';
}

/**
 * Round visitor coords for shareable URLs (privacy: 3 decimals ≈ 110 m).
 * @param {unknown} value
 * @returns {number|null}
 */
export function roundNearMeCoord(value) {
    if (value === null || value === undefined || value === '') return null;
    const n = Number(value);
    if (!Number.isFinite(n)) return null;
    return Number(n.toFixed(3));
}

/**
 * Haversine distance in kilometers (client-side soft checks).
 * @param {number} lat1
 * @param {number} lng1
 * @param {number} lat2
 * @param {number} lng2
 * @returns {number}
 */
export function distanceKm(lat1, lng1, lat2, lng2) {
    const toRad = (d) => (d * Math.PI) / 180;
    const R = 6371;
    const dLat = toRad(lat2 - lat1);
    const dLng = toRad(lng2 - lng1);
    const a = Math.sin(dLat / 2) ** 2
        + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

/**
 * @param {{
 *   enableHighAccuracy?: boolean,
 *   timeout?: number,
 *   maximumAge?: number,
 *   decimals?: number,
 *   geolocation?: Geolocation,
 * }} [options]
 * @returns {Promise<{ lat: number, lng: number }>}
 */
export function requestUserLocation(options = {}) {
    const geo = options.geolocation
        ?? (typeof navigator !== 'undefined' ? navigator.geolocation : null);
    const decimals = options.decimals ?? 3;

    return new Promise((resolve, reject) => {
        if (!geo || typeof geo.getCurrentPosition !== 'function') {
            reject({ code: 'unsupported' });
            return;
        }

        geo.getCurrentPosition(
            (pos) => {
                const round = (n) => Number(Number(n).toFixed(decimals));
                resolve({
                    lat: decimals === 3
                        ? roundNearMeCoord(pos.coords.latitude)
                        : round(pos.coords.latitude),
                    lng: decimals === 3
                        ? roundNearMeCoord(pos.coords.longitude)
                        : round(pos.coords.longitude),
                });
            },
            (err) => {
                reject({ code: mapGeolocationError(err), raw: err });
            },
            {
                enableHighAccuracy: options.enableHighAccuracy ?? false,
                timeout: options.timeout ?? 10_000,
                maximumAge: options.maximumAge ?? 60_000,
            },
        );
    });
}
