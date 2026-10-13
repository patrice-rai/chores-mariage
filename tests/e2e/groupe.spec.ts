import { type BrowserContext, type Page } from '@playwright/test'
import {
  ORIGINE,
  choisir,
  deposerReference,
  entrer,
  expect,
  ouvrirAdmin,
  preparer,
  test,
  verifierAxe,
  verifierAxeDeuxThemes,
} from './outils'

// Phase 5 : répétitions et présences, vidéos des cousins (téléversement avec progression, commentaire horodaté,
// suppression), Jour J et check-list, service worker (pages et vidéo gardées, hors ligne). Données de test : une
// répétition datée de 2099, des vidéos fabriquées ici par canvas + MediaRecorder (jamais versionnées), la vidéo
// « Démo » ; tout est dans la base de test, effacée à la fin.

/** Petite vidéo WebM enregistrée dans le navigateur de test (bandes de couleur et numéro de seconde). */
async function fabriquerVideo(context: BrowserContext, secondes = 4): Promise<Buffer> {
  const atelier = await context.newPage()
  await atelier.setContent('<!doctype html><canvas width="320" height="180"></canvas>')
  const base64 = await atelier.evaluate(async (duree) => {
    const canvas = document.querySelector('canvas') as HTMLCanvasElement
    const ctx = canvas.getContext('2d') as CanvasRenderingContext2D
    const flux = canvas.captureStream(15)
    const enregistreur = new MediaRecorder(flux, { mimeType: 'video/webm' })
    const morceaux: Blob[] = []
    enregistreur.ondataavailable = (e) => morceaux.push(e.data)
    const fini = new Promise((resolu) => (enregistreur.onstop = resolu))
    const debut = performance.now()
    enregistreur.start(250)
    await new Promise<void>((resolu) => {
      const image = () => {
        const ms = performance.now() - debut
        ctx.fillStyle = Math.floor(ms / 1000) % 2 ? 'rgb(138, 21, 56)' : 'rgb(46, 107, 58)'
        ctx.fillRect(0, 0, 320, 180)
        ctx.fillStyle = 'white'
        ctx.font = 'bold 80px sans-serif'
        ctx.fillText(String(Math.floor(ms / 1000)), 130, 120)
        if (ms < duree * 1000) requestAnimationFrame(image)
        else resolu()
      }
      image()
    })
    enregistreur.stop()
    await fini
    const octets = new Uint8Array(await new Blob(morceaux, { type: 'video/webm' }).arrayBuffer())
    let texte = ''
    for (let i = 0; i < octets.length; i += 0x8000) texte += String.fromCharCode(...octets.subarray(i, i + 0x8000))
    return btoa(texte)
  }, secondes)
  await atelier.close()
  return Buffer.from(base64, 'base64')
}

async function ajouterRepetition(page: Page, date: string): Promise<void> {
  await page.goto('/admin/repetitions')
  await page.getByLabel('Date').fill(date)
  await page.getByLabel('Lieu').fill('Salle Démo')
  await page.getByLabel('Statut').selectOption('decidee')
  await page.getByLabel('On travaille').fill('Section A')
  await page.getByRole('button', { name: 'Ajouter la répétition' }).click()
  await expect(page.getByRole('status').filter({ hasText: 'Répétition ajoutée.' })).toBeVisible()
}

