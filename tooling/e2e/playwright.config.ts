import { defineConfig } from '@playwright/test';

// Istanza Dolibarr usa-e-getta (vedi TOOLING.md §6). Gia' in esecuzione: NO webServer.
export default defineConfig({
  testDir: './tests',
  timeout: 60_000,
  expect: { timeout: 10_000 },
  use: {
    baseURL: process.env.HENAXAI_BASE_URL || 'http://localhost:9199',
    headless: true,
    ignoreHTTPSErrors: true,
    screenshot: 'only-on-failure',
  },
  reporter: [['list'], ['json', { outputFile: 'results.json' }]],
});
