// Lance les captures d'écran de la phase (tests/e2e/captures.spec.ts) en 390 et 1 280 px.
// Usage : pnpm captures            → docs/captures/phase-06/
//         pnpm captures phase-02   → docs/captures/phase-02/
import { spawnSync } from 'node:child_process'

const phase = process.argv[2] ?? 'phase-06'
if (!/^phase-\d{2}$/.test(phase)) {
  console.error('Usage : pnpm captures [phase-NN]')
  process.exit(1)
}
const resultat = spawnSync('pnpm', ['exec', 'playwright', 'test', 'tests/e2e/captures.spec.ts'], {
  stdio: 'inherit',
  shell: process.platform === 'win32',
  env: { ...process.env, CAPTURES: '1', CAPTURES_PHASE: phase },
})
process.exit(resultat.status ?? 1)