test('répétitions : Je viens puis Je ne peux pas, sur la page et sur Ma page', async ({ page }) => {
  const preparation = preparer()
  await entrer(page, preparation)
  await ouvrirAdmin(page, preparation)
  await ajouterRepetition(page, '2099-03-07')
  await page.goto('/')
  await choisir(page, 'Jessica')

  // Ma page : la prochaine répétition décidée reçoit les deux boutons.
  const carte = page.locator('#prochaine-repetition').locator('..')
  await expect(carte.getByText('samedi 7 mars 2099', { exact: true })).toBeVisible()
  await carte.getByRole('button', { name: 'Je viens' }).click()
  await expect(page).toHaveURL(/\/moi\?ok=oui/)
  await expect(page.getByRole('status').filter({ hasText: 'C’est noté : tu viens samedi 7 mars 2099.' })).toBeVisible()
  await expect(page.locator('#prochaine-repetition').locator('..').getByRole('button', { name: /Je viens/ })).toHaveAttribute('aria-pressed', 'true')
  await expect(page.getByText('Viennent : Jessica.')).toBeVisible()
  await verifierAxeDeuxThemes(page)

  await page.getByRole('link', { name: 'Répétitions', exact: true }).click()
  const repetition = page.locator('article', { hasText: 'Samedi 7 mars 2099' })
  await expect(repetition.getByText('Ta réponse : je viens.')).toBeVisible()
  await repetition.getByRole('button', { name: 'Je ne peux pas' }).click()
  await expect(page.getByRole('status').filter({ hasText: 'C’est noté : tu ne peux pas venir samedi 7 mars 2099.' })).toBeVisible()
  await expect(page.locator('article', { hasText: 'Samedi 7 mars 2099' }).getByText('Ta réponse : je ne peux pas.')).toBeVisible()
  await expect(page.locator('article', { hasText: 'Samedi 7 mars 2099' }).getByText(/Ne peuvent pas :\s*Jessica/)).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Passées' })).toBeVisible()
  await verifierAxeDeuxThemes(page)

  // Patrice note qui était là et le compte rendu de la répétition du 6 septembre.
  await page.goto('/admin/repetitions')
  await verifierAxeDeuxThemes(page)
  await page.getByRole('link', { name: 'Dimanche 6 septembre 2026' }).click()
  await page.getByLabel('Alex', { exact: true }).selectOption('1')
  await page.getByLabel('Compte rendu').fill('Compte rendu de démonstration.')
  await page.getByRole('button', { name: 'Enregistrer la répétition' }).click()
  await expect(page.getByRole('status').filter({ hasText: 'Répétition enregistrée.' })).toBeVisible()
  await verifierAxe(page)
  await page.goto('/repetitions')
  await expect(page.getByText(/Présents :\s*Alex/)).toBeVisible()
  await expect(page.getByText('Compte rendu de démonstration.')).toBeVisible()
})

