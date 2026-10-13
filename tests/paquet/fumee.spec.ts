import { execFileSync } from 'node:child_process'
import AxeBuilder from '@axe-core/playwright'
import { type APIResponse, type BrowserContext, type Page, expect, test } from '@playwright/test'

// Essai du paquet (scripts/essai-paquet.mjs) : le zip décompressé dans un conteneur qui imite Hostinger, en HTTPS,
// sous /chores, avec le code hors de la racine web (chemin-app.php) et ENV = production. Un seul scénario, dans
// l'ordre de docs/deploy/README.md : installation sans SSH, parcours et axe, sécurité, scripts comme en SSH
// (migrer, importer, nouveau lien), sauvegarde, perte de la base, restauration. Le lien et la clé restent en mémoire.

const ORIGINE = 'https://127.0.0.1:8096'
const CONTENEUR = process.env.ESSAI_CONTENEUR ?? 'chores-mariage-paquet'
const APP = '/home/u000000000/domains/exemple.test/chores-app'

/** Commande dans le conteneur, sous l'identité du compte qui fait tourner PHP (comme en SSH chez l'hébergeur). */
function enSsh(commande: string): string {
  return execFileSync('docker', ['exec', '-u', 'www-data', '-w', APP, CONTENEUR, 'sh', '-c', commande], {
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  })
}

function cleAdmin(): string {
  const sortie = execFileSync('docker', ['exec', '-u', 'www-data', CONTENEUR, 'php', '/tmp/essai/outil-essai.php', APP], { encoding: 'utf8' })
  const cle = (JSON.parse(sortie) as { cle: string }).cle
  if (!/^\/chores\/admin\/cle\/[A-Za-z0-9_-]{43}$/.test(cle)) throw new Error('Clé d’essai mal formée.')
  return cle
}

/** Aucune requête hors de l'origine de l'essai ; violations CSP et erreurs de page relevées. */
async function surveiller(context: BrowserContext, externes: string[], erreurs: string[]): Promise<void> {
  await context.route('**/*', (route) => {
    const url = route.request().url()
    if (url.startsWith(ORIGINE + '/') || url.startsWith('data:') || url.startsWith('blob:')) return route.continue()
    externes.push(url)
    return route.abort('blockedbyclient')
  })
  context.on('page', (page) => {
    page.on('console', (message) => {
      if (/Content Security Policy|Refused to/i.test(message.text())) erreurs.push(`${page.url()} : ${message.text()}`)
    })
    page.on('pageerror', (erreur) => erreurs.push(`${page.url()} : ${erreur.message}`))
  })
}

async function axe(page: Page): Promise<void> {
  await page.waitForLoadState('load')
  await page.evaluate(() => document.fonts.ready.then(() => undefined))
  for (const largeur of [390, 1280]) {
    await page.setViewportSize({ width: largeur, height: largeur === 390 ? 844 : 900 })
    const resultat = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze()
    const resume = resultat.violations.map((v) => `${v.id} : ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`)
    expect(resume, `axe sur ${page.url()} en ${largeur} px`).toEqual([])
  }
  await page.setViewportSize({ width: 390, height: 844 })
}

/** En-têtes de sécurité ; `php` : réponse de PHP (en production, la CSP demande aussi la mise à niveau HTTPS). */
function entetesDeSecurite(reponse: APIResponse, quoi: string, php = true): void {
  const h = reponse.headers()
  expect(h['content-security-policy'], quoi).toContain("default-src 'self'")
  if (php) expect(h['content-security-policy'], quoi).toContain('upgrade-insecure-requests')
  expect(h['content-security-policy'], quoi).not.toContain('unsafe-inline')
  expect(h['x-content-type-options'], quoi).toBe('nosniff')
  expect(h['referrer-policy'], quoi).toBe('no-referrer')
  expect(h['x-robots-tag'], quoi).toContain('noindex')
  expect(h['x-frame-options'], quoi).toBe('DENY')
  expect(h['x-powered-by'], quoi).toBeUndefined()
}

