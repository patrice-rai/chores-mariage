// Améliorations communes à toutes les pages (rien n'en dépend : tout marche sans JavaScript) :
// - bascule de thème sans rechargement (le formulaire enregistre aussi le choix en cookie, par fetch) ;
// - bannière « Pas de réseau » quand la connexion tombe, avec les niveaux en attente d'envoi ;
// - service worker (PWA) : enregistrement, encart « Nouvelle version » ; niveaux notés hors ligne renvoyés ;
// - « Garder sur mon téléphone » et liste « Hors ligne » (pwa.js, chargé seulement s'il sert) ;
// - décomptes autonomes avec « Rejouer » (page /gabarits).
import { lireFile, rejouer, texteFile, viderFile } from './file-niveaux.js'

const TEXTE_HORS_LIGNE = 'Pas de réseau : tu peux répéter les chansons gardées sur ton téléphone.'
const BASE = new URL('../', import.meta.url)

function basculeTheme(formulaire) {
  formulaire.addEventListener('submit', (e) => {
    const bouton = e.submitter
    const theme = bouton?.value
    if (theme !== 'clair' && theme !== 'sombre') return
    e.preventDefault()
    document.documentElement.dataset.theme = theme
    // Le bouton touché disparaît (l'autre état s'affiche) : le focus passe au bouton visible.
    formulaire.querySelector(`button[name="theme"]:not([value="${theme}"])`)?.focus()
    const donnees = new FormData(formulaire)
    donnees.set('theme', theme)
    fetch(formulaire.action, {
      method: 'POST',
      body: donnees,
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    }).catch(() => {
      // Hors ligne : le thème s'applique quand même sur cette page.
    })
  })
}

const banniere = document.querySelector('[data-hors-ligne]')

function majBanniere() {
  if (!banniere) return
  const attente = lireFile().length
  banniere.textContent = navigator.onLine ? '' : TEXTE_HORS_LIGNE + (attente ? ` ${texteFile(attente)} au retour du réseau.` : '')
}

async function renvoyerNiveaux() {
  const envoyes = await rejouer()
  majBanniere()
  if (envoyes.length) document.dispatchEvent(new CustomEvent('niveaux:envoyes', { detail: { envoyes } }))
}

function banniereHorsLigne() {
  window.addEventListener('online', () => {
    majBanniere()
    renvoyerNiveaux()
  })
  window.addEventListener('offline', majBanniere)
  document.addEventListener('niveaux:file', majBanniere)
  majBanniere()
  renvoyerNiveaux()
}

/** Service worker : enregistré sur le périmètre du site ; « Nouvelle version » quand il en remplace un autre. */
function serviceWorker() {
  if (!('serviceWorker' in navigator)) return
  const avait = Boolean(navigator.serviceWorker.controller)
  navigator.serviceWorker.addEventListener('controllerchange', () => {
    const zone = document.querySelector('[data-nouvelle-version]')
    if (!avait || !zone || zone.childElementCount) return
    // La zone role="status" est toujours là, vide : le texte qui arrive est annoncé.
    const bloc = document.createElement('div')
    bloc.className = 'nouvelle-version'
    const texte = document.createElement('p')
    texte.className = 'nouvelle-version__texte'
    texte.textContent = 'Nouvelle version du site : recharge la page pour l’avoir.'
    // Une action : un bouton qui recharge vraiment (un lien vers l'adresse avec ancre ne ferait que défiler).
    const bouton = document.createElement('button')
    bouton.type = 'button'
    bouton.className = 'bouton bouton--or nouvelle-version__action'
    bouton.textContent = 'Recharger'
    bouton.addEventListener('click', () => location.reload())
    bloc.append(texte, bouton)
    zone.append(bloc)
  })
  navigator.serviceWorker.register(new URL('sw.js', BASE), { scope: BASE.pathname }).catch(() => {
    // Navigation privée, navigateur ancien : le site marche en ligne, sans « Garder ».
  })
}

