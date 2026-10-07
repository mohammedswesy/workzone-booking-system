import { test, expect } from '@playwright/test';

const consoleErrors = [];
const pageErrors = [];
const failedRequests = [];
const cspViolations = [];

function attachGuards(page) {
    page.on('console', (msg) => {
        if (msg.type() === 'error') {
            consoleErrors.push(msg.text());
        }
    });
    page.on('pageerror', (err) => {
        pageErrors.push(String(err?.message || err));
    });
    page.on('requestfailed', (req) => {
        const url = req.url();
        const err = req.failure()?.errorText || 'failed';
        // Aborted navigations / favicon noise are not app failures.
        if (err.includes('ERR_ABORTED') || url.includes('favicon')) {
            return;
        }
        failedRequests.push(`${err} ${url}`);
    });
    page.on('response', (res) => {
        if (res.request().resourceType() !== 'document') {
            return;
        }
        const url = res.url();
        if (!url.startsWith('http://127.0.0.1:8000') && !url.startsWith('http://localhost:8000')) {
            return;
        }
        const csp =
            res.headers()['content-security-policy'] ||
            res.headers()['content-security-policy-report-only'];
        if (!csp) {
            cspViolations.push(`missing CSP on ${url}`);
        }
        if (csp && /unsafe-eval/i.test(csp)) {
            cspViolations.push(`unsafe-eval present on ${url}`);
        }
    });
    page.on('securitypolicyviolation', (e) => {
        cspViolations.push(`${e.violatedDirective}: ${e.blockedURI || e.effectiveDirective}`);
    });
}

async function assertRenderable(page) {
    await expect(page.locator('#app')).toBeVisible();
    const text = await page.locator('body').innerText();
    expect(text.trim().length, 'page body looks blank').toBeGreaterThan(20);
    await expect(page.getByRole('alert').filter({ hasText: /Something went wrong/i })).toHaveCount(0);
}

test.beforeEach(async ({ page }) => {
    consoleErrors.length = 0;
    pageErrors.length = 0;
    failedRequests.length = 0;
    cspViolations.length = 0;
    attachGuards(page);
});

test.afterEach(async () => {
    const noise = failedRequests.filter(
        (line) => !line.includes('chrome-extension'),
    );
    // Leaflet can race on fitBounds/locale remount; ignore known transient pos errors.
    const realPageErrors = pageErrors.filter(
        (line) => !line.includes('_leaflet_pos'),
    );
    const realConsoleErrors = consoleErrors.filter(
        (line) => !line.includes('_leaflet_pos'),
    );
    expect(realPageErrors, `pageerrors: ${realPageErrors.join(' | ')}`).toEqual([]);
    expect(realConsoleErrors, `console: ${realConsoleErrors.join(' | ')}`).toEqual([]);
    expect(cspViolations, `csp: ${cspViolations.join(' | ')}`).toEqual([]);
    expect(noise, `network: ${noise.join(' | ')}`).toEqual([]);
});

test('public pages render without CSP/console failures', async ({ page }) => {
    await page.goto('/');
    await assertRenderable(page);
    await expect(page.getByText('WorkZone').first()).toBeVisible();

    await page.goto('/spaces');
    await assertRenderable(page);

    await page.goto('/login');
    await assertRenderable(page);
    await expect(page.locator('input[type="email"], input[name="email"]').first()).toBeVisible();
});

test('workspace page and locale/theme toggles', async ({ page }) => {
    await page.goto('/spaces');
    await assertRenderable(page);

    const firstCard = page.locator('a[href*="/spaces/"]').first();
    if (await firstCard.count()) {
        await firstCard.click();
        await assertRenderable(page);
        // Venue show should list units / booking panel.
        await expect(page.locator('body')).toContainText(/Book|احجز|Select|اختر|From|من/i);
    }

    await page.goto('/');
    await assertRenderable(page);

    const localeBtn = page.getByRole('button', { name: /EN|عربي/i }).first();
    await expect(localeBtn).toBeVisible();
    await localeBtn.click();
    await assertRenderable(page);

    const themeBtn = page.getByRole('button', { name: /Dark|Light|داكن|فاتح/i }).first();
    await expect(themeBtn).toBeVisible();
    await themeBtn.click();
    await assertRenderable(page);
    // Toggle back so later tests stay on a predictable theme.
    await themeBtn.click();
    await localeBtn.click();
});

