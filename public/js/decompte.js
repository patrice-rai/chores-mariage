// Décompte 5-6-7-8 (spec § 4) : un chiffre par temps à la cadence donnée, texte « Décompte : n » toujours écrit,
// annonce polie pour les lecteurs d'écran. Seule animation du site : la classe decompte__chiffre--bat, coupée par
// la feuille en mouvement réduit (prefers-reduced-motion).

const CHIFFRES = [5, 6, 7, 8]

/**
 * Lance le décompte. Renvoie { arreter() }. fin() est appelée après le 8, au temps suivant.
 * @param {{ chiffre: HTMLElement, texte?: HTMLElement|null, annonce?: HTMLElement|null, cadence: number,
 *           fin?: () => void }} options
 */
export function lancerDecompte({ chiffre, texte = null, annonce = null, cadence, fin = () => {} }) {
  const minuteries = []
  let actif = true

  const afficher = (n) => {
    if (!actif) return
    chiffre.textContent = String(n)
    chiffre.classList.remove('decompte__chiffre--bat')
    // Relance l'animation : lecture d'une dimension entre le retrait et l'ajout de la classe.
    void chiffre.offsetWidth
    chiffre.classList.add('decompte__chiffre--bat')
    if (texte) texte.textContent = `Décompte : ${n}`
    if (annonce) annonce.textContent = `Décompte : ${n}`
  }

  CHIFFRES.forEach((n, i) => {
    if (i === 0) afficher(n)
    else minuteries.push(setTimeout(() => afficher(n), cadence * i))
  })
  minuteries.push(
    setTimeout(() => {
      if (!actif) return
      actif = false
      chiffre.classList.remove('decompte__chiffre--bat')
      fin()
    }, cadence * CHIFFRES.length),
  )

  return {
    arreter() {
      actif = false
      minuteries.forEach(clearTimeout)
      chiffre.classList.remove('decompte__chiffre--bat')
    },
  }
}
