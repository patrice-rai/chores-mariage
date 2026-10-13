import { ORIGINE, expect, test, verifierAxe } from './outils'

// Phase 3 : mode répétition sur la chanson « Démo » (toujours sombre, grandes commandes, Section suivante, Quitter).

test('mode répétition : toujours sombre, axe sans violation, grandes commandes', async ({ page }) => {
  await page.emulateMedia({ colorScheme: 'light' })
  await page.goto('/gabarits/repeter?section=1')
  await expect(page).toHaveTitle('Répéter Démo — Chorés du mariage')
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'sombre')
  const l = page.locator('#lecteur-repetition')
  const lecture = l.getByRole('button', { name: 'Lecture' })
  await expect(lecture).toBeVisible()
  expect((await lecture.boundingBox())?.height).toBeGreaterThanOrEqual(72)
  expect((await l.getByRole('button', { name: 'Section suivante' }).boundingBox())?.height).toBeGreaterThanOrEqual(72)
  expect((await l.getByRole('button', { name: '0,75 ×' }).boundingBox())?.height).toBeGreaterThanOrEqual(60)
  await expect(l.locator('[data-statut]')).toHaveText('Pas de miroir · 1 ×')
  await expect(l.locator('[data-nom-section]')).toHaveText('Section A')
  await verifierAxe(page)

  await l.getByRole('button', { name: /^Miroir/ }).click()
  await l.getByRole('button', { name: '0,75 ×' }).click()
  await l.getByRole('button', { name: /^Boucle/ }).click()
  await expect(l.locator('[data-statut]')).toHaveText('Miroir · 0,75 × · Boucle')
  await verifierAxe(page)
})

test('mode répétition : Section suivante sans rechargement, décompte en grand, Quitter', async ({ page }, info) => {
  await page.goto('/gabarits/repeter?section=1')
  const l = page.locator('#lecteur-repetition')
  await l.getByRole('button', { name: 'Section suivante' }).click()
  await expect(l.locator('[data-nom-section]')).toHaveText('Section B')
  await expect(l.locator('[data-comptes-section]')).toHaveText('2 × 8 · 0:08')
  await expect(page).toHaveURL(ORIGINE + '/gabarits/repeter?section=2')

  await l.getByRole('button', { name: 'Lecture' }).click()
  const chiffre = l.locator('[data-decompte-chiffre]')
  await expect(chiffre).toBeVisible()
  const taille = await chiffre.evaluate((c) => parseFloat(getComputedStyle(c).fontSize))
  // 260 px (plafonné à 60 % de la hauteur de l'écran vidéo quand il est petit).
  expect(taille).toBeGreaterThanOrEqual(info.project.name === 'bureau' ? 260 : 259)
  await expect(l.getByRole('button', { name: 'Pause' })).toBeVisible({ timeout: 5_000 })
  // Section suivante pendant la lecture : on passe à C et le décompte repart.
  await l.getByRole('button', { name: 'Section suivante' }).click()
  await expect(l.locator('[data-nom-section]')).toHaveText('Section C')
  await expect(l.getByRole('button', { name: 'Arrêter' })).toBeVisible()
  await l.getByRole('button', { name: 'Arrêter' }).click()

  await l.getByRole('link', { name: 'Quitter' }).click()
  await expect(page).toHaveURL(ORIGINE + '/gabarits?section=3#lecteur-demo')
})

test('mode répétition : Échap quitte, une vraie chanson demande le lien', async ({ page }) => {
  await page.goto('/gabarits/repeter?section=2')
  const l = page.locator('#lecteur-repetition')
  await expect(l.getByRole('button', { name: 'Lecture' })).toBeVisible()
  // En mode répétition, les raccourcis marchent sans focus particulier : Espace lance puis arrête le décompte.
  await page.keyboard.press(' ')
  await expect(l.getByRole('button', { name: 'Arrêter' })).toBeVisible()
  await page.keyboard.press(' ')
  await expect(l.getByRole('button', { name: 'Lecture' })).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(page).toHaveURL(ORIGINE + '/gabarits?section=2#lecteur-demo')

  await page.goto('/chanson/kanimaa/repeter')
  await expect(page).toHaveURL(ORIGINE + '/lien')
})
