// Lecteur de la vidéo de référence (spec § 4) : vitesses, miroir, boucle par section, décompte 5-6-7-8 au tempo,
// sections, raccourcis clavier, mode répétition (plein écran, écran gardé allumé). Amélioration seulement : sans ce
// script, la vidéo garde ses contrôles natifs et les liens (Plein écran, Section suivante, Quitter) marchent.
import {
  VITESSES,
  cadenceMs,
  dansBoucle,
  formatTemps,
  libelleVitesse,
  pourcentage,
  sectionA,
  sectionPrecedente,
  sectionSuivante,
  statutRepetition,
} from './lecteur-calc.js'
import { lancerDecompte } from './decompte.js'

const PREFIXE_PREFERENCES = 'cm-lecteur-'
const CLE_RACCOURCIS = 'cm-raccourcis'

function lire(cle) {
  try {
    return JSON.parse(localStorage.getItem(cle) ?? 'null')
  } catch {
    return null
  }
}

function ecrire(cle, valeur) {
  try {
    localStorage.setItem(cle, JSON.stringify(valeur))
  } catch {
    // Stockage refusé (navigation privée, réglages) : le lecteur marche sans mémoire.
  }
}

function comptesEtDuree(section) {
  return `${section.comptes} × 8 · ${formatTemps(Math.round(section.fin - section.debut))}`
}

class Lecteur {
  constructor(racine) {
    this.racine = racine
    this.video = racine.querySelector('[data-video]')
    this.cle = racine.dataset.cle ?? ''
    this.bpm = Number(racine.dataset.bpm) || null
    this.repetition = racine.dataset.mode === 'repetition'
    this.lienSection = racine.dataset.lienSection ?? ''
    try {
      this.sections = JSON.parse(racine.dataset.sections || '[]')
    } catch {
      this.sections = []
    }
    this.courante = Math.min(Number(racine.dataset.courante) || 0, Math.max(0, this.sections.length - 1))
    this.vitesse = 1
    this.miroir = false
    this.boucle = false
    this.decompte = null
    this.sansDecompte = false
    this.deverrouille = false
    this.verrou = null
    this.demandeVerrou = null
    this.rvfc = false
    this.reprise = null
    const q = (s) => racine.querySelector(s)
    this.el = {
      lecture: q('[data-action="lecture"]'),
      miroir: q('[data-action="miroir"]'),
      boucle: q('[data-action="boucle"]'),
      suivante: q('[data-action="suivante"]'),
      quitter: q('[data-action="quitter"]'),
      pleinEcran: q('[data-action="plein-ecran"]'),
      lienRepetition: q('[data-lien-repetition]'),
      vitesses: [...racine.querySelectorAll('[data-vitesse]')],
      badge: q('[data-badge-miroir]'),
      horloge: q('[data-horloge]'),
      noms: [...racine.querySelectorAll('[data-nom-section]')],
      comptes: q('[data-comptes-section]'),
      statut: q('[data-statut]'),
      voileChargement: q('[data-voile-chargement]'),
      voileDecompte: q('[data-voile-decompte]'),
      chiffre: q('[data-decompte-chiffre]'),
      texteDecompte: q('[data-decompte-texte]'),
      annonce: q('[data-annonce]'),
      message: q('[data-message]'),
      position: q('[data-position]'),
      borne: q('[data-borne]'),
    }
    this.listeSections = document.querySelector(`[data-sections-de="${CSS.escape(racine.id)}"]`)
    this.lignes = this.listeSections ? [...this.listeSections.querySelectorAll('[data-ligne-section]')] : []
  }

