// @ts-check
const { test, expect } = require('@playwright/test');
const { doLogin, BASE_URL } = require('./helpers');

test.describe('Login', () => {

  test('pagina login contiene il form', async ({ page }) => {
    await page.goto(BASE_URL + '?controller=login');
    await expect(page.locator('form.b2b-login-form')).toBeVisible();
    await expect(page.locator('#login')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.locator('button[type="submit"].b2b-btn-primary')).toBeVisible();
  });

  test('credenziali errate mostrano errore', async ({ page }) => {
    await page.goto(BASE_URL + '?controller=login');
    await page.fill('#login',    'utente_inesistente');
    await page.fill('#password', 'password_sbagliata');
    await page.click('button[type="submit"].b2b-btn-primary');
    // rimane sulla pagina di login
    await expect(page).toHaveURL(/controller=login/);
  });

  test('credenziali corrette redirigono al catalogo', async ({ page }) => {
    await doLogin(page);
    await expect(page).toHaveURL(/controller=catalog/);
    await expect(page.locator('.b2b-catalog-header')).toBeVisible();
  });

  test('utente autenticato viene rediretto al catalogo se torna sul login', async ({ page }) => {
    await doLogin(page);
    await page.goto(BASE_URL + '?controller=login');
    // deve essere già loggato: il catalogo compare
    await expect(page.locator('.b2b-catalog-header')).toBeVisible();
  });

});
