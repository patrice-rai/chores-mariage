import { execFileSync } from 'node:child_process'
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { gzipSync } from 'node:zlib'
import { type Page } from '@playwright/test'
import { ORIGINE, choisir, deposerReference, entrer, expect, ouvrirAdmin, poserSections, preparer, test, verifierAxe, verifierAxeDeuxThemes, videoDemo } from './outils'

// Phase 6 : recette de toutes les pages (cousin et administration) avec des données de test (« Section A »,
// « Section B », vidéo « Démo », une vidéo de cousin) : axe en clair, en sombre et en mode répétition ; aucune
// violation de la CSP ni erreur de script ; zoom 400 % (320 × 225) sans défilement horizontal ; focus jamais masqué ;
// cibles ; inventaire des crochets ; aucun mot anglais ni emoji ; poids des pages ; journal sans adresse IP ;
// installation sans SSH sur une base vide. Résultats chiffrés dans docs/recette/ (projet « mobile ») et
// docs/RECETTE.md.

const DOSSIER = 'docs/recette'

type Donnees = { video: number; repetition: number }

/** Prépare la base de test : sections, référence, placements, une vidéo de cousin ; Catherine choisie. */
async function preparerDonnees(page: Page): Promise<Donnees> {
  const preparation = preparer()
  await entrer(page, preparation)
  await ouvrirAdmin(page, preparation)
  await poserSections(page, 'kanimaa')
  await deposerReference(page, 'kanimaa')
  await page.goto('/admin/chansons/kanimaa/placements')
  for (const [prenom, x, y] of [
    ['Catherine', '50', '50'],
    ['Fabien', '25', '40'],
    ['Alex', '75', '60'],
  ]) {
    const point = page.locator('fieldset.admin__point').filter({ hasText: prenom })
    await point.getByLabel(/^x/).fill(x)
    await point.getByLabel(/^y/).fill(y)
  }
  await page.getByRole('button', { name: 'Enregistrer les placements' }).first().click()
  await expect(page.getByText('Placements enregistrés')).toBeVisible()
  await page.goto('/')
  await choisir(page, 'Catherine')
  await page.goto('/videos/ajouter')
  await page.getByLabel('Chanson').selectOption('kanimaa')
  await page.getByLabel('Titre').fill('Démo, section A')
  await page.getByLabel('Vidéo', { exact: true }).setInputFiles({ name: 'demo.webm', mimeType: 'video/webm', buffer: videoDemo() })
  await page.getByRole('button', { name: 'Envoyer' }).click()
  await expect(page).toHaveURL(/\/videos\/\d+\?ok=ajoutee$/, { timeout: 60_000 })
  const video = Number(/\/videos\/(\d+)/.exec(page.url())?.[1])
  await page.getByLabel('À quel instant').fill('0:03')
  await page.getByLabel('Ton commentaire').fill('Section A : bras plus hauts.')
  await page.getByRole('button', { name: 'Commenter' }).click()
  await expect(page.getByRole('status').filter({ hasText: 'Ton commentaire est ajouté.' })).toBeVisible()
  return { video, repetition: 1 }
}

function adressesCousin(d: Donnees): string[] {
  return [
    '/moi',
    '/chanson/kanimaa',
    '/chanson/kanimaa?section=2',
    '/chanson/yaaro-yaarodi',
    '/chanson/innisai',
    '/placements/kanimaa',
    '/placements/kanimaa?scene=1',
    '/repetitions',
    '/videos',
    '/videos/ajouter',
    `/videos/${d.video}`,
    '/jour-j',
    '/hors-ligne',
    '/page-qui-n-existe-pas',
  ]
}

function adressesAdmin(d: Donnees): string[] {
  return [
    '/admin',
    '/admin/lien',
    '/admin/reglages',
    '/admin/chansons',
    '/admin/chansons/kanimaa',
    '/admin/chansons/kanimaa/sections',
    '/admin/chansons/kanimaa/placements',
    '/admin/journal',
    '/admin/repetitions',
    `/admin/repetitions/${d.repetition}`,
    '/admin/jour-j',
    '/admin/videos',
    '/admin/base',
    '/gabarits',
  ]
}

