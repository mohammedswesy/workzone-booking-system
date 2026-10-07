import { describe, expect, it } from 'vitest';
import {
    buildMarkerPopupHtml,
    escapeHtml,
    fitMapToMarkers,
    formatMapPrice,
    mapNumberLocale,
} from '@/utils/mapPopup';

describe('formatMapPrice', () => {
    it('formats USD in English', () => {
        expect(formatMapPrice(14, 'en')).toMatch(/\$14\.00|USD/);
        expect(formatMapPrice(14, 'en')).toContain('14');
    });

    it('formats with Arabic locale digits/grouping', () => {
        const ar = formatMapPrice(14, 'ar');
        expect(ar).toBeTruthy();
        // Arabic locale may use Eastern digits or Western with Arabic currency pattern.
        expect(ar.replace(/[^\d٠-٩]/g, '')).toMatch(/14|١٤/);
    });

    it('returns empty for invalid amounts', () => {
        expect(formatMapPrice(null, 'en')).toBe('');
        expect(formatMapPrice('x', 'en')).toBe('');
    });
});

describe('buildMarkerPopupHtml', () => {
    it('localizes From/Details labels', () => {
        const htmlEn = buildMarkerPopupHtml(
            { name: 'Coastal', from_price: 14, url: '/spaces/coastal' },
            {
                fromPrice: ({ price }) => `From ${price}/hr`,
                details: 'Details',
            },
            'en',
        );
        expect(htmlEn).toContain('From');
        expect(htmlEn).toContain('Details');
        expect(htmlEn).toContain('Coastal');
        expect(htmlEn).not.toContain('<script');

        const htmlAr = buildMarkerPopupHtml(
            { name: 'Coastal', from_price: 14, url: '/spaces/coastal' },
            {
                fromPrice: ({ price }) => `من ${price}/ساعة`,
                details: 'التفاصيل',
            },
            'ar',
        );
        expect(htmlAr).toContain('من');
        expect(htmlAr).toContain('التفاصيل');
        expect(htmlAr).toContain('/ساعة');
    });

    it('escapes HTML in names', () => {
        expect(escapeHtml('<b>x</b>')).toBe('&lt;b&gt;x&lt;/b&gt;');
        const html = buildMarkerPopupHtml(
            { name: '<img src=x>', from_price: null, url: '#' },
            { fromPrice: () => '', details: 'Details' },
            'en',
        );
        expect(html).not.toContain('<img');
        expect(html).toContain('&lt;img');
    });
});

describe('fitMapToMarkers', () => {
    it('centers a single marker at a sensible zoom (≤15)', () => {
        const calls = [];
        const map = {
            setView: (...args) => calls.push(['setView', ...args]),
            fitBounds: (...args) => calls.push(['fitBounds', ...args]),
        };
        fitMapToMarkers(map, [[31.5, 34.4]]);
        expect(calls[0][0]).toBe('setView');
        expect(calls[0][2]).toBeLessThanOrEqual(15);
        expect(calls[0][2]).toBeGreaterThanOrEqual(12);
    });

    it('fitBounds multiple markers with maxZoom 15', () => {
        const calls = [];
        const map = {
            setView: (...args) => calls.push(['setView', ...args]),
            fitBounds: (...args) => calls.push(['fitBounds', ...args]),
        };
        fitMapToMarkers(map, [[31.5, 34.4], [25.0, 55.1]]);
        expect(calls[0][0]).toBe('fitBounds');
        expect(calls[0][2].maxZoom).toBe(15);
        expect(calls[0][2].padding).toEqual([40, 40]);
    });

    it('no-ops without markers', () => {
        const map = { setView: () => {}, fitBounds: () => {} };
        expect(() => fitMapToMarkers(map, [])).not.toThrow();
    });
});

describe('mapNumberLocale', () => {
    it('maps ar* to ar', () => {
        expect(mapNumberLocale('ar')).toBe('ar');
        expect(mapNumberLocale('en')).toBe('en-US');
    });
});
