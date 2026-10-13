import { type Browser, type Page } from '@playwright/test'
import {
  ORIGINE,
  choisir,
  deposerReference,
  entrer,
  expect,
  ouvrirAdmin,
  poserSections,
  preparer,
  test,
  verifierAxe,
  verifierAxeDeuxThemes,
  type Preparation,
} from './outils'

// Phase 4 : faits du groupe importés, Ma page, page d'une chanson, niveaux et grille, placements, vidéo de
// référence, administration. Chaque test repart d'une base de test neuve (preparer) ; les données de test
// (« Section A », « Section B », vidéo « Démo » synthétique) sont effacées à la fin des tests.

async function nouveauCousin(browser: Browser, preparation: Preparation, prenom: string): Promise<Page> {
  const contexte = await browser.newContext({ baseURL: ORIGINE, locale: 'fr-FR' })
  await contexte.route('**/*', (route) => {
    const url = route.request().url()
    return url.startsWith(ORIGINE) || url.startsWith('data:') || url.startsWith('blob:') ? route.continue() : route.abort('blockedbyclient')
  })
  const page = await contexte.newPage()
  await entrer(page, preparation)
  await choisir(page, prenom)
  return page
}

test('Jessica : trois chorés, Kanimaa sans sections ni vidéo', async ({ page }) => {
  const preparation = preparer()
  await entrer(page, preparation)
  await choisir(page, 'Jessica')

  await expect(page.locator('.carte-chanson__titre')).toHaveText(['Celebration of Varisu', 'Kanimaa', 'Yaaro Yaarodi'])
  await expect(page.getByText('Proposées, pas tranchées')).toBeVisible()
  await expect(page.getByText('Maamadura, Sangeeth Celebration, The Wedding Song')).toBeVisible()
  await expect(page.getByRole('heading', { name: /Prochaine répétition/ })).toBeVisible()
  await expect(page.getByRole('navigation', { name: 'Aller à' }).getByRole('link')).toHaveText(['Placements', 'Répétitions', 'Vidéos des cousins', 'Jour J'])
  await verifierAxeDeuxThemes(page)

  await page.getByRole('link', { name: 'Répéter Kanimaa' }).click()
  await expect(page).toHaveURL(ORIGINE + '/chanson/kanimaa')
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Kanimaa')
  await expect(page.getByText('Les sections ne sont pas encore posées. Patrice peut les créer dans l’administration.')).toBeVisible()
  await expect(page.getByText('Pas encore de vidéo de référence pour cette chanson.')).toBeVisible()
  await expect(page.getByRole('link', { name: /Reprise dansée d’Ahinth Vijay/ })).toHaveAttribute('rel', 'noopener noreferrer')
  await verifierAxeDeuxThemes(page)
})