/** Erreurs de console (CSP comprise) et erreurs de script d'une page, relevées pendant toute sa vie. */
function surveillerConsole(page: Page): string[] {
  const erreurs: string[] = []
  page.on('console', (m) => {
    // La page introuvable répond 404 exprès : Chromium le note en console, ce n'est pas une erreur du site.
    if (/Failed to load resource: .* 404/.test(m.text()) && page.url().endsWith('/page-qui-n-existe-pas')) return
    if (m.type() === 'error' || /Content Security Policy|Refused to/i.test(m.text())) erreurs.push(`${page.url()} : ${m.text()}`)
  })
  page.on('pageerror', (e) => erreurs.push(`${page.url()} : ${e.message}`))
  return erreurs
}

test('recette : toutes les pages, axe en clair et en sombre, aucune violation de la CSP', async ({ page }, info) => {
  test.setTimeout(600_000)
  const d = await preparerDonnees(page)
  const erreurs = surveillerConsole(page)
  const structure: Record<string, unknown> = {}
  for (const adresse of [...adressesCousin(d), ...adressesAdmin(d)]) {
    const reponse = await page.goto(adresse)
    expect([200, 404], adresse).toContain(reponse?.status())
    // Structure (2.4.2, 1.3.1, 2.4.1, 3.2.6) : titre « … — Chorés du mariage », un seul h1, <main id="contenu">,
    // lien d'évitement en premier, navigations nommées, aide « Un souci ? » en pied de page, au même endroit.
    const s = await page.evaluate(() => ({
      titre: document.title,
      h1: document.querySelectorAll('h1').length,
      main: document.querySelectorAll('main#contenu').length,
      evitementEnPremier: document.querySelector('a[href], button, input, select, textarea')?.classList.contains('evitement') ?? false,
      navSansNom: [...document.querySelectorAll('nav')].filter((n) => !n.getAttribute('aria-label') && !n.getAttribute('aria-labelledby')).length,
      aideEnPied: document.body.lastElementChild?.matches('footer.pied') === true && (document.querySelector('footer.pied')?.textContent ?? '').includes('Un souci ? Écris à Patrice'),
      tableauxSansLegende: [...document.querySelectorAll('table')].filter((t) => !t.querySelector('caption')).length,
      lang: document.documentElement.lang,
    }))
    structure[adresse] = s
    expect(s.titre, adresse).toMatch(/^(.+ — )?Chorés du mariage$/)
    expect(s.h1, `${adresse} : un seul h1`).toBe(1)
    expect(s.main, adresse).toBe(1)
    expect(s.evitementEnPremier, `${adresse} : lien d’évitement en premier`).toBe(true)
    expect(s.navSansNom, adresse).toBe(0)
    expect(s.aideEnPied, `${adresse} : aide au même endroit`).toBe(true)
    expect(s.tableauxSansLegende, adresse).toBe(0)
    expect(s.lang).toBe('fr')
    for (const schema of ['light', 'dark'] as const) {
      await page.emulateMedia({ colorScheme: schema })
      await verifierAxe(page)
    }
    await page.emulateMedia({ colorScheme: 'light' })
  }
  // Mode répétition (toujours sombre), avec et sans section, et la démonstration des gabarits.
  for (const adresse of ['/chanson/kanimaa/repeter', '/chanson/kanimaa/repeter?section=2', '/chanson/yaaro-yaarodi/repeter', '/gabarits/repeter']) {
    await page.goto(adresse)
    await verifierAxe(page)
  }
  // Ordre de focus (2.4.3) relevé sur deux pages : les 14 premiers arrêts de tabulation.
  const ordre: Record<string, string[]> = {}
  for (const adresse of ['/moi', '/chanson/kanimaa']) {
    await page.goto(adresse)
    ordre[adresse] = []
    for (let i = 0; i < 14; i++) {
      await page.keyboard.press('Tab')
      ordre[adresse].push(await page.evaluate(() => {
        const el = document.activeElement as HTMLElement
        return `${el.tagName.toLowerCase()} « ${(el.innerText || el.getAttribute('aria-label') || (el as HTMLInputElement).value || '').trim().replace(/\s+/g, ' ').slice(0, 40)} »`
      }))
    }
  }
  if (info.project.name === 'mobile') {
    mkdirSync(DOSSIER, { recursive: true })
    writeFileSync(`${DOSSIER}/structure.json`, JSON.stringify({ pages: structure, ordreDuFocus: ordre }, null, 2) + '\n')
  }
  // Lien manquant (sans cookie).
  await page.context().clearCookies()
  await page.goto('/lien')
  await verifierAxe(page)
  expect(erreurs, 'erreurs de console ou violations de la CSP').toEqual([])
})

