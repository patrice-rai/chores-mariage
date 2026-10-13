import { execFileSync } from 'node:child_process'
import AxeBuilder from '@axe-core/playwright'
import { test as base, expect, type Page } from '@playwright/test'

// Outils communs des tests de bout en bout.
// - `test` bloque toute requête qui sortirait de 127.0.0.1:8095 et fait échouer le test qui l'a tentée
//   (preuve que rien ne sort du site) ;
// - `preparer()` crée un lien neuf et remet le débit à zéro par scripts/e2e-outil.php (refusé hors ENV = dev) ;
//   le lien et la clé d'administration restent dans la mémoire du test, jamais affichés ;
// - `verifierAxe()` : aucune violation WCAG 2.2 A/AA.

export const ORIGINE = 'http://127.0.0.1:8095'

type Outils = { requetesExternes: string[] }

export const test = base.extend<Outils>({
  requetesExternes: [
    async ({ context }, utiliser) => {
      const externes: string[] = []
      await context.route('**/*', (route) => {
        const url = route.request().url()
        if (url.startsWith(ORIGINE + '/') || url === ORIGINE) return route.continue()
        if (url.startsWith('data:') || url.startsWith('blob:')) return route.continue()
        externes.push(url)
        return route.abort('blockedbyclient')
      })
      await utiliser(externes)
      expect(externes, 'requêtes hors de 127.0.0.1:8095').toEqual([])
    },
    { auto: true },
  ],
})

export { expect }

export type Preparation = { lien: string; cle: string }

export function preparer(): Preparation {
  const sortie = execFileSync('docker', ['compose', 'exec', '-T', 'web', 'php', 'scripts/e2e-outil.php', 'preparer'], {
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  })
  const donnees = JSON.parse(sortie) as Preparation
  if (!/^\/e\/[A-Za-z0-9_-]{43}$/.test(donnees.lien)) throw new Error('Lien de test mal formé.')
  return donnees
}

export async function entrer(page: Page, preparation: Preparation): Promise<void> {
  await page.goto(preparation.lien)
  await expect(page).toHaveURL(ORIGINE + '/')
}

export async function choisir(page: Page, prenom: string): Promise<void> {
  await page.getByRole('button', { name: prenom, exact: true }).click()
  await expect(page).toHaveURL(ORIGINE + '/moi')
}

export async function verifierAxe(page: Page): Promise<void> {
  // Feuilles et polices chargées : sans elles, axe mesurerait une page sans mise en forme.
  await page.waitForLoadState('load')
  await page.evaluate(() => document.fonts.ready.then(() => undefined))
  const resultat = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
    .analyze()
  const resume = resultat.violations.map((v) => `${v.id} (${v.impact}) : ${v.help} — ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`)
  expect(resume, `violations axe sur ${page.url()}`).toEqual([])
}

/** axe dans les deux thèmes (clair puis sombre), puis retour au clair. */
export async function verifierAxeDeuxThemes(page: Page): Promise<void> {
  for (const schema of ['light', 'dark'] as const) {
    await page.emulateMedia({ colorScheme: schema })
    await verifierAxe(page)
  }
  await page.emulateMedia({ colorScheme: 'light' })
}

/** Ouvre l'administration sur ce navigateur (cookie posé par la clé ; jamais affichée). */
export async function ouvrirAdmin(page: Page, preparation: Preparation): Promise<void> {
  await page.goto(preparation.cle)
  await expect(page).toHaveURL(ORIGINE + '/admin')
}

/** La vidéo « Démo » synthétique (canvas + MediaRecorder, 20 s, scripts/video-demo.mjs), lue dans le conteneur. */
export function videoDemo(): Buffer {
  return execFileSync('docker', ['compose', 'exec', '-T', 'web', 'cat', 'stockage/medias/demo.webm'], {
    stdio: ['ignore', 'pipe', 'pipe'],
    maxBuffer: 64 * 1024 * 1024,
  })
}

/** En administration : pose « Section A » (0:00 → 0:08) et « Section B » (0:08 → 0:16) sur une chanson. */
export async function poserSections(page: Page, slug: string): Promise<void> {
  for (const [nom, debut, fin] of [
    ['Section A', '0:00', '0:08'],
    ['Section B', '0:08', '0:16'],
  ]) {
    await page.goto(`/admin/chansons/${slug}/sections`)
    const formulaire = page.locator('section[aria-labelledby="nouvelle-section"]')
    await formulaire.getByLabel('Nom').fill(nom)
    await formulaire.getByLabel('Début').fill(debut)
    await formulaire.getByLabel('Fin', { exact: true }).fill(fin)
    await formulaire.getByLabel('Comptes de 8').fill('2')
    await formulaire.getByRole('button', { name: 'Ajouter la section' }).click()
    await expect(page.getByText('Section ajoutée.')).toBeVisible()
  }
}

/** En administration : dépose la vidéo « Démo » comme vidéo de référence d'une chanson. */
export async function deposerReference(page: Page, slug: string): Promise<void> {
  await page.goto(`/admin/chansons/${slug}`)
  await page.locator('#reference-video').setInputFiles({ name: 'demo.webm', mimeType: 'video/webm', buffer: videoDemo() })
  await page.getByRole('button', { name: 'Déposer la vidéo' }).click()
  await expect(page.getByRole('status').filter({ hasText: 'Vidéo de référence déposée' })).toBeVisible()
}
