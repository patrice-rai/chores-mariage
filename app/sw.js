// Service worker du site « Chorés du mariage » (spec § 8). Modèle servi par PHP sur BASE_PATH/sw.js
// (app/controleurs/pwa.php) : VERSION, BASE et PRECACHE y sont remplacés par la version des fichiers, le périmètre et
// la liste exacte de l'interface.
// - interface (feuilles, scripts, polices, icônes, page « Hors ligne ») gardée à l'installation, cache d'abord ;
// - pages : réseau d'abord, sinon la dernière copie de la même page, sinon « Hors ligne » ; 60 pages au plus ;
// - POST : jamais touchés ; administration, outils et entrée par le lien : jamais gardés ;
// - vidéos (/m/{id}) : jamais gardées sans demande ; une vidéo gardée par « Garder sur mon téléphone » (cache
//   videos-v1, rempli par public/js/pwa.js) est servie d'ici, plages d'octets comprises (réponses 206), après une
//   vérification légère auprès du serveur quand le réseau est là (une vidéo supprimée disparaît du téléphone) ;
// - lien renouvelé (/lien) : pages et vidéos gardées effacées ; prénom changé (entrée) : pages gardées effacées.

const VERSION = '__VERSION__'
const BASE = '__BASE__'
const PRECACHE = ['__PRECACHE__']
const INTERFACE = `interface-${VERSION}`
const PAGES = 'pages-v1'
const VIDEOS = 'videos-v1'
const PAGES_MAX = 60
const JAMAIS_GARDEES = /^\/(admin|gabarits|installer|e|api|m)(\/|$)/
/** Paramètres d'un instant (confirmation, demande de suppression, position) : la page gardée ne les garde pas. */
const PARAMETRES_PASSAGERS = ['ok', 'r', 't', 'supprimer', 'supprimer-commentaire', 'codec']
/** Vidéos déjà vérifiées auprès du serveur depuis la dernière page chargée en ligne. */
const verifiees = new Map()

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches
      .open(INTERFACE)
      .then((cache) => cache.addAll(PRECACHE.map((url) => new Request(url, { cache: 'reload', credentials: 'same-origin' }))))
      .then(() => self.skipWaiting()),
  )
})

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches
      .keys()
      .then((noms) => Promise.all(noms.filter((n) => n.startsWith('interface-') && n !== INTERFACE).map((n) => caches.delete(n))))
      .then(() => self.clients.claim()),
  )
})

/** Chemin sans BASE (« /moi »), ou null hors du site. */
function cheminDe(url) {
  if (url.origin !== self.location.origin || !url.pathname.startsWith(BASE)) return null
  return '/' + url.pathname.slice(BASE.length)
}

self.addEventListener('fetch', (e) => {
  const requete = e.request
  if (requete.method !== 'GET') return
  const url = new URL(requete.url)
  const chemin = cheminDe(url)
  if (chemin === null) return
  if (chemin.startsWith('/m/')) {
    e.respondWith(video(requete))
  } else if (requete.mode === 'navigate') {
    e.respondWith(page(e, requete, chemin))
  } else if (/^\/(css|js|polices|icones)\//.test(chemin) || chemin === '/manifest.webmanifest') {
    e.respondWith(statique(requete))
  }
})

/** Clé d'une page gardée : l'adresse sans les paramètres d'un instant. */
function cleDePage(adresse) {
  const url = new URL(adresse)
  for (const nom of PARAMETRES_PASSAGERS) url.searchParams.delete(nom)
  url.hash = ''
  return url.href
}

async function garderPage(cle, reponse) {
  const cache = await caches.open(PAGES)
  await cache.put(cle, reponse)
  const cles = await cache.keys()
  // Les plus anciennes d'abord (ordre d'insertion) : on garde les PAGES_MAX dernières.
  await Promise.all(cles.slice(0, Math.max(0, cles.length - PAGES_MAX)).map((r) => cache.delete(r)))
}

async function effacerVideo(id) {
  const cache = await caches.open(VIDEOS)
  const cles = await cache.keys()
  await Promise.all(cles.filter((r) => cheminDe(new URL(r.url)) === `/m/${id}`).map((r) => cache.delete(r)))
}

