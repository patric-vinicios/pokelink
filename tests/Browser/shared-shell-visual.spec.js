import { expect, test } from '@playwright/test';

const destinations = [
    { name: 'inicio', path: '/', activeNav: 'home' },
    { name: 'favoritos', path: '/favoritos', activeNav: 'favorites' },
    { name: 'chat', path: '/chat', activeNav: 'chat' },
    { name: 'perfil', path: '/perfil', activeNav: 'profile' },
];

async function login(page) {
    await page.goto('/');

    if (page.url().includes('/login')) {
        await page.locator('input[type="email"]').fill('admin@pokelink.test');
        await page.locator('input[type="password"]').fill(process.env.POKELINK_BROWSER_PASSWORD ?? 'password');
        await page.locator('button[type="submit"]').click();
        await page.waitForURL('**/');
    }
}

async function shellGeometry(page) {
    return page.evaluate(() => {
        const bounds = (selector) => {
            const rect = document.querySelector(selector).getBoundingClientRect();

            return {
                x: rect.x,
                y: rect.y,
                width: rect.width,
                height: rect.height,
                right: rect.right,
            };
        };

        const stage = document.querySelector('.pokelink-stage');
        const topbar = document.querySelector('.pokelink-topbar');

        return {
            sidebar: bounds('.pokelink-sidebar'),
            topbar: bounds('.pokelink-topbar'),
            stage: bounds('.pokelink-stage'),
            stageBorderRadius: getComputedStyle(stage).borderTopLeftRadius,
            topbarClipPath: getComputedStyle(topbar).clipPath,
            topbarMaskClipPath: getComputedStyle(topbar, '::before').clipPath,
        };
    });
}

test('desktop destinations reuse the exact same navigation shell', async ({ page }, testInfo) => {
    await login(page);
    await page.evaluate(() => document.fonts.ready);

    let referenceGeometry;

    for (const destination of destinations) {
        await page.goto(destination.path);
        await expect(page.locator('.pokelink-sidebar')).toBeVisible();
        await expect(page.locator('.pokelink-topbar')).toBeVisible();
        await expect(page.locator('.pokelink-stage')).toBeVisible();
        await expect(page.locator(`.pokelink-sidebar-link[data-nav="${destination.activeNav}"]`))
            .toHaveClass(/border-indigo-400/);

        const geometry = await shellGeometry(page);

        if (!referenceGeometry) {
            referenceGeometry = geometry;
        } else {
            expect(geometry).toEqual(referenceGeometry);
        }

        expect(geometry.stage.y).toBeLessThan(geometry.topbar.height);
        expect(geometry.stage.x).toBeCloseTo(geometry.sidebar.right, 0);
        expect(geometry.stageBorderRadius).not.toBe('0px');
        expect(geometry.topbarClipPath).toBe('none');
        expect(geometry.topbarMaskClipPath).not.toBe('none');

        await page.screenshot({
            path: testInfo.outputPath(`shared-shell-${destination.name}.png`),
            animations: 'disabled',
        });
    }
});

test('mobile destinations reuse the same collapsible navigation', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page);

    for (const destination of destinations) {
        await page.goto(destination.path);
        await page.locator('.pokelink-menu-button').click();
        await expect(page.locator('#mobile-navigation')).toBeVisible();
        await expect(page.locator('#mobile-navigation .pokelink-mobile-link')).toHaveCount(4);
        await expect(page.locator(`#mobile-navigation .pokelink-mobile-link[data-nav="${destination.activeNav}"]`))
            .toHaveClass(/border-indigo-400/);
    }
});