test('recette : zoom 400 % (320 × 225) sans défilement horizontal', async ({ page }) => {
  test.setTimeout(600_000)
  const d = await preparerDonnees(page)
  await page.setViewportSize({ width: 320, height: 225 })
  const fautes: string[] = []
  for (const adresse of [...adressesCousin(d), ...adressesAdmin(d), '/chanson/kanimaa/repeter']) {
    await page.goto(adresse)
    const [largeur, visible] = await page.evaluate(() => [document.documentElement.scrollWidth, document.documentElement.clientWidth])
    if (largeur > visible) fautes.push(`${adresse} : ${largeur} > ${visible}`)
  }
  await page.context().clearCookies()
  for (const adresse of ['/lien', '/']) {
    await page.goto(adresse)
    const [largeur, visible] = await page.evaluate(() => [document.documentElement.scrollWidth, document.documentElement.clientWidth])
    if (largeur > visible) fautes.push(`${adresse} (sans lien) : ${largeur} > ${visible}`)
  }
  expect(fautes).toEqual([])
})

test('recette : focus jamais masqué, cibles d’au moins 24 px, contrastes sur le rendu', async ({ page }, info) => {
  test.setTimeout(600_000)
  const d = await preparerDonnees(page)
  const masques: string[] = []
  const petites: string[] = []
  const sous44: Record<string, string[]> = {}
  for (const adresse of ['/moi', '/chanson/kanimaa', '/chanson/kanimaa/repeter?section=1', '/placements/kanimaa', '/repetitions', `/videos/${d.video}`, '/jour-j', '/admin', '/admin/base', '/admin/chansons/kanimaa/sections']) {
    await page.goto(adresse)
    // Focus : 60 arrêts de tabulation ; l'élément qui a le focus n'est jamais entièrement caché (2.4.11).
    for (let i = 0; i < 60; i++) {
      await page.keyboard.press('Tab')
      const etat = await page.evaluate(() => {
        const el = document.activeElement as HTMLElement | null
        if (!el || el === document.body) return null
        const r = el.getBoundingClientRect()
        if (r.width === 0 || r.height === 0) return null
        const points = [
          [r.left + r.width / 2, r.top + r.height / 2],
          [r.left + 2, r.top + 2],
          [r.right - 2, r.bottom - 2],
        ]
        const visibles = points.filter(([x, y]) => {
          if (x < 0 || y < 0 || x > innerWidth || y > innerHeight) return false
          const dessus = document.elementFromPoint(x, y)
          return dessus !== null && (dessus === el || el.contains(dessus) || dessus.contains(el) || (el as HTMLInputElement).labels?.[0]?.contains(dessus) === true)
        })
        return visibles.length > 0 ? null : `${el.tagName}.${el.className} « ${(el.textContent ?? '').trim().slice(0, 30)} »`
      })
      if (etat) masques.push(`${adresse} : ${etat}`)
    }
    // Cibles : 24 × 24 au moins (2.5.8), sauf un lien dans une phrase ; relevé de celles sous 44 px.
    const mesures = await page.evaluate(() =>
      [...document.querySelectorAll('a[href], button, input:not([type="hidden"]), select, textarea, summary, [tabindex="0"]')]
        .filter((el) => (el as HTMLElement).offsetParent !== null && !el.closest('.visuellement-cache, .evitement'))
        .map((el) => {
          const r = el.getBoundingClientRect()
          const parent = el.parentElement
          const dansUnePhrase = el.tagName === 'A' && parent !== null && ['P', 'LI', 'TD', 'SPAN'].includes(parent.tagName)
            && [...parent.childNodes].some((n) => n.nodeType === 3 && (n.textContent ?? '').trim() !== '')
          return { quoi: `${el.tagName.toLowerCase()} « ${((el as HTMLElement).innerText || el.getAttribute('aria-label') || '').trim().slice(0, 30)} »`, l: Math.round(r.width), h: Math.round(r.height), dansUnePhrase }
        }),
    )
    for (const m of mesures) {
      if (!m.dansUnePhrase && (m.l < 24 || m.h < 24)) petites.push(`${adresse} : ${m.quoi} ${m.l}×${m.h}`)
      if (!m.dansUnePhrase && (m.l < 44 || m.h < 44)) (sous44[adresse] ??= []).push(`${m.quoi} ${m.l}×${m.h}`)
    }
  }
  expect(masques, 'focus masqué').toEqual([])
  expect(petites, 'cibles sous 24 px').toEqual([])
  expect(sous44, 'cibles sous 44 px (règle du projet)').toEqual({})

  // Scène doublée d'une liste en mots, annonces polies du lecteur et du décompte, grille en vrai tableau.
  await page.goto('/placements/kanimaa')
  await expect(page.getByRole('img', { name: /Scène vue de dessus/ }).first()).toBeVisible()
  await expect(page.locator('.scene__liste').first()).toContainText('Catherine (toi) : au centre, au milieu')
  await page.goto('/chanson/kanimaa')
  expect(await page.locator('[aria-live="polite"][data-annonce]').count()).toBeGreaterThan(0)
  const grille = page.locator('table:has(caption)').filter({ hasText: 'Section A' }).first()
  await expect(grille.locator('th[scope="col"]').first()).toBeAttached()
  await expect(grille.locator('th[scope="row"]').first()).toBeAttached()

  // Contrastes calculés sur le rendu : badge « Miroir activé », surimpressions du mode répétition, points de scène.
  const contraste = async (selecteur: string): Promise<number[]> =>
    page.locator(selecteur).evaluateAll((els) => {
      const rgb = (c: string) => (c.match(/[\d.]+/g) ?? []).map(Number)
      const lum = ([r, g, b]: number[]) => {
        const f = (v: number) => {
          const s = v / 255
          return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4
        }
        return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b)
      }
      return els.map((el) => {
        let fond: HTMLElement | null = el as HTMLElement
        while (fond && (getComputedStyle(fond).backgroundColor === 'rgba(0, 0, 0, 0)' || getComputedStyle(fond).backgroundColor === 'transparent')) fond = fond.parentElement
        const a = lum(rgb(getComputedStyle(el).color))
        const [r, g, b, alpha = 1] = rgb(getComputedStyle(fond ?? document.body).backgroundColor)
        const sous = alpha < 1 ? [255, 0] : [null]
        const ratios = sous.map((dessous) => {
          const melange = dessous === null ? [r, g, b] : [r, g, b].map((v) => v * alpha + dessous * (1 - alpha))
          const l = lum(melange)
          return (Math.max(a, l) + 0.05) / (Math.min(a, l) + 0.05)
        })
        return Math.round(Math.min(...ratios) * 100) / 100
      })
    })
  const mesures: Record<string, number[]> = {}
  for (const schema of ['light', 'dark'] as const) {
    await page.emulateMedia({ colorScheme: schema })
    await page.goto('/chanson/kanimaa')
    const miroir = page.getByRole('button', { name: /^Miroir/ }).first()
    if ((await miroir.getAttribute('aria-pressed')) !== 'true') await miroir.click()
    await expect(miroir).toHaveAttribute('aria-pressed', 'true')
    mesures[`badge Miroir activé (${schema})`] = await contraste('.lecteur__badge-miroir:not([hidden])')
    await page.goto('/placements/kanimaa')
    mesures[`points de scène (${schema})`] = await contraste('.scene__point')
  }
  await page.goto('/chanson/kanimaa/repeter?section=1')
  mesures['surimpressions du mode répétition'] = await contraste('.lecteur__surimpression, .lecteur__statut, .lecteur--repetition .lecteur__section-nom')
  for (const [quoi, valeurs] of Object.entries(mesures)) {
    expect(valeurs.length, `${quoi} : rien de mesuré`).toBeGreaterThan(0)
    for (const v of valeurs) expect(v, quoi).toBeGreaterThanOrEqual(4.5)
  }
  if (info.project.name === 'mobile') {
    mkdirSync(DOSSIER, { recursive: true })
    writeFileSync(`${DOSSIER}/cibles-et-contrastes.json`, JSON.stringify({ contrastes: mesures, ciblesSous44: sous44 }, null, 2) + '\n')
  }
})

