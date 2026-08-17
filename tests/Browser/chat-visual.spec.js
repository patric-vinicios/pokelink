import { expect, test } from '@playwright/test';

async function login(page) {
    await page.goto('/');

    if (page.url().includes('/login')) {
        await page.locator('input[type="email"]').fill('user@pokelink.test');
        await page.locator('input[type="password"]').fill(process.env.POKELINK_BROWSER_PASSWORD ?? 'password');
        await page.locator('button[type="submit"]').click();
        await page.waitForURL('**/');
    }
}

test('chat reuses the Pokedex hero and matches the conversation reference', async ({ page }, testInfo) => {
    await login(page);
    await page.goto('/chat');
    await page.evaluate(() => document.fonts.ready);

    await expect(page.locator('.chat-page .catalog-hero')).toBeVisible();
    await expect(page.locator('.chat-page h1')).toContainText('Converse com seus');
    await expect(page.locator('.chat-page h1')).toContainText('Pokémon favoritos');
    await expect(page.locator('.trainer-chat-panel')).toBeVisible();
    await expect(page.locator('.chat-directory-search, .chat-directory-tabs')).toHaveCount(0);

    const firstConversation = page.locator('.chat-user-row').first();
    if (await firstConversation.count()) {
        await firstConversation.click();
        await expect(page.locator('.chat-thread-header')).toBeVisible();
        await expect(page.locator('.chat-thread-actions button')).toHaveCount(3);
        await expect(page.locator('.chat-composer')).toBeVisible();
    }

    const geometry = await page.evaluate(() => {
        const panel = document.querySelector('.trainer-chat-panel').getBoundingClientRect();
        const directory = document.querySelector('.chat-directory').getBoundingClientRect();

        return {
            panelWidth: panel.width,
            directoryRatio: directory.width / panel.width,
            panelRadius: getComputedStyle(document.querySelector('.trainer-chat-panel')).borderRadius,
            pageOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        };
    });

    expect(geometry.directoryRatio).toBeGreaterThan(0.3);
    expect(geometry.directoryRatio).toBeLessThan(0.4);
    expect(geometry.panelRadius).not.toBe('0px');
    expect(geometry.pageOverflow).toBe(0);

    await page.screenshot({
        path: testInfo.outputPath('chat-reference-desktop.png'),
        animations: 'disabled',
        fullPage: false,
    });
});

test('chat remains usable on mobile', async ({ page }, testInfo) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page);
    await page.goto('/chat');

    await expect(page.locator('.chat-page h1')).toBeVisible();
    await expect(page.locator('.chat-directory-search, .chat-directory-tabs')).toHaveCount(0);

    const firstConversation = page.locator('.chat-user-row').first();
    if (await firstConversation.count()) {
        await firstConversation.click();
        await expect(page.locator('.chat-composer')).toBeVisible();
        await expect(page.locator('.chat-composer-action')).toHaveCount(2);
    }

    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBe(390);

    await page.screenshot({
        path: testInfo.outputPath('chat-reference-mobile.png'),
        animations: 'disabled',
        fullPage: false,
    });
});
