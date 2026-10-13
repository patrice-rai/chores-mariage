import { type Page } from '@playwright/test'
import { ORIGINE, expect, test, verifierAxe } from './outils'

// Phase 3 : /gabarits (jetons, composants, lecteur, décompte, scène) dans les deux thèmes, en 390 et 1 280 px.
// La vidéo « Démo » est fabriquée par la préparation globale (scripts/video-demo.mjs) si elle manque.

const video = (page: Page) => page.locator('#lecteur-demo video')
const lecteur = (page: Page) => page.locator('#lecteur-demo')

async function ouvrir(page: Page, adresse = '/gabarits') {
  await page.goto(adresse)
  // Le script du lecteur a démarré quand les commandes sont révélées et les contrôles natifs masqués.
  await expect(lecteur(page).getByRole('button', { name: 'Lecture' })).toBeVisible()
  await expect.poll(() => video(page).evaluate((v: HTMLVideoElement) => v.readyState)).toBeGreaterThanOrEqual(1)
}

test('gabarits en clair puis en sombre : axe sans violation, bascule mémorisée', async ({ page, context }) => {
  await page.emulateMedia({ colorScheme: 'light' })
  await ouvrir(page)
  await expect(page).toHaveTitle('Gabarits — Chorés du mariage')
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Gabarits')
  await expect(page.locator('html')).not.toHaveAttribute('data-theme')
  await verifierAxe(page)

  const bascule = page.getByRole('button', { name: /^Mode sombre/ }).first()
  await expect(bascule).toHaveAttribute('aria-pressed', 'false')
  await expect(bascule).toContainText('désactivé')
  await bascule.click()
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'sombre')
  const active = page.getByRole('button', { name: /^Mode sombre/ })
  await expect(active).toHaveAttribute('aria-pressed', 'true')
  await expect(active).toContainText('activé')
  await expect(active).toBeFocused()
  await verifierAxe(page)

  // Le choix est enregistré (cookie) : la page revient sombre, rendue par le serveur.
  await expect.poll(async () => (await context.cookies()).find((c) => c.name === 'cm_theme')?.value).toBe('sombre')
  await page.reload()
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'sombre')
  const fond = await page.evaluate(() => getComputedStyle(document.body).backgroundColor)
  expect(fond).toBe('rgb(23, 18, 26)')

  await page.getByRole('button', { name: /^Mode sombre/ }).click()
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'clair')
})

test('sans choix enregistré, le thème suit le système', async ({ page }) => {
  await page.emulateMedia({ colorScheme: 'dark' })
  await page.goto('/gabarits')
  expect(await page.evaluate(() => getComputedStyle(document.body).backgroundColor)).toBe('rgb(23, 18, 26)')
  await expect(page.getByRole('button', { name: /^Mode sombre/ })).toHaveAttribute('aria-pressed', 'true')
  await verifierAxe(page)
})

test('la bascule marche sans JavaScript', async ({ browser }) => {
  const contexte = await browser.newContext({ baseURL: ORIGINE, javaScriptEnabled: false, colorScheme: 'light' })
  const page = await contexte.newPage()
  await page.goto('/gabarits?section=2')
  // Sans JavaScript : contrôles natifs, commandes du lecteur absentes, liens utilisables.
  await expect(page.locator('#lecteur-demo video')).toHaveAttribute('controls', '')
  await expect(page.locator('#lecteur-demo').getByRole('button', { name: 'Lecture' })).toBeHidden()
  await expect(page.locator('#lecteur-demo').getByRole('link', { name: /Plein écran/ })).toHaveAttribute('href', '/gabarits/repeter?section=2')
  await page.getByRole('button', { name: /^Mode sombre/ }).click()
  await expect(page).toHaveURL(ORIGINE + '/gabarits?section=2')
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'sombre')
  await contexte.close()
})

