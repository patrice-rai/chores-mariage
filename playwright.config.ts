import { defineConfig, devices } from '@playwright/test'

// Tests de bout en bout contre le site Docker (http://127.0.0.1:8095 ; `docker compose up -d --build` d'abord).
// Deux projets : téléphone 390 × 844 et bureau 1 280 × 900, tous deux sous Chromium.
// Un seul travailleur : chaque test crée un nouveau lien secret, qui invalide les précédents.
// Toute requête hors de 127.0.0.1:8095 fait échouer le test (tests/e2e/outils.ts).
export default defineConfig({
  testDir: 'tests/e2e',
  testMatch: '**/*.spec.ts',
  globalSetup: './tests/e2e/preparation-globale.ts',
  globalTeardown: './tests/e2e/fin-globale.ts',
  fullyParallel: false,
  workers: 1,
  timeout: 120_000,
  expect: { timeout: 10_000 },
  forbidOnly: true,
  retries: 0,
  reporter: [['list']],
  outputDir: 'test-results',
  use: {
    baseURL: 'http://127.0.0.1:8095',
    locale: 'fr-FR',
    timezoneId: 'Europe/Paris',
    // Pas de trace : elles garderaient les liens et la clé de test.
    trace: 'off',
  },
  // --lang=fr-FR : interface de Chromium en français (bouton du choix de fichier sur les captures), comme sur les
  // téléphones des cousins.
  projects: [
    {
      name: 'mobile',
      use: { ...devices['Pixel 7'], viewport: { width: 390, height: 844 }, browserName: 'chromium', launchOptions: { args: ['--lang=fr-FR'] } },
    },
    {
      name: 'bureau',
      use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 900 }, launchOptions: { args: ['--lang=fr-FR'] } },
    },
  ],
})
