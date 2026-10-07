/**
 * Shared cover URL resolver for workspace cards/details.
 * Prefers cover_image_url (server accessor), then gallery/legacy fields.
 * Returns null when empty so the UI can show a neutral placeholder.
 * Rejects third-party absolute URLs so CSP img-src 'self' stays intact.
 */
export function workspaceCoverUrl(space) {
    if (!space) return null;

    const primary = space.images?.find((img) => img.is_primary) || space.images?.[0];
    const candidates = [
        space.cover_image_url,
        primary?.card_url,
        primary?.url,
        space.images?.[0]?.card_url,
        space.images?.[0]?.url,
        space.image_url,
    ];

    for (const value of candidates) {
        if (typeof value === 'string' && value.trim() !== '') {
            const url = value.trim();
            if (isAllowedWorkspaceImageUrl(url)) {
                return url;
            }
        }
    }

    return null;
}

function isAllowedWorkspaceImageUrl(url) {
    // Relative app/storage paths are always fine.
    if (url.startsWith('/') || url.startsWith('data:') || url.startsWith('blob:')) {
        return true;
    }

    // Absolute URLs only when they point at the current origin.
    try {
        const parsed = new URL(url, window.location.origin);
        return parsed.origin === window.location.origin;
    } catch {
        return false;
    }
}