  demarrer() {
    this.racine.querySelectorAll('[data-js-seulement]').forEach((el) => {
      el.hidden = false
    })
    // Liens de repli remplacés par leur bouton (« Section suivante ») quand le script prend la main.
    this.racine.querySelectorAll('[data-sans-js]').forEach((el) => {
      el.hidden = true
    })
    const preferences = lire(PREFIXE_PREFERENCES + this.cle)
    if (preferences && VITESSES.includes(preferences.vitesse)) this.vitesse = preferences.vitesse
    if (preferences && typeof preferences.miroir === 'boolean') this.miroir = preferences.miroir

    if (this.video) {
      this.video.controls = false
      this.ecouterVideo()
    }
    this.el.lecture?.addEventListener('click', () => this.basculerLecture())
    this.el.miroir?.addEventListener('click', () => this.basculerMiroir())
    this.el.boucle?.addEventListener('click', () => this.basculerBoucle())
    this.el.vitesses.forEach((b) => b.addEventListener('click', () => this.choisirVitesse(Number(b.dataset.vitesse))))
    this.el.suivante?.addEventListener('click', () => this.allerA(sectionSuivante(this.courante, this.sections.length)))
    if (this.el.pleinEcran) {
      if (document.fullscreenEnabled) {
        this.el.pleinEcran.addEventListener('click', () => this.basculerPleinEcran())
        // L'état suit aussi les sorties par Échap ou par le navigateur.
        document.addEventListener('fullscreenchange', () => {
          const actif = Boolean(document.fullscreenElement)
          this.majBouton(this.el.pleinEcran, actif, actif ? 'activé' : 'désactivé')
        })
      } else {
        this.el.pleinEcran.hidden = true
      }
    }
    this.lignes.forEach((ligne) => {
      const lien = ligne.querySelector('[data-section]')
      lien?.addEventListener('click', (e) => {
        e.preventDefault()
        this.choisirSection(Number(lien.dataset.section))
        this.annoncer(this.descriptionSection())
      })
    })
    this.appliquerVitesse()
    this.appliquerMiroir()
    this.majBoucle()
    this.majSection()
    this.majLecture()
  }