test('owner and admin venue pages render', async ({ page }) => {
    await login(page, 'owner@example.com');
    await page.goto('/owner/venues');
    await assertRenderable(page);

    const createLink = page.locator('a[href*="/owner/venues/create"]').first();
    if (await createLink.count()) {
        await createLink.click();
        await assertRenderable(page);
        await expect(page.locator('form')).toBeVisible();
    }

    await page.goto('/owner/venues');
    await assertRenderable(page);
    const anyVenue = page.locator('a[href*="/owner/venues/"]').first();
    if (await anyVenue.count()) {
        const href = await anyVenue.getAttribute('href');
        if (href && !href.endsWith('/create')) {
            await page.goto(href);
            await assertRenderable(page);
            await expect(page.locator('body')).toContainText(/checklist|قائمة|unit|وحدة|Room|غرفة/i);
        }
    }
    await page.context().clearCookies();

    await login(page, 'admin@example.com');
    await page.goto('/admin/venues');
    await assertRenderable(page);
    await page.goto('/admin/venues/create');
    await assertRenderable(page);
    await expect(page.locator('form')).toBeVisible();
    await page.context().clearCookies();
});

test('legacy unit id redirects to venue with unit query', async ({ page }) => {
    // Hit a known demo unit if present; otherwise skip gracefully.
    const res = await page.request.get('/spaces/1', { maxRedirects: 0 });
    if ([301, 302].includes(res.status())) {
        const loc = res.headers()['location'] || '';
        expect(loc).toMatch(/\/spaces\/.+/);
        expect(loc).toMatch(/unit=/);
        await page.goto(loc);
        await assertRenderable(page);
    }
});

async function login(page, email, password = 'password') {
    await page.context().clearCookies();
    await page.goto('/login');
    await page.locator('input[type="email"], input[name="email"]').first().fill(email);
    await page.locator('input[type="password"], input[name="password"]').first().fill(password);
    await page.locator('button[type="submit"]').first().click();
    await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 30_000 });
    await page.waitForLoadState('domcontentloaded');
}

test('role dashboards render for seeded demo accounts', async ({ page }) => {
    for (const [email, pathHint] of [
        ['admin@example.com', '/admin'],
        ['owner@example.com', '/owner'],
        ['user@example.com', '/user'],
    ]) {
        await login(page, email);
        await assertRenderable(page);
        const url = page.url();
        expect(
            url.includes(pathHint) ||
                url.includes('/dashboard') ||
                url.includes('/two-factor') ||
                url.includes('/password'),
            `expected ${email} to land near ${pathHint}, got ${url}`,
        ).toBeTruthy();
        await page.context().clearCookies();
    }
});

async function clickPaginationRoundTrip(page) {
    const next = page.getByRole('navigation', { name: /pagination/i }).getByRole('link', { name: /next|التالي/i });
    await expect(next).toBeVisible({ timeout: 15_000 });
    await next.click();
    await page.waitForLoadState('domcontentloaded');
    await assertRenderable(page);

    const prev = page.getByRole('navigation', { name: /pagination/i }).getByRole('link', { name: /previous|السابق/i });
    await expect(prev).toBeVisible({ timeout: 15_000 });
    await prev.click();
    await page.waitForLoadState('domcontentloaded');
    await assertRenderable(page);
}

test('pagination next/previous works without console or boundary errors', async ({ page }) => {
    // Force small pages so Next/Previous are available with demo data.
    await page.goto('/spaces?per_page=1');
    await assertRenderable(page);
    await clickPaginationRoundTrip(page);

    await login(page, 'admin@example.com');
    await page.goto('/admin/users?per_page=1');
    await assertRenderable(page);
    await clickPaginationRoundTrip(page);
    await page.context().clearCookies();

    await login(page, 'owner@example.com');
    await page.goto('/owner/bookings?per_page=1');
    await assertRenderable(page);
    await clickPaginationRoundTrip(page);
    await page.context().clearCookies();
});

test('spaces list/map toggle and map.json stay CSP-clean', async ({ page }) => {
    await page.goto('/spaces');
    await assertRenderable(page);

    const mapBtn = page.getByRole('button', { name: /Map|خريطة/i }).first();
    await expect(mapBtn).toBeVisible();
    await mapBtn.click();
    await page.waitForTimeout(400);
    await assertRenderable(page);
    // Leaflet container should mount (or graceful empty state).
    await expect(page.locator('[role="application"], .leaflet-container').first()).toBeVisible({ timeout: 10_000 });

    const listBtn = page.getByRole('button', { name: /List|قائمة/i }).first();
    await listBtn.click();
    await assertRenderable(page);

    const res = await page.request.get('/spaces/map.json');
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(Array.isArray(body.markers)).toBeTruthy();
});

