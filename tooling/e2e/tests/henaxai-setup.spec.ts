import { test, expect, Page } from '@playwright/test';

// Credenziali dell'istanza dev usa-e-getta (TOOLING.md §6).
const ADMIN = process.env.HENAXAI_ADMIN || 'admin';
const PASS = process.env.HENAXAI_PASS || 'admindev';
// Il flusso save+validate fa POST di form: su Dolibarr l'automazione e' bloccata dal
// token CSRF (vedi TOOLING.md §9). Abilitabile solo su istanza dev con CSRF rilassato.
const FULL = process.env.HENAXAI_E2E_FULL === '1';

async function login(page: Page) {
  await page.goto('/index.php?mainmenu=home');
  const userField = page.locator('input[name="username"]');
  if (await userField.count()) {
    await userField.fill(ADMIN);
    await page.fill('input[name="password"]', PASS);
    await page.locator('input[type="submit"], button[type="submit"]').first().click();
    await page.waitForLoadState('networkidle');
  }
}

test('henax-ai setup: la UI si renderizza con tutti i provider', async ({ page }) => {
  await login(page);
  await page.goto('/custom/henax-ai/admin/setup.php');
  await expect(page.locator('body')).toContainText('Provider AI');
  for (const label of ['OpenAI', 'Anthropic (Claude)', 'Google Gemini', 'OpenRouter',
                       'Qwen', 'GLM', 'Mistral', 'Groq', 'DeepSeek', 'xAI', 'Ollama', 'AnythingLLM']) {
    await expect(page.locator('body')).toContainText(label);
  }
  await expect(page.locator('input[type="submit"][value="Salva"]')).toBeVisible();
  await expect(page.locator('input[type="submit"][value="Valida i provider con API key"]')).toBeVisible();
  await expect(page.locator('input[name="key_OPENROUTER"]')).toBeVisible();
});

// Flusso completo POST (save -> validate -> matrice). Richiede CSRF rilassato sul dev
// (HENAXAI_E2E_FULL=1). Vedi TOOLING.md §9 per il razionale e la procedura.
test('henax-ai setup: salva key e valida la matrice', async ({ page }) => {
  test.skip(!FULL, 'POST di form bloccato dal CSRF Dolibarr in automazione (set HENAXAI_E2E_FULL=1 su dev con CSRF rilassato)');
  await login(page);
  await page.goto('/custom/henax-ai/admin/setup.php');
  await page.fill('input[name="key_OPENROUTER"]', 'dummy-e2e');
  await page.check('input[name="active_provider"][value="openrouter"]');
  await page.locator('input[type="submit"][value="Salva"]').click();
  await page.waitForLoadState('networkidle');
  await page.locator('input[type="submit"][value="Valida i provider con API key"]').click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText('Esito validazione');
  await expect(page.locator('table', { hasText: 'Provider' }).last()).toContainText('OpenRouter');
});