test('paquet : installation, parcours, sécurité, scripts, sauvegarde et restauration', async ({ browser }) => {
  const externes: string[] = []
  const erreurs: string[] = []
  const context = await browser.newContext({ ignoreHTTPSErrors: true, baseURL: ORIGINE, viewport: { width: 390, height: 844 }, locale: 'fr-FR' })
  await surveiller(context, externes, erreurs)
  const page = await context.newPage()
  const cle = cleAdmin()
  let lien = ''
  let slug = ''

  await test.step('installation sans SSH (/chores/installer)', async () => {
    await page.goto('/chores/installer')
    await expect(page.getByRole('heading', { level: 1, name: 'Installation' })).toBeVisible()
    await axe(page)
    await page.getByLabel('Clé d’administration').fill('pas-la-bonne-cle')
    await page.getByRole('button', { name: 'Installer le site' }).click()
    await expect(page.getByText('ce n’est pas la bonne').first()).toBeVisible()
    await page.getByLabel('Clé d’administration').fill(cle.replace('/chores/admin/cle/', ''))
    await page.getByRole('button', { name: 'Installer le site' }).click()
    await expect(page.getByRole('status').filter({ hasText: 'C’est prêt' })).toBeVisible()
    await expect(page.getByText(/Faits du groupe importés : 8 chanson\(s\)/)).toBeVisible()
    lien = await page.locator('#premier-lien').inputValue()
    expect(/^https:\/\/127\.0\.0\.1:8096\/chores\/e\/[A-Za-z0-9_-]{43}$/.test(lien), 'premier lien bien formé').toBe(true)
    await axe(page)

    // Cookies posés en production : HttpOnly, Secure, SameSite=Lax, limités à /chores.
    const cookies = await context.cookies()
    for (const nom of ['cm_acces', 'cm_admin']) {
      const cookie = cookies.find((c) => c.name === nom)
      expect(cookie, nom).toBeDefined()
      expect(cookie?.httpOnly, nom).toBe(true)
      expect(cookie?.secure, nom).toBe(true)
      expect(cookie?.sameSite, nom).toBe('Lax')
      expect(cookie?.path, nom).toBe('/chores')
    }
    const deNouveau = await context.request.get('/chores/installer')
    expect(deNouveau.status()).toBe(404)
  })

  await test.step('entrée, prénom, Ma page, une chanson, placements, répétitions, vidéos, Jour J (axe)', async () => {
    await page.getByRole('link', { name: 'Entrer avec le lien' }).click()
    await expect(page).toHaveURL(ORIGINE + '/chores/')
    await axe(page)
    await page.getByRole('button', { name: 'Patrice', exact: true }).click()
    await expect(page).toHaveURL(ORIGINE + '/chores/moi')
    await axe(page)
    const cookiePrenom = (await context.cookies()).find((c) => c.name === 'cm_prenom')
    expect(cookiePrenom?.secure && cookiePrenom.httpOnly && cookiePrenom.path === '/chores').toBe(true)

    // Une répétition de test datée de 2099 (les dates importées du groupe finissent par passer), puis « Je viens » :
    // donnée qui servira à la restauration.
    await page.goto('/chores/admin/repetitions')
    await page.getByLabel('Date').fill('2099-03-07')
    await page.getByLabel('Lieu').fill('Salle Démo')
    await page.getByLabel('Statut').selectOption('decidee')
    await page.getByRole('button', { name: 'Ajouter la répétition' }).click()
    await expect(page.getByRole('status').filter({ hasText: 'Répétition ajoutée.' })).toBeVisible()
    await page.goto('/chores/moi')
    const carte = page.locator('#prochaine-repetition').locator('..')
    await expect(carte.getByText('samedi 7 mars 2099', { exact: true })).toBeVisible()
    await carte.getByRole('button', { name: 'Je viens' }).click()
    await expect(page).toHaveURL(/\/chores\/moi\?ok=oui/)

    const premiere = page.locator('a[href^="/chores/chanson/"]').first()
    slug = ((await premiere.getAttribute('href')) ?? '').replace('/chores/chanson/', '')
    expect(slug).toMatch(/^[a-z0-9-]+$/)
    for (const chemin of [`/chores/chanson/${slug}`, '/chores/placements', '/chores/repetitions', '/chores/videos', '/chores/videos/ajouter', '/chores/jour-j', '/chores/hors-ligne']) {
      const reponse = await page.goto(chemin)
      expect(reponse?.status(), chemin).toBe(200)
      await axe(page)
    }
  })

  await test.step('administration, vidéo de référence servie par /chores/m/', async () => {
    await page.goto(cle)
    await expect(page).toHaveURL(ORIGINE + '/chores/admin')
    await expect(page.getByText('Version du site : 1.0.0. Base : à jour.')).toBeVisible()
    await axe(page)
    await page.goto('/chores/admin/base')
    await expect(page.getByText('Base : à jour.')).toBeVisible()
    await axe(page)
    for (const chemin of ['/chores/admin/chansons', `/chores/admin/chansons/${slug}`, '/chores/admin/reglages', '/chores/admin/repetitions', '/chores/admin/videos', '/chores/admin/jour-j', '/chores/admin/journal', '/chores/admin/lien', '/chores/gabarits']) {
      const reponse = await page.goto(chemin)
      expect(reponse?.status(), chemin).toBe(200)
    }

    // Vidéo « Démo » synthétique (lue dans le conteneur du site local, jamais versionnée) déposée comme référence.
    const demo = execFileSync('docker', ['compose', 'exec', '-T', 'web', 'cat', 'stockage/medias/demo.webm'], { stdio: ['ignore', 'pipe', 'pipe'], maxBuffer: 64 * 1024 * 1024 })
    await page.goto(`/chores/admin/chansons/${slug}`)
    await page.locator('#reference-video').setInputFiles({ name: 'demo.webm', mimeType: 'video/webm', buffer: demo })
    await page.getByRole('button', { name: 'Déposer la vidéo' }).click()
    await expect(page.getByRole('status').filter({ hasText: 'Vidéo de référence déposée' })).toBeVisible()

    await page.goto(`/chores/chanson/${slug}`)
    const source = (await page.locator('video').first().getAttribute('src')) ?? ''
    expect(source).toMatch(/^\/chores\/m\/\d+\?v=[0-9a-f]{8}$/)
    const media = await context.request.get(source, { headers: { Range: 'bytes=0-99' } })
    expect(media.status()).toBe(206)
    expect(media.headers()['content-type']).toBe('video/webm')
    entetesDeSecurite(media, '/m/ avec cookie')
    await axe(page)

    // Sans cookie : refusé.
    const inconnu = await browser.newContext({ ignoreHTTPSErrors: true, baseURL: ORIGINE })
    const refus = await inconnu.request.get(source, { maxRedirects: 0 })
    expect(refus.status()).toBe(302)
    expect(refus.headers()['location']).toBe('/chores/lien')
    expect((await inconnu.request.get('/chores/admin', { maxRedirects: 0 })).status()).toBe(404)
    await inconnu.close()
  })

  await test.step('en-têtes, fichiers protégés, compression', async () => {
    const r = context.request
    for (const [chemin, statut] of [
      ['/chores/lien', 200],
      [`/chores/api/etat?chanson=${slug}`, 200],
      ['/chores/page-inconnue', 404],
      ['/chores/css/base.css', 200],
      ['/chores/sw.js', 200],
    ] as const) {
      const reponse = await r.get(chemin, { maxRedirects: 0, headers: { 'Accept-Encoding': 'gzip, br' } })
      expect(reponse.status(), chemin).toBe(statut)
      entetesDeSecurite(reponse, chemin, !chemin.endsWith('.css'))
      if (!chemin.endsWith('.css')) {
        expect(reponse.headers()['content-encoding'], `${chemin} jamais compressé`).toBeUndefined()
      }
      if (chemin.startsWith('/chores/lien')) {
        expect(reponse.headers()['strict-transport-security']).toBe('max-age=31536000')
        expect(reponse.headers()['cache-control']).toBe('no-store')
      }
      if (chemin.startsWith('/chores/api')) expect(reponse.headers()['content-type']).toContain('application/json')
    }
    for (const chemin of ['/chores-app/config.php', '/chores-app/VERSION', '/chores-app/stockage/base.sqlite', '/chores/chemin-app.php', '/chores/.htaccess', '/chores/index.php.bak', '/repli-essai/VERSION', '/repli-essai/.htaccess']) {
      const statut = (await r.get(chemin, { maxRedirects: 0 })).status()
      expect([403, 404], chemin).toContain(statut)
    }
    expect((await r.get('/repli-essai/VERSION')).status(), 'le .htaccess du paquet refuse tout').toBe(403)
    expect(await (await r.get('/chores/robots.txt')).text()).toContain('Disallow: /')
  })

  await test.step('comme en SSH : migrer, importer, nouveau lien (tout le monde est déconnecté)', async () => {
    expect(enSsh('php scripts/migrer.php')).toContain('Base à jour')
    expect(enSsh('php scripts/importer-etat.php')).toContain('Ajouts : 0 chanson(s), 0 lien(s), 0 répétition(s)')
    const sortie = enSsh('php scripts/nouveau-lien.php')
    const nouveau = /https:\/\/127\.0\.0\.1:8096\/chores\/e\/[A-Za-z0-9_-]{43}/.exec(sortie)?.[0] ?? ''
    // Comparaisons en booléens : un échec n'écrit jamais un lien dans le rapport.
    expect(nouveau !== '' && nouveau !== lien, 'un nouveau lien, différent du premier').toBe(true)

    await page.goto('/chores/moi')
    await expect(page).toHaveURL(ORIGINE + '/chores/lien')
    await page.goto(lien)
    await expect(page).toHaveURL(ORIGINE + '/chores/lien')
    await page.goto(nouveau)
    await expect(page).toHaveURL(ORIGINE + '/chores/')
    await page.getByRole('button', { name: 'Patrice', exact: true }).click()
    await expect(page).toHaveURL(ORIGINE + '/chores/moi')
    lien = nouveau
  })

  await test.step('témoin de maintenance : 503 en français, sans ouvrir la base', async () => {
    enSsh('touch stockage/maintenance.actif')
    const pause = await context.request.get('/chores/moi', { maxRedirects: 0 })
    expect(pause.status()).toBe(503)
    expect(pause.headers()['retry-after']).toBe('60')
    entetesDeSecurite(pause, 'page de maintenance')
    expect(await pause.text()).toContain('Le site est en maintenance quelques instants.')
    enSsh('rm stockage/maintenance.actif')
    expect((await context.request.get('/chores/moi', { maxRedirects: 0 })).status()).toBe(200)
  })

  await test.step('sauvegarde, perte de la base, restauration (comme en SSH)', async () => {
    await page.goto('/chores/repetitions')
    await expect(page.locator('article.repetition-carte').filter({ hasText: '7 mars 2099' }).getByText('Ta réponse : je viens.')).toBeVisible()
    const sauvegarde = /stockage\/sauvegardes\/(base-[0-9-]+\.sqlite)/.exec(enSsh('php scripts/sauvegarde.php'))?.[1] ?? ''
    expect(sauvegarde).not.toBe('')

    // Perte : la base disparaît, une base vide prend sa place (aucun lien : personne n'entre).
    enSsh('mv stockage/base.sqlite /tmp/base-perdue.sqlite && rm -f stockage/base.sqlite-wal stockage/base.sqlite-shm && php scripts/migrer.php')
    await page.goto('/chores/moi')
    await expect(page).toHaveURL(ORIGINE + '/chores/lien')

    const restauration = enSsh(`php scripts/restaurer.php stockage/sauvegardes/${sauvegarde} --oui`)
    expect(restauration).toContain(`Base restaurée depuis ${sauvegarde} (9 cousins)`)
    expect(restauration).toContain('avant-restauration/')
    await page.goto('/chores/repetitions')
    await expect(page).toHaveURL(ORIGINE + '/chores/repetitions')
    await expect(page.locator('article.repetition-carte').filter({ hasText: '7 mars 2099' }).getByText('Ta réponse : je viens.')).toBeVisible()
    await page.goto('/chores/admin/base')
    await expect(page.getByText('Base : à jour.')).toBeVisible()
    await expect(page.locator('.admin__sauvegardes li', { hasText: sauvegarde })).toBeVisible()
  })

  await test.step('sauvegarde et restauration depuis l’administration (sans SSH), lien gardé', async () => {
    await page.goto('/chores/admin/base')
    await page.getByRole('button', { name: 'Faire une sauvegarde maintenant' }).click()
    await expect(page.getByRole('status').filter({ hasText: 'Sauvegarde faite.' })).toBeVisible()
    await axe(page)
    const recente = (await page.locator('.admin__sauvegardes li code').first().textContent()) ?? ''
    // Changement après la sauvegarde : « Je ne peux pas ».
    await page.goto('/chores/repetitions')
    await page.locator('article.repetition-carte').filter({ hasText: '7 mars 2099' }).getByRole('button', { name: 'Je ne peux pas' }).click()
    await expect(page.locator('article.repetition-carte').filter({ hasText: '7 mars 2099' }).getByText('Ta réponse : je ne peux pas.')).toBeVisible()
    // Un nouveau lien après la sauvegarde : il doit rester le bon après la restauration.
    const sortie = enSsh('php scripts/nouveau-lien.php')
    const dernier = /https:\/\/127\.0\.0\.1:8096\/chores\/e\/[A-Za-z0-9_-]{43}/.exec(sortie)?.[0] ?? ''
    await page.goto(dernier)
    await page.getByRole('button', { name: 'Patrice', exact: true }).click()
    await page.goto('/chores/admin/base')
    await page.locator('.admin__sauvegardes li', { hasText: recente }).getByRole('link', { name: /^Restaurer/ }).click()
    await expect(page.getByRole('region', { name: /^Restaurer la sauvegarde du/ })).toBeVisible()
    await axe(page)
    await page.getByRole('button', { name: 'Oui, restaurer' }).click()
    await expect(page).toHaveURL(ORIGINE + '/chores/admin/base?ok=restauree')
    await expect(page.getByRole('status').filter({ hasText: 'Sauvegarde restaurée ; le lien secret n’a pas changé.' })).toBeVisible()
    await page.goto('/chores/repetitions')
    await expect(page).toHaveURL(ORIGINE + '/chores/repetitions')
    await expect(page.locator('article.repetition-carte').filter({ hasText: '7 mars 2099' }).getByText('Ta réponse : je viens.')).toBeVisible()
    // Le lien d'avant la sauvegarde ne revient pas ; le dernier marche toujours.
    const autre = await browser.newContext({ ignoreHTTPSErrors: true, baseURL: ORIGINE })
    expect((await autre.request.get(lien, { maxRedirects: 0 })).headers()['location']).toBe('/chores/lien')
    expect((await autre.request.get(dernier, { maxRedirects: 0 })).headers()['location']).toBe('/chores/')
    await autre.close()
  })

  expect(externes, 'requêtes hors de l’origine').toEqual([])
  expect(erreurs, 'violations CSP ou erreurs de page').toEqual([])
  await context.close()
})
