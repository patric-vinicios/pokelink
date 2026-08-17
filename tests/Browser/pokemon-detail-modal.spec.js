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

test('a pokemon card opens and closes its details without navigating away', async ({ page }, testInfo) => {
    await login(page);

    const card = page.locator('.pokemon-card:not(.pokemon-card-skeleton)').first();
    const pokemonName = (await card.locator('.pokemon-card-name').textContent()).trim();
    const catalogUrl = page.url();

    await card.locator('.pokemon-card-link').click();

    const dialog = page.locator('.pokemon-detail-dialog');
    await expect(dialog).toBeVisible();
    await expect(dialog.locator('#pokemon-detail-title')).toHaveText(pokemonName, { ignoreCase: true });
    await expect(page).toHaveURL(catalogUrl);
    await expect(page.locator('.pokelink-stage')).toHaveClass(/pokelink-stage--modal-open/);
    await expect.poll(() => page.locator('.pokelink-stage').evaluate((stage) => getComputedStyle(stage).overflow)).toBe('hidden');
    await expect(dialog.locator('.pokemon-detail-loading')).toBeHidden({ timeout: 20_000 });
    await expect(dialog.locator('.pokemon-detail-tab-panel, .pokemon-detail-state--compact').first()).toBeVisible();
    await expect.poll(() => dialog.locator('.pokemon-detail-art img').evaluate((image) => image.complete && image.naturalWidth > 0)).toBe(true);

    const visualBackground = await dialog.locator('.pokemon-detail-visual').evaluate((visual) => getComputedStyle(visual).backgroundImage);
    expect(visualBackground).toContain('linear-gradient');
    const overviewBounds = await dialog.boundingBox();

    await dialog.getByRole('button', { name: 'Estatísticas' }).click();
    await expect(dialog.locator('.pokemon-detail-tab-panel--stats')).toBeVisible();
    const statsBounds = await dialog.boundingBox();
    expect(statsBounds.height).toBeCloseTo(overviewBounds.height, 0);

    await dialog.getByRole('button', { name: 'Movimentos' }).click();
    await expect(dialog.locator('.pokemon-detail-tab-panel--moves')).toBeVisible();
    const movesBounds = await dialog.boundingBox();
    expect(movesBounds.height).toBeCloseTo(overviewBounds.height, 0);

    await dialog.getByRole('button', { name: 'Visão geral' }).click();
    await expect(dialog.locator('.pokemon-detail-overview-stats')).toBeVisible();

    await page.screenshot({
        path: testInfo.outputPath('pokemon-detail-modal.png'),
        animations: 'disabled',
    });

    await dialog.getByRole('button', { name: 'Fechar detalhes' }).click();

    await expect(dialog).toBeHidden();
    await expect(page.locator('.pokelink-stage')).not.toHaveClass(/pokelink-stage--modal-open/);

    await page.getByRole('button', { name: 'Ver detalhes de Charmander' }).click();

    await expect(dialog).toBeVisible();
    await expect(dialog.locator('#pokemon-detail-title')).toHaveText('Charmander', { ignoreCase: true });
    await expect(dialog).toHaveAttribute('data-primary-type', 'fire');
    await expect(page).toHaveURL(catalogUrl);

    const fireVisualBackground = await dialog.locator('.pokemon-detail-visual').evaluate((visual) => getComputedStyle(visual).backgroundImage);
    expect(fireVisualBackground).not.toBe(visualBackground);

    await page.setViewportSize({ width: 390, height: 844 });

    const mobileBounds = await dialog.boundingBox();
    expect(mobileBounds.x).toBeGreaterThanOrEqual(15);
    expect(mobileBounds.x + mobileBounds.width).toBeLessThanOrEqual(375);
    expect(mobileBounds.height).toBeLessThanOrEqual(812);
    await expect(dialog.getByRole('button', { name: 'Fechar detalhes' })).toBeVisible();
    await expect(dialog.locator('.pokemon-detail-tabs')).toBeVisible();

    await page.screenshot({
        path: testInfo.outputPath('pokemon-detail-modal-mobile.png'),
        animations: 'disabled',
    });
});
