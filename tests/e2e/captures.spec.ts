import { execFileSync } from 'node:child_process'
import { mkdirSync } from 'node:fs'
import { choisir, deposerReference, entrer, expect, ouvrirAdmin, preparer, test } from './outils'

// Captures des écrans de la phase en 390 et 1 280 px (pnpm captures phase-NN), dans docs/captures/phase-NN/.
// Ignorées par `pnpm test:e2e` pour ne pas réécrire les images à chaque série. Phase 6 (défaut) : installation et
// tableau d'administration, test en fin de fichier. Phase 5 (pnpm captures phase-05) : répétitions et présences,
// vidéos des cousins, Jour J, hors ligne, administration du groupe. Données de test : une répétition datée de 2099,
// une vidéo WebM fabriquée ici par canvas + MediaRecorder, la vidéo « Démo » ; base de test effacée ensuite.
// (Les écrans des phases 2 à 4 restent dans leurs dossiers, tels que leur phase les a laissés.)

const PHASE = process.env.CAPTURES_PHASE ?? 'phase-06'
const dossier = `docs/captures/${PHASE}`

test.skip(!process.env.CAPTURES, 'captures seulement avec pnpm captures')

test('captures de la phase 5', async ({ page, context }, info) => {
  test.skip(PHASE !== 'phase-05', 'écrans de la phase 5 seulement avec pnpm captures phase-05')
  mkdirSync(dossier, { recursive: true })
  const suffixe = info.project.name === 'mobile' ? '390' : '1280'
  const capturer = async (nom: string, pleinePage = true) => {
    await page.evaluate(() => document.fonts.ready)
    await page.screenshot({ path: `${dossier}/${nom}-${suffixe}.png`, fullPage: pleinePage })
  }
  const changerDePrenom = async (prenom: string) => {
    await page.goto('/moi')
    await page.getByRole('button', { name: /^Changer/ }).click()
    await choisir(page, prenom)
  }

  // Vidéo de test fabriquée dans le navigateur (aucun binaire versionné).
  const atelier = await context.newPage()
  await atelier.setContent('<!doctype html><canvas width="320" height="180"></canvas>')
  const base64 = await atelier.evaluate(async () => {
    const canvas = document.querySelector('canvas') as HTMLCanvasElement
    const ctx = canvas.getContext('2d') as CanvasRenderingContext2D
    const enregistreur = new MediaRecorder(canvas.captureStream(15), { mimeType: 'video/webm' })
    const morceaux: Blob[] = []
    enregistreur.ondataavailable = (e) => morceaux.push(e.data)
    const fini = new Promise((r) => (enregistreur.onstop = r))
    enregistreur.start(250)
    const debut = performance.now()
    await new Promise<void>((resolu) => {
      const image = () => {
        const ms = performance.now() - debut
        ctx.fillStyle = Math.floor(ms / 1000) % 2 ? 'rgb(138, 21, 56)' : 'rgb(46, 107, 58)'
        ctx.fillRect(0, 0, 320, 180)
        if (ms < 4000) requestAnimationFrame(image)
        else resolu()
      }
      image()
    })
    enregistreur.stop()
    await fini
    const octets = new Uint8Array(await new Blob(morceaux).arrayBuffer())
    let texte = ''
    for (let i = 0; i < octets.length; i += 0x8000) texte += String.fromCharCode(...octets.subarray(i, i + 0x8000))
    return btoa(texte)
  })
  await atelier.close()

  await page.emulateMedia({ colorScheme: 'light' })
  const preparation = preparer()
  await entrer(page, preparation)
  await ouvrirAdmin(page, preparation)
  await page.goto('/admin/repetitions')
  await page.getByLabel('Date').fill('2099-03-07')
  await page.getByLabel('Lieu').fill('Salle Démo')
  await page.getByLabel('Statut').selectOption('decidee')
  await page.getByLabel('On travaille').fill('Section A')
  await page.getByRole('button', { name: 'Ajouter la répétition' }).click()
  await capturer('admin-repetitions')
  await page.goto('/admin/repetitions')
  await page.getByRole('link', { name: 'Dimanche 6 septembre 2026' }).click()
  await capturer('admin-repetition')
  await deposerReference(page, 'kanimaa')
  await page.goto('/admin/jour-j')
  await capturer('admin-jour-j')

  await page.goto('/')
  await choisir(page, 'Jessica')
  await capturer('ma-page-presence')
  await page.locator('#prochaine-repetition').locator('..').getByRole('button', { name: 'Je viens' }).click()
  await capturer('ma-page-je-viens')
  await page.goto('/repetitions')
  await capturer('repetitions')
  await page.emulateMedia({ colorScheme: 'dark' })
  await capturer('repetitions-sombre')
  await page.emulateMedia({ colorScheme: 'light' })

  await page.goto('/videos')
  await capturer('videos-vide')
  await page.goto('/videos/ajouter?chanson=kanimaa')
  await capturer('videos-ajouter')
  await page.getByLabel('Titre').fill('Section A de Jessica')
  await page.getByLabel('Vidéo', { exact: true }).setInputFiles({ name: 'faux.mp4', mimeType: 'video/mp4', buffer: Buffer.from('Ceci n’est pas une vidéo.'.padEnd(800, ' ')) })
  await page.getByRole('button', { name: 'Envoyer' }).click()
  await expect(page.getByRole('alert')).toBeVisible()
  await capturer('videos-ajouter-erreur')
  await page.getByLabel('Note pour les cousins').fill('Je bloque sur le tour.')
  await page.getByLabel('Vidéo', { exact: true }).setInputFiles({ name: 'section-a.webm', mimeType: 'video/webm', buffer: Buffer.from(base64, 'base64') })
  await page.getByRole('button', { name: 'Envoyer' }).click()
  await expect(page).toHaveURL(/\/videos\/\d+\?ok=ajoutee$/)
  await page.getByLabel('À quel instant').fill('0:03')
  await page.getByLabel('Ton commentaire').fill('Bras plus haut ici')
  await page.getByRole('button', { name: 'Commenter' }).click()
  await expect(page.getByRole('status').filter({ hasText: 'Ton commentaire est ajouté.' })).toBeVisible()
  const pageVideo = page.url().split('?')[0]
  await changerDePrenom('Alex')
  await page.goto(pageVideo)
  await page.getByLabel('Ton commentaire').fill('Super, on reprend ça samedi')
  await page.getByRole('button', { name: 'Commenter' }).click()
  await capturer('video')
  await page.emulateMedia({ colorScheme: 'dark' })
  await capturer('video-sombre')
  await page.emulateMedia({ colorScheme: 'light' })
  await page.goto('/videos')
  await capturer('videos-album')
  await page.goto('/chanson/kanimaa')
  await expect(page.getByRole('button', { name: /Garder sur mon téléphone/ })).toBeVisible()
  await capturer('chanson-videos-garder')
  await page.goto('/moi')
  await capturer('ma-page-ce-qui-bouge')

  await page.goto('/jour-j')
  await capturer('jour-j-sans-ordre')
  await page.getByRole('button', { name: /Tenue/ }).click()
  await ouvrirAdmin(page, preparation)
  for (const [slug, ordre] of [
    ['kanimaa', '1'],
    ['celebration-of-varisu', '2'],
  ]) {
    await page.goto(`/admin/chansons/${slug}`)
    await page.getByLabel('Ordre de passage').fill(ordre)
    await page.getByRole('button', { name: 'Enregistrer la fiche' }).click()
  }
  await page.goto('/jour-j')
  await capturer('jour-j-avec-ordre')
  await page.emulateMedia({ colorScheme: 'dark' })
  await capturer('jour-j-sombre')
  await page.emulateMedia({ colorScheme: 'light' })
  await page.goto('/admin/videos')
  await capturer('admin-videos')
  await page.goto('/admin')
  await capturer('admin-tableau')

  await context.setOffline(true)
  await page.goto('/placements')
  await expect(page.getByRole('heading', { name: 'Pas de réseau' })).toBeVisible()
  await capturer('hors-ligne')
  await page.goto('/moi')
  await capturer('ma-page-hors-ligne', false)
  await context.setOffline(false)
})

