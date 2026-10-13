// Copie les polices auto-hébergées depuis les paquets @fontsource (dépendances de développement) vers
// public/polices/, avec la version du paquet dans le nom des fichiers (cache long sans risque de version périmée),
// et recopie les licences OFL dans public/polices/LICENCES.md. Aucun appel réseau : les paquets sont déjà installés.
// Usage : node scripts/polices.mjs
import { copyFileSync, mkdirSync, readFileSync, statSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'

const racine = new URL('..', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1')
const destination = join(racine, 'public', 'polices')

const paquets = [
  {
    nom: '@fontsource/yeseva-one',
    famille: 'Yeseva One',
    prefixe: 'yeseva-one',
    fichiers: ['yeseva-one-latin-400-normal.woff2', 'yeseva-one-latin-ext-400-normal.woff2'],
  },
  {
    nom: '@fontsource-variable/nunito-sans',
    famille: 'Nunito Sans (variable, axe wght)',
    prefixe: 'nunito-sans',
    fichiers: ['nunito-sans-latin-wght-normal.woff2', 'nunito-sans-latin-ext-wght-normal.woff2'],
  },
]

mkdirSync(destination, { recursive: true })
const licences = [
  '# Licences des polices',
  '',
  'Polices auto-hébergées (aucun appel à un service de polices), copiées par `scripts/polices.mjs` depuis les paquets',
  '`@fontsource` installés en dépendances de développement. Les deux sont sous SIL Open Font License 1.1, recopiée',
  'ci-dessous telle que fournie dans chaque paquet.',
  '',
]
let total = 0
for (const paquet of paquets) {
  const dossier = join(racine, 'node_modules', ...paquet.nom.split('/'))
  const version = JSON.parse(readFileSync(join(dossier, 'package.json'), 'utf8')).version
  const suffixe = 'v' + version.replaceAll('.', '-')
  licences.push(`## ${paquet.famille} — ${paquet.nom} ${version}`, '')
  for (const fichier of paquet.fichiers) {
    const cible = fichier.replace(/\.woff2$/, `-${suffixe}.woff2`)
    copyFileSync(join(dossier, 'files', fichier), join(destination, cible))
    const taille = statSync(join(destination, cible)).size
    total += taille
    licences.push(`- \`${cible}\` (${taille} octets)`)
    console.log(`${cible} : ${taille} octets`)
  }
  licences.push('', '```', readFileSync(join(dossier, 'LICENSE'), 'utf8').trim(), '```', '')
}
writeFileSync(join(destination, 'LICENCES.md'), licences.join('\n'))
console.log(`Total : ${total} octets (${(total / 1024).toFixed(1)} Ko).`)