async function page(evenement, requete, chemin) {
  const cle = cleDePage(requete.url)
  let reponse
  try {
    reponse = await fetch(requete)
  } catch {
    let copie
    if (!JAMAIS_GARDEES.test(chemin)) {
      const cache = await caches.open(PAGES)
      // La même page, sinon la même page avec une autre section (?section=2) ou un autre mode.
      copie = (await cache.match(cle)) ?? (await cache.match(cle, { ignoreSearch: true }))
    }
    return copie ?? (await caches.match(BASE + 'hors-ligne', { cacheName: INTERFACE })) ?? reponseSansReseau()
  }
  // Réseau présent : les vidéos gardées seront revérifiées une fois pour cette nouvelle page.
  verifiees.clear()
  const video = /^\/videos\/(\d+)$/.exec(chemin)
  if (reponse.status === 404 && video) {
    // Page d'une vidéo disparue : la page gardée s'efface (la copie de la vidéo, elle, s'efface à sa prochaine
    // lecture, après la vérification auprès du serveur : une navigation venue d'ailleurs ne vide rien d'autre).
    evenement.waitUntil(caches.open(PAGES).then((c) => c.delete(cle)))
  }
  if (reponse.ok && reponse.type === 'basic') {
    if (chemin === '/lien') {
      // Lien renouvelé (le serveur l'a constaté : cookie d'accès périmé) : rien de ce qui était gardé ne doit rester
      // lisible sur ce téléphone. Un simple lien vers /lien, sans cet en-tête, ne vide rien.
      if (reponse.headers.get('X-Chores-Oublier') === '1') await Promise.all([caches.delete(PAGES), caches.delete(VIDEOS)])
    } else if (chemin === '/') {
      // Entrée (prénom changé) : les pages gardées étaient celles d'un autre prénom.
      await caches.delete(PAGES)
    } else if (!JAMAIS_GARDEES.test(chemin) && chemin !== '/hors-ligne') {
      evenement.waitUntil(garderPage(cle, reponse.clone()).catch(() => {}))
    }
  }
  return reponse
}

function reponseSansReseau() {
  return new Response('Pas de réseau.', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } })
}

async function statique(requete) {
  const cache = await caches.open(INTERFACE)
  // Les adresses portent une empreinte (?v=…) ; le service worker change de version avec les fichiers.
  const garde = await cache.match(requete, { ignoreSearch: true })
  if (garde) return garde
  const reponse = await fetch(requete)
  if (reponse.ok && reponse.type === 'basic') cache.put(requete, reponse.clone()).catch(() => {})
  return reponse
}

/**
 * Une vidéo gardée existe-t-elle encore sur le serveur (« la », « absente » ou « lien retire ») ?
 * Une requête HEAD (jamais servie par le cache HTTP du
 * navigateur), une seule fois par vidéo et par page ouverte en ligne, même si elle échoue ; sans réseau (ou sans
 * réponse en 3 secondes), on fait confiance à la copie.
 */
function encoreSurLeServeur(url) {
  if (!verifiees.has(url)) {
    verifiees.set(url, (async () => {
      const controle = new AbortController()
      const minuterie = setTimeout(() => controle.abort(), 3000)
      try {
        const reponse = await fetch(url, { method: 'HEAD', cache: 'no-store', credentials: 'same-origin', redirect: 'manual', signal: controle.signal })
        if (reponse.type === 'opaqueredirect') return 'lien retire'
        return reponse.status === 404 ? 'absente' : 'la'
      } catch {
        return 'la'
      } finally {
        clearTimeout(minuterie)
      }
    })())
  }
  return verifiees.get(url)
}

/** Vidéo gardée : servie depuis le cache, entière ou par plage d'octets ; sinon le réseau, sans rien garder. */
async function video(requete) {
  const cache = await caches.open(VIDEOS)
  const garde = await cache.match(requete.url)
  if (!garde) return fetch(requete)
  const etat = await encoreSurLeServeur(requete.url)
  if (etat !== 'la') {
    // Supprimée ou remplacée (ancienne adresse ?v=) : cette copie s'efface ; lien retiré : toutes les copies de la
    // vidéo. Le serveur répond lui-même (404 ou page du lien), sans passer par le cache HTTP.
    if (etat === 'absente') await cache.delete(requete.url)
    else await effacerVideo(cheminDe(new URL(requete.url)).slice(3))
    return fetch(new Request(requete, { cache: 'no-store' }))
  }
  const contenu = await garde.blob()
  const type = garde.headers.get('Content-Type') || 'video/mp4'
  const taille = contenu.size
  const plage = /^bytes=(\d*)-(\d*)$/.exec((requete.headers.get('Range') || '').trim())
  if (!plage || (plage[1] === '' && plage[2] === '')) {
    return new Response(contenu, { status: 200, headers: { 'Content-Type': type, 'Content-Length': String(taille), 'Accept-Ranges': 'bytes' } })
  }
  let debut
  let fin
  if (plage[1] === '') {
    debut = Math.max(0, taille - Number(plage[2]))
    fin = taille - 1
  } else {
    debut = Number(plage[1])
    fin = plage[2] === '' ? taille - 1 : Math.min(Number(plage[2]), taille - 1)
  }
  if (!(debut < taille) || fin < debut) {
    return new Response('', { status: 416, headers: { 'Content-Range': `bytes */${taille}` } })
  }
  return new Response(contenu.slice(debut, fin + 1, type), {
    status: 206,
    headers: {
      'Content-Type': type,
      'Content-Length': String(fin - debut + 1),
      'Content-Range': `bytes ${debut}-${fin}/${taille}`,
      'Accept-Ranges': 'bytes',
    },
  })
}
