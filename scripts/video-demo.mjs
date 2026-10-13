// Fabrique la vidéo de démonstration de /gabarits (chanson « Démo ») : 20 secondes de bandes de couleur avec le
// numéro de la seconde, dessinées dans un <canvas> et enregistrées par MediaRecorder dans le Chromium de
// Playwright (aucune image générée par IA, aucun réseau, aucune vidéo réelle). La durée est réécrite dans l'en-tête
// WebM (MediaRecorder ne l'écrit pas, et sans elle le navigateur ne sait pas se positionner dans la vidéo).
// Le fichier est déposé dans le volume Docker (stockage/medias/demo.webm, jamais versionné) par docker compose exec.
// Usage : node scripts/video-demo.mjs [--si-absente]
import { spawnSync } from 'node:child_process'
import { chromium } from '@playwright/test'

const DUREE_MS = 20_000
const CIBLE = 'stockage/medias/demo.webm'

function docker(args, entree) {
  return spawnSync('docker', ['compose', 'exec', '-T', '-u', 'www-data', 'web', ...args], {
    input: entree,
    stdio: [entree ? 'pipe' : 'ignore', 'pipe', 'pipe'],
    maxBuffer: 64 * 1024 * 1024,
  })
}

if (process.argv.includes('--si-absente')) {
  const test = docker(['test', '-s', CIBLE])
  if (test.status === 0) {
    console.log('Vidéo de démonstration déjà présente.')
    process.exit(0)
  }
}

// --- Enregistrement dans le navigateur ---------------------------------------------------------------------
const navigateur = await chromium.launch()
let octets
try {
  const page = await navigateur.newPage()
  await page.setContent('<!doctype html><canvas width="640" height="360"></canvas>')
  const resultat = await page.evaluate(async (dureeMs) => {
    const canvas = document.querySelector('canvas')
    const ctx = canvas.getContext('2d')
    const teintes = ['#8a1538', '#c9930a', '#2e6b3a', '#b4530a', '#5c4a44', '#a4284c', '#e0ab2a', '#312736']
    const dessiner = (ms) => {
      const seconde = Math.floor(ms / 1000)
      const largeur = canvas.width / 8
      for (let i = 0; i < 8; i++) {
        ctx.fillStyle = teintes[(i + seconde) % 8]
        ctx.fillRect(i * largeur, 0, largeur, canvas.height)
      }
      // Repère de mouvement : une barre qui traverse l'écran à chaque seconde.
      ctx.fillStyle = '#fff7ec'
      ctx.fillRect(((ms % 1000) / 1000) * canvas.width, canvas.height - 24, 12, 24)
      ctx.fillStyle = '#17121a'
      ctx.fillRect(canvas.width / 2 - 110, canvas.height / 2 - 80, 220, 160)
      ctx.fillStyle = '#fff7ec'
      ctx.font = 'bold 110px sans-serif'
      ctx.textAlign = 'center'
      ctx.textBaseline = 'middle'
      ctx.fillText(String(seconde), canvas.width / 2, canvas.height / 2 - 6)
      ctx.font = 'bold 22px sans-serif'
      ctx.fillText('Démo', canvas.width / 2, canvas.height / 2 + 58)
    }
    dessiner(0)
    const flux = canvas.captureStream(30)
    const type = ['video/webm;codecs=vp9', 'video/webm;codecs=vp8', 'video/webm'].find((t) => MediaRecorder.isTypeSupported(t))
    const enregistreur = new MediaRecorder(flux, {
      mimeType: type,
      videoBitsPerSecond: 1_000_000,
      videoKeyFrameIntervalDuration: 1000,
    })
    const morceaux = []
    enregistreur.ondataavailable = (e) => e.data.size && morceaux.push(e.data)
    const fini = new Promise((resoudre) => {
      enregistreur.onstop = resoudre
    })
    const debut = performance.now()
    enregistreur.start(1000)
    const minuterie = setInterval(() => dessiner(performance.now() - debut), 1000 / 30)
    await new Promise((resoudre) => setTimeout(resoudre, dureeMs))
    dessiner(dureeMs - 1)
    clearInterval(minuterie)
    enregistreur.stop()
    await fini
    const duree = performance.now() - debut
    const tampon = new Uint8Array(await new Blob(morceaux, { type }).arrayBuffer())
    let binaire = ''
    for (let i = 0; i < tampon.length; i += 0x8000) binaire += String.fromCharCode(...tampon.subarray(i, i + 0x8000))
    return { base64: btoa(binaire), duree, type }
  }, DUREE_MS)
  octets = Buffer.from(resultat.base64, 'base64')
  console.log(`Enregistré : ${octets.length} octets, ${Math.round(resultat.duree)} ms, ${resultat.type}.`)
  octets = ecrireDuree(octets, resultat.duree)
} finally {
  await navigateur.close()
}

