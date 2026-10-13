// Hook Stop : lance `composer check` dans le conteneur avant de rendre la main.
// Échec → code 2 et dernières lignes sur stderr : Claude reprend la main pour corriger ou dire ce qui échoue.
// Une seule relance par arrêt (`stop_hook_active`), pour ne jamais tourner en rond.
import { readFileSync } from 'node:fs'
import { spawnSync } from 'node:child_process'

let entree = {}
try {
  entree = JSON.parse(readFileSync(0, 'utf8') || '{}')
} catch {
  entree = {}
}
if (entree.stop_hook_active) process.exit(0)

const racine = process.env.CLAUDE_PROJECT_DIR || process.cwd()
const options = { cwd: racine, encoding: 'utf8', timeout: 540_000, shell: false }

const ps = spawnSync('docker', ['compose', 'ps', '--status', 'running', '-q', 'web'], options)
if (ps.status !== 0 || !String(ps.stdout).trim()) {
  process.stderr.write('composer check non lancé : le conteneur web ne tourne pas (docker compose up -d --build).\n')
  process.exit(2)
}

const check = spawnSync('docker', ['compose', 'exec', '-T', 'web', 'composer', 'check'], options)
if (check.status !== 0) {
  const sortie = `${check.stdout ?? ''}\n${check.stderr ?? ''}`.trim().split('\n').slice(-60).join('\n')
  process.stderr.write(`composer check a échoué :\n${sortie}\n`)
  process.exit(2)
}
process.exit(0)
