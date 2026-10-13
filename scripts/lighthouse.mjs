// Lighthouse mobile en recette locale (phase 6) : `pnpm lighthouse`.
// Sept pages de la spec § 13 et du prompt de la phase, 3 passes chacune, médiane ; réglages par défaut de
// Lighthouse (mobile, Moto G Power, throttling simulé « Slow 4G », processeur ralenti 4 fois). Seuils : performance
// ≥ 90, accessibilité ≥ 95, bonnes pratiques ≥ 95. Résultats dans docs/recette/lighthouse.json (aucun cookie).
//
// Données : la base de test des e2e (scripts/e2e-outil.php : jamais la base de Patrice), préparée ici par
// Playwright comme dans les tests (« Section A », « Section B », vidéo « Démo », trois points de scène), puis effacée.
// Les cookies de test passent à Lighthouse par l'en-tête Cookie, ne sont jamais écrits ni affichés. Chrome : le
// Chromium de Playwright, profil neuf à chaque passe (dossier temporaire du système), réseau d'arrière-plan coupé.
import { execFileSync } from 'node:child_process'
import { mkdir, rm, writeFile } from 'node:fs/promises'
import { createRequire } from 'node:module'
import os from 'node:os'
import path from 'node:path'
import { pathToFileURL } from 'node:url'
import { chromium } from '@playwright/test'

const SITE = 'http://127.0.0.1:8095'
const PASSES = Number(process.env.LH_PASSES ?? 3)
const PAGES = ['/moi', '/chanson/kanimaa', '/chanson/kanimaa/repeter', '/placements/kanimaa', '/repetitions', '/videos', '/jour-j']
const SEUILS = { performance: 0.9, accessibilite: 0.95, bonnesPratiques: 0.95 }

const cheminLighthouse = createRequire(import.meta.url).resolve('lighthouse')
const { default: lighthouse } = await import(pathToFileURL(cheminLighthouse).href)
const chromeLauncher = await import(pathToFileURL(createRequire(cheminLighthouse).resolve('chrome-launcher')).href)

const outil = (commande) =>
  execFileSync('docker', ['compose', 'exec', '-T', 'web', 'php', 'scripts/e2e-outil.php', commande], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'inherit'] })

/** Base de test préparée par un vrai navigateur ; renvoie l'en-tête Cookie de Catherine (jamais affiché). */
async function preparer() {
  const { lien, cle } = JSON.parse(outil('preparer'))
  const navigateur = await chromium.launch()
  const page = await navigateur.newPage({ baseURL: SITE })
  const attendre = async (texte) => page.getByText(texte).first().waitFor({ timeout: 15_000 })
  await page.goto(lien)
  await page.goto(cle)
  for (const [nom, debut, fin] of [['Section A', '0:00', '0:08'], ['Section B', '0:08', '0:16']]) {
    await page.goto('/admin/chansons/kanimaa/sections')
    const f = page.locator('section[aria-labelledby="nouvelle-section"]')
    await f.getByLabel('Nom').fill(nom)
    await f.getByLabel('Début').fill(debut)
    await f.getByLabel('Fin', { exact: true }).fill(fin)
    await f.getByLabel('Comptes de 8').fill('2')
    await f.getByRole('button', { name: 'Ajouter la section' }).click()
    await attendre('Section ajoutée.')
  }
  const demo = execFileSync('docker', ['compose', 'exec', '-T', 'web', 'cat', 'stockage/medias/demo.webm'], { stdio: ['ignore', 'pipe', 'pipe'], maxBuffer: 64 * 1024 * 1024 })
  await page.goto('/admin/chansons/kanimaa')
  await page.locator('#reference-video').setInputFiles({ name: 'demo.webm', mimeType: 'video/webm', buffer: demo })
  await page.getByRole('button', { name: 'Déposer la vidéo' }).click()
  await attendre('Vidéo de référence déposée')
  await page.goto('/admin/chansons/kanimaa/placements')
  for (const [prenom, x, y] of [['Catherine', '50', '50'], ['Fabien', '25', '40'], ['Alex', '75', '60']]) {
    const point = page.locator('fieldset.admin__point').filter({ hasText: prenom })
    await point.getByLabel(/^x/).fill(x)
    await point.getByLabel(/^y/).fill(y)
  }
  await page.getByRole('button', { name: 'Enregistrer les placements' }).first().click()
  await attendre('Placements enregistrés')
  await page.goto('/')
  await page.getByRole('button', { name: 'Catherine', exact: true }).click()
  await page.waitForURL(SITE + '/moi')
  const cookies = (await page.context().cookies()).filter((c) => ['cm_acces', 'cm_prenom'].includes(c.name))
  await navigateur.close()
  return cookies.map((c) => `${c.name}=${c.value}`).join('; ')
}

const mediane = (valeurs) => [...valeurs].sort((a, b) => a - b)[Math.floor(valeurs.length / 2)]
const octets = (lhr, type) => lhr.audits['resource-summary']?.details?.items?.find((i) => i.resourceType === type)?.transferSize ?? 0

const profils = path.join(os.tmpdir(), `chores-lighthouse-${process.pid}`)
await mkdir(profils, { recursive: true })
let numero = 0

