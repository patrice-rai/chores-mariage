import { execFileSync } from 'node:child_process'

// Avant les tests de bout en bout :
// - la vidéo « Démo » (synthétique, jamais versionnée) doit exister dans le conteneur ; scripts/video-demo.mjs la
//   fabrique (20 s d'enregistrement) seulement si elle manque ;
// - le site passe sur la base de test (scripts/e2e-outil.php debut) : les données de Patrice ne sont jamais touchées.
// Le démontage global (fin-globale.ts) efface la base de test, ses vidéos et le témoin.
export default function preparationGlobale(): void {
  execFileSync('node', ['scripts/video-demo.mjs', '--si-absente'], { stdio: 'inherit' })
  execFileSync('docker', ['compose', 'exec', '-T', 'web', 'php', 'scripts/e2e-outil.php', 'debut'], { stdio: ['ignore', 'ignore', 'inherit'] })
}