test('lecteur sans vidéo : la phrase « Pas encore de vidéo » n’est recouverte par rien', async ({ page }) => {
  for (const taille of ['100%', '200%']) {
    await page.goto('/gabarits')
    await page.evaluate((t) => {
      document.documentElement.style.fontSize = t
    }, taille)
    // Le contenu inert n'est pas « touchable » (elementFromPoint l'ignore) : on lève inert pour mesurer.
    await page.evaluate(() => document.querySelectorAll('[inert]').forEach((e) => e.removeAttribute('inert')))
    for (const id of ['#lecteur-vide', '#lecteur-etat-repetition']) {
      const lecteurVide = page.locator(id)
      await expect(lecteurVide.locator('[data-infos]')).toHaveCount(0)
      const ecran = lecteurVide.locator('.lecteur__ecran')
      await ecran.evaluate((e) => e.scrollIntoView({ block: 'start' }))
      expect(await ecran.evaluate((e) => e.scrollTop), `${id} ${taille}`).toBe(0)
      for (const texte of ['Démo sans vidéo', 'Pas encore de vidéo de référence pour cette chanson.']) {
        const element = lecteurVide.getByText(texte, { exact: true })
        const boite = await element.boundingBox()
        const cadre = await ecran.boundingBox()
        expect(boite && cadre, `${id} ${texte}`).toBeTruthy()
        if (!boite || !cadre) continue
        expect(boite.y + boite.height, `${id} ${taille} ${texte} : bas`).toBeLessThanOrEqual(cadre.y + cadre.height + 1)
        // Au centre du texte, c'est bien lui qu'on voit (rien ne le recouvre).
        const vu = await page.evaluate(
          ([x, y]) => document.elementFromPoint(x, y)?.textContent?.trim() ?? '',
          [boite.x + boite.width / 2, boite.y + boite.height / 2],
        )
        expect(vu, `${id} ${taille} ${texte}`).toBe(texte)
      }
    }
  }
})

test('lecteur : vitesse, miroir et préférences mémorisées', async ({ page }) => {
  await ouvrir(page)
  const l = lecteur(page)
  await expect(video(page)).not.toHaveAttribute('controls')

  await l.getByRole('button', { name: '0,5 ×' }).click()
  await expect(l.getByRole('button', { name: '0,5 ×' })).toHaveAttribute('aria-pressed', 'true')
  await expect(l.getByRole('button', { name: '1 ×' })).toHaveAttribute('aria-pressed', 'false')
  // La vitesse choisie est écrite (« ✓ »), pas seulement colorée.
  await expect(l.getByRole('button', { name: '0,5 ×' }).locator('.commande__coche')).toBeVisible()
  await expect(l.getByRole('button', { name: '1 ×' }).locator('.commande__coche')).toBeHidden()
  expect(await video(page).evaluate((v: HTMLVideoElement) => v.playbackRate)).toBe(0.5)
  expect(await video(page).evaluate((v: HTMLVideoElement) => v.preservesPitch)).toBe(true)

  const miroir = l.getByRole('button', { name: /^Miroir/ })
  await miroir.click()
  await expect(miroir).toHaveAttribute('aria-pressed', 'true')
  await expect(miroir).toContainText('activé')
  await expect(l).toHaveClass(/lecteur--miroir/)
  await expect(l.locator('[data-badge-miroir]')).toBeVisible()
  await expect(l.locator('[data-badge-miroir]')).toHaveText('Miroir activé')
  expect(await video(page).evaluate((v) => getComputedStyle(v).transform)).toBe('matrix(-1, 0, 0, 1, 0, 0)')
  // Les surimpressions ne sont jamais retournées.
  expect(await l.locator('[data-badge-miroir]').evaluate((b) => getComputedStyle(b).transform)).toBe('none')

  await page.reload()
  await expect(lecteur(page)).toHaveClass(/lecteur--miroir/)
  await expect(lecteur(page).getByRole('button', { name: '0,5 ×' })).toHaveAttribute('aria-pressed', 'true')
  expect(await video(page).evaluate((v: HTMLVideoElement) => v.playbackRate)).toBe(0.5)
})

test('lecteur : décompte 5, 6, 7, 8 au tempo puis la lecture démarre', async ({ page }) => {
  await ouvrir(page)
  const l = lecteur(page)
  await page.locator('[data-ligne-section="1"] a').click()
  await expect(page).toHaveURL(/section=2/)
  await l.getByRole('button', { name: 'Lecture' }).click()
  const texte = l.locator('[data-decompte-texte]')
  const debut = Date.now()
  for (const n of [5, 6, 7, 8]) await expect(texte).toHaveText(`Décompte : ${n}`, { timeout: 3_000 })
  await expect(l.getByRole('button', { name: 'Pause' })).toBeVisible({ timeout: 3_000 })
  // Tempo inconnu : 120 par minute, soit quatre temps de 500 ms avant la lecture.
  expect(Date.now() - debut).toBeGreaterThanOrEqual(1_400)
  await expect.poll(() => video(page).evaluate((v: HTMLVideoElement) => !v.paused && v.currentTime > 8)).toBe(true)
  await expect(l.locator('[data-voile-decompte]')).toBeHidden()
  await l.getByRole('button', { name: 'Pause' }).click()
  await expect.poll(() => video(page).evaluate((v: HTMLVideoElement) => v.paused)).toBe(true)
})

