import { describe, expect, it } from 'vitest';
import {
    mapGeolocationError,
    requestUserLocation,
    roundNearMeCoord,
} from '@/utils/geolocation';
import { parseCoordinatePaste } from '@/utils/coordinates';
import { formatDistanceKm, buildMarkerPopupHtml } from '@/utils/mapPopup';

describe('roundNearMeCoord', () => {
    it('rounds to 3 decimals for shareable URLs', () => {
        expect(roundNearMeCoord(31.5123456)).toBe(31.512);
        expect(roundNearMeCoord(34.4712345)).toBe(34.471);
        expect(roundNearMeCoord('')).toBeNull();
        expect(roundNearMeCoord('abc')).toBeNull();
    });
});

describe('mapGeolocationError', () => {
    it('maps browser error codes', () => {
        expect(mapGeolocationError({ code: 1 })).toBe('denied');
        expect(mapGeolocationError({ code: 2 })).toBe('unavailable');
        expect(mapGeolocationError({ code: 3 })).toBe('timeout');
        expect(mapGeolocationError({ code: 'unsupported' })).toBe('unsupported');
        expect(mapGeolocationError({})).toBe('unknown');
    });
});

describe('requestUserLocation', () => {
    it('resolves rounded coordinates on success', async () => {
        const geolocation = {
            getCurrentPosition: (ok) => {
                ok({ coords: { latitude: 31.50171234, longitude: 34.46681234 } });
            },
        };
        await expect(requestUserLocation({ geolocation })).resolves.toEqual({
            lat: 31.502,
            lng: 34.467,
        });
    });

    it('rejects with denied when permission denied', async () => {
        const geolocation = {
            getCurrentPosition: (_ok, err) => {
                err({ code: 1 });
            },
        };
        await expect(requestUserLocation({ geolocation })).rejects.toMatchObject({ code: 'denied' });
    });

    it('rejects with unavailable', async () => {
        const geolocation = {
            getCurrentPosition: (_ok, err) => {
                err({ code: 2 });
            },
        };
        await expect(requestUserLocation({ geolocation })).rejects.toMatchObject({ code: 'unavailable' });
    });

    it('rejects with timeout', async () => {
        const geolocation = {
            getCurrentPosition: (_ok, err) => {
                err({ code: 3 });
            },
        };
        await expect(requestUserLocation({ geolocation })).rejects.toMatchObject({ code: 'timeout' });
    });

    it('rejects unsupported when geolocation missing', async () => {
        await expect(requestUserLocation({ geolocation: null })).rejects.toMatchObject({
            code: 'unsupported',
        });
    });

    it('supports 7-decimal precision for venue forms', async () => {
        const geolocation = {
            getCurrentPosition: (ok) => {
                ok({ coords: { latitude: 31.50171234, longitude: 34.46681234 } });
            },
        };
        await expect(requestUserLocation({ geolocation, decimals: 7 })).resolves.toEqual({
            lat: 31.5017123,
            lng: 34.4668123,
        });
    });
});

describe('near-me paste reuses coordinate parser', () => {
    it('parses pair and maps urls client-side', () => {
        expect(parseCoordinatePaste('31.5, 34.4').kind).toBe('pair');
        expect(parseCoordinatePaste('https://www.google.com/maps?q=31.5,34.4').kind).toBe('pair');
        expect(parseCoordinatePaste('٣١٫٥، ٣٤٫٤').kind).toBe('pair');
    });
});

describe('formatDistanceKm + popup', () => {
    it('formats distance for en/ar', () => {
        expect(formatDistanceKm(12.4, 'en')).toMatch(/12/);
        expect(formatDistanceKm(3.2, 'en')).toMatch(/3/);
        expect(formatDistanceKm(null, 'en')).toBe('');
    });

    it('includes distance line in popup when present', () => {
        const html = buildMarkerPopupHtml(
            { name: 'Coastal', from_price: 14, distance_km: 2.5, url: '/spaces/x' },
            {
                fromPrice: ({ price }) => `From ${price}/hr`,
                details: 'Details',
                distance: ({ distance }) => `${distance} km`,
            },
            'en',
        );
        expect(html).toContain('km');
        expect(html).toContain('Coastal');
    });
});