test('vidéos : téléversement avec progression, commentaire à 0:03, suppression', async ({ page, context }) => {
  const video = await fabriquerVideo(context)
  const preparation = preparer()
  await entrer(page, preparation)
  await choisir(page, 'Jessica')
  await page.goto('/videos')
  await expect(page.getByText('Ces vidéos restent entre nous')).toBeVisible()
  await expect(page.getByText('Personne n’a encore posté de vidéo.')).toBeVisible()
  await verifierAxeDeuxThemes(page)

  await page.getByRole('link', { name: 'Ajouter ma vidéo' }).click()
  await expect(page.getByText('200 Mo au plus, soit environ 3 minutes en 720p.')).toBeVisible()
  await verifierAxeDeuxThemes(page)

  // Un texte déguisé en vidéo : refusé en français, le titre reste saisi.
  await page.getByLabel('Chanson').selectOption('kanimaa')
  await page.getByLabel('Titre').fill('Section A de Jessica')
  await page.getByLabel('Vidéo', { exact: true }).setInputFiles({ name: 'faux.mp4', mimeType: 'video/mp4', buffer: Buffer.from('Ceci n’est pas une vidéo.'.padEnd(800, ' ')) })
  await page.getByRole('button', { name: 'Envoyer' }).click()
  const resume = page.getByRole('alert')
  await expect(resume).toContainText('Ce fichier n’est pas une vidéo acceptée : envoie un fichier MP4, MOV, WEBM.')
  await expect(resume).toBeFocused()
  await expect(page.getByLabel('Titre')).toHaveValue('Section A de Jessica')
  await verifierAxe(page)

  // La vraie vidéo, avec un envoi ralenti pour voir la progression.
  const cdp = await context.newCDPSession(page)
  await cdp.send('Network.enable')
  await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 20, downloadThroughput: -1, uploadThroughput: Math.max(8 * 1024, Math.floor(video.length / 4)) })
  await page.getByLabel('Note pour les cousins').fill('Je bloque sur le tour.')
  await page.getByLabel('Vidéo', { exact: true }).setInputFiles({ name: 'section-a.webm', mimeType: 'video/webm', buffer: video })
  await page.getByRole('button', { name: 'Envoyer' }).click()
  await expect(page.getByRole('button', { name: 'Annuler l’envoi' })).toBeVisible()
  await expect(page.locator('[data-texte-progression]')).toHaveText(/^\d{1,3} % · [\d,]+ Mo sur [\d,]+ Mo$/)
  await expect(page).toHaveURL(/\/videos\/\d+\?ok=ajoutee$/, { timeout: 30_000 })
  await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: -1 })
  await expect(page.getByRole('status').filter({ hasText: 'Ta vidéo est en ligne' })).toBeVisible()
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Jessica · Section A de Jessica')
  await expect(page.locator('video')).toHaveAttribute('src', /^\/m\/\d+\?v=[0-9a-f]{8}$/)
  await page.waitForFunction(() => (document.querySelector('video') as HTMLVideoElement).readyState >= 1)

  // Commentaire horodaté, puis le lien « à 0:03 » positionne la vidéo sans recharger.
  await page.getByRole('button', { name: '0,5 ×' }).click()
  await expect(page.getByRole('button', { name: '0,5 ×' })).toHaveAttribute('aria-pressed', 'true')
  await page.getByLabel('À quel instant').fill('0:03')
  await page.getByLabel('Ton commentaire').fill('Bras plus haut ici')
  await page.getByRole('button', { name: 'Commenter' }).click()
  await expect(page.getByRole('status').filter({ hasText: 'Ton commentaire est ajouté.' })).toBeVisible()
  const instant = page.getByRole('link', { name: /à 0:03/ })
  await expect(instant).toBeVisible()
  await page.evaluate(() => {
    ;(document.querySelector('video') as HTMLVideoElement).currentTime = 0
  })
  const adresse = page.url()
  await instant.click()
  await expect.poll(() => page.evaluate(() => (document.querySelector('video') as HTMLVideoElement).currentTime)).toBeCloseTo(3, 0)
  expect(page.url().split('?')[0]).toBe(adresse.split('?')[0])
  // « Prendre la position » remplit l'instant du formulaire.
  await page.getByRole('button', { name: 'Prendre la position' }).click()
  await expect(page.getByLabel('À quel instant')).toHaveValue('0:03')
  await verifierAxeDeuxThemes(page)

  await page.goto('/videos')
  await expect(page.getByRole('heading', { name: /Kanimaa/ })).toBeVisible()
  await expect(page.getByText('Jessica · Section A de Jessica')).toBeVisible()
  await expect(page.getByRole('link', { name: /à 0:03/ })).toBeVisible()
  await verifierAxeDeuxThemes(page)
  await page.goto('/moi')
  await expect(page.getByText('Jessica a posté « Section A de Jessica »')).toBeVisible()
  await verifierAxeDeuxThemes(page)

  // Administration des vidéos (taille, format, remplacement) : lisible dans les deux thèmes.
  await ouvrirAdmin(page, preparation)
  await page.goto('/admin/videos')
  await expect(page.getByLabel('Remplacer « Section A de Jessica » par une version convertie')).toBeVisible()
  await expect(page.getByRole('heading', { name: /Kanimaa · Jessica : Section A de Jessica/ })).toBeVisible()
  await verifierAxeDeuxThemes(page)

  // Suppression par l'auteur, avec confirmation en page.
  await page.goto('/videos')
  await page.getByRole('link', { name: /voir la vidéo de Jessica/ }).click()
  await page.getByRole('link', { name: 'Supprimer ma vidéo' }).click()
  await expect(page.getByText('Supprimer « Section A de Jessica » ?')).toBeVisible()
  await verifierAxe(page)
  await page.getByRole('button', { name: 'Oui, supprimer la vidéo' }).click()
  await expect(page).toHaveURL(ORIGINE + '/videos?ok=supprimee')
  await expect(page.getByRole('status').filter({ hasText: 'Ta vidéo est supprimée' })).toBeVisible()
  await expect(page.getByText('Personne n’a encore posté de vidéo.')).toBeVisible()
})

test('Jour J : sans ordre, puis avec l’ordre posé par Patrice ; check-list partagée', async ({ page }) => {
  const preparation = preparer()
  await entrer(page, preparation)
  await choisir(page, 'Josiane')
  await page.goto('/jour-j')
  await expect(page.getByText('Mariage le [date] · [lieu].')).toBeVisible()
  await expect(page.getByText('L’ordre de passage n’est pas encore fixé.')).toBeVisible()
  await verifierAxeDeuxThemes(page)

  const tenue = page.getByRole('button', { name: /Tenue/ })
  await expect(tenue).toContainText('à faire')
  await tenue.click()
  await expect(page.getByRole('status').filter({ hasText: 'C’est noté dans la check-list.' })).toBeVisible()
  await expect(page.getByRole('button', { name: /Tenue/ })).toContainText('fait par Josiane')
  await expect(page.getByRole('button', { name: /Tenue/ })).toHaveAttribute('aria-pressed', 'true')

  await ouvrirAdmin(page, preparation)
  for (const [slug, ordre] of [
    ['kanimaa', '1'],
    ['celebration-of-varisu', '2'],
  ]) {
    await page.goto(`/admin/chansons/${slug}`)
    await page.getByLabel('Ordre de passage').fill(ordre)
    await page.getByRole('button', { name: 'Enregistrer la fiche' }).click()
    await expect(page.getByRole('status').filter({ hasText: 'Fiche enregistrée.' })).toBeVisible()
  }
  await page.goto('/admin/jour-j')
  await page.getByLabel('Nouvelle ligne').fill('Ligne Démo')
  await page.getByRole('button', { name: 'Ajouter la ligne' }).click()
  await expect(page.getByRole('status').filter({ hasText: 'Ligne ajoutée.' })).toBeVisible()
  await verifierAxe(page)

  await page.goto('/jour-j')
  await expect(page.locator('.frise__titre')).toHaveText([/Kanimaa/, /Celebration of Varisu/])
  await expect(page.getByText('[à compléter]').first()).toBeVisible()
  await expect(page.getByRole('button', { name: /Ligne Démo/ })).toBeVisible()
  await verifierAxeDeuxThemes(page)
  await page.getByRole('link', { name: 'Mode scène — placements en grand' }).click()
  await expect(page).toHaveURL(/\/placements\/kanimaa\?scene=1&depuis=jour-j/)
  await page.getByRole('link', { name: /Quitter/ }).click()
  await expect(page).toHaveURL(ORIGINE + '/jour-j')
})

