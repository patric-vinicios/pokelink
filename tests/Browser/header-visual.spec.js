import { expect, test } from '@playwright/test';

async function login(page) {
    await page.goto('/');

    if (page.url().includes('/login')) {
        await page.locator('input[type="email"]').fill('admin@pokelink.test');
        await page.locator('input[type="password"]').fill('password');
        await page.locator('button[type="submit"]').click();
        await page.waitForURL('**/');
    }
}

test('header and sidebar follow the supplied desktop reference', async ({ page }, testInfo) => {
    await login(page);
    await expect(page.locator('.pokelink-sidebar')).toBeVisible();
    await page.evaluate(() => document.fonts.ready);
    await expect.poll(() => page.evaluate(() => [...document.images]
        .filter((image) => image.complete && image.naturalWidth === 0)
        .map((image) => image.src))).toEqual([]);

    const metrics = await page.evaluate(() => {
        const rect = (selector) => {
            const bounds = document.querySelector(selector).getBoundingClientRect();

            return {
                x: bounds.x,
                y: bounds.y,
                width: bounds.width,
                height: bounds.height,
                right: bounds.right,
            };
        };

        const profileText = document.querySelector('.pokelink-profile-trigger .min-w-0');
        const arrow = document.querySelector('.pokelink-profile-trigger > svg');
        const textBounds = profileText.getBoundingClientRect();
        const arrowBounds = arrow.getBoundingClientRect();

        return {
            sidebar: rect('.pokelink-sidebar'),
            logo: rect('.pokelink-sidebar-logo'),
            topbar: rect('.pokelink-topbar'),
            hero: rect('.catalog-hero'),
            welcome: rect('.catalog-welcome'),
            topball: rect('.pokelink-topball'),
            arrowGap: arrowBounds.x - textBounds.right,
            avatarCount: document.querySelectorAll('.pokelink-avatar').length,
            topbarBackground: getComputedStyle(document.querySelector('.pokelink-topbar')).backgroundImage,
        };
    });

    console.log(JSON.stringify(metrics, null, 2));

    expect(metrics.sidebar.width).toBeGreaterThanOrEqual(127);
    expect(metrics.sidebar.width).toBeLessThanOrEqual(129);
    expect(metrics.logo.width / metrics.sidebar.width).toBeCloseTo(0.625, 2);
    expect(metrics.logo.x + (metrics.logo.width / 2)).toBeCloseTo(metrics.sidebar.width / 2, 0);
    expect(metrics.logo.y).toBeGreaterThanOrEqual(25);
    expect(metrics.logo.y).toBeLessThanOrEqual(29);
    expect(metrics.topbar.height).toBeGreaterThanOrEqual(64);
    expect(metrics.topbar.height).toBeLessThanOrEqual(66);
    expect(metrics.hero.x).toBeGreaterThanOrEqual(127);
    expect(metrics.hero.x).toBeLessThanOrEqual(129);
    expect(metrics.hero.y).toBeGreaterThanOrEqual(29);
    expect(metrics.hero.y).toBeLessThanOrEqual(32);
    expect(metrics.welcome.x).toBeGreaterThanOrEqual(194);
    expect(metrics.welcome.x).toBeLessThanOrEqual(197);
    expect(metrics.topball.x).toBeGreaterThanOrEqual(696);
    expect(metrics.topball.x).toBeLessThanOrEqual(701);
    expect(metrics.arrowGap).toBeGreaterThanOrEqual(5);
    expect(metrics.arrowGap).toBeLessThanOrEqual(9);
    expect(metrics.avatarCount).toBe(0);
    expect(metrics.topbarBackground).not.toContain('14px 14px');

    const logoPaintBounds = await page.locator('.pokelink-sidebar-logo img').evaluate(async (image) => {
        await image.decode();

        const canvas = document.createElement('canvas');
        canvas.width = image.naturalWidth;
        canvas.height = image.naturalHeight;

        const context = canvas.getContext('2d');
        context.drawImage(image, 0, 0);

        const pixels = context.getImageData(0, 0, canvas.width, canvas.height).data;
        let firstPaintedRow = canvas.height;

        for (let y = 0; y < canvas.height; y += 1) {
            for (let x = 0; x < canvas.width; x += 1) {
                if (pixels[((y * canvas.width) + x) * 4 + 3] > 0) {
                    firstPaintedRow = y;
                    return { firstPaintedRow, naturalHeight: image.naturalHeight, naturalWidth: image.naturalWidth };
                }
            }
        }

        return { firstPaintedRow, naturalHeight: image.naturalHeight, naturalWidth: image.naturalWidth };
    });

    expect(logoPaintBounds.firstPaintedRow).toBeGreaterThan(0);

    await page.locator('.pokelink-profile-trigger').click();
    const profileDropdown = page.locator('.pokelink-desktop-profile .absolute.z-50');
    await expect(profileDropdown).toBeVisible();

    const dropdownVisibility = await profileDropdown.evaluate((dropdown) => {
        const bounds = dropdown.getBoundingClientRect();
        const topbar = document.querySelector('.pokelink-topbar').getBoundingClientRect();
        const visibleElement = document.elementFromPoint(
            bounds.left + (bounds.width / 2),
            Math.min(bounds.bottom - 2, window.innerHeight - 2),
        );

        return {
            extendsBelowTopbar: bounds.bottom > topbar.bottom,
            visibleAboveContent: Boolean(visibleElement?.closest('.pokelink-desktop-profile')),
        };
    });

    expect(dropdownVisibility.extendsBelowTopbar).toBe(true);
    expect(dropdownVisibility.visibleAboveContent).toBe(true);

    await page.screenshot({
        path: testInfo.outputPath('header-dropdown-899x679.png'),
        animations: 'disabled',
    });

    await page.locator('.pokelink-profile-trigger').click();
    await expect(profileDropdown).toBeHidden();

    await page.screenshot({
        path: testInfo.outputPath('header-899x679.png'),
        animations: 'disabled',
    });

    await page.locator('.pokelink-stage').evaluate((stage) => stage.scrollTo({ top: 500, behavior: 'instant' }));
    await expect.poll(() => page.locator('.pokelink-stage').evaluate((stage) => stage.scrollTop)).toBeGreaterThan(400);

    const scrolled = await page.evaluate(() => {
        const topbar = document.querySelector('.pokelink-topbar').getBoundingClientRect();
        const stage = document.querySelector('.pokelink-stage').getBoundingClientRect();
        const visibleAtHeaderBottom = document.elementFromPoint(
            topbar.x + (topbar.width * 0.9),
            topbar.bottom - 1,
        );

        return {
            topbarTop: topbar.top,
            topbarBottom: topbar.bottom,
            stageTop: stage.top,
            stageScrollTop: document.querySelector('.pokelink-stage').scrollTop,
            windowScrollY: window.scrollY,
            topbarPosition: getComputedStyle(document.querySelector('.pokelink-topbar')).position,
            topbarClipPath: getComputedStyle(document.querySelector('.pokelink-topbar')).clipPath,
            topbarMaskClipPath: getComputedStyle(document.querySelector('.pokelink-topbar'), '::before').clipPath,
            stageClipPath: getComputedStyle(document.querySelector('.pokelink-stage')).clipPath,
            headerOwnsBottomEdge: Boolean(visibleAtHeaderBottom?.closest('.pokelink-topbar')),
        };
    });

    expect(scrolled.topbarPosition).toBe('fixed');
    expect(scrolled.topbarTop).toBe(0);
    expect(scrolled.topbarBottom).toBeGreaterThanOrEqual(64);
    expect(scrolled.stageTop).toBeLessThan(scrolled.topbarBottom);
    expect(scrolled.stageScrollTop).toBeGreaterThan(400);
    expect(scrolled.windowScrollY).toBe(0);
    expect(scrolled.topbarClipPath).toBe('none');
    expect(scrolled.topbarMaskClipPath).not.toBe('none');
    expect(scrolled.stageClipPath).toBe('none');
    expect(scrolled.headerOwnsBottomEdge).toBe(true);

    await page.screenshot({
        path: testInfo.outputPath('header-after-scroll-899x679.png'),
        animations: 'disabled',
    });

    await page.locator('.pokelink-stage').evaluate((stage) => stage.scrollTo({ top: 0, behavior: 'instant' }));
    await page.setViewportSize({ width: 1444, height: 768 });

    const wide = await page.evaluate(() => {
        const sidebar = document.querySelector('.pokelink-sidebar').getBoundingClientRect();
        const topbar = document.querySelector('.pokelink-topbar').getBoundingClientRect();
        const hero = document.querySelector('.catalog-hero').getBoundingClientRect();

        return {
            sidebarWidthRatio: sidebar.width / window.innerWidth,
            heroTopRatio: hero.top / window.innerHeight,
            topbarHeightRatio: topbar.height / window.innerHeight,
            heroStartsAtSidebar: Math.abs(hero.left - sidebar.right) < 1,
        };
    });

    expect(wide.sidebarWidthRatio).toBeCloseTo(0.1425, 3);
    expect(wide.heroTopRatio).toBeCloseTo(0.0457, 3);
    expect(wide.topbarHeightRatio).toBeCloseTo(0.0957, 3);
    expect(wide.heroStartsAtSidebar).toBe(true);

    await page.screenshot({
        path: testInfo.outputPath('header-1444x768.png'),
        animations: 'disabled',
    });
});
