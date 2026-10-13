// Hook PreToolUse : refuse ce que CLAUDE.md interdit, avant l'exécution de l'outil.
// - lecture, écriture ou recherche visant config.php (secrets) ou stockage/ (base et vidéos des cousins) ;
// - commandes interdites : git push, gh pr/repo, suppression récursive, docker … prune, conteneurs des autres
//   projets, --no-verify, docker compose down -v.
// Refus → code 2 et raison sur stderr (Claude Code bloque l'appel et montre la raison).
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

const AUTRES_PROJETS = /(hospitalite|argan|bagnolet|paroisse)/i

const COMMANDES_INTERDITES = [
  { motif: /\bgit\b[^|;&\n]*\bpush\b/i, raison: 'git push (interdit : aucun dépôt distant)' },
  { motif: /\bgit\b[^|;&\n]*\bremote\s+(add|set-url)\b/i, raison: 'ajout d’un dépôt distant (interdit)' },
  { motif: /\bgh\s+(pr|repo|auth)\b/i, raison: 'gh pr / repo / auth (interdit)' },
  { motif: /--no-verify\b/i, raison: '--no-verify (interdit)' },
  { motif: /\bgit\b[^|;&\n]*\bcommit\b[^|;&\n]*\s-[a-zA-Z]*n[a-zA-Z]*\b/, raison: 'git commit -n (équivaut à --no-verify)' },
  { motif: /\bdocker\b[^|;&\n]*\bprune\b/i, raison: 'docker … prune (interdit : docker rm <nom> ciblé seulement)' },
  { motif: /\bdocker[\s-]compose\b[^|;&\n]*\bdown\b[^|;&\n]*(\s-v\b|--volumes\b)/i, raison: 'docker compose down -v (supprimerait la base locale)' },
  { motif: /\bdocker\b[^|;&\n]*\bvolume\s+(rm|remove)\b/i, raison: 'suppression d’un volume Docker (à faire à la main)' },
  { motif: /\bdocker\b[^|;&\n]*\b(rm|stop|kill|restart|pause|exec)\b[^|;&\n]*/i, test: (c) => AUTRES_PROJETS.test(c), raison: 'conteneur d’un autre projet (interdit)' },
  { motif: /(^|[\s;&|("'\\])rm\b[^|;&\n]*\s(-[a-zA-Z]*[rR][a-zA-Z]*|--recursive)\b/, raison: 'suppression récursive (rm -r)' },
  { motif: /(^|[\s;&|("'])(Remove-Item|ri|rm|rmdir|rd|del|erase)\s(?:[^|;&\n]*\s)?-r[a-z]*\b/i, raison: 'suppression récursive (-Recurse)' },
  { motif: /(^|[\s;&|("'])(rmdir|rd|del|erase)\s[^|;&\n]*\/s\b/i, raison: 'suppression récursive (/s)' },
  { motif: /\brimraf\b/i, raison: 'suppression récursive (rimraf)' },
]

const SECRET_CONFIG = /(^|[^\w.-])config\.php\b/i
const STOCKAGE_CHEMIN = /(^|[\s"'=:/\\])stockage[\\/]/i
const STOCKAGE_LECTURE = /\b(ls|dir|cat|type|gc|Get-Content|Get-ChildItem|gci|sqlite3|cp|mv|copy|move|Copy-Item|Move-Item|tar|zip|Compress-Archive|find|du|head|tail|less|more|xxd|od|strings)\b[^|;&\n]*(^|[\s"'=:/\\])stockage(\s|$|["'])/i
const BASE_SQLITE = /\bbase\.sqlite\b/i

function normaliser(texte) {
  return String(texte).replace(/\r\n/g, '\n')
}

/** Raison du refus d'une commande shell, ou null si elle est permise. */
export function commandeInterdite(commande) {
  if (typeof commande !== 'string' || !commande) return null
  const texte = normaliser(commande)
  for (const { motif, test, raison } of COMMANDES_INTERDITES) {
    if (motif.test(texte) && (!test || test(texte))) return raison
  }
  if (SECRET_CONFIG.test(texte)) return 'config.php contient les secrets : ni lecture ni écriture (seul scripts/config-dev.php le génère)'
  if (STOCKAGE_CHEMIN.test(texte) || STOCKAGE_LECTURE.test(texte) || BASE_SQLITE.test(texte)) {
    return 'stockage/ contient la base et les vidéos des cousins : passer par les scripts (migrer.php, nouveau-lien.php…)'
  }
  return null
}

/** Raison du refus d'un chemin de fichier, ou null s'il est permis. */
export function cheminInterdit(chemin) {
  if (typeof chemin !== 'string' || !chemin) return null
  const c = chemin.replace(/\\/g, '/')
  if (/(^|\/)config\.php$/i.test(c)) return 'config.php contient les secrets : ni lecture ni écriture'
  if (/(^|\/)stockage(\/|$)/i.test(c)) return 'stockage/ contient la base et les vidéos des cousins : hors d’atteinte de l’agent'
  if (/\.sqlite(-wal|-shm)?$/i.test(c)) return 'fichier de base SQLite : hors d’atteinte de l’agent'
  return null
}

/** Raison du refus d'un appel d'outil, ou null. */
export function verifier(entree) {
  const outil = entree?.tool_name ?? ''
  const params = entree?.tool_input ?? {}
  if (outil === 'Bash' || outil === 'PowerShell') return commandeInterdite(params.command)
  for (const cle of ['file_path', 'path', 'notebook_path']) {
    const raison = cheminInterdit(params[cle])
    if (raison) return raison
  }
  if (outil === 'Glob' && typeof params.pattern === 'string') {
    const raison = cheminInterdit(params.pattern.replace(/\*+/g, 'x'))
    if (raison) return raison
  }
  return null
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
  let entree = {}
  try {
    entree = JSON.parse(readFileSync(0, 'utf8') || '{}')
  } catch {
    process.exit(0)
  }
  const raison = verifier(entree)
  if (raison) {
    process.stderr.write(`Refusé par .claude/hooks/garde-fous.mjs : ${raison}\n`)
    process.exit(2)
  }
  process.exit(0)
}
