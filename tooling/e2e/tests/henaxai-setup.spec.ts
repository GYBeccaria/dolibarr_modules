import { test, expect, Page } from '@playwright/test';

// Credenziali dell'istanza dev usa-e-getta (TOOLING.md §6).
const ADMIN = process.env.HENAXAI_ADMIN || 'admin';
const PASS = process.env.HENAXAI_PASS || 'admindev';

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

// Flusso completo POST: salva una key, valida, verifica la matrice. Il form POST porta il
// token CSRF (newToken()) che Playwright invia con la submit: funziona senza rilassare nulla.
test('henax-ai setup: salva key e valida la matrice', async ({ page }) => {
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