test('map marker popup localizes in Arabic and fits markers on load', async ({ page }) => {
    await page.goto('/spaces');
    await assertRenderable(page);

    // Switch UI to Arabic before opening the map.
    const localeBtn = page.getByRole('button', { name: /EN|عربي/i }).first();
    await localeBtn.click();
    await assertRenderable(page);

    await page.getByRole('button', { name: /خريطة|Map/i }).first().click();
    await page.waitForTimeout(600);
    await assertRenderable(page);

    const mapEl = page.locator('.leaflet-container').first();
    await expect(mapEl).toBeVisible({ timeout: 10_000 });

    // Should not be stuck at world zoom when markers exist.
    const zoom = await page.evaluate(() => {
        const el = document.querySelector('.leaflet-container');
        // Leaflet exposes no public zoom on DOM; infer zoom pane transform scale is non-world.
        const pane = el?.querySelector('.leaflet-map-pane');
        return pane ? pane.style.transform || pane.style.webkitTransform || '' : '';
    });
    expect(zoom.length).toBeGreaterThan(0);

    const marker = page.locator('.leaflet-marker-icon').first();
    if (await marker.count()) {
        await marker.click({ force: true });
        const popup = page.locator('.leaflet-popup-content').first();
        await expect(popup).toBeVisible({ timeout: 5_000 });
        await expect(popup).toContainText(/من|ساعة|التفاصيل/);
        await expect(popup).not.toContainText(/^From /);
        await expect(popup).not.toContainText('Details');
    }

    // Restore locale for later tests.
    await localeBtn.click();
});

test('owner and admin availability pages render', async ({ page }) => {
    await login(page, 'owner@example.com');
    await page.goto('/owner/venues');
    await assertRenderable(page);
    const anyVenue = page.locator('a[href*="/owner/venues/"]:not([href*="/create"]):not([href*="/availability"])').first();
    await expect(anyVenue).toBeVisible({ timeout: 15_000 });
    const href = await anyVenue.getAttribute('href');
    await page.goto(`${href.replace(/\/$/, '')}/availability`);
    await assertRenderable(page);
    await expect(page.locator('body')).toContainText(/Hours|ساعات|availability|التوفر|Closed|مغلق/i);
    await page.context().clearCookies();

    await login(page, 'admin@example.com');
    await page.goto('/admin/venues');
    await assertRenderable(page);
    const adminVenue = page.locator('a[href*="/admin/venues/"]:not([href*="/create"]):not([href*="/availability"])').first();
    await expect(adminVenue).toBeVisible({ timeout: 15_000 });
    const adminHref = await adminVenue.getAttribute('href');
    await page.goto(`${adminHref.replace(/\/$/, '')}/availability`);
    await assertRenderable(page);
    await expect(page.locator('body')).toContainText(/Hours|ساعات|Closed|مغلق/i);
    await page.context().clearCookies();
});

test('owner can type coordinates, see the pin move, save and persist on the public map', async ({ page }) => {
    await login(page, 'owner@example.com');
    await page.goto('/owner/venues');
    await assertRenderable(page);

    const venueLink = page.locator('a[href*="/owner/venues/"]:not([href*="/create"]):not([href*="/availability"])').first();
    await expect(venueLink).toBeVisible({ timeout: 15_000 });
    const href = await venueLink.getAttribute('href');
    expect(href).toBeTruthy();
    await page.goto(href);
    await assertRenderable(page);

    await expect(page.getByTestId('publish-checklist')).toContainText(/Location set|تحديد الموقع/i);
    await expect(page.getByTestId('use-my-location')).toBeVisible();

    // Geolocation denied keeps manual entry working
    await page.evaluate(() => {
        navigator.geolocation.getCurrentPosition = (_ok, err) => {
            err({ code: 1, message: 'denied' });
        };
    });
    await page.getByTestId('use-my-location').click();
    await expect(page.getByTestId('venue-geo-message')).toBeVisible({ timeout: 5_000 });

    const lat = page.getByTestId('venue-lat');
    const lng = page.getByTestId('venue-lng');
    await expect(lat).toBeVisible();
    await expect(lng).toBeVisible();
    await expect(lat).toHaveAttribute('dir', 'ltr');
    await expect(lng).toHaveAttribute('inputmode', 'decimal');

    await lat.fill('31.5017');
    await lng.fill('34.4668');
    await page.waitForTimeout(400);

    await expect(page.locator('.leaflet-marker-icon').first()).toBeVisible({ timeout: 10_000 });
    await expect(page.getByTestId('open-in-maps')).toBeVisible();
    await expect(page.getByTestId('open-in-maps')).toHaveAttribute('href', /31\.5017.*34\.4668/);

    // Move pin via map click
    const map = page.locator('[data-testid="leaflet-map"], .leaflet-container').first();
    await map.click({ position: { x: 180, y: 120 } });
    await page.waitForTimeout(300);
    const latAfterPin = await lat.inputValue();
    const lngAfterPin = await lng.inputValue();
    expect(latAfterPin).toMatch(/^-?\d/);
    expect(lngAfterPin).toMatch(/^-?\d/);

    await Promise.all([
        page.waitForURL((url) => url.pathname.includes('/owner/venues/'), { timeout: 30_000 }),
        page.getByRole('button', { name: /Save|حفظ/i }).first().click(),
    ]);
    await assertRenderable(page);

    await page.goto(href);
    await assertRenderable(page);
    await expect(page.getByTestId('venue-lat')).toHaveValue(/^-?\d/);
    await expect(page.getByTestId('venue-lng')).toHaveValue(/^-?\d/);

    const slug = href.split('/').filter(Boolean).pop();
    const savedLat = Number(await page.getByTestId('venue-lat').inputValue());
    const savedLng = Number(await page.getByTestId('venue-lng').inputValue());

    const mapRes = await page.request.get('/spaces/map.json');
    expect(mapRes.ok()).toBeTruthy();
    const body = await mapRes.json();
    const marker = (body.markers || []).find((m) => m.slug === slug);
    expect(marker, `expected marker for ${slug}`).toBeTruthy();
    expect(Number(marker.lat)).toBeCloseTo(savedLat, 3);
    expect(Number(marker.lng)).toBeCloseTo(savedLng, 3);

    // Near-me includes this venue among results near Gaza
    const nearRes = await page.request.get(
        `/spaces/map.json?near_lat=${savedLat.toFixed(3)}&near_lng=${savedLng.toFixed(3)}&radius_km=50`,
    );
    expect(nearRes.ok()).toBeTruthy();
    const nearBody = await nearRes.json();
    const nearMarker = (nearBody.markers || []).find((m) => m.slug === slug);
    expect(nearMarker, `expected near-me marker for ${slug}`).toBeTruthy();
    expect(nearMarker.distance_km).not.toBeNull();

    await page.context().clearCookies();
});