// Phase 6 : installation sans SSH (formulaire, refus, premier lien) et tableau d'administration (« Base et
// sauvegardes »), sur une base de test vide puis installée ; aucune donnée inventée.
test('captures de la phase 6', async ({ page }, info) => {
  test.skip(PHASE !== 'phase-06', 'écrans de la phase 6 seulement avec pnpm captures phase-06')
  mkdirSync(dossier, { recursive: true })
  const suffixe = info.project.name === 'mobile' ? '390' : '1280'
  const capturer = async (nom: string) => {
    await page.evaluate(() => document.fonts.ready)
    await page.screenshot({ path: `${dossier}/${nom}-${suffixe}.png`, fullPage: true })
  }
  const sortie = execFileSync('docker', ['compose', 'exec', '-T', 'web', 'php', 'scripts/e2e-outil.php', 'vierge'], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] })
  const cle = (JSON.parse(sortie) as { cle: string }).cle
  await page.emulateMedia({ colorScheme: 'light' })
  await page.goto('/installer')
  await capturer('installation')
  await page.emulateMedia({ colorScheme: 'dark' })
  await capturer('installation-sombre')
  await page.emulateMedia({ colorScheme: 'light' })
  await page.getByRole('button', { name: 'Installer le site' }).click()
  await capturer('installation-refus')
  await page.getByLabel('Clé d’administration').fill(cle)
  await page.getByRole('button', { name: 'Installer le site' }).click()
  await expect(page.getByRole('status').filter({ hasText: 'C’est prêt' })).toBeVisible()
  // Le lien de test ne doit pas figurer sur l'image : la zone (en lecture seule) est réécrite avant la capture.
  await page.locator('#premier-lien').evaluate((zone: HTMLTextAreaElement) => {
    zone.value = 'http://127.0.0.1:8095/e/[lien de test masqué]'
  })
  await capturer('installation-faite')
  await page.goto('/admin/base')
  await page.getByRole('button', { name: 'Faire une sauvegarde maintenant' }).click()
  await expect(page.getByRole('status').filter({ hasText: 'Sauvegarde faite.' })).toBeVisible()
  await capturer('admin-base')
  await page.locator('.admin__sauvegardes li').getByRole('link', { name: /^Restaurer/ }).click()
  await capturer('admin-base-restaurer')
  await page.goto('/admin')
  await capturer('admin-tableau')
  await page.emulateMedia({ colorScheme: 'dark' })
  await capturer('admin-tableau-sombre')
  await page.goto('/admin/base')
  await capturer('admin-base-sombre')
})
