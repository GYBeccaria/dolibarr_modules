// @ts-check
const { defineConfig } = require('@playwright/test');

const BASE_URL = process.env.BASE_URL || 'http://localhost:9100/custom/b2border/public/index.php';

module.exports = defineConfig({
  testDir: './e2e',
  timeout: 30000,
  retries: 1,
  reporter: [['list'], ['html', { outputFolder: 'playwright-report', open: 'never' }]],

  use: {
    baseURL: BASE_URL,
    headless: true,
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    launchOptions: {
      args: ['--no-sandbox', '--disable-dev-shm-usage'],
    },
  },
});