  ecouterVideo() {
    const v = this.video
    v.addEventListener('timeupdate', () => this.surTemps(v.currentTime))
    v.addEventListener('loadedmetadata', () => {
      this.appliquerVitesse()
      this.majBornes()
      this.majPosition()
    })
    v.addEventListener('play', () => {
      this.majLecture()
      this.suivreImages()
      this.garderEcran(true)
    })
    v.addEventListener('pause', () => {
      this.majLecture()
      this.chargement(false)
      this.garderEcran(false)
    })
    v.addEventListener('ended', () => {
      // Boucle sur une section qui finit avec la vidéo : le navigateur s'arrête avant le dernier timeupdate.
      const section = this.sections[this.courante]
      if (this.boucle && section) {
        v.currentTime = section.debut
        this.jouer()
      } else {
        this.majLecture()
      }
    })
    for (const nom of ['waiting', 'stalled']) {
      v.addEventListener(nom, () => {
        if (!v.paused) this.chargement(true)
      })
    }
    for (const nom of ['canplay', 'playing', 'seeked']) v.addEventListener(nom, () => this.chargement(false))
    v.addEventListener('error', () => {
      this.ecrireMessage('La vidéo ne se charge pas : vérifie le réseau, ou dis-le à Patrice.')
    })
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible' && !v.paused) this.garderEcran(true)
    })
  }

  // --- Lecture et décompte -------------------------------------------------------------------------------

  basculerLecture() {
    if (!this.video) return
    if (this.decompte) {
      this.arreterDecompte()
      return
    }
    if (!this.video.paused && !this.video.ended) {
      this.video.pause()
      return
    }
    this.lancer()
  }

  lancer() {
    const v = this.video
    if (!v) return
    this.ecrireMessage('')
    if (this.sansDecompte) {
      // Le navigateur a refusé le démarrage différé : ce geste-ci lance la vidéo tout de suite.
      this.sansDecompte = false
      this.jouer()
      return
    }
    // Dans le geste de l'utilisateur, une lecture aussitôt interrompue autorise la lecture après le décompte
    // sur les téléphones qui exigent un geste (Safari sur iPhone).
    if (!this.deverrouille) {
      this.deverrouille = true
      v.play()?.catch(() => {})
      v.pause()
    }
    const section = this.sections[this.courante]
    // Après un saut (« à 0:42 »), le décompte part de cet instant ; sinon du début de la section.
    if (this.reprise !== null) this.positionner(this.reprise)
    else if (section) this.positionner(section.debut)
    this.reprise = null
    this.el.voileDecompte.hidden = false
    this.decompte = lancerDecompte({
      chiffre: this.el.chiffre,
      texte: this.el.texteDecompte,
      annonce: this.el.annonce,
      cadence: cadenceMs(this.bpm, this.vitesse),
      fin: () => {
        this.decompte = null
        this.el.voileDecompte.hidden = true
        this.jouer()
      },
    })
    this.majLecture()
  }

  jouer() {
    const promesse = this.video.play()
    promesse?.catch((erreur) => {
      // Seul un refus de lecture automatique appelle un nouveau geste ; une vidéo illisible a son propre message.
      if (erreur?.name !== 'NotAllowedError') return
      this.sansDecompte = true
      this.ecrireMessage('Touche Lecture à nouveau : ton navigateur a bloqué le démarrage.')
      this.majLecture()
    })
    this.majLecture()
  }

  arreterDecompte() {
    if (!this.decompte) return
    this.decompte.arreter()
    this.decompte = null
    this.el.voileDecompte.hidden = true
    this.annoncer('Décompte arrêté')
    this.majLecture()
  }

  majLecture() {
    const b = this.el.lecture
    if (!b) return
    const enLecture = this.video && !this.video.paused && !this.video.ended
    b.textContent = this.decompte ? 'Arrêter' : enLecture ? 'Pause' : 'Lecture'
  }

  chargement(visible) {
    if (!this.el.voileChargement) return
    const montrer = visible && !this.decompte
    // Vidéo gardée par « Garder sur mon téléphone » (pwa.js pose data-garde) : elle vient du téléphone, pas du réseau.
    const texte = this.racine.dataset.garde === '1' ? 'Vidéo gardée sur ton téléphone' : 'Chargement de la vidéo…'
    const zone = this.el.voileChargement.querySelector('[data-voile-texte]')
    if (zone) zone.textContent = texte
    if (montrer && this.el.voileChargement.hidden) this.annoncer(texte)
    this.el.voileChargement.hidden = !montrer
  }

  // --- Temps, boucle, progression ------------------------------------------------------------------------

  positionner(t) {
    if (!this.video) return
    try {
      this.video.currentTime = t
    } catch {
      // Métadonnées pas encore chargées : la position sera prise au chargement.
    }
    this.surTemps(t)
  }

  /** Fin réelle de la section : jamais au-delà de la fin de la vidéo. */
  finSection(section) {
    const d = this.video?.duration
    return Number.isFinite(d) && d > 0 ? Math.min(section.fin, d) : section.fin
  }

  surTemps(t) {
    const section = this.sections[this.courante]
    if (this.boucle && section && this.video && !this.video.paused) {
      const corrige = dansBoucle(t, section.debut, this.finSection(section))
      if (corrige !== t) {
        this.video.currentTime = corrige
        t = corrige
      }
    } else if (!this.boucle && this.sections.length && this.video && !this.video.paused) {
      const i = sectionA(t, this.sections)
      if (i >= 0 && i !== this.courante) {
        this.courante = i
        this.majSection()
        this.majAdresse()
      }
    }
    if (this.el.horloge) this.el.horloge.textContent = formatTemps(t)
    this.majPosition(t)
  }

  /** Boucle plus précise que timeupdate (4 fois par seconde) quand le navigateur suit chaque image. */
  suivreImages() {
    const v = this.video
    if (this.rvfc || !v || typeof v.requestVideoFrameCallback !== 'function') return
    this.rvfc = true
    const image = (_maintenant, meta) => {
      if (v.paused || v.ended) {
        this.rvfc = false
        return
      }
      const section = this.sections[this.courante]
      if (this.boucle && section) {
        const corrige = dansBoucle(meta.mediaTime, section.debut, this.finSection(section))
        if (corrige !== meta.mediaTime) v.currentTime = corrige
      }
      v.requestVideoFrameCallback(image)
    }
    v.requestVideoFrameCallback(image)
  }

  duree() {
    const d = this.video?.duration
    if (Number.isFinite(d) && d > 0) return d
    return this.sections.length ? this.sections[this.sections.length - 1].fin : 0
  }

  majPosition(t = this.video?.currentTime ?? 0) {
    if (this.el.position) this.el.position.style.width = `${pourcentage(t, this.duree())}%`
  }

  majBornes() {
    const borne = this.el.borne
    const section = this.sections[this.courante]
    if (!borne) return
    if (!section || !this.duree()) {
      borne.hidden = true
      return
    }
    const debut = pourcentage(section.debut, this.duree())
    borne.hidden = false
    borne.style.left = `${debut}%`
    borne.style.width = `${pourcentage(section.fin, this.duree()) - debut}%`
  }

  // --- Sections --------------------------------------------------------------------------------------------

  choisirSection(i, relancer = false) {
    if (!this.sections[i]) return
    const enCours = Boolean(this.decompte) || (this.video && !this.video.paused && !this.video.ended)
    this.arreterDecompte()
    this.courante = i
    if (this.video) {
      this.video.pause()
      this.positionner(this.sections[i].debut)
    }
    this.majSection()
    this.majAdresse()
    if (relancer && enCours) this.lancer()
  }

  /** ?section=N dans l'adresse : un rechargement revient sur la même section. */
  majAdresse() {
    try {
      const adresse = new URL(location.href)
      adresse.searchParams.set('section', String(this.courante + 1))
      history.replaceState(history.state, '', adresse)
    } catch {
      // Adresse non modifiable : sans conséquence.
    }
  }

  allerA(i) {
    if (i === null) return
    this.choisirSection(i, true)
    this.annoncer(this.descriptionSection())
  }

  descriptionSection() {
    const s = this.sections[this.courante]
    return s ? `${s.nom}, ${comptesEtDuree(s)}` : ''
  }

  majSection() {
    const s = this.sections[this.courante]
    const nombre = this.sections.length
    if (s) {
      this.el.noms.forEach((el) => {
        el.textContent = s.nom
      })
      if (this.el.comptes) this.el.comptes.textContent = comptesEtDuree(s)
    }
    this.lignes.forEach((ligne) => {
      const courante = Number(ligne.dataset.ligneSection) === this.courante
      ligne.classList.toggle('ligne-section--courante', courante)
      const lien = ligne.querySelector('[data-section]')
      if (courante) lien?.setAttribute('aria-current', 'true')
      else lien?.removeAttribute('aria-current')
      const enCours = ligne.querySelector('[data-en-cours]')
      if (enCours) enCours.hidden = !courante
    })
    if (this.el.lienRepetition) this.el.lienRepetition.href = this.lienSection + (this.courante + 1)
    const repli = this.racine.querySelector('[data-lien-suivante]')
    if (repli && nombre > 0) repli.href = this.lienSection + (sectionSuivante(this.courante, nombre) + 1)
    if (this.el.quitter) {
      try {
        const adresse = new URL(this.el.quitter.href)
        adresse.searchParams.set('section', String(this.courante + 1))
        this.el.quitter.href = adresse.toString()
      } catch {
        // Adresse relative inattendue : on la laisse.
      }
    }
    this.majBornes()
    // La page (« Ma place ») suit la section choisie sans rechargement.
    this.racine.dispatchEvent(new CustomEvent('lecteur:section', { bubbles: true, detail: { index: this.courante } }))
  }

  // --- Vitesse, miroir, boucle ----------------------------------------------------------------------------

  choisirVitesse(v) {
    if (!VITESSES.includes(v)) return
    this.vitesse = v
    this.appliquerVitesse()
    this.memoriser()
  }

  appliquerVitesse() {
    if (this.video) {
      this.video.defaultPlaybackRate = this.vitesse
      this.video.playbackRate = this.vitesse
      this.video.preservesPitch = true
      if ('webkitPreservesPitch' in this.video) this.video.webkitPreservesPitch = true
    }
    this.el.vitesses.forEach((b) => b.setAttribute('aria-pressed', String(Number(b.dataset.vitesse) === this.vitesse)))
    this.majStatut()
  }

  basculerMiroir() {
    this.miroir = !this.miroir
    this.appliquerMiroir()
    this.memoriser()
  }

  appliquerMiroir() {
    this.racine.classList.toggle('lecteur--miroir', this.miroir)
    if (this.el.badge) this.el.badge.hidden = !this.miroir
    this.majBouton(this.el.miroir, this.miroir, this.miroir ? 'activé' : 'désactivé')
    this.majStatut()
  }

  basculerBoucle() {
    if (!this.sections.length) return
    this.boucle = !this.boucle
    this.majBoucle()
  }

  majBoucle() {
    this.majBouton(this.el.boucle, this.boucle, this.boucle ? 'activée' : 'désactivée')
    this.majStatut()
  }

  majBouton(bouton, actif, mot) {
    if (!bouton) return
    bouton.setAttribute('aria-pressed', String(actif))
    const etat = bouton.querySelector('[data-etat]')
    if (etat) etat.textContent = mot
  }

  majStatut() {
    if (this.el.statut) this.el.statut.textContent = statutRepetition(this.miroir, this.vitesse, this.boucle)
  }

  memoriser() {
    ecrire(PREFIXE_PREFERENCES + this.cle, { vitesse: this.vitesse, miroir: this.miroir })
  }

  // --- Mode répétition : plein écran, écran allumé ---------------------------------------------------------

  basculerPleinEcran() {
    if (document.fullscreenElement) {
      document.exitFullscreen?.().catch(() => {})
      return
    }
    // Toute la page (bannière « Pas de réseau » comprise).
    document.documentElement.requestFullscreen?.().catch(() => this.ecrireMessage('Le plein écran n’est pas disponible ici.'))
  }

  garderEcran(oui) {
    if (!this.repetition || !('wakeLock' in navigator)) return
    if (oui && !this.verrou && !this.demandeVerrou) {
      // Une seule demande à la fois : un second verrou écraserait le premier sans le rendre.
      this.demandeVerrou = navigator.wakeLock
        .request('screen')
        .then((verrou) => {
          // Mise en pause pendant la demande : on rend aussitôt le verrou.
          if (!this.video || this.video.paused) {
            verrou.release().catch(() => {})
            return
          }
          this.verrou = verrou
          verrou.addEventListener('release', () => {
            this.verrou = null
          })
        })
        .catch(() => {})
        .finally(() => {
          this.demandeVerrou = null
        })
    } else if (!oui && this.verrou) {
      this.verrou.release().catch(() => {})
      this.verrou = null
    }
  }

  // --- Messages ------------------------------------------------------------------------------------------

  annoncer(texte) {
    if (this.el.annonce) this.el.annonce.textContent = texte
  }

  ecrireMessage(texte) {
    if (this.el.message) this.el.message.textContent = texte
  }

  /** Saut à un instant (commentaire horodaté) : la section suit, et Lecture comptera depuis cet instant. */
  allerAuTemps(t) {
    if (!this.video) return
    this.arreterDecompte()
    this.video.pause()
    const i = sectionA(t, this.sections)
    if (i >= 0 && i !== this.courante) {
      this.courante = i
      this.majSection()
      this.majAdresse()
    }
    this.positionner(t)
    this.reprise = t
    this.annoncer(`Vidéo placée à ${formatTemps(t)}`)
  }

  /** Contient l'élément (le lecteur lui-même ou sa liste de sections). */
  contient(element) {
    return element instanceof Node && (this.racine.contains(element) || Boolean(this.listeSections?.contains(element)))
  }
}

