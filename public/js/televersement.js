// Téléversement d'une vidéo (spec § 7) : sans ce script, le formulaire part normalement. Avec lui : taille
// contrôlée avant l'envoi, XMLHttpRequest avec progression (« 42 % · 31 Mo sur 74 Mo »), bouton Annuler, erreurs du
// serveur affichées dans le résumé en haut du formulaire et aux champs, en toutes lettres.

const MO = 1024 * 1024
const LIBELLES = { 'video-chanson': 'Chanson', 'video-titre': 'Titre', 'video-note': 'Note', 'video-fichier': 'Vidéo' }

function mo(octets) {
  return octets >= 10 * MO ? String(Math.round(octets / MO)) : (octets / MO).toFixed(1).replace('.', ',')
}

/** Résumé des erreurs (même rendu que app/vues/composants/resume-erreurs.php), focus dessus. */
function afficherErreurs(formulaire, erreurs) {
  const zone = formulaire.querySelector('[data-resume-erreurs]')
  const message = formulaire.querySelector('[data-message]')
  if (message) message.textContent = ''
  formulaire.querySelectorAll('[data-erreur-js]').forEach((p) => p.remove())
  formulaire.querySelectorAll('.champ--erreur-js').forEach((bloc) => bloc.classList.remove('champ--erreur', 'champ--erreur-js'))
  formulaire.querySelectorAll('[aria-invalid="true"]').forEach((champ) => champ.removeAttribute('aria-invalid'))
  const ids = Object.keys(erreurs)
  const resume = document.createElement('div')
  resume.className = 'formulaire__resume'
  resume.setAttribute('role', 'alert')
  resume.tabIndex = -1
  const titre = document.createElement('p')
  titre.className = 'formulaire__resume-titre'
  titre.textContent = ids.length > 1 ? `Ta vidéo n’est pas partie, il reste ${ids.length} choses à corriger :` : 'Ta vidéo n’est pas partie, il reste une chose à corriger :'
  const liste = document.createElement('ul')
  for (const id of ids) {
    const li = document.createElement('li')
    const a = document.createElement('a')
    a.href = `#${id}`
    a.textContent = `${LIBELLES[id] ?? 'Vidéo'} : ${erreurs[id]}`
    li.append(a)
    liste.append(li)
    const champ = formulaire.querySelector(`#${CSS.escape(id)}`)
    if (champ) {
      champ.setAttribute('aria-invalid', 'true')
      champ.closest('.champ')?.classList.add('champ--erreur', 'champ--erreur-js')
      const p = document.createElement('p')
      p.className = 'champ__erreur'
      p.id = `${id}-erreur-js`
      p.dataset.erreurJs = ''
      const mot = document.createElement('span')
      mot.className = 'champ__erreur-mot'
      mot.textContent = 'Erreur :'
      p.append(mot, ` ${erreurs[id]}`)
      champ.before(p)
      const decrit = (champ.getAttribute('aria-describedby') || '').split(' ').filter((v) => v && !v.endsWith('-erreur-js'))
      champ.setAttribute('aria-describedby', [...decrit, p.id].join(' '))
    }
  }
  resume.append(titre, liste)
  zone.replaceChildren(resume)
  resume.focus()
}

function demarrer(formulaire) {
  const max = Number(formulaire.dataset.maxOctets) || 0
  const fichier = formulaire.querySelector('input[type="file"]')
  const envoyer = formulaire.querySelector('[data-envoyer]')
  const bloc = formulaire.querySelector('[data-progression]')
  const barre = formulaire.querySelector('[data-barre]')
  const texte = formulaire.querySelector('[data-texte-progression]')
  const annuler = formulaire.querySelector('[data-annuler]')
  const message = formulaire.querySelector('[data-message]')
  let xhr = null

  // Fin de l'envoi : le bouton « Envoyer » revient et reprend le focus (sauf si le résumé des erreurs le prend).
  const fin = () => {
    xhr = null
    bloc.hidden = true
    envoyer.hidden = false
    envoyer.disabled = false
    envoyer.focus()
  }

  annuler.addEventListener('click', () => {
    if (!xhr) return
    xhr.abort()
  })

  formulaire.addEventListener('submit', (e) => {
    e.preventDefault()
    if (xhr) return
    const choisi = fichier.files?.[0]
    if (choisi && max && choisi.size > max) {
      afficherErreurs(formulaire, { 'video-fichier': `La vidéo est trop lourde (${mo(choisi.size)} Mo) : ${Math.round(max / MO)} Mo au plus. Filme une section ou deux.` })
      return
    }
    message.textContent = ''
    xhr = new XMLHttpRequest()
    xhr.open('POST', formulaire.action)
    xhr.setRequestHeader('Accept', 'application/json')
    xhr.responseType = 'json'
    let dernier = -1
    xhr.upload.addEventListener('progress', (p) => {
      // Un dernier évènement peut arriver après la réponse : l'envoi est déjà fini.
      if (!xhr || !p.lengthComputable) return
      const pourcent = Math.min(100, Math.floor((p.loaded / p.total) * 100))
      barre.value = pourcent
      barre.textContent = `${pourcent} %`
      texte.textContent = `${pourcent} % · ${mo(p.loaded)} Mo sur ${mo(p.total)} Mo`
      // Annonce par tranche de 25 % (la barre et le texte, eux, suivent tout).
      if (Math.floor(pourcent / 25) !== dernier) {
        dernier = Math.floor(pourcent / 25)
        message.textContent = `Envoi : ${pourcent} %`
      }
    })
    xhr.addEventListener('load', () => {
      const reponse = xhr.response
      const statut = xhr.status
      fin()
      message.textContent = ''
      if (statut === 201 && reponse?.url) {
        message.textContent = 'Vidéo envoyée : ouverture de sa page…'
        location.assign(reponse.url)
        return
      }
      if (reponse?.erreurs) {
        afficherErreurs(formulaire, reponse.erreurs)
        return
      }
      if (statut === 413) {
        afficherErreurs(formulaire, { 'video-fichier': `La vidéo est trop lourde : ${Math.round(max / MO)} Mo au plus.` })
        return
      }
      if (statut === 400) {
        message.textContent = 'Ce formulaire a expiré : recharge la page, puis choisis de nouveau ta vidéo.'
        return
      }
      message.textContent = statut === 429 ? 'Tu as déjà envoyé beaucoup de vidéos : réessaie dans une heure.' : 'L’envoi n’a pas abouti : réessaie (avec le wifi, c’est plus sûr).'
    })
    xhr.addEventListener('error', () => {
      fin()
      message.textContent = 'L’envoi s’est interrompu (réseau coupé ?) : rien n’a été gardé. Réessaie avec le wifi.'
    })
    xhr.addEventListener('abort', () => {
      fin()
      message.textContent = 'Envoi annulé : rien n’a été gardé.'
      envoyer.focus()
    })
    barre.value = 0
    texte.textContent = '0 %'
    bloc.hidden = false
    envoyer.hidden = true
    envoyer.disabled = true
    annuler.focus()
    xhr.send(new FormData(formulaire))
  })
}

document.querySelectorAll('form[data-televersement]').forEach(demarrer)
