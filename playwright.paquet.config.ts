import { defineConfig, devices } from '@playwright/test'

// Essai du paquet (pnpm essai:paquet, scripts/essai-paquet.mjs) : le zip décompressé dans un conteneur jetable qui
// imite Hostinger, servi en HTTPS (certificat auto-signé) sur https://127.0.0.1:8096/chores/. Un seul scénario,
// dans l'ordre : il installe, parcourt, vérifie, sauvegarde puis restaure la même base.
export default defineConfig({
  testDir: 'tests/paquet',
  testMatch: '**/*.spec.ts',
  fullyParallel: false,
  workers: 1,
  timeout: 600_000,
  expect: { timeout: 15_000 },
  forbidOnly: true,
  retries: 0,
  reporter: [['list']],
  outputDir: 'test-results/paquet',
  use: {
    baseURL: 'https://127.0.0.1:8096',
    ignoreHTTPSErrors: true,
    locale: 'fr-FR',
    timezoneId: 'Europe/Paris',
    trace: 'off',
  },
  projects: [
    {
      name: 'paquet',
      use: { ...devices['Pixel 7'], viewport: { width: 390, height: 844 }, browserName: 'chromium', launchOptions: { args: ['--lang=fr-FR'] } },
    },
  ],
})