test('recette : crochets, mots anglais, emoji, poids des pages, vidéos en preload="metadata"', async ({ page }, info) => {
  test.setTimeout(600_000)
  const d = await preparerDonnees(page)
  const crochets: Record<string, string[]> = {}
  const anglais: string[] = []
  const emoji: string[] = []
  const poids: Record<string, { html: number; css: number; cssGzip: number; js: number; jsGzip: number; polices: number }> = {}
  const MOTS_ANGLAIS = /\b(the|and|loading|error|submit|cancel|save|delete|upload|download|play|stop|settings|password|email|next|previous|back|home|video|videos|bpm|choose file|no file chosen|mirror|loop|speed)\b/i
  // Noms propres permis : titres des chansons (docs/whatsapp/etat.json, plus « Innisai », son orthographe importée),
  // artistes et chaînes des tutoriels ; noms de fichiers de la documentation cités à Patrice.
  const etat = JSON.parse(readFileSync('docs/whatsapp/etat.json', 'utf8')) as { chansons: { titre: string }[] }
  const nomsPropres = [...etat.chansons.map((c) => c.titre), 'Innisai', 'The Officials', 'Dance Marine', 'Learn How To Dance', 'nk little dance']
  const sansNomsPropres = (texte: string) => nomsPropres.reduce((t, n) => t.split(n).join(' '), texte).replace(/\S+\.md\b/g, ' ')
  for (const adresse of [...adressesCousin(d), ...adressesAdmin(d), '/chanson/kanimaa/repeter']) {
    await page.goto(adresse)
    await page.waitForLoadState('networkidle')
    // Poids : chaque feuille, module et police chargés par la page, relus sans cache ; taille brute et compressée
    // (gzip, comme mod_deflate pour les feuilles et les scripts ; les polices woff2 et la page ne sont pas compressées).
    const chargees = await page.evaluate(() => [...new Set(performance.getEntriesByType('resource').map((e) => e.name))])
    const p = { html: (await (await page.request.get(adresse)).body()).length, css: 0, cssGzip: 0, js: 0, jsGzip: 0, polices: 0 }
    for (const url of chargees) {
      const chemin = new URL(url).pathname
      const type = chemin.endsWith('.css') ? 'css' : chemin.endsWith('.js') ? 'js' : chemin.endsWith('.woff2') ? 'polices' : null
      if (!type || !url.startsWith(ORIGINE)) continue
      const corps = await (await page.request.get(url)).body()
      if (type === 'polices') p.polices += corps.length
      else {
        p[type] += corps.length
        p[`${type}Gzip`] += gzipSync(corps).length
      }
    }
    poids[adresse] = p

    const textes = await page.evaluate(() => {
      const sortie: { texte: string; tutoriel: boolean }[] = []
      const parcours = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT)
      while (parcours.nextNode()) {
        const n = parcours.currentNode
        const parent = n.parentElement
        if (!parent || parent.closest('script, style, .visuellement-cache[hidden]')) continue
        const texte = (n.textContent ?? '').trim()
        if (texte) sortie.push({ texte, tutoriel: parent.closest('a[href^="https://"]') !== null })
      }
      const attributs = [...document.querySelectorAll('[aria-label], [title], [placeholder], [alt]')].flatMap((el) =>
        ['aria-label', 'title', 'placeholder', 'alt'].map((a) => el.getAttribute(a) ?? '').filter(Boolean),
      )
      return [...sortie, ...attributs.map((texte) => ({ texte, tutoriel: false }))]
    })
    for (const { texte, tutoriel } of textes) {
      for (const m of texte.match(/\[[^\]]{1,60}\]/g) ?? []) (crochets[adresse] ??= []).includes(m) || crochets[adresse].push(m)
      // Les titres des tutoriels (liens sortants) gardent le nom de leur chaîne YouTube (décision 19 de la phase 4).
      const mot = MOTS_ANGLAIS.exec(sansNomsPropres(texte))?.[0]
      if (!tutoriel && mot) anglais.push(`${adresse} : « ${mot} » dans « ${texte.slice(0, 60)} »`)
      // Toute la famille pictographique (❤, ✔, ☀…) ; les flèches de texte (↗ des liens sortants) restent permises.
      if (/(?![←-⇿])\p{Extended_Pictographic}/u.test(texte)) emoji.push(`${adresse} : « ${texte.slice(0, 60)} »`)
    }
    const preload = await page.locator('video').evaluateAll((vs) => vs.map((v) => v.getAttribute('preload')))
    for (const p of preload) expect(p, `${adresse} : preload des vidéos`).toBe('metadata')
  }
  if (info.project.name === 'mobile') {
    mkdirSync(DOSSIER, { recursive: true })
    writeFileSync(`${DOSSIER}/crochets.json`, JSON.stringify(crochets, null, 2) + '\n')
    writeFileSync(`${DOSSIER}/poids.json`, JSON.stringify(poids, null, 2) + '\n')
  }
  expect(anglais, 'mots anglais').toEqual([])
  expect(emoji, 'emoji').toEqual([])
  // Budgets de la spec § 13, mesurés comme en phase 3 (décision 4) : CSS et JS transférés (compressés), page et
  // polices telles quelles.
  // /gabarits (page de travail de Patrice, tous les composants à la fois) n'est pas une page des cousins.
  for (const [adresse, p] of Object.entries(poids).filter(([a]) => a !== '/gabarits')) {
    expect(p.html, `${adresse} : HTML ≤ 60 Ko`).toBeLessThanOrEqual(60 * 1024)
    expect(p.cssGzip, `${adresse} : CSS ≤ 40 Ko`).toBeLessThanOrEqual(40 * 1024)
    expect(p.jsGzip, `${adresse} : JS ≤ 40 Ko`).toBeLessThanOrEqual(40 * 1024)
    expect(p.polices, `${adresse} : polices ≤ 120 Ko`).toBeLessThanOrEqual(120 * 1024)
  }
})