test('lecteur : Arrêter pendant le décompte, boucle qui ramène au début de la section', async ({ page }) => {
  await ouvrir(page)
  const l = lecteur(page)
  await l.getByRole('button', { name: 'Lecture' }).click()
  await l.getByRole('button', { name: 'Arrêter' }).click()
  await expect(l.locator('[data-voile-decompte]')).toBeHidden()
  await expect(l.getByRole('button', { name: 'Lecture' })).toBeVisible()

  const boucle = l.getByRole('button', { name: /^Boucle/ })
  await boucle.click()
  await expect(boucle).toHaveAttribute('aria-pressed', 'true')
  await expect(boucle).toContainText('activée')
  await l.getByRole('button', { name: 'Lecture' }).click()
  await expect(l.getByRole('button', { name: 'Pause' })).toBeVisible({ timeout: 5_000 })
  // Section A : 0 à 8 s. On saute juste avant la fin : la boucle doit ramener au début.
  await video(page).evaluate((v: HTMLVideoElement) => {
    v.currentTime = 7.6
  })
  await expect.poll(() => video(page).evaluate((v: HTMLVideoElement) => v.currentTime), { timeout: 5_000 }).toBeLessThan(2)
  expect(await video(page).evaluate((v: HTMLVideoElement) => v.paused)).toBe(false)
})

test('lecteur au clavier : raccourcis, Tab et Entrée, raccourcis désactivables', async ({ page }) => {
  await ouvrir(page)
  const l = lecteur(page)
  // Focus hors du lecteur : les raccourcis ne font rien (la page défile normalement).
  await page.locator('h1').click()
  await page.keyboard.press('m')
  await expect(l.getByRole('button', { name: /^Miroir/ })).toHaveAttribute('aria-pressed', 'false')
  // Focus dans le lecteur : ils agissent, et le changement est annoncé.
  await l.getByRole('button', { name: '1 ×' }).focus()
  await page.keyboard.press('m')
  await expect(l.getByRole('button', { name: /^Miroir/ })).toHaveAttribute('aria-pressed', 'true')
  await expect(l.locator('[data-annonce]')).toHaveText('Miroir activé')
  await page.keyboard.press('1')
  await expect(l.getByRole('button', { name: '0,5 ×' })).toHaveAttribute('aria-pressed', 'true')
  await expect(l.locator('[data-annonce]')).toHaveText('Vitesse 0,5 ×')
  await page.keyboard.press('b')
  await expect(l.getByRole('button', { name: /^Boucle/ })).toHaveAttribute('aria-pressed', 'true')
  await page.keyboard.press('n')
  await expect(page.locator('[data-ligne-section="1"] a')).toHaveAttribute('aria-current', 'true')
  await expect(l.locator('[data-annonce]')).toHaveText(/Section B/)
  await page.keyboard.press('p')
  await expect(page.locator('[data-ligne-section="0"] a')).toHaveAttribute('aria-current', 'true')

  // Tab jusqu'au bouton Lecture, puis Entrée.
  const lecture = l.getByRole('button', { name: 'Lecture' })
  await lecture.focus()
  await page.keyboard.press('Tab')
  await expect(l.getByRole('link', { name: /Plein écran/ })).toBeFocused()
  await page.keyboard.press('Shift+Tab')
  await expect(lecture).toBeFocused()
  await page.keyboard.press('Enter')
  await expect(l.getByRole('button', { name: 'Arrêter' })).toBeFocused()
  await page.keyboard.press('Enter')

  // Raccourcis désactivés : M ne fait plus rien.
  await l.getByText('Raccourcis clavier').click()
  const raccourcis = l.getByRole('button', { name: /^Raccourcis/ })
  await raccourcis.click()
  await expect(raccourcis).toHaveAttribute('aria-pressed', 'false')
  await expect(raccourcis).toContainText('désactivés')
  await l.getByRole('button', { name: '1 ×' }).focus()
  await page.keyboard.press('m')
  await expect(l.getByRole('button', { name: /^Miroir/ })).toHaveAttribute('aria-pressed', 'true')
})

