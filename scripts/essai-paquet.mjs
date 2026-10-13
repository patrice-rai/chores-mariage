// Essai du paquet de mise en ligne (phase 6) : `pnpm essai:paquet`.
// 1. construit dist/chores-mariage-vX.Y.Z.zip (scripts/paquet.php, dans le conteneur du site) ;
// 2. lance un conteneur jetable `chores-mariage-paquet` (image locale php:8.3-apache du projet) sur
//    https://127.0.0.1:8096, y décompresse le zip dans une arborescence qui imite Hostinger
//    (public_html/chores/ + chores-app/ hors racine web, chemin-app.php, BASE_PATH=/chores, ENV=production) ;
// 3. joue tests/paquet/fumee.spec.ts (installation sans SSH, scripts comme en SSH, parcours, axe, sécurité,
//    sauvegarde puis restauration sur une base vide) ;
// 4. supprime le conteneur (docker rm -f ciblé), même en cas d'échec.
// Le lien et la clé de l'essai ne sont jamais affichés : ils passent du conteneur au test par la mémoire.
import { execFileSync, spawnSync } from 'node:child_process'
import { readFileSync } from 'node:fs'

const CONTENEUR = 'chores-mariage-paquet'
const IMAGE = 'chores-mariage-web:dev'

const docker = (args, options = {}) => execFileSync('docker', args, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'inherit'], ...options })

function supprimerConteneur() {
  spawnSync('docker', ['rm', '-f', CONTENEUR], { stdio: 'ignore' })
}

const version = /VERSION = '([0-9.]+)'/.exec(readFileSync('app/version.php', 'utf8'))?.[1]
if (!version) throw new Error('Version introuvable dans app/version.php.')

// La vidéo « Démo » (synthétique, jamais versionnée) sert de vidéo de référence pendant l'essai.
execFileSync('node', ['scripts/video-demo.mjs', '--si-absente'], { stdio: 'inherit' })
console.log('Paquet :')
process.stdout.write(docker(['compose', 'exec', '-T', 'web', 'php', 'scripts/paquet.php']))

supprimerConteneur()
let statut = 1
try {
  docker(['run', '-d', '--name', CONTENEUR, '-p', '127.0.0.1:8096:443', '--entrypoint', 'sleep', IMAGE, 'infinity'])
  docker(['exec', CONTENEUR, 'mkdir', '-p', '/tmp/essai'])
  for (const [source, cible] of [
    [`dist/chores-mariage-v${version}.zip`, 'paquet.zip'],
    ['scripts/config-dev.php', 'config-dev.php'],
    ['tests/paquet/outil-essai.php', 'outil-essai.php'],
    ['tests/paquet/vhost-essai.conf', 'vhost-essai.conf'],
    ['tests/paquet/installer-essai.sh', 'installer-essai.sh'],
  ]) {
    docker(['cp', source, `${CONTENEUR}:/tmp/essai/${cible}`])
  }
  process.stdout.write(docker(['exec', CONTENEUR, 'sh', '/tmp/essai/installer-essai.sh']))
  docker(['exec', '-d', CONTENEUR, 'apache2-foreground'])
  // Apache prêt ? Sans réponse en 30 secondes, l'essai s'arrête (rien ne sert de lancer les tests).
  let pret = false
  for (let essai = 0; essai < 60 && !pret; essai++) {
    const sonde = spawnSync('docker', ['exec', CONTENEUR, 'php', '-r',
      'exit(@file_get_contents("https://127.0.0.1/chores/robots.txt", false, stream_context_create(["ssl" => ["verify_peer" => false, "verify_peer_name" => false]])) === false ? 1 : 0);'])
    pret = sonde.status === 0
    if (!pret) await new Promise((ok) => setTimeout(ok, 500))
  }
  if (!pret) throw new Error('Apache ne répond pas dans le conteneur d’essai.')
  const resultat = spawnSync('pnpm', ['exec', 'playwright', 'test', '--config', 'playwright.paquet.config.ts'], {
    stdio: 'inherit',
    shell: process.platform === 'win32',
    env: { ...process.env, ESSAI_CONTENEUR: CONTENEUR },
  })
  statut = resultat.status ?? 1
} finally {
  supprimerConteneur()
}
console.log(statut === 0 ? 'Essai du paquet réussi ; conteneur supprimé.' : 'Essai du paquet en échec ; conteneur supprimé.')
process.exit(statut)