async function decomptes() {
  const blocs = document.querySelectorAll('[data-decompte]')
  if (!blocs.length) return
  const [{ lancerDecompte }, { cadenceMs }] = await Promise.all([import('./decompte.js'), import('./lecteur-calc.js')])
  blocs.forEach((bloc) => {
    const bouton = bloc.querySelector('[data-decompte-rejouer]')
    if (!bouton) return
    let enCours = null
    bouton.hidden = false
    bouton.addEventListener('click', () => {
      enCours?.arreter()
      enCours = lancerDecompte({
        chiffre: bloc.querySelector('[data-decompte-chiffre]'),
        texte: bloc.querySelector('[data-decompte-texte]'),
        annonce: bloc.querySelector('[data-decompte-annonce]'),
        cadence: cadenceMs(Number(bloc.dataset.bpm) || null, 1),
        fin: () => {
          enCours = null
        },
      })
    })
  })
}

/** Bouton qui n'a de sens qu'avec JavaScript : son paragraphe (caché sans JavaScript) apparaît. */
function montrer(bouton) {
  const bloc = bouton.closest('[data-avec-js]')
  if (bloc) bloc.hidden = false
}

/** « Afficher la clé » (installation) : le champ masqué passe en clair pour relire ce qui a été collé. */
function afficherSecrets() {
  document.querySelectorAll('[data-afficher-secret]').forEach((bouton) => {
    const champ = document.getElementById(bouton.getAttribute('aria-controls') ?? '')
    if (!champ) return
    montrer(bouton)
    bouton.addEventListener('click', () => {
      const masque = champ.type === 'password'
      champ.type = masque ? 'text' : 'password'
      bouton.textContent = masque ? 'Masquer la clé' : 'Afficher la clé'
    })
  })
}

/** « Copier le lien » (installation, nouveau lien) : presse-papiers, résultat annoncé dans une zone role="status". */
function copierChamps() {
  document.querySelectorAll('[data-copier]').forEach((bouton) => {
    const champ = document.getElementById(bouton.dataset.copier ?? '')
    const statut = document.getElementById(bouton.dataset.copierStatut ?? '')
    if (!champ || !statut || !navigator.clipboard) return
    montrer(bouton)
    bouton.addEventListener('click', async () => {
      statut.textContent = ''
      try {
        await navigator.clipboard.writeText(champ.value)
        statut.textContent = bouton.dataset.copierFait ?? 'Copié.'
      } catch {
        champ.focus()
        champ.setSelectionRange(0, champ.value.length)
        statut.textContent = 'Copie impossible : sélectionne le texte et copie-le à la main.'
      }
    })
  })
}

document.querySelectorAll('[data-bascule-theme]').forEach(basculeTheme)
afficherSecrets()
copierChamps()
// Erreurs d'un formulaire renvoyé par le serveur : le résumé prend le focus (il est en haut du formulaire, parfois
// loin sous le lecteur), et le lecteur d'écran le lit.
document.querySelector('[data-resume-serveur]')?.focus()
// Page « Lien manquant » (lien renouvelé) : ce téléphone oublie ce qu'il gardait (titres des vidéos gardées,
// niveaux en attente) ; le service worker, lui, efface les pages et les vidéos.
if (document.querySelector('[data-oublier-telephone]')) {
  viderFile()
  try {
    localStorage.removeItem('cm-gardees')
  } catch {
    // Rien à oublier.
  }
}
// « Changer » de prénom : les niveaux notés hors ligne étaient ceux du prénom d'avant, ils ne partent pas sous le
// nouveau.
document.querySelectorAll(`form[action="${new URL('changer', BASE).pathname}"]`).forEach((f) => f.addEventListener('submit', viderFile))
// Écran d'entrée (aucun prénom choisi sur ce téléphone, par « Changer » ou cookie expiré) : même chose, le prénom
// choisi ensuite ne doit pas recevoir les niveaux d'un autre.
if (location.pathname === BASE.pathname) viderFile()
banniereHorsLigne()
serviceWorker()
decomptes()
if (document.querySelector('[data-garder], [data-liste-gardees]')) import('./pwa.js')
