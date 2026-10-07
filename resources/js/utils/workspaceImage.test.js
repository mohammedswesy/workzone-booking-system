import { describe, expect, it } from 'vitest';
import { workspaceCoverUrl } from './workspaceImage';

describe('workspaceCoverUrl', () => {
    it('prefers card_url then url for primary images', () => {
        const url = workspaceCoverUrl({
            images: [
                { is_primary: true, card_url: '/storage/venues/derivatives/card/a.webp', url: '/storage/venues/a.jpg' },
            ],
        });
        expect(url).toBe('/storage/venues/derivatives/card/a.webp');
    });

    it('returns null when no image so UI can show a fallback', () => {
        expect(workspaceCoverUrl({})).toBeNull();
        expect(workspaceCoverUrl({ images: [] })).toBeNull();
    });

    it('rejects third-party absolute URLs', () => {
        expect(workspaceCoverUrl({ cover_image_url: 'https://evil.example/x.jpg' })).toBeNull();
    });
});
