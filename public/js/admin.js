// Administration de Patrice (amélioration seulement : chaque champ se remplit aussi à la main) :
// - « Taper le tempo » : un toucher par temps, la moyenne des 8 derniers intervalles s'écrit dans le champ tempo ;
// - « Début / Fin depuis le lecteur » : copie la position de la vidéo de référence au format mm:ss,d.

function tempo() {
  const bouton = document.querySelector('[data-taper-tempo]')
  const champ = document.querySelector('[data-tempo]')
  const etat = document.querySelector('[data-tempo-etat]')
  if (!bouton || !champ) return
  bouton.closest('[data-js-seulement]')?.removeAttribute('hidden')
  let touches = []
  bouton.addEventListener('click', () => {
    const maintenant = performance.now()
    // Plus de 2 secondes sans toucher : on recommence.
    if (touches.length && maintenant - touches[touches.length - 1] > 2000) touches = []
    touches.push(maintenant)
    touches = touches.slice(-9)
    if (touches.length < 2) {
      if (etat) etat.textContent = 'Continue à toucher à chaque temps…'
      return
    }
    const intervalles = touches.slice(1).map((t, i) => t - touches[i])
    const moyenne = intervalles.reduce((a, b) => a + b, 0) / intervalles.length
    const bpm = Math.round(60000 / moyenne)
    if (bpm >= 40 && bpm <= 240) {
      champ.value = String(bpm)
      if (etat) etat.textContent = `${bpm} temps par minute (moyenne de ${intervalles.length} intervalle${intervalles.length > 1 ? 's' : ''}). Enregistre la fiche pour le garder.`
    }
  })
}

function formatTemps(secondes) {
  const dixiemes = Math.round(secondes * 10)
  const minutes = Math.floor(dixiemes / 600)
  const reste = dixiemes % 600
  return `${minutes}:${String(Math.floor(reste / 10)).padStart(2, '0')},${reste % 10}`
}

function depuisLecteur() {
  const video = document.getElementById('video-admin')
  if (!video) return
  document.querySelectorAll('[data-depuis-lecteur]').forEach((bouton) => {
    bouton.hidden = false
    bouton.addEventListener('click', () => {
      const champ = document.getElementById(bouton.dataset.depuisLecteur)
      if (!champ) return
      champ.value = formatTemps(video.currentTime || 0)
      champ.focus()
    })
  })
}

tempo()
depuisLecteur()
