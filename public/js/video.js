// Page d'une vidéo de cousin (spec § 4, « lecteur simplifié ») : vitesses 0,5 / 0,75 / 1 × (hauteur de la musique
// gardée), miroir avec état écrit, commentaires « à 0:42 » qui positionnent la vidéo sans recharger la page,
// « Prendre la position » pour le formulaire, durée posée au premier visionnage de l'auteur. Sans ce script : les
// contrôles natifs de la vidéo et des liens « ?t= » (fragment #t= de la vidéo).
import { formatTemps, libelleVitesse } from './lecteur-calc.js'

function annoncer(zone, texte) {
  if (!zone) return
  zone.textContent = ''
  requestAnimationFrame(() => {
    zone.textContent = texte
  })
}

function demarrer(racine) {
  const video = racine.querySelector('[data-video]')
  if (!video) return
  const annonce = racine.querySelector('[data-annonce]')
  const horloge = racine.querySelector('[data-horloge]')
  const badge = racine.querySelector('[data-badge-miroir]')
  const miroir = racine.querySelector('[data-action="miroir"]')
  racine.querySelectorAll('[data-js-seulement]').forEach((el) => {
    el.hidden = false
  })
  video.preservesPitch = true
  if ('webkitPreservesPitch' in video) video.webkitPreservesPitch = true

  racine.querySelectorAll('[data-vitesse]').forEach((bouton) => {
    bouton.addEventListener('click', () => {
      const vitesse = Number(bouton.dataset.vitesse)
      video.playbackRate = vitesse
      racine.querySelectorAll('[data-vitesse]').forEach((b) => b.setAttribute('aria-pressed', b === bouton ? 'true' : 'false'))
      annoncer(annonce, `Vitesse ${libelleVitesse(vitesse)}`)
    })
  })

  miroir?.addEventListener('click', () => {
    const actif = !racine.classList.contains('lecteur-simple--miroir')
    racine.classList.toggle('lecteur-simple--miroir', actif)
    miroir.setAttribute('aria-pressed', actif ? 'true' : 'false')
    miroir.querySelector('[data-etat]').textContent = actif ? 'activé' : 'désactivé'
    if (badge) badge.hidden = !actif
    annoncer(annonce, actif ? 'Miroir activé' : 'Miroir désactivé')
  })

  video.addEventListener('timeupdate', () => {
    if (horloge) horloge.textContent = formatTemps(video.currentTime)
  })

  // Durée posée une fois, par le navigateur de l'auteur (le serveur ne lit jamais la vidéo).
  const poserDuree = racine.dataset.poserDuree
  if (poserDuree) {
    const envoyer = () => {
      if (!Number.isFinite(video.duration) || video.duration <= 0) return
      const donnees = new FormData()
      donnees.set('duree', video.duration.toFixed(2))
      donnees.set('csrf', document.querySelector('form[data-commentaire] input[name="csrf"]')?.value ?? '')
      fetch(poserDuree, { method: 'POST', body: donnees, credentials: 'same-origin', headers: { Accept: 'application/json' } }).catch(() => {})
    }
    if (video.readyState >= 1) envoyer()
    else video.addEventListener('loadedmetadata', envoyer, { once: true })
  }

  // « à 0:42 » : positionne la vidéo de la page, sans recharger.
  document.addEventListener('click', (e) => {
    const lien = e.target instanceof Element ? e.target.closest('a[data-temps]') : null
    if (!lien) return
    const adresse = new URL(lien.href, location.href)
    if (adresse.pathname !== location.pathname) return
    e.preventDefault()
    const temps = Number(lien.dataset.temps) || 0
    video.currentTime = temps
    history.replaceState(null, '', `${location.pathname}?t=${Math.floor(temps)}#lecteur-video`)
    racine.scrollIntoView({ block: 'start' })
    video.focus({ preventScroll: true })
    annoncer(annonce, `Vidéo à ${formatTemps(temps)}`)
  })

  const position = document.querySelector('[data-prendre-position]')
  const champ = document.querySelector('#commentaire-temps')
  if (position) position.hidden = false
  position?.addEventListener('click', () => {
    if (!champ) return
    champ.value = formatTemps(video.currentTime)
    annoncer(annonce, `Instant pris : ${champ.value}`)
    champ.focus()
  })
}

document.querySelectorAll('[data-lecteur-simple]').forEach(demarrer)
