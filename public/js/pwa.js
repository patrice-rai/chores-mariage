// « Garder sur mon téléphone » (spec § 8) et liste de la page « Hors ligne ». Chargé par site.js seulement quand la
// page en a besoin. Une vidéo gardée est rangée entière dans le cache videos-v1, que le service worker (sw.js) sert
// ensuite, plages d'octets comprises ; la page qui la montre est gardée avec (cache pages-v1). Sans service worker
// qui contrôle la page, les boutons restent cachés : rien ne serait servi hors ligne.

const VIDEOS = 'videos-v1'
const PAGES = 'pages-v1'
const CLE_LISTE = 'cm-gardees'
const MO = 1024 * 1024

function lireListe() {
  try {
    const liste = JSON.parse(localStorage.getItem(CLE_LISTE) || '{}')
    return liste && typeof liste === 'object' ? liste : {}
  } catch {
    return {}
  }
}

function ecrireListe(liste) {
  try {
    localStorage.setItem(CLE_LISTE, JSON.stringify(liste))
  } catch {
    // Rien : la vidéo reste gardée, seule la liste de la page « Hors ligne » ne l'affichera pas.
  }
}

function absolue(url) {
  return new URL(url, location.href).href
}

async function estGardee(url) {
  const cache = await caches.open(VIDEOS)
  return Boolean(await cache.match(absolue(url)))
}

function mo(octets) {
  return Math.max(1, Math.round(octets / MO))
}

class Garder {
  constructor(racine) {
    this.racine = racine
    this.url = racine.dataset.url
    this.taille = Number(racine.dataset.taille) || 0
    this.bouton = racine.querySelector('[data-garder-action]')
    this.progression = racine.querySelector('[data-garder-progression]')
    this.etat = racine.querySelector('[data-garder-etat]')
    this.libelle = this.bouton.innerHTML
    this.controleur = null
    this.bouton.addEventListener('click', () => this.basculer())
  }

  async montrer() {
    this.racine.hidden = false
    this.majBouton(await estGardee(this.url))
  }

  majBouton(gardee) {
    this.gardee = gardee
    if (gardee) {
      this.bouton.textContent = 'Retirer de mon téléphone'
      this.dire('Gardée sur ton téléphone : elle se lit sans réseau.')
    } else {
      this.bouton.innerHTML = this.libelle
    }
    const lecteur = this.racine.closest('[data-lecteur], [data-lecteur-simple]') ?? document.querySelector('[data-lecteur]')
    if (lecteur) lecteur.dataset.garde = gardee ? '1' : '0'
  }

  dire(texte) {
    this.etat.textContent = texte
  }

  async basculer() {
    if (this.controleur) {
      this.controleur.abort()
      return
    }
    if (this.gardee) {
      await (await caches.open(VIDEOS)).delete(absolue(this.url))
      const liste = lireListe()
      delete liste[absolue(this.url)]
      ecrireListe(liste)
      this.majBouton(false)
      this.dire('Retirée de ton téléphone.')
      return
    }
    await this.garder()
  }

