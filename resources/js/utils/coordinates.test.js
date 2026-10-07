import { describe, expect, it } from 'vitest';
import {
    looksLikeMapsUrl,
    looksSwapped,
    normalizeNumericString,
    parseCoordinatePaste,
    parseGoogleMapsUrl,
    roundCoord,
} from '@/utils/coordinates';

describe('normalizeNumericString', () => {
    it('converts Arabic-Indic digits and Arabic decimal separator', () => {
        expect(normalizeNumericString('٣١٫٥٠١٧')).toBe('31.5017');
    });

    it('converts Eastern Arabic-Indic digits', () => {
        expect(normalizeNumericString('۳۱.۵')).toBe('31.5');
    });
});

describe('parseGoogleMapsUrl', () => {
    it('parses @lat,lng', () => {
        expect(parseGoogleMapsUrl('https://www.google.com/maps/@31.5017,34.4668,15z')).toEqual({
            lat: 31.5017,
            lng: 34.4668,
        });
    });

    it('parses ?q=lat,lng', () => {
        expect(parseGoogleMapsUrl('https://www.google.com/maps?q=31.5017,34.4668')).toEqual({
            lat: 31.5017,
            lng: 34.4668,
        });
    });

    it('parses !3d!4d place markers', () => {
        expect(
            parseGoogleMapsUrl('https://www.google.com/maps/place/Foo/@0,0,17z/data=!3d31.5017!4d34.4668'),
        ).toEqual({
            lat: 31.5017,
            lng: 34.4668,
        });
    });

    it('returns null for short links without embedded coords', () => {
        expect(parseGoogleMapsUrl('https://maps.app.goo.gl/abc123')).toBeNull();
    });
});

describe('parseCoordinatePaste', () => {
    it('parses comma / semicolon / space pairs', () => {
        expect(parseCoordinatePaste('31.5017, 34.4668')).toEqual({
            kind: 'pair',
            lat: 31.5017,
            lng: 34.4668,
        });
        expect(parseCoordinatePaste('31.5017;34.4668').kind).toBe('pair');
        expect(parseCoordinatePaste('31.5017 34.4668').kind).toBe('pair');
    });

    it('parses Arabic digits and decimal comma in a pair', () => {
        expect(parseCoordinatePaste('٣١٫٥٠١٧، ٣٤٫٤٦٦٨')).toEqual({
            kind: 'pair',
            lat: 31.5017,
            lng: 34.4668,
        });
    });

    it('extracts coords from Google Maps URLs', () => {
        expect(parseCoordinatePaste('https://www.google.com/maps?q=31.5,34.4').kind).toBe('pair');
    });

    it('flags unparseable map URLs without fetching', () => {
        expect(parseCoordinatePaste('https://maps.app.goo.gl/short')).toEqual({ kind: 'url_unparsed' });
        expect(looksLikeMapsUrl('https://maps.app.goo.gl/short')).toBe(true);
    });

    it('parses a single number', () => {
        expect(parseCoordinatePaste('31.5017')).toEqual({ kind: 'single', value: 31.5017 });
    });

    it('rounds to 7 decimals', () => {
        expect(roundCoord(31.50171234567)).toBe(31.5017123);
    });
});

describe('looksSwapped', () => {
    it('detects lat out of range that would be a valid lng', () => {
        expect(looksSwapped(120, 31.5)).toBe(true);
        expect(looksSwapped(31.5, 34.4)).toBe(false);
    });
});