test('Nadine : un solo décidé à créer ; « qui s’en occupe » enregistré', async ({ page }) => {
  const preparation = preparer()
  await entrer(page, preparation)
  await choisir(page, 'Nadine')
  await expect(page.locator('.carte-chanson__titre')).toHaveText(['Innisai'])
  await expect(page.getByText('Choré décidée par le groupe')).toBeVisible()
  await expect(page.getByText(/Choré à créer/)).toBeVisible()

  await page.getByRole('link', { name: 'Répéter Innisai' }).click()
  await expect(page.getByText('[film à confirmer] · [durée inconnue] · tempo à confirmer (97 tpm ?) · solo de Nadine')).toBeVisible()
  await expect(page.getByText('La choré n’est pas encore posée. Qui s’en occupe ?')).toBeVisible()
  await page.getByRole('button', { name: 'Nadine', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Nadine s’en occupe' })).toHaveAttribute('aria-pressed', 'true')
  await expect(page.getByRole('status').filter({ hasText: 'C’est noté' })).toBeVisible()
  await verifierAxe(page)
})

test('Patrice : Thalapathy Kacheri en attente', async ({ page }) => {
  const preparation = preparer()
  await entrer(page, preparation)
  await choisir(page, 'Patrice')
  await expect(page.locator('.carte-chanson__titre')).toHaveText(['Celebration of Varisu', 'Kanimaa', 'Thalapathy Kacheri'])
  await expect(page.getByText('En attente — tu as demandé à t’ajouter, pas encore de réponse. Tu peux déjà répéter.')).toBeVisible()
  await page.getByRole('link', { name: 'Répéter Thalapathy Kacheri' }).click()
  await expect(page.getByText('Alex, Fabien, Flobert · Patrice en attente').first()).toBeVisible()
  await expect(page.getByRole('link', { name: /Mahir Shanz, vue miroir/ })).toBeVisible()
  await verifierAxe(page)
})

test('administration : sections A et B, puis Jessica marque « Je maîtrise » ; grille et progression suivent', async ({ page, browser }) => {
  const preparation = preparer()
  await entrer(page, preparation)
  await ouvrirAdmin(page, preparation)
  await verifierAxe(page)
  await poserSections(page, 'kanimaa')
  await expect(page.getByRole('heading', { name: '1. Section A' })).toBeVisible()
  await expect(page.getByRole('heading', { name: '2. Section B' })).toBeVisible()
  await verifierAxe(page)

  // Une section qui chevauche est refusée en toutes lettres, sans ressaisie.
  const formulaire = page.locator('section[aria-labelledby="nouvelle-section"]')
  await formulaire.getByLabel('Nom').fill('Section C')
  await formulaire.getByLabel('Début').fill('0:10')
  await formulaire.getByLabel('Fin', { exact: true }).fill('0:20')
  await formulaire.getByLabel('Comptes de 8').fill('2')
  await formulaire.getByRole('button', { name: 'Ajouter la section' }).click()
  await expect(page.getByRole('alert')).toContainText('chevauche « Section B »')
  await expect(page.locator('#nouvelle-nom')).toHaveValue('Section C')
  await verifierAxe(page)

  const jessica = await nouveauCousin(browser, preparation, 'Jessica')
  await jessica.goto('/chanson/kanimaa')
  const lignes = jessica.locator('.ligne-section')
  await expect(lignes).toHaveCount(2)
  await lignes.nth(1).getByRole('link').click()
  await expect(lignes.nth(1).getByText('en cours')).toBeVisible()
  await expect(jessica).toHaveURL(ORIGINE + '/chanson/kanimaa?section=2')

  await lignes.nth(0).getByRole('button', { name: 'Je maîtrise' }).click()
  await expect(lignes.nth(0).getByRole('button', { name: 'Je maîtrise' })).toHaveAttribute('aria-pressed', 'true')
  await expect(jessica.getByText('Toi : 1 section sur 2 maîtrisée.')).toBeVisible()
  await expect(jessica.locator('.grille-maitrise__moi td').first()).toHaveClass(/grille-maitrise__case--2/)
  await verifierAxeDeuxThemes(jessica)

  await jessica.reload()
  await expect(jessica.locator('.grille-maitrise__moi td').first()).toHaveClass(/grille-maitrise__case--2/)
  await jessica.goto('/moi')
  await expect(jessica.getByText('1 section sur 2 maîtrisée').first()).toBeVisible()
  await expect(jessica.getByText('Jessica maîtrise Section A')).toBeVisible()
  await jessica.context().close()
})

test.describe('sans JavaScript', () => {
  test.use({ javaScriptEnabled: false })

  test('niveaux et grille par formulaire', async ({ page }) => {
    const preparation = preparer()
    await entrer(page, preparation)
    await ouvrirAdmin(page, preparation)
    await poserSections(page, 'yaaro-yaarodi')
    await page.goto('/')
    await choisir(page, 'Josiane')
    await page.goto('/chanson/yaaro-yaarodi?section=2')
    const ligne = page.locator('.ligne-section').nth(1)
    await ligne.getByRole('button', { name: 'Presque' }).click()
    await expect(page).toHaveURL(/\/chanson\/yaaro-yaarodi\?section=2#section-\d+$/)
    await expect(page.locator('.ligne-section').nth(1).getByRole('button', { name: 'Presque' })).toHaveAttribute('aria-pressed', 'true')
    await expect(page.locator('.grille-maitrise__moi td').nth(1)).toHaveClass(/grille-maitrise__case--1/)
  })
})

test('placements : Fabien se déplace d’un pas, Alex le voit', async ({ page, browser }) => {
  const preparation = preparer()
  await entrer(page, preparation)
  await ouvrirAdmin(page, preparation)
  await poserSections(page, 'kanimaa')
  await page.goto('/admin/chansons/kanimaa/placements')
  for (const [prenom, x, y] of [
    ['Fabien', '50', '50'],
    ['Alex', '25', '50'],
  ]) {
    const point = page.locator('fieldset.admin__point').filter({ hasText: prenom })
    await point.getByLabel(/^x/).fill(x)
    await point.getByLabel(/^y/).fill(y)
  }
  await page.getByRole('button', { name: 'Enregistrer les placements' }).first().click()
  await expect(page.getByText('Placements enregistrés')).toBeVisible()
  await verifierAxe(page)
  // Entrée dans un champ enregistre ; elle ne déplace personne (premier bouton du formulaire : « Enregistrer »).
  const josiane = page.locator('fieldset.admin__point').filter({ hasText: 'Josiane' })
  await josiane.getByLabel(/^x/).fill('75')
  await josiane.getByLabel(/^y/).fill('50')
  await josiane.getByLabel(/^y/).press('Enter')
  await expect(page.getByText('Placements enregistrés')).toBeVisible()
  await expect(page.locator('fieldset.admin__point').filter({ hasText: 'Alex' }).getByLabel(/^x/)).toHaveValue('25')
  await expect(page.locator('fieldset.admin__point').filter({ hasText: 'Josiane' }).getByLabel(/^x/)).toHaveValue('75')

  const fabien = await nouveauCousin(browser, preparation, 'Fabien')
  await fabien.goto('/placements/kanimaa')
  await expect(fabien.getByRole('link', { name: /Kanimaa/ }).first()).toHaveAttribute('aria-current', 'page')
  await expect(fabien.locator('.scene__non-places')).toContainText('Non placés :')
  await verifierAxeDeuxThemes(fabien)
  await fabien.getByRole('button', { name: 'Me déplacer à droite' }).click()
  await expect(fabien.getByText('C’est enregistré : tout le monde voit ta nouvelle place.')).toBeVisible()

  const alex = await nouveauCousin(browser, preparation, 'Alex')
  await alex.goto('/placements/kanimaa')
  await expect(alex.locator('.scene__liste li').filter({ hasText: 'Fabien' })).toContainText('Fabien : au centre, au milieu')
  await expect(alex.locator('.scene__liste li').filter({ hasText: 'Alex' })).toContainText('Alex (toi) : côté jardin (à gauche), au milieu')
  await alex.goto('/placements/kanimaa?section=1&scene=1')
  await expect(alex.getByText('Mode scène', { exact: true })).toBeVisible()
  await verifierAxe(alex)
  await fabien.context().close()
  await alex.context().close()
})

test('vidéo de référence déposée : lecteur et mode répétition sur de vraies sections', async ({ page }) => {
  const preparation = preparer()
  await entrer(page, preparation)
  await ouvrirAdmin(page, preparation)
  await poserSections(page, 'kanimaa')
  await deposerReference(page, 'kanimaa')
  await verifierAxe(page)

  await page.goto('/')
  await choisir(page, 'Catherine')
  await page.goto('/chanson/kanimaa')
  const lecteur = page.locator('#lecteur-chanson')
  await expect(lecteur.locator('video')).toHaveAttribute('src', /^\/m\/\d+\?v=[0-9a-f]{8}$/)
  await lecteur.getByRole('button', { name: '0,5 ×' }).click()
  await lecteur.getByRole('button', { name: /^Miroir/ }).click()
  await lecteur.getByRole('button', { name: /^Boucle/ }).click()
  await expect(lecteur.getByRole('button', { name: /^Boucle/ })).toHaveAttribute('aria-pressed', 'true')
  await lecteur.getByRole('button', { name: 'Lecture' }).click()
  await expect(lecteur.locator('[data-decompte-texte]')).toHaveText(/Décompte : [5-8]/)
  await expect(lecteur.getByRole('button', { name: 'Pause' })).toBeVisible({ timeout: 10_000 })
  await lecteur.getByRole('button', { name: 'Pause' }).click()

  await page.goto('/chanson/kanimaa/repeter?section=1')
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'sombre')
  const r = page.locator('#lecteur-repetition')
  await expect(r.locator('[data-nom-section]')).toHaveText('Section A')
  await r.getByRole('button', { name: 'Lecture' }).click()
  await expect(r.getByRole('button', { name: 'Pause' })).toBeVisible({ timeout: 10_000 })
  await r.getByRole('button', { name: 'Section suivante' }).click()
  await expect(r.locator('[data-nom-section]')).toHaveText('Section B')
  await r.getByRole('button', { name: 'Arrêter' }).click()
  await verifierAxe(page)
  await r.getByRole('link', { name: 'Quitter' }).click()
  await expect(page).toHaveURL(ORIGINE + '/chanson/kanimaa?section=2#lecteur-chanson')

  // Le flux vidéo exige le cookie d'accès.
  const reponse = await page.request.get(await lecteur.locator('video').getAttribute('src').then((s) => s ?? ''), { headers: { Range: 'bytes=0-99' } })
  expect(reponse.status()).toBe(206)
})

test('administration invisible sans clé ; écrans d’administration accessibles', async ({ page }) => {
  const preparation = preparer()
  await entrer(page, preparation)
  await choisir(page, 'Patrice')
  for (const adresse of ['/admin', '/admin/chansons', '/admin/chansons/kanimaa', '/admin/reglages', '/admin/journal']) {
    const reponse = await page.goto(adresse)
    expect(reponse?.status(), adresse).toBe(404)
  }
  await ouvrirAdmin(page, preparation)
  // Aucune question du groupe n'est versionnée (dépôt public) : le tableau s'ouvre, l'espace des vidéos s'affiche.
  await expect(page.getByText(/Vidéos : /)).toBeVisible()
  for (const adresse of ['/admin/chansons', '/admin/chansons/kanimaa', '/admin/chansons/kanimaa/sections', '/admin/chansons/kanimaa/placements', '/admin/reglages', '/admin/journal']) {
    await page.goto(adresse)
    await verifierAxe(page)
  }
  await page.goto('/admin/chansons/kanimaa')
  // Trois touchers à une demi-seconde d'écart : environ 120 temps par minute.
  for (let i = 0; i < 3; i++) {
    await page.getByRole('button', { name: 'Taper le tempo' }).click()
    await page.waitForTimeout(500)
  }
  await expect(page.locator('[data-tempo-etat]')).toContainText('temps par minute')

  await page.goto('/admin/reglages')
  await page.getByLabel('Lien du sondage de dates').fill('http://pas-sur.test')
  await page.getByRole('button', { name: 'Enregistrer les réglages' }).click()
  await expect(page.getByRole('alert')).toContainText('https://')
  await verifierAxe(page)
})

test('répétition importée d’etat.json (date et lieu, sans réponse du groupe)', async ({ page }) => {
  const preparation = preparer()
  await entrer(page, preparation)
  await choisir(page, 'Flobert')
  await page.goto('/repetitions')
  await expect(page.getByText('Villiers')).toBeVisible()
  await verifierAxeDeuxThemes(page)
})

test('pages des phases 4 et 5 : 320 px avec le texte à 200 % sans défilement horizontal, rien sous 14 px', async ({ page }) => {
  const preparation = preparer()
  await entrer(page, preparation)
  await ouvrirAdmin(page, preparation)
  await poserSections(page, 'kanimaa')
  await page.goto('/admin/chansons/kanimaa/placements')
  const point = page.locator('fieldset.admin__point').filter({ hasText: 'Catherine' })
  await point.getByLabel(/^x/).fill('50')
  await point.getByLabel(/^y/).fill('50')
  await page.getByRole('button', { name: 'Enregistrer les placements' }).first().click()
  await page.goto('/')
  await choisir(page, 'Catherine')
  const adresses = [
    '/moi',
    '/chanson/kanimaa',
    '/chanson/yaaro-yaarodi',
    '/chanson/kanimaa/repeter',
    '/placements/kanimaa',
    '/placements/kanimaa?scene=1',
    '/repetitions',
    '/videos',
    '/videos/ajouter',
    '/jour-j',
    '/hors-ligne',
    '/admin',
    '/admin/repetitions',
    '/admin/repetitions/1',
    '/admin/jour-j',
    '/admin/videos',
    '/admin/chansons',
    '/admin/chansons/kanimaa',
    '/admin/chansons/kanimaa/sections',
    '/admin/chansons/kanimaa/placements',
    '/admin/reglages',
    '/admin/journal',
  ]
  for (const adresse of adresses) {
    await page.setViewportSize({ width: 390, height: 844 })
    await page.goto(adresse)
    const petits = await page.evaluate(() =>
      [...document.querySelectorAll('body *')]
        .filter((el) => el.childNodes.length && [...el.childNodes].some((n) => n.nodeType === 3 && n.textContent?.trim()))
        .filter((el) => el.closest('.visuellement-cache') === null && (el as HTMLElement).offsetParent !== null)
        .filter((el) => parseFloat(getComputedStyle(el).fontSize) < 14)
        .map((el) => `${el.tagName}.${el.className} « ${el.textContent?.trim().slice(0, 30)} »`),
    )
    expect(petits, adresse).toEqual([])
    await page.setViewportSize({ width: 320, height: 800 })
    await page.evaluate(() => {
      document.documentElement.style.fontSize = '200%'
    })
    const largeurs = await page.evaluate(() => [document.documentElement.scrollWidth, document.documentElement.clientWidth])
    expect(largeurs[0], adresse).toBeLessThanOrEqual(largeurs[1])
  }
})