test('service worker : pages servies hors ligne, vidéo gardée lue sans réseau', async ({ page, context }) => {
  const preparation = preparer()
  await entrer(page, preparation)
  await ouvrirAdmin(page, preparation)
  await deposerReference(page, 'kanimaa')
  await page.goto('/')
  await choisir(page, 'Jessica')

  // Le service worker s'installe, prend la main, puis garde les pages visitées.
  await page.evaluate(() => navigator.serviceWorker.ready.then(() => undefined))
  await page.reload()
  await expect.poll(() => page.evaluate(() => Boolean(navigator.serviceWorker.controller))).toBe(true)
  await page.goto('/moi')
  await page.goto('/chanson/kanimaa')
  const garder = page.getByRole('button', { name: /Garder sur mon téléphone/ })
  await expect(garder).toBeVisible()
  await verifierAxe(page)
  await garder.click()
  await expect(page.getByText('Gardée sur ton téléphone : elle se lit sans réseau.')).toBeVisible({ timeout: 30_000 })
  await expect(page.getByRole('button', { name: 'Retirer de mon téléphone' })).toBeVisible()

  await context.setOffline(true)
  await page.goto('/moi')
  await expect(page.getByRole('heading', { name: 'Salut Jessica' })).toBeVisible()
  await expect(page.locator('[data-hors-ligne]')).toContainText('Pas de réseau')
  await page.goto('/jour-j')
  await expect(page.getByRole('heading', { name: 'Pas de réseau' })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Kanimaa' })).toBeVisible()
  await verifierAxe(page)

  await page.goto('/chanson/kanimaa')
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Kanimaa')
  const lecture = await page.evaluate(async () => {
    const v = document.querySelector('video') as HTMLVideoElement
    v.muted = true
    v.currentTime = 2
    await v.play()
    await new Promise((r) => setTimeout(r, 1500))
    v.pause()
    return { temps: v.currentTime, erreur: v.error?.code ?? null }
  })
  expect(lecture.erreur).toBeNull()
  expect(lecture.temps).toBeGreaterThan(2.5)
  await context.setOffline(false)

  await page.getByRole('button', { name: 'Retirer de mon téléphone' }).click()
  await expect(page.getByText('Retirée de ton téléphone.')).toBeVisible()

  // Gardée de nouveau, puis supprimée sur le serveur : la copie du téléphone s'efface à la lecture suivante.
  await page.getByRole('button', { name: /Garder sur mon téléphone/ }).click()
  await expect(page.getByRole('button', { name: 'Retirer de mon téléphone' })).toBeVisible({ timeout: 30_000 })
  const adresse = await page.locator('video').getAttribute('src')
  await page.goto('/admin/videos')
  await page.getByRole('link', { name: /Supprimer la vidéo Référence : Kanimaa/ }).click()
  await page.getByRole('button', { name: 'Oui, supprimer la vidéo' }).click()
  await expect(page.getByRole('status').filter({ hasText: 'Vidéo supprimée' })).toBeVisible()
  const apres = await page.evaluate(async (url) => {
    const reponse = await fetch(url as string, { headers: { Range: 'bytes=0-99' } })
    const copies = await (await caches.open('videos-v1')).keys()
    return { statut: reponse.status, copies: copies.length }
  }, adresse)
  expect(apres).toEqual({ statut: 404, copies: 0 })
})
