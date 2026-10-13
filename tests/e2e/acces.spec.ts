import { ORIGINE, choisir, entrer, expect, preparer, test, verifierAxe } from './outils'

// Phase 2 : entrée par le lien secret, prénom, Ma page, administration, renouvellement du lien.

test('un faux lien mène à la page « lien manquant »', async ({ page }) => {
  preparer()
  await page.goto('/e/' + 'A'.repeat(43))
  await expect(page).toHaveURL(ORIGINE + '/lien')
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Chorés du mariage')
  await expect(page.getByText('Ce lien n’est plus valable (ou tu n’en as pas). Demande le nouveau à Patrice.')).toBeVisible()
  await verifierAxe(page)
})

test('sans cookie, Ma page renvoie vers la page du lien', async ({ page }) => {
  await page.goto('/moi')
  await expect(page).toHaveURL(ORIGINE + '/lien')
  await page.goto('/')
  await expect(page).toHaveURL(ORIGINE + '/lien')
})

test('lien → prénom → Ma page → Changer → entrée', async ({ page, context }) => {
  const preparation = preparer()
  await entrer(page, preparation)

  await expect(page).toHaveTitle('Entrée — Chorés du mariage')
  const prenoms = page.locator('.bouton-prenom')
  await expect(prenoms).toHaveText(['Alex', 'Catherine', 'Fabien', 'Flobert', 'Jessica', 'Josiane', 'Nadine', 'Patrice', 'Stéphane'])
  const boite = await prenoms.first().boundingBox()
  expect(boite?.height).toBeGreaterThanOrEqual(72)
  await verifierAxe(page)

  await choisir(page, 'Jessica')
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Salut Jessica')
  await expect(page).toHaveTitle('Ma page — Chorés du mariage')
  await verifierAxe(page)

  const cookies = await context.cookies()
  for (const nom of ['cm_acces', 'cm_prenom']) {
    const cookie = cookies.find((c) => c.name === nom)
    expect(cookie, nom).toBeDefined()
    expect(cookie?.httpOnly).toBe(true)
    expect(cookie?.sameSite).toBe('Lax')
  }

  // Le prénom est mémorisé : revenir sur l'accueil mène à Ma page.
  await page.goto('/')
  await expect(page).toHaveURL(ORIGINE + '/moi')

  await page.getByRole('button', { name: /^Changer/ }).click()
  await expect(page).toHaveURL(ORIGINE + '/')
  await expect(page.getByRole('button', { name: 'Jessica', exact: true })).toBeVisible()
})

test('le lien d’évitement mène au contenu', async ({ page }) => {
  const preparation = preparer()
  await entrer(page, preparation)
  await page.keyboard.press('Tab')
  const evitement = page.getByRole('link', { name: 'Aller au contenu' })
  await expect(evitement).toBeFocused()
  await expect(evitement).toBeVisible()
  await page.keyboard.press('Enter')
  await expect(page.locator('main')).toBeFocused()
})

test('administration : 404 sans clé, tableau avec la clé', async ({ page }) => {
  const preparation = preparer()
  const sansCle = await page.goto('/admin')
  expect(sansCle?.status()).toBe(404)
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Page introuvable')

  await entrer(page, preparation)
  await choisir(page, 'Patrice')
  const toujoursSansCle = await page.goto('/admin')
  expect(toujoursSansCle?.status()).toBe(404)

  const mauvaiseCle = await page.goto('/admin/cle/' + 'x'.repeat(43))
  expect(mauvaiseCle?.status()).toBe(404)

  await page.goto(preparation.cle)
  await expect(page).toHaveURL(ORIGINE + '/admin')
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Administration')
  await expect(page.getByText(/Lien en service : version \d+/)).toBeVisible()
  await expect(page.getByRole('table', { name: 'Choix du prénom, par cousin' })).toBeVisible()
  await expect(page.getByRole('table', { name: /dernières lignes/ })).toBeVisible()
  await verifierAxe(page)
})