// --- Raccourcis clavier (désactivables : WCAG 2.1.4) ----------------------------------------------------------

function raccourcisActifs() {
  return lire(CLE_RACCOURCIS) !== false
}

function majBoutonsRaccourcis(actifs) {
  document.querySelectorAll('[data-action="raccourcis"]').forEach((b) => {
    b.setAttribute('aria-pressed', String(actifs))
    const etat = b.querySelector('[data-etat]')
    if (etat) etat.textContent = actifs ? 'activés' : 'désactivés'
  })
}

function ecouterClavier(lecteur) {
  document.addEventListener('keydown', (e) => {
    if (!raccourcisActifs() || e.ctrlKey || e.metaKey || e.altKey || e.defaultPrevented) return
    const cible = e.target
    if (cible instanceof HTMLElement && (cible.isContentEditable || cible.closest('input, textarea, select'))) return
    // Sur une page qui défile, les raccourcis n'agissent que si le focus est dans le lecteur ou ses sections ;
    // en mode répétition (le lecteur occupe la page), partout.
    if (!lecteur.repetition && !lecteur.contient(document.activeElement)) return
    const touche = e.key.length === 1 ? e.key.toLowerCase() : e.key
    const vitesse = (v) => () => {
      lecteur.choisirVitesse(v)
      lecteur.annoncer(`Vitesse ${libelleVitesse(v)}`)
    }
    const actions = {
      ' ': () => lecteur.basculerLecture(),
      m: () => {
        lecteur.basculerMiroir()
        lecteur.annoncer(lecteur.miroir ? 'Miroir activé' : 'Miroir désactivé')
      },
      b: () => {
        lecteur.basculerBoucle()
        lecteur.annoncer(lecteur.boucle ? 'Boucle activée' : 'Boucle désactivée')
      },
      1: vitesse(0.5),
      2: vitesse(0.75),
      3: vitesse(1),
      n: () => lecteur.allerA(sectionSuivante(lecteur.courante, lecteur.sections.length)),
      p: () => lecteur.allerA(sectionPrecedente(lecteur.courante, lecteur.sections.length)),
      Escape: () => {
        if (lecteur.repetition && !document.fullscreenElement && lecteur.el.quitter) location.assign(lecteur.el.quitter.href)
      },
    }
    const action = actions[touche]
    if (!action) return
    // Espace sur un bouton, un lien ou un résumé garde son rôle natif (activer l'élément).
    if (touche === ' ' && cible instanceof HTMLElement && cible.closest('button, a, summary, details')) return
    e.preventDefault()
    action()
  })
}