test('recette : journal et débit sans adresse IP ni navigateur (inspection de la base de test)', async ({ page }) => {
  await preparerDonnees(page)
  // Un faux lien et un refus de clé, pour que le journal porte aussi des refus.
  await page.goto('/e/' + 'A'.repeat(43))
  await page.goto('/admin/cle/' + 'B'.repeat(43))
  const sortie = execFileSync('docker', ['compose', 'exec', '-T', 'web', 'php', 'scripts/e2e-outil.php', 'inspecter'], { encoding: 'utf8' })
  const resultat = JSON.parse(sortie) as { lignes: number; suspectes: string[] }
  expect(resultat.lignes).toBeGreaterThan(10)
  expect(resultat.suspectes).toEqual([])
})

test('installation sans SSH sur une base vide : formulaire, refus, premier lien, sauvegarde et restauration', async ({ page }) => {
  const sortie = execFileSync('docker', ['compose', 'exec', '-T', 'web', 'php', 'scripts/e2e-outil.php', 'vierge'], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] })
  const cle = (JSON.parse(sortie) as { cle: string }).cle
  await page.goto('/installer')
  await expect(page.getByRole('heading', { level: 1, name: 'Installation' })).toBeVisible()
  await verifierAxe(page)
  await page.emulateMedia({ colorScheme: 'dark' })
  await verifierAxe(page)
  await page.emulateMedia({ colorScheme: 'light' })
  // Clé vide, puis mauvaise clé : erreur en toutes lettres, en haut et au champ (aria-invalid, aria-describedby).
  await page.getByRole('button', { name: 'Installer le site' }).click()
  await expect(page.getByRole('alert')).toBeFocused()
  await expect(page.getByRole('alert')).toContainText('colle la valeur de CLE_ADMIN')
  await page.getByLabel('Clé d’administration').fill('pas-la-bonne-cle')
  await page.getByRole('button', { name: 'Afficher la clé' }).click()
  await expect(page.getByLabel('Clé d’administration')).toHaveAttribute('type', 'text')
  await expect(page.getByRole('button', { name: 'Masquer la clé' })).toBeVisible()
  await page.getByRole('button', { name: 'Installer le site' }).click()
  await expect(page.getByRole('alert')).toContainText('ce n’est pas la bonne')
  const champ = page.getByLabel('Clé d’administration')
  await expect(champ).toHaveAttribute('aria-invalid', 'true')
  await expect(champ).toHaveAttribute('aria-describedby', /installation-cle-erreur/)
  await expect(champ).toHaveValue('')
  await verifierAxe(page)
  await page.setViewportSize({ width: 320, height: 225 })
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth), '400 %').toBe(true)
  await page.setViewportSize({ width: 390, height: 844 })
  await champ.fill(cle)
  await page.getByRole('button', { name: 'Installer le site' }).click()
  await expect(page.getByRole('status').filter({ hasText: 'C’est prêt' })).toBeVisible()
  expect(new RegExp(`^${ORIGINE.replace(/[.:/]/g, '\\$&')}/e/[A-Za-z0-9_-]{43}$`).test(await page.locator('#premier-lien').inputValue()), 'premier lien bien formé').toBe(true)
  await page.getByRole('button', { name: 'Copier le lien' }).click()
  await expect(page.locator('#copie-statut')).toHaveText(/^(Lien copié.|Copie impossible : sélectionne le texte et copie-le à la main.)$/)
  await page.setViewportSize({ width: 320, height: 225 })
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth), '400 % après installation').toBe(true)
  await page.setViewportSize({ width: 390, height: 844 })
  for (const schema of ['light', 'dark'] as const) {
    await page.emulateMedia({ colorScheme: schema })
    await verifierAxe(page)
  }
  await page.emulateMedia({ colorScheme: 'light' })
  await page.getByRole('link', { name: 'Ouvrir l’administration' }).click()
  await expect(page).toHaveURL(ORIGINE + '/admin')
  await expect(page.getByText('Version du site : 1.0.0. Base : à jour.')).toBeVisible()
  await page.getByRole('link', { name: 'Base et sauvegardes' }).first().click()
  await expect(page).toHaveURL(ORIGINE + '/admin/base')
  await page.getByRole('button', { name: 'Faire une sauvegarde maintenant' }).click()
  await expect(page.getByRole('status').filter({ hasText: 'Sauvegarde faite.' })).toBeInViewport()
  await expect(page.locator('.admin__sauvegardes li')).toHaveCount(1)
  await verifierAxeDeuxThemes(page)
  // Restauration depuis l'administration : confirmation en page, puis la même session reste ouverte (lien gardé).
  await page.locator('.admin__sauvegardes li').getByRole('link', { name: /^Restaurer/ }).click()
  await expect(page.getByRole('region', { name: /^Restaurer la sauvegarde du/ })).toBeVisible()
  await expect(page.getByRole('region', { name: /^Restaurer la sauvegarde du/ })).toBeFocused()
  await verifierAxe(page)
  await page.getByRole('button', { name: 'Oui, restaurer' }).click()
  await expect(page.getByRole('status').filter({ hasText: 'Sauvegarde restaurée ; le lien secret n’a pas changé.' })).toBeInViewport()
  expect((await page.request.get('/installer')).status()).toBe(404)
  expect((await page.request.post('/installer', { maxRedirects: 0 })).status(), 'pas de 400 qui trahirait l’adresse').toBe(404)
})