  async garder() {
    if (!navigator.onLine) {
      this.dire('Pas de réseau : reviens quand tu en auras pour garder la vidéo.')
      return
    }
    if (navigator.storage?.estimate) {
      try {
        const { quota = 0, usage = 0 } = await navigator.storage.estimate()
        const libre = quota - usage
        if (quota && libre < this.taille * 1.1) {
          this.dire(`Pas assez de place sur ton téléphone : il faut ${mo(this.taille)} Mo, il en reste ${Math.max(0, Math.floor(libre / MO))}.`)
          return
        }
      } catch {
        // Estimation impossible : on essaie quand même ; l'échec éventuel est dit plus bas.
      }
    }
    this.controleur = new AbortController()
    this.bouton.textContent = 'Arrêter le téléchargement'
    this.progression.hidden = false
    this.progression.value = 0
    const cle = absolue(this.url)
    try {
      const reponse = await fetch(cle, { cache: 'no-store', credentials: 'same-origin', signal: this.controleur.signal })
      if (!reponse.ok || !reponse.body) throw new Error('reseau')
      const total = Number(reponse.headers.get('Content-Length')) || this.taille
      const [lecture, rangement] = reponse.body.tee()
      const cache = await caches.open(VIDEOS)
      const enCache = cache.put(cle, new Response(rangement, { headers: { 'Content-Type': reponse.headers.get('Content-Type') || 'video/mp4' } }))
      // Son échec (annulation, place) est traité plus bas, au moment où on l'attend.
      enCache.catch(() => {})
      const lecteur = lecture.getReader()
      let recu = 0
      let dernier = -1
      for (;;) {
        const { done, value } = await lecteur.read()
        if (done) break
        recu += value.byteLength
        const pourcent = total ? Math.min(100, Math.floor((recu / total) * 100)) : 0
        this.progression.value = pourcent
        this.progression.textContent = `${pourcent} %`
        // Une annonce par tranche de 25 % : assez pour suivre, sans bavarder.
        if (Math.floor(pourcent / 25) !== dernier) {
          dernier = Math.floor(pourcent / 25)
          this.dire(`Téléchargement : ${pourcent} % · ${mo(recu)} Mo sur ${mo(total)} Mo`)
        }
      }
      await enCache
      const liste = lireListe()
      liste[cle] = { titre: this.racine.dataset.titre, page: absolue(this.racine.dataset.page) }
      ecrireListe(liste)
      // La page qui montre la vidéo est gardée avec : elle s'ouvrira sans réseau.
      ;(await caches.open(PAGES)).add(new Request(liste[cle].page, { credentials: 'same-origin' })).catch(() => {})
      if (navigator.storage?.persist) navigator.storage.persist().catch(() => {})
      this.majBouton(true)
    } catch (erreur) {
      await (await caches.open(VIDEOS)).delete(cle).catch(() => {})
      this.majBouton(false)
      if (erreur?.name === 'AbortError') this.dire('Téléchargement arrêté : rien n’a été gardé.')
      else if (erreur?.name === 'QuotaExceededError') this.dire('Pas assez de place sur ton téléphone : rien n’a été gardé.')
      else this.dire('La vidéo n’a pas pu être gardée (réseau coupé ?) : rien n’a été gardé. Réessaie avec le wifi.')
    } finally {
      this.controleur = null
      this.progression.hidden = true
    }
  }
}

/** Page « Hors ligne » : les vidéos réellement présentes dans le cache, avec leur page. */
async function listeHorsLigne() {
  const liste = document.querySelector('[data-liste-gardees]')
  if (!liste || !('caches' in window)) return
  const cache = await caches.open(VIDEOS)
  const presentes = new Set((await cache.keys()).map((r) => r.url))
  const connues = lireListe()
  const elements = Object.entries(connues).filter(([url]) => presentes.has(url))
  if (!elements.length) return
  liste.replaceChildren(
    ...elements.map(([, info]) => {
      const li = document.createElement('li')
      const a = document.createElement('a')
      a.href = info.page
      a.textContent = info.titre
      li.append(a)
      return li
    }),
  )
  liste.hidden = false
  document.querySelector('[data-gardees-vide]')?.setAttribute('hidden', '')
  const section = document.querySelector('[data-gardees-section]')
  if (section) section.hidden = false
}

/**
 * Une vidéo remplacée par Patrice (version convertie) change d'adresse (?v=…) : l'ancienne copie gardée ne servirait
 * plus à rien et prendrait de la place. Elle est effacée dès que la page montre la nouvelle adresse.
 */
async function effacerAnciennesCopies(blocs) {
  const cache = await caches.open(VIDEOS)
  const cles = await cache.keys()
  const liste = lireListe()
  for (const bloc of blocs) {
    const actuelle = new URL(absolue(bloc.url))
    for (const requete of cles) {
      const garde = new URL(requete.url)
      if (garde.pathname === actuelle.pathname && garde.href !== actuelle.href) {
        await cache.delete(requete)
        delete liste[garde.href]
      }
    }
  }
  ecrireListe(liste)
}

async function demarrer() {
  listeHorsLigne()
  const blocs = [...document.querySelectorAll('[data-garder]')].map((racine) => new Garder(racine))
  if (blocs.length && 'caches' in window) await effacerAnciennesCopies(blocs).catch(() => {})
  if (!blocs.length || !('serviceWorker' in navigator) || !('caches' in window) || !window.ReadableStream) return
  const montrer = () => blocs.forEach((b) => b.montrer())
  if (navigator.serviceWorker.controller) montrer()
  else navigator.serviceWorker.addEventListener('controllerchange', montrer, { once: true })
}

demarrer()