test('commentaire horodaté : « à 0:12 » positionne le lecteur sans recharger, la section suit', async ({ page }) => {
  await ouvrir(page)
  await page.locator('[data-ligne-section="0"] a').click()
  await expect(page).toHaveURL(/section=1/)
  await page.evaluate(() => {
    ;(window as unknown as { marque: number }).marque = 1
  })
  await page.getByRole('link', { name: /à 0:12/ }).click()
  await expect.poll(() => video(page).evaluate((v: HTMLVideoElement) => Math.round(v.currentTime))).toBe(12)
  expect(await page.evaluate(() => (window as unknown as { marque?: number }).marque)).toBe(1)
  await expect(page).toHaveURL(/section=2/)
  await expect(lecteur(page).locator('[data-nom-section]')).toHaveText('Section B')
  await expect(page.locator('[data-ligne-section="1"] a')).toHaveAttribute('aria-current', 'true')
  await expect(lecteur(page).getByRole('button', { name: 'Lecture' })).toBeFocused()
  // Lecture compte depuis 0:12, pas depuis le début de la section.
  await page.keyboard.press('Enter')
  await expect(lecteur(page).getByRole('button', { name: 'Pause' })).toBeVisible({ timeout: 5_000 })
  expect(await video(page).evaluate((v: HTMLVideoElement) => v.currentTime)).toBeGreaterThanOrEqual(12)
})

test('boucle sur la dernière section : la fin de la vidéo ramène au début de la section', async ({ page }) => {
  await ouvrir(page)
  const l = lecteur(page)
  await page.locator('[data-ligne-section="2"] a').click()
  await l.getByRole('button', { name: /^Boucle/ }).click()
  await l.getByRole('button', { name: 'Lecture' }).click()
  await expect(l.getByRole('button', { name: 'Pause' })).toBeVisible({ timeout: 5_000 })
  await video(page).evaluate((v: HTMLVideoElement) => {
    v.currentTime = 19.5
  })
  await expect.poll(() => video(page).evaluate((v: HTMLVideoElement) => v.currentTime), { timeout: 5_000 }).toBeLessThan(18)
  expect(await video(page).evaluate((v: HTMLVideoElement) => v.paused || v.ended)).toBe(false)
})

test('décompte jamais coupé : petits écrans, texte agrandi à 200 %', async ({ page }, info) => {
  const fenetres =
    info.project.name === 'bureau'
      ? [
          { width: 1280, height: 900 },
          { width: 1280, height: 600 },
        ]
      : [
          { width: 390, height: 844 },
          { width: 375, height: 667 },
          { width: 360, height: 640 },
        ]
  for (const fenetre of fenetres) {
    await page.setViewportSize(fenetre)
    for (const taille of ['100%', '200%']) {
      for (const [adresse, id] of [
        ['/gabarits', '#lecteur-demo'],
        ['/gabarits/repeter', '#lecteur-repetition'],
      ] as const) {
        await page.goto(adresse)
        await page.evaluate((t) => {
          document.documentElement.style.fontSize = t
        }, taille)
        const l = page.locator(id)
        await l.getByRole('button', { name: 'Lecture' }).click()
        await expect(l.locator('[data-decompte-texte]')).toHaveText(/Décompte/)
        const ecran = await l.locator('.lecteur__ecran').boundingBox()
        for (const partie of ['[data-decompte-chiffre]', '[data-decompte-texte]']) {
          const boite = await l.locator(partie).boundingBox()
          const ou = `${fenetre.width} × ${fenetre.height}, texte ${taille}, ${adresse} ${partie}`
          expect(boite && ecran, ou).toBeTruthy()
          if (!boite || !ecran) continue
          expect(boite.y, `${ou} : haut`).toBeGreaterThanOrEqual(ecran.y - 1)
          expect(boite.y + boite.height, `${ou} : bas`).toBeLessThanOrEqual(ecran.y + ecran.height + 1)
        }
        await l.getByRole('button', { name: 'Arrêter' }).click()
      }
    }
  }
})

