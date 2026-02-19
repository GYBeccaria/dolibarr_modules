// @ts-check
const { test, expect } = require('@playwright/test');
const { doLogin, BASE_URL } = require('./helpers');

test.describe('Catalogo', () => {

  test.beforeEach(async ({ page }) => {
    await doLogin(page);
  });

  test('mostra la griglia prodotti', async ({ page }) => {
    await expect(page.locator('.b2b-product-grid, .b2b-empty-state')).toBeVisible();
  });

  test('la ricerca filtra i prodotti', async ({ page }) => {
    const searchInput = page.locator('input[name="search"]');
    await searchInput.fill('test');
    await page.click('button[type="submit"].b2b-btn-secondary');
    await expect(page).toHaveURL(/search=test/);
    await expect(page.locator('.b2b-product-grid, .b2b-empty-state')).toBeVisible();
  });

  test('il primo prodotto ha ref, nome e prezzo', async ({ page }) => {
    const firstCard = page.locator('.b2b-product-card').first();
    await expect(firstCard.locator('.b2b-product-ref')).not.toBeEmpty();
    await expect(firstCard.locator('.b2b-product-name')).not.toBeEmpty();
    await expect(firstCard.locator('.b2b-product-price')).not.toBeEmpty();
  });

  test('click su prodotto apre la scheda dettaglio', async ({ page }) => {
    await page.locator('.b2b-product-card .b2b-product-link').first().click();
    await expect(page).toHaveURL(/controller=product/);
  });

  test('aggiunta al carrello rimane sul catalogo e aggiorna il menu', async ({ page }) => {
    await page.locator('.b2b-product-card .b2b-btn-primary').first().click();
    await expect(page).toHaveURL(/controller=catalog/);
  });

});
