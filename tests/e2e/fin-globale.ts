import { execFileSync } from 'node:child_process'

// Après les tests de bout en bout (même en cas d'échec) : base de test, vidéos de test et témoin effacés ; le site
// sert de nouveau la base de Patrice.
export default function finGlobale(): void {
  execFileSync('docker', ['compose', 'exec', '-T', 'web', 'php', 'scripts/e2e-outil.php', 'fin'], { stdio: ['ignore', 'ignore', 'inherit'] })
}