// --- Démarrage -------------------------------------------------------------------------------------------------

const lecteurs = [...document.querySelectorAll('[data-lecteur]')].map((racine) => {
  const lecteur = new Lecteur(racine)
  lecteur.demarrer()
  return lecteur
})

// Raccourcis seulement pour un lecteur qui a une vidéo (sans vidéo, le panneau qui les désactive est absent).
const principal = lecteurs.find((l) => l.video)
if (principal) {
  ecouterClavier(principal)
  majBoutonsRaccourcis(raccourcisActifs())
  document.querySelectorAll('[data-action="raccourcis"]').forEach((b) =>
    b.addEventListener('click', () => {
      const actifs = !raccourcisActifs()
      ecrire(CLE_RACCOURCIS, actifs)
      majBoutonsRaccourcis(actifs)
    }),
  )
}

if (lecteurs.length) {
  // Commentaires horodatés : « à 0:42 » positionne le lecteur visé par le lien de la même page, sans recharger,
  // et lui donne le focus (bouton Lecture).
  document.addEventListener('click', (e) => {
    const lien = e.target instanceof Element ? e.target.closest('a[data-temps]') : null
    if (!lien) return
    const adresse = new URL(lien.href, location.href)
    if (adresse.pathname !== location.pathname) return
    const lecteur = lecteurs.find((l) => l.racine.id === adresse.hash.slice(1) && l.video)
    if (!lecteur) return
    e.preventDefault()
    lecteur.allerAuTemps(Number(lien.dataset.temps) || 0)
    lecteur.racine.scrollIntoView({ block: 'start' })
    lecteur.el.lecture?.focus({ preventScroll: true })
  })
}