// --- Dépôt dans le volume Docker ------------------------------------------------------------------------------
const dossier = docker(['mkdir', '-p', 'stockage/medias'])
if (dossier.status !== 0) {
  console.error('Impossible de créer le dossier des médias dans le conteneur :', dossier.stderr.toString())
  process.exit(1)
}
const depot = docker(['sh', '-c', `cat > ${CIBLE}`], octets)
if (depot.status !== 0) {
  console.error('Dépôt impossible :', depot.stderr.toString())
  process.exit(1)
}
console.log(`Vidéo de démonstration déposée dans le conteneur (${octets.length} octets).`)

// --- Réécriture de la durée dans l'en-tête WebM (EBML) ----------------------------------------------------------

/** Lit un identifiant EBML (marqueur de longueur conservé). */
function lireId(t, pos) {
  let longueur = 1
  let masque = 0x80
  while (longueur <= 4 && !(t[pos] & masque)) {
    masque >>= 1
    longueur++
  }
  let id = 0
  for (let i = 0; i < longueur; i++) id = id * 256 + t[pos + i]
  return { id, longueur }
}

/** Lit une taille EBML (entier de longueur variable). */
function lireTaille(t, pos) {
  let longueur = 1
  let masque = 0x80
  while (longueur <= 8 && !(t[pos] & masque)) {
    masque >>= 1
    longueur++
  }
  let valeur = t[pos] & (masque - 1)
  let inconnue = valeur === masque - 1
  for (let i = 1; i < longueur; i++) {
    valeur = valeur * 256 + t[pos + i]
    if (t[pos + i] !== 0xff) inconnue = false
  }
  return { valeur, longueur, inconnue }
}

/** Taille EBML sur 8 octets (0x01 suivi de 7 octets). */
function taille8(valeur) {
  const b = Buffer.alloc(8)
  b[0] = 0x01
  let v = valeur
  for (let i = 7; i >= 1; i--) {
    b[i] = v % 256
    v = Math.floor(v / 256)
  }
  return b
}

function ecrireDuree(t, dureeMs) {
  const ID_SEGMENT = 0x18538067
  const ID_INFO = 0x1549a966
  const ID_SEEKHEAD = 0x114d9b74
  const ID_ECHELLE = 0x2ad7b1
  const ID_DUREE = 0x4489
  let pos = 0
  const entete = lireId(t, pos)
  const tailleEntete = lireTaille(t, pos + entete.longueur)
  pos += entete.longueur + tailleEntete.longueur + tailleEntete.valeur
  const segment = lireId(t, pos)
  if (segment.id !== ID_SEGMENT) throw new Error('WebM inattendu : pas de segment.')
  const tailleSegment = lireTaille(t, pos + segment.longueur)
  // Insérer des octets dans Info n'est sûr que si la taille du segment est « inconnue » (cas de MediaRecorder).
  if (!tailleSegment.inconnue) {
    console.warn('Segment WebM de taille fixe : durée laissée telle quelle.')
    return t
  }
  pos += segment.longueur + tailleSegment.longueur
  while (pos < t.length) {
    const el = lireId(t, pos)
    const taille = lireTaille(t, pos + el.longueur)
    const contenu = pos + el.longueur + taille.longueur
    if (el.id === ID_SEEKHEAD) {
      console.warn('Index WebM présent : durée laissée telle quelle.')
      return t
    }
    if (el.id === ID_INFO) {
      let echelle = 1_000_000
      let p = contenu
      while (p < contenu + taille.valeur) {
        const enfant = lireId(t, p)
        const tailleEnfant = lireTaille(t, p + enfant.longueur)
        const debutEnfant = p + enfant.longueur + tailleEnfant.longueur
        if (enfant.id === ID_DUREE) return t
        if (enfant.id === ID_ECHELLE) {
          echelle = 0
          for (let i = 0; i < tailleEnfant.valeur; i++) echelle = echelle * 256 + t[debutEnfant + i]
        }
        p = debutEnfant + tailleEnfant.valeur
      }
      const duree = Buffer.alloc(11)
      duree[0] = 0x44
      duree[1] = 0x89
      duree[2] = 0x88
      duree.writeDoubleBE((dureeMs * 1_000_000) / echelle, 3)
      const nouveauContenu = Buffer.concat([t.subarray(contenu, contenu + taille.valeur), duree])
      return Buffer.concat([
        t.subarray(0, pos),
        t.subarray(pos, pos + el.longueur),
        taille8(nouveauContenu.length),
        nouveauContenu,
        t.subarray(contenu + taille.valeur),
      ])
    }
    if (taille.inconnue) break
    pos = contenu + taille.valeur
  }
  console.warn('En-tête WebM sans bloc Info : durée laissée telle quelle.')
  return t
}