async function unePasse(url, cookie) {
  for (let essai = 1; ; essai++) {
    const profil = path.join(profils, String(++numero))
    await mkdir(profil, { recursive: true })
    const chrome = await chromeLauncher.launch({
      chromePath: chromium.executablePath(),
      userDataDir: profil,
      maxConnectionRetries: 200,
      chromeFlags: ['--headless=new', '--no-first-run', '--no-default-browser-check', '--disable-background-networking', '--disable-component-update', '--disable-sync', '--disable-features=RenderDocument,BackForwardCache', '--disable-site-isolation-trials', '--lang=fr-FR'],
    })
    try {
      const { lhr } = await lighthouse(
        url,
        { port: chrome.port, output: 'json', logLevel: 'error', onlyCategories: ['performance', 'accessibility', 'best-practices'] },
        { extends: 'lighthouse:default', settings: { extraHeaders: { Cookie: cookie }, locale: 'fr' } },
      )
      if ((lhr.runtimeError?.code === 'NO_NAVSTART' || /ERR_ABORTED/.test(lhr.runtimeError?.message ?? '')) && essai < 6) continue
      if (lhr.runtimeError) throw new Error(`${url} : ${lhr.runtimeError.code}`)
      return lhr
    } finally {
      try {
        chrome.kill()
      } catch {
        // déjà fermé
      }
      await new Promise((ok) => setTimeout(ok, 500))
    }
  }
}

execFileSync('node', ['scripts/video-demo.mjs', '--si-absente'], { stdio: 'inherit' })
outil('debut')
const resultats = []
try {
  const cookie = await preparer()
  for (const chemin of PAGES) {
    const mesures = []
    for (let passe = 0; passe < PASSES; passe++) {
      const lhr = await unePasse(SITE + chemin, cookie)
      if (lhr.finalDisplayedUrl !== SITE + chemin) throw new Error(`${chemin} : redirigé vers ${lhr.finalDisplayedUrl} (cookie refusé ?)`)
      const echecs = (categorie) => lhr.categories[categorie].auditRefs
        .filter((r) => r.weight > 0 && lhr.audits[r.id].score !== null && lhr.audits[r.id].score < 1)
        .map((r) => r.id)
      mesures.push({
        performance: lhr.categories.performance.score,
        accessibilite: lhr.categories.accessibility.score,
        bonnesPratiques: lhr.categories['best-practices'].score,
        fcp: Math.round(lhr.audits['first-contentful-paint'].numericValue),
        lcp: Math.round(lhr.audits['largest-contentful-paint'].numericValue),
        tbt: Math.round(lhr.audits['total-blocking-time'].numericValue),
        cls: Math.round(lhr.audits['cumulative-layout-shift'].numericValue * 1000) / 1000,
        total: octets(lhr, 'total'),
        script: octets(lhr, 'script'),
        feuilles: octets(lhr, 'stylesheet'),
        polices: octets(lhr, 'font'),
        document: octets(lhr, 'document'),
        auditsNonParfaits: [...new Set([...echecs('accessibility'), ...echecs('best-practices')])],
      })
    }
    const nombres = Object.keys(mesures[0]).filter((k) => typeof mesures[0][k] === 'number')
    const m = Object.fromEntries(nombres.map((k) => [k, mediane(mesures.map((x) => x[k]))]))
    m.passes = mesures.map((x) => ({ performance: x.performance, accessibilite: x.accessibilite, bonnesPratiques: x.bonnesPratiques }))
    m.auditsNonParfaits = [...new Set(mesures.flatMap((x) => x.auditsNonParfaits))]
    resultats.push({ chemin, ...m })
    console.log(`${chemin.padEnd(28)} perf ${m.performance}  a11y ${m.accessibilite}  bonnes pratiques ${m.bonnesPratiques}  LCP ${m.lcp} ms  TBT ${m.tbt} ms  CLS ${m.cls}`)
  }
} finally {
  outil('fin')
  await rm(profils, { recursive: true, force: true, maxRetries: 3 }).catch(() => null)
}

await mkdir('docs/recette', { recursive: true })
const version = execFileSync('node', ['-p', "require('lighthouse/package.json').version"], { encoding: 'utf8' }).trim()
await writeFile('docs/recette/lighthouse.json', JSON.stringify({ lighthouse: version, passes: PASSES, reglages: 'mobile par défaut (Slow 4G simulé, processeur ×4)', pages: resultats }, null, 2) + '\n')

const echecs = resultats.flatMap((r) => [
  r.performance < SEUILS.performance ? `${r.chemin} : performance ${r.performance}` : null,
  r.accessibilite < SEUILS.accessibilite ? `${r.chemin} : accessibilité ${r.accessibilite}` : null,
  r.bonnesPratiques < SEUILS.bonnesPratiques ? `${r.chemin} : bonnes pratiques ${r.bonnesPratiques}` : null,
]).filter(Boolean)
if (echecs.length) {
  console.error('Seuils non atteints :\n' + echecs.join('\n'))
  process.exit(1)
}
console.log('Lighthouse : tous les seuils atteints (docs/recette/lighthouse.json).')