test('scène : flèches ← et → à leur place et cliquables, à la première et à la dernière section', async ({ page }) => {
  for (const section of [1, 2, 3]) {
    await page.goto(`/gabarits?section=${section}`)
    const navigation = page.locator('.scene__navigation')
    await navigation.scrollIntoViewIfNeeded()
    const avant = await navigation.locator('.scene__fleche-section--avant').boundingBox()
    const apres = await navigation.locator('.scene__fleche-section--apres').boundingBox()
    const afficher = await navigation.getByRole('button', { name: 'Afficher cette section' }).boundingBox()
    expect(avant && apres && afficher).toBeTruthy()
    if (!avant || !apres || !afficher) continue
    expect(avant.x + avant.width, `section ${section}`).toBeLessThanOrEqual(afficher.x)
    expect(apres.x, `section ${section}`).toBeGreaterThanOrEqual(afficher.x + afficher.width)
    for (const [nom, boite] of [
      ['précédente', avant],
      ['suivante', apres],
    ] as const) {
      const touche = await page.evaluate(
        ([x, y]) => document.elementFromPoint(x, y)?.closest('.scene__fleche-section')?.textContent?.trim() ?? '',
        [boite.x + boite.width / 2, boite.y + boite.height / 2],
      )
      expect(touche, `section ${section}, flèche ${nom}`).toContain(nom === 'précédente' ? '←' : '→')
    }
  }
  await page.goto('/gabarits?section=3')
  await page.getByRole('link', { name: 'Section précédente' }).click()
  await expect(page).toHaveURL(/section=2/)
})

test('320 px avec le texte à 200 % : aucun défilement horizontal', async ({ page }) => {
  await page.setViewportSize({ width: 320, height: 800 })
  for (const adresse of ['/gabarits', '/gabarits/repeter']) {
    await page.goto(adresse)
    await page.evaluate(() => {
      document.documentElement.style.fontSize = '200%'
      document.querySelectorAll('details').forEach((d) => {
        d.open = true
      })
    })
    const largeurs = await page.evaluate(() => [document.documentElement.scrollWidth, document.documentElement.clientWidth])
    expect(largeurs[0], adresse).toBeLessThanOrEqual(largeurs[1])
  }
})

test('mouvement réduit : aucune animation pendant le décompte', async ({ browser }) => {
  for (const [mouvement, attendu] of [
    ['reduce', 0],
    ['no-preference', 1],
  ] as const) {
    const contexte = await browser.newContext({ baseURL: ORIGINE, reducedMotion: mouvement })
    const page = await contexte.newPage()
    await page.goto('/gabarits')
    const bloc = page.locator('[data-decompte]')
    await bloc.getByRole('button', { name: 'Rejouer' }).click()
    await expect(bloc.locator('[data-decompte-texte]')).toHaveText('Décompte : 5')
    // Le chiffre porte la classe de l'animation ; seule la feuille décide si elle joue.
    const chiffre = bloc.locator('[data-decompte-chiffre]')
    await expect(chiffre).toHaveClass(/decompte__chiffre--bat/)
    const nom = await chiffre.evaluate((c) => getComputedStyle(c).animationName)
    if (attendu === 0) {
      expect(nom).toBe('none')
      expect(await page.evaluate(() => document.getAnimations().length)).toBe(0)
    } else {
      expect(nom).toBe('battement')
    }
    await expect(bloc.locator('[data-decompte-texte]')).toHaveText('Décompte : 8', { timeout: 3_000 })
    await contexte.close()
  }
})

test('zoom 200 % : aucun défilement horizontal', async ({ page }, info) => {
  // 1 280 px à 200 % = 640 px de large ; au téléphone, 320 px (largeur de référence de WCAG 1.4.10).
  await page.setViewportSize({ width: info.project.name === 'bureau' ? 640 : 320, height: 800 })
  for (const adresse of ['/gabarits', '/gabarits/repeter']) {
    await page.goto(adresse)
    const largeurs = await page.evaluate(() => [document.documentElement.scrollWidth, document.documentElement.clientWidth])
    expect(largeurs[0], adresse).toBeLessThanOrEqual(largeurs[1])
  }
})

test('rien sous 14 px dans les gabarits', async ({ page }) => {
  await page.goto('/gabarits')
  const petits = await page.evaluate(() =>
    [...document.querySelectorAll('body *')]
      .filter((el) => el.childNodes.length && [...el.childNodes].some((n) => n.nodeType === 3 && n.textContent?.trim()))
      .filter((el) => el.closest('.visuellement-cache') === null && (el as HTMLElement).offsetParent !== null)
      .filter((el) => parseFloat(getComputedStyle(el).fontSize) < 14)
      .map((el) => `${el.tagName}.${el.className} « ${el.textContent?.trim().slice(0, 30)} »`),
  )
  expect(petits).toEqual([])
})
