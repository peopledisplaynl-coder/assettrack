// End-to-end tests against the real PHP app + MariaDB with the demo school.
// Locally: the global setup starts tools/dev/stack.sh (Linux/macOS/WSL).
// Point AT_BASE_URL at another running instance to skip that.
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: 'tests/e2e',
  workers: 1,
  fullyParallel: false,
  timeout: 30_000,
  reporter: [['list']],
  globalSetup: './tests/e2e/global-setup.js',
  use: {
    baseURL: process.env.AT_BASE_URL || 'http://127.0.0.1:8080',
    locale: 'nl-NL',
    trace: 'retain-on-failure',
  },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'] }, testIgnore: /mobile\.spec\.js/ },
    { name: 'phone', use: { ...devices['Pixel 7'] }, testMatch: /mobile\.spec\.js/ },
  ],
});
