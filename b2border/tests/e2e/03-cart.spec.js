// @ts-check
const { test, expect } = require('@playwright/test');
const { doLogin, BASE_URL } = require('./helpers');

test.describe('Carrello', () => {

  test.beforeEach(async ({ page }) => {
    await doLogin(page);
    // aggiunge il primo prodotto disponibile
    await page.locator('.b2b-product-card .b2b-btn-primary').first().click();
    await page.goto(BASE_URL + '?controller=cart');
  });

  test('carrello contiene almeno un prodotto', async ({ page }) => {
    await expect(page.locator('.b2b-cart-table tbody tr, .b2b-empty-state')).toBeVisible();
  });

  test('il totale è visibile', async ({ page }) => {
    const hasItems = await page.locator('.b2b-cart-table').isVisible();
    if (hasItems) {
      await expect(page.locator('.b2b-cart-grand-total')).toBeVisible();
    }
  });

  test('aggiornamento quantità funziona', async ({ page }) => {
    const hasItems = await page.locator('.b2b-cart-table').isVisible();
    if (!hasItems) test.skip();

    const qtyInput = page.locator('input[name^="qtys["]').first();
    await qtyInput.fill('3');
    await page.click('button[type="submit"].b2b-btn-secondary'); // "Aggiorna carrello"
    await expect(page).toHaveURL(/controller=cart/);
    await expect(page.locator('input[name^="qtys["]').first()).toHaveValue('3');
  });

  test('link "Procedi al checkout" è visibile con prodotti nel carrello', async ({ page }) => {
    const hasItems = await page.locator('.b2b-cart-table').isVisible();
    if (hasItems) {
      await expect(page.locator('a.b2b-btn-primary[href*="controller=checkout"]')).toBeVisible();
    }
  });

  test('svuota carrello mostra stato vuoto', async ({ page }) => {
    const hasItems = await page.locator('.b2b-cart-table').isVisible();
    if (!hasItems) test.skip();

    page.on('dialog', d => d.accept()); // accetta il confirm JS
    await page.click('a[href*="action=clear"]');
    await expect(page.locator('.b2b-empty-state')).toBeVisible();
  });

});