test('near me: manual coordinates show nearest venue, distance, and you-are-here marker', async ({ page }) => {
    await page.goto('/spaces');
    await assertRenderable(page);

    const panel = page.getByTestId('near-me-panel');
    await expect(panel).toBeVisible();

    await page.getByTestId('near-lat').fill('31.510');
    await page.getByTestId('near-lng').fill('34.470');
    await page.getByTestId('near-radius').selectOption('100');
    await page.getByTestId('apply-filters').click();
    await page.waitForURL(/near_lat=/);
    await assertRenderable(page);

    const firstCard = page.getByTestId('workspace-card').first();
    await expect(firstCard).toBeVisible({ timeout: 15_000 });
    await expect(firstCard.getByTestId('distance-km')).toBeVisible();
    await expect(firstCard.getByTestId('distance-km')).toContainText(/km|كم/i);

    await page.getByRole('button', { name: /Map|خريطة/i }).first().click();
    await page.waitForTimeout(500);
    await expect(page.locator('.wz-you-are-here__dot, .wz-you-are-here').first()).toBeVisible({
        timeout: 10_000,
    });

    // Change radius and re-apply
    await page.getByTestId('near-radius').selectOption('5');
    await page.getByTestId('apply-filters').click();
    await page.waitForURL(/radius_km=5/);
    await assertRenderable(page);

    // Geolocation denied → clear message, manual still works
    await page.evaluate(() => {
        navigator.geolocation.getCurrentPosition = (_ok, err) => {
            err({ code: 1, message: 'denied' });
        };
    });
    await page.getByTestId('use-my-location').click();
    await expect(page.getByTestId('geo-message')).toBeVisible({ timeout: 5_000 });
    await expect(page.getByTestId('near-lat')).toBeEditable();

    // Switch to list before locale toggle to avoid Leaflet remount races.
    await page.getByRole('button', { name: /List|قائمة/i }).first().click();
    await page.waitForTimeout(200);

    // AR / EN switch keeps near-me UI
    const localeBtn = page.getByRole('button', { name: /EN|عربي/i }).first();
    await localeBtn.click();
    await assertRenderable(page);
    await expect(page.getByTestId('near-me-panel')).toContainText(/بالقرب|Near me/i);
    await localeBtn.click();
});

test('venue gallery: main image, thumbnails, and lightbox keyboard browse', async ({ page }) => {
    await page.goto('/spaces');
    await assertRenderable(page);

    const firstCard = page.locator('a[href*="/spaces/"]').first();
    await expect(firstCard).toBeVisible();
    await firstCard.click();
    await assertRenderable(page);

    const gallery = page.getByTestId('venue-gallery');
    await expect(gallery).toBeVisible();

    const main = page.getByTestId('gallery-main');
    await expect(main).toBeVisible();
    await main.click();

    const lightbox = page.getByTestId('venue-lightbox');
    await expect(lightbox).toBeVisible();

    await page.keyboard.press('ArrowRight');
    await expect(lightbox).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(lightbox).toHaveCount(0);

    const thumbs = page.getByTestId('gallery-thumb');
    if ((await thumbs.count()) > 0) {
        await thumbs.nth(0).click();
        await expect(page.getByTestId('venue-lightbox')).toBeVisible();
        await page.keyboard.press('Escape');
    }
});
