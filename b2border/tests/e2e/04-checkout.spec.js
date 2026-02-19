// @ts-check
const { test, expect } = require('@playwright/test');
const { doLogin, BASE_URL } = require('./helpers');

/**
 * Aggiunge il primo prodotto del catalogo al carrello.
 * @param {import('@playwright/test').Page} page
 */
async function addFirstProduct(page) {
  await page.goto(BASE_URL + '?controller=catalog');
  const addBtn = page.locator('.b2b-product-card .b2b-btn-primary').first();
  const hasProduct = await addBtn.isVisible();
  if (!hasProduct) return false;
  await addBtn.click();
  return true;
}

test.describe('Checkout e Conferma ordine', () => {

  test.beforeEach(async ({ page }) => {
    await doLogin(page);
  });

  test('checkout è accessibile con prodotti nel carrello', async ({ page }) => {
    const added = await addFirstProduct(page);
    if (!added) test.skip();

    await page.goto(BASE_URL + '?controller=checkout');
    await expect(page.locator('.b2b-checkout-layout')).toBeVisible();
    await expect(page.locator('.b2b-checkout-summary')).toBeVisible();
    await expect(page.locator('.b2b-checkout-details')).toBeVisible();
  });

  test('checkout mostra riepilogo prodotti', async ({ page }) => {
    const added = await addFirstProduct(page);
    if (!added) test.skip();

    await page.goto(BASE_URL + '?controller=checkout');
    await expect(page.locator('.b2b-checkout-summary .b2b-table tbody tr')).toHaveCount(
      await page.locator('.b2b-checkout-summary .b2b-table tbody tr').count()
    );
    await expect(page.locator('.b2b-cart-grand-total')).toBeVisible();
  });

  test('flusso completo: aggiunta → checkout → conferma ordine', async ({ page }) => {
    const added = await addFirstProduct(page);
    if (!added) test.skip();

    await page.goto(BASE_URL + '?controller=checkout');

    // compila campo ref cliente (opzionale)
    await page.fill('#ref_client', 'TEST-E2E-001');
    await page.fill('#note_public', 'Ordine generato da test automatico Playwright');

    // conferma — accetta il dialog JS
    page.on('dialog', d => d.accept());
    await page.click('button[type="submit"].b2b-btn-primary');

    // deve arrivare alla pagina di conferma
    await expect(page).toHaveURL(/controller=confirmation/);
    await expect(page.locator('.b2b-confirmation')).toBeVisible();
    await expect(page.locator('.b2b-confirmation-icon')).toBeVisible();
    await expect(page.locator('.b2b-confirmation-table')).toBeVisible();
  });

  test('dalla conferma si può tornare al catalogo', async ({ page }) => {
    const added = await addFirstProduct(page);
    if (!added) test.skip();

    await page.goto(BASE_URL + '?controller=checkout');
    page.on('dialog', d => d.accept());
    await page.click('button[type="submit"].b2b-btn-primary');
    await page.waitForURL(/controller=confirmation/);

    await page.click('.b2b-confirmation-actions a.b2b-btn-primary');
    await expect(page).toHaveURL(/controller=catalog/);
  });

  test('carrello è vuoto dopo la conferma ordine', async ({ page }) => {
    const added = await addFirstProduct(page);
    if (!added) test.skip();

    await page.goto(BASE_URL + '?controller=checkout');
    page.on('dialog', d => d.accept());
    await page.click('button[type="submit"].b2b-btn-primary');
    await page.waitForURL(/controller=confirmation/);

    await page.goto(BASE_URL + '?controller=cart');
    await expect(page.locator('.b2b-empty-state')).toBeVisible();
  });

});
