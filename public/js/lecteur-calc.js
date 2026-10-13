// Fonctions pures du lecteur (spec § 4), sans DOM : testées par node --test (tests/js/lecteur-calc.test.mjs).
// Mêmes règles d'affichage que app/format.php.

export const VITESSES = [0.5, 0.75, 1]
export const TEMPO_PAR_DEFAUT = 120
export const CADENCE_MIN_MS = 380
/** Avance de la boucle : on revient au début un peu avant la fin, pour ne jamais entendre la section suivante. */
export const MARGE_BOUCLE_S = 0.08
/** Tolérance avant le début (le positionnement s'arrête parfois un peu avant l'instant demandé). */
export const TOLERANCE_DEBUT_S = 0.25

/** Durée d'un temps du décompte en millisecondes : max(380, 60000 / bpm / vitesse) ; tempo inconnu : 120. */
export function cadenceMs(bpm, vitesse = 1) {
  const tempo = Number.isFinite(bpm) && bpm > 0 ? bpm : TEMPO_PAR_DEFAUT
  const v = Number.isFinite(vitesse) && vitesse > 0 ? vitesse : 1
  return Math.max(CADENCE_MIN_MS, Math.round(60000 / tempo / v))
}

/**
 * Instant corrigé par la boucle A-B : t s'il est dans la section, sinon le début de la section
 * (fin atteinte, à MARGE_BOUCLE_S près, ou position nettement avant le début).
 */
export function dansBoucle(t, debut, fin) {
  if (!Number.isFinite(t) || !(fin > debut)) return t
  if (t >= fin - MARGE_BOUCLE_S || t < debut - TOLERANCE_DEBUT_S) return debut
  return t
}

/** 17.4 → « 0:17 » ; 3725 → « 1:02:05 » (secondes tronquées, comme une horloge). */
export function formatTemps(secondes) {
  const s = Number.isFinite(secondes) && secondes > 0 ? Math.floor(secondes) : 0
  const h = Math.floor(s / 3600)
  const m = Math.floor((s % 3600) / 60)
  const r = String(s % 60).padStart(2, '0')
  return h > 0 ? `${h}:${String(m).padStart(2, '0')}:${r}` : `${m}:${r}`
}

/** 244 → « 4 min 04 » (arrondi à la seconde) ; inconnue → « durée inconnue ». */
export function formatDuree(secondes) {
  if (!Number.isFinite(secondes) || secondes < 0) return 'durée inconnue'
  const s = Math.round(secondes)
  return `${Math.floor(s / 60)} min ${String(s % 60).padStart(2, '0')}`
}

/** 0.75 → « 0,75 × ». */
export function libelleVitesse(vitesse) {
  return `${String(vitesse).replace('.', ',')} ×`
}

/** Section suivante (on repart de la première après la dernière) ; null sans section. */
export function sectionSuivante(index, nombre) {
  if (!(nombre > 0)) return null
  return (((index + 1) % nombre) + nombre) % nombre
}

/** Section précédente (la dernière avant la première) ; null sans section. */
export function sectionPrecedente(index, nombre) {
  if (!(nombre > 0)) return null
  return (((index - 1) % nombre) + nombre) % nombre
}

/** Index de la section qui contient l'instant t, ou -1. */
export function sectionA(t, sections) {
  for (let i = 0; i < sections.length; i++) {
    if (t >= sections[i].debut && t < sections[i].fin) return i
  }
  return -1
}

/** Pourcentage (0 à 100) d'un instant sur une durée, borné. */
export function pourcentage(t, duree) {
  if (!(duree > 0) || !Number.isFinite(t)) return 0
  return Math.min(100, Math.max(0, (100 * t) / duree))
}

/** Statut du mode répétition : « Miroir · 0,75 × · Boucle ». */
export function statutRepetition(miroir, vitesse, boucle) {
  return `${miroir ? 'Miroir' : 'Pas de miroir'} · ${libelleVitesse(vitesse)}${boucle ? ' · Boucle' : ''}`
}
