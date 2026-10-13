// File des niveaux marqués hors ligne (spec § 4) : rangée dans localStorage, renvoyée au retour du réseau (et à
// chaque chargement de page), sans Background Sync (absent de Safari). Un niveau plus récent pour la même section
// remplace l'ancien. Aucun jeton n'est rangé : le renvoi prend celui de la page ouverte à ce moment-là (le cookie
// du jeton ne dure que la session). Seule une vraie réponse du serveur (JSON, sans redirection) compte comme
// « envoyé » ; un refus définitif (lien renouvelé, prénom changé, choré quittée) retire l'élément sans le compter, un
// jeton périmé (400) le garde pour la page suivante.

const CLE = 'cm-file-niveaux'
let enCours = null

export function lireFile() {
  try {
    const file = JSON.parse(localStorage.getItem(CLE) || '[]')
    return Array.isArray(file) ? file.filter((e) => e && typeof e.action === 'string' && typeof e.niveau === 'string') : []
  } catch {
    return []
  }
}

function ecrireFile(file) {
  try {
    if (file.length) localStorage.setItem(CLE, JSON.stringify(file))
    else localStorage.removeItem(CLE)
  } catch {
    // Stockage refusé (navigation privée) : rien à garder.
  }
}

/** Met un niveau en file ; renvoie le nombre de niveaux à envoyer. */
export function mettreEnFile(action, niveau) {
  const file = lireFile().filter((e) => e.action !== action)
  file.push({ action, niveau: String(niveau) })
  ecrireFile(file)
  return file.length
}

/** « 1 niveau à envoyer », « 2 niveaux à envoyer ». */
export function texteFile(nombre) {
  return `${nombre} niveau${nombre > 1 ? 'x' : ''} à envoyer`
}

/** Oublie la file (prénom changé, lien renouvelé). */
export function viderFile() {
  ecrireFile([])
}

async function envoyerFile() {
  const file = lireFile()
  const jeton = document.querySelector('input[name="csrf"]')?.value
  if (!file.length || !navigator.onLine || !jeton) return []
  const traites = []
  const envoyes = []
  for (const element of file) {
    // Le jeton ne part que vers ce site (une entrée de localStorage modifiée ne l'envoie pas ailleurs).
    if (new URL(element.action, location.href).origin !== location.origin) {
      traites.push(element)
      continue
    }
    const donnees = new FormData()
    donnees.set('niveau', element.niveau)
    donnees.set('csrf', jeton)
    let reponse
    try {
      reponse = await fetch(element.action, {
        method: 'POST',
        body: donnees,
        credentials: 'same-origin',
        redirect: 'manual',
        headers: { Accept: 'application/json' },
      })
    } catch {
      break
    }
    // Débit, panne, ou jeton périmé (400 : la page venait du cache) : on réessaiera avec une page fraîche.
    if (reponse.status === 400 || reponse.status === 429 || reponse.status >= 500) continue
    traites.push(element)
    const json = (reponse.headers.get('content-type') || '').includes('application/json')
    if (reponse.ok && json) {
      const resultat = await reponse.json().catch(() => null)
      if (resultat && typeof resultat.niveau === 'number') envoyes.push({ action: element.action, ...resultat })
    }
  }
  // Relire la file : un niveau touché pendant l'envoi y est resté.
  ecrireFile(lireFile().filter((e) => !traites.some((t) => t.action === e.action && t.niveau === e.niveau)))
  return envoyes
}

/**
 * Renvoie la file (un seul renvoi à la fois).
 * @returns {Promise<Array<{action: string, niveau: number, libelle: string, progression: string}>>} niveaux enregistrés
 */
export function rejouer() {
  enCours ??= envoyerFile().finally(() => {
    enCours = null
  })
  return enCours
}
