// Page d'une chanson (amélioration seulement : tout marche sans JavaScript, par formulaires et liens) :
// - les niveaux « Pas encore / Presque / Je maîtrise » s'envoient sans recharger la page (fetch, réponse JSON),
//   puis les boutons, la ligne de la grille « Qui maîtrise quoi » et la progression se mettent à jour ;
// - « Ma place » suit la section choisie dans le lecteur (évènement lecteur:section) ;
// - hors ligne, un niveau touché attend le retour du réseau (file-niveaux.js, renvoyé par site.js).

import { mettreEnFile, texteFile } from './file-niveaux.js'

const GLYPHES = ['·', '≈', '✓']
const MOTS = ['pas encore', 'presque', 'je maîtrise']

function majBoutons(formulaire, niveau) {
  formulaire.querySelectorAll('button[name="niveau"]').forEach((bouton) => {
    const choisi = Number(bouton.value) === niveau
    bouton.setAttribute('aria-pressed', choisi ? 'true' : 'false')
    const coche = bouton.querySelector('.niveau__coche')
    if (coche) coche.hidden = !choisi
  })
}

function majGrille(index, niveau) {
  const ligne = document.querySelector('.grille-maitrise__moi')
  const caseGrille = ligne?.querySelectorAll('td')[index]
  if (!caseGrille) return
  caseGrille.className = `grille-maitrise__case grille-maitrise__case--${niveau}`
  const [glyphe, mot] = caseGrille.querySelectorAll('span')
  if (glyphe) glyphe.textContent = GLYPHES[niveau]
  if (mot) mot.textContent = MOTS[niveau]
}

/** Sans réponse JSON (réseau coupé, formulaire expiré, lien renouvelé…), le formulaire part normalement : la page
 *  qui revient dit en français ce qui se passe. */
function envoyerSansScript(formulaire, valeur) {
  const champ = document.createElement('input')
  champ.type = 'hidden'
  champ.name = 'niveau'
  champ.value = valeur
  formulaire.append(champ)
  formulaire.submit()
}

/** Une zone role="status" toujours présente ; le texte est vidé puis remis au tour suivant pour que le même
 *  message soit annoncé deux fois de suite. */
function annoncer(zone, texte) {
  if (!zone) return
  zone.textContent = ''
  requestAnimationFrame(() => {
    zone.textContent = texte
  })
}

function niveaux() {
  const message = document.querySelector('[data-niveau-message]')
  const progression = document.querySelector('[data-progression]')
  document.querySelectorAll('form[data-niveau]').forEach((formulaire) => {
    formulaire.addEventListener('submit', async (e) => {
      const bouton = e.submitter
      if (!bouton || bouton.name !== 'niveau') return
      e.preventDefault()
      const donnees = new FormData(formulaire)
      donnees.set('niveau', bouton.value)
      let reponse
      try {
        reponse = await fetch(formulaire.action, {
          method: 'POST',
          body: donnees,
          credentials: 'same-origin',
          headers: { Accept: 'application/json' },
        })
      } catch {
        if (navigator.onLine) {
          envoyerSansScript(formulaire, bouton.value)
          return
        }
        // Hors ligne : le niveau attend le retour du réseau (site.js le renvoie), la page le montre déjà.
        const nombre = mettreEnFile(formulaire.action, bouton.value)
        majBoutons(formulaire, Number(bouton.value))
        majGrille(Number(formulaire.dataset.niveau), Number(bouton.value))
        annoncer(message, `Pas de réseau : ton niveau est noté sur ton téléphone (${texteFile(nombre)}).`)
        document.dispatchEvent(new CustomEvent('niveaux:file'))
        return
      }
      if (!(reponse.headers.get('content-type') || '').includes('application/json')) {
        envoyerSansScript(formulaire, bouton.value)
        return
      }
      let resultat = null
      try {
        resultat = await reponse.json()
      } catch {
        resultat = null
      }
      if (!reponse.ok || !resultat || typeof resultat.niveau !== 'number') {
        // Les refus du serveur sont déjà en français ; sinon, une phrase fixe.
        annoncer(message, typeof resultat?.erreur === 'string' ? resultat.erreur : 'Ton niveau n’a pas été enregistré : recharge la page et réessaie.')
        return
      }
      majBoutons(formulaire, resultat.niveau)
      majGrille(Number(formulaire.dataset.niveau), resultat.niveau)
      if (progression) progression.textContent = `Toi : ${resultat.progression}.`
      annoncer(message, `C’est noté : ${resultat.libelle.toLowerCase()}. ${resultat.progression}.`)
    })
  })
}

function maPlace() {
  const scenes = document.querySelectorAll('[data-mini-scene]')
  if (!scenes.length) return
  document.addEventListener('lecteur:section', (e) => {
    const index = e.detail?.index
    if (typeof index !== 'number') return
    scenes.forEach((scene) => {
      scene.hidden = Number(scene.dataset.miniScene) !== index
    })
  })
}

niveaux()
maPlace()

document.addEventListener('niveaux:envoyes', (e) => {
  const envoyes = e.detail?.envoyes ?? []
  const nombre = envoyes.length
  // L'écran montre ce que le serveur a enregistré (la page avait été rendue avant le renvoi).
  for (const resultat of envoyes) {
    const cible = new URL(resultat.action, location.href).href
    const formulaire = [...document.querySelectorAll('form[data-niveau]')].find((f) => f.action === cible)
    if (!formulaire) continue
    majBoutons(formulaire, resultat.niveau)
    majGrille(Number(formulaire.dataset.niveau), resultat.niveau)
    const progression = document.querySelector('[data-progression]')
    if (progression) progression.textContent = `Toi : ${resultat.progression}.`
  }
  if (!nombre) return
  annoncer(document.querySelector('[data-niveau-message]'), `Réseau revenu : ${nombre} niveau${nombre > 1 ? 'x' : ''} envoyé${nombre > 1 ? 's' : ''}.`)
})