test('un nouveau lien invalide l’ancien cookie et l’ancien lien', async ({ page, browser }) => {
  const preparation = preparer()
  await entrer(page, preparation)
  await choisir(page, 'Patrice')
  // Un autre appareil d'un cousin, entré avec l'ancien lien.
  const autre = await browser.newContext({ baseURL: ORIGINE })
  const autrePage = await autre.newPage()
  await entrer(autrePage, preparation)
  await page.goto(preparation.cle)
  await page.getByRole('link', { name: 'Nouveau lien' }).click()
  await expect(page).toHaveURL(ORIGINE + '/admin/lien')
  await verifierAxe(page)

  await page.getByRole('button', { name: 'Créer un nouveau lien' }).click()
  await expect(page.getByRole('status').filter({ hasText: 'Nouveau lien créé' })).toBeVisible()
  const nouveau = await page.getByLabel('Le lien').inputValue()
  expect(nouveau).toMatch(/^http:\/\/127\.0\.0\.1:8095\/e\/[A-Za-z0-9_-]{43}$/)
  await expect(page.getByLabel('Texte prêt à coller dans WhatsApp')).toHaveValue(new RegExp(nouveau.replace(/[.*+?^${}()|[\]\\/]/g, '\\$&')))
  await expect(page.getByText('Pense à l’envoyer aussi à Jessica et Stéphane')).toBeVisible()
  await verifierAxe(page)

  // L'ancien cookie d'accès de l'autre appareil ne vaut plus rien, l'ancien lien non plus.
  await autrePage.goto('/')
  await expect(autrePage).toHaveURL(ORIGINE + '/lien')
  await autrePage.goto(preparation.lien)
  await expect(autrePage).toHaveURL(ORIGINE + '/lien')
  // Le nouveau lien rouvre la porte ; le prénom est à rechoisir (renouveler efface tout, spec § 6).
  await autrePage.goto(nouveau.replace(ORIGINE, ''))
  await expect(autrePage).toHaveURL(ORIGINE + '/')
  await autre.close()

  // L'appareil de Patrice suit le nouveau lien, mais le prénom est à rechoisir aussi.
  await page.goto('/moi')
  await expect(page).toHaveURL(ORIGINE + '/')
  await choisir(page, 'Patrice')
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Salut Patrice')

  // Revenir sur le formulaire et le renvoyer ne remplace pas le lien qui vient d'être diffusé.
  await page.goto(preparation.cle)
  await page.goto('/admin/lien')
  const versionAvant = await page.locator('input[name="version"]').inputValue()
  await page.evaluate(() => {
    const champ = document.querySelector<HTMLInputElement>('input[name="version"]')
    if (champ) champ.value = String(Number(champ.value) - 1)
  })
  await page.getByRole('button', { name: 'Créer un nouveau lien' }).click()
  await expect(page.getByRole('status').filter({ hasText: 'le lien a déjà été renouvelé' })).toBeVisible()
  await page.goto('/moi')
  await expect(page).toHaveURL(ORIGINE + '/moi')
  expect(versionAvant).toMatch(/^\d+$/)
})

test('un formulaire sans jeton CSRF est refusé', async ({ page }) => {
  const preparation = preparer()
  await entrer(page, preparation)
  const reponse = await page.request.post('/prenom', { form: { cousin: '1' }, maxRedirects: 0 })
  expect(reponse.status()).toBe(400)
})

test('rien ne sort : une ressource externe est bloquée et détectée', async ({ page, requetesExternes }) => {
  // La CSP (connect-src 'self') arrête déjà un fetch externe dans la page ; la navigation, elle, passe par
  // l'interception, qui doit la bloquer et la compter.
  await page.goto('https://exemple.invalid/traceur').catch(() => undefined)
  expect(requetesExternes).toEqual(['https://exemple.invalid/traceur'])
  // Vidé pour que la vérification automatique de fin de test ne compte que les vraies fuites.
  requetesExternes.length = 0
})
