/**
 * Helper condiviso: esegue il login sul portale B2B.
 * Modifica LOGIN e PASSWORD con le credenziali di un account B2B reale nel DB.
 */
const LOGIN    = process.env.B2B_LOGIN    || 'tuo_utente';
const PASSWORD = process.env.B2B_PASSWORD || 'tua_password';

const BASE_URL = process.env.BASE_URL || 'http://localhost:9100/custom/b2border/public/index.php';

/**
 * @param {import('@playwright/test').Page} page
 */
async function doLogin(page) {
  await page.goto(BASE_URL + '?controller=login');
  await page.fill('#login',    LOGIN);
  await page.fill('#password', PASSWORD);
  await page.click('button[type="submit"].b2b-btn-primary');

  // Aspetta navigazione (max 10s), poi diagnostica se non siamo al catalogo
  try {
    await page.waitForURL(/controller=catalog/, { timeout: 10000 });
  } catch (_) {
    const currentUrl = page.url();
    const errors = await page.locator('.b2b-msg-error').allTextContents().catch(() => []);
    const bodySnippet = (await page.locator('body').textContent().catch(() => '')).substring(0, 400).replace(/\s+/g, ' ');
    throw new Error(
      `Login non riuscito.\n` +
      `URL attuale: ${currentUrl}\n` +
      `Errori pagina: ${errors.join(' | ') || '(nessuno)'}\n` +
      `Corpo pagina: ${bodySnippet}`
    );
  }
}

module.exports = { doLogin, BASE_URL, LOGIN };
