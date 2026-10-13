// Preuve que le hook PreToolUse (.claude/hooks/garde-fous.mjs) refuse ce que CLAUDE.md interdit,
// et laisse passer le travail ordinaire.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { spawnSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import { commandeInterdite, cheminInterdit, verifier } from '../../.claude/hooks/garde-fous.mjs'

const crochet = fileURLToPath(new URL('../../.claude/hooks/garde-fous.mjs', import.meta.url))

test('commandes interdites refusées', () => {
  for (const commande of [
    'git push',
    'git push origin main',
    'git -C . push',
    'git remote add origin https://exemple.test/depot.git',
    'gh pr create --fill',
    'gh repo create chores',
    'git commit --no-verify -m x',
    'git commit -nm x',
    'rm -rf vendor',
    'rm -r tests',
    'Remove-Item -Recurse -Force node_modules',
    'docker system prune -af',
    'docker container prune',
    'docker volume prune',
    'docker compose down -v',
    'docker volume rm chores-mariage_stockage',
    'docker stop hospitalite-postgres-1',
    'docker rm bagnolet-db',
    'cat config.php',
    'Get-Content .\\config.php',
    'docker compose exec -T web cat stockage/base.sqlite',
    'ls stockage',
    'sqlite3 base.sqlite',
  ]) {
    assert.ok(commandeInterdite(commande), `devrait être refusée : ${commande}`)
  }
})

test('travail ordinaire permis', () => {
  for (const commande of [
    'git status',
    'git commit -m "chore: socle"',
    'git add -A',
    'docker compose up -d --build',
    'docker compose exec -T web composer check',
    'docker compose exec -T web php scripts/migrer.php',
    'docker compose exec -T web php scripts/config-dev.php',
    'docker compose exec -T web php scripts/nouveau-lien.php',
    'pnpm test:e2e',
    'node --test tests/js',
    'cat config.example.php',
    'docker rm chores-mariage-web',
  ]) {
    assert.equal(commandeInterdite(commande), null, `devrait passer : ${commande}`)
  }
})

test('chemins interdits refusés', () => {
  assert.ok(cheminInterdit('P:\\PROJECT\\chores-mariage\\config.php'))
  assert.ok(cheminInterdit('/p/PROJECT/chores-mariage/stockage/base.sqlite'))
  assert.ok(cheminInterdit('stockage/medias/1-abc.mp4'))
  assert.equal(cheminInterdit('P:\\PROJECT\\chores-mariage\\config.example.php'), null)
  assert.equal(cheminInterdit('P:\\PROJECT\\chores-mariage\\scripts\\config-dev.php'), null)
  assert.equal(cheminInterdit('app/bd.php'), null)
  assert.ok(verifier({ tool_name: 'Read', tool_input: { file_path: 'config.php' } }))
  assert.ok(verifier({ tool_name: 'Glob', tool_input: { pattern: 'stockage/**' } }))
  assert.equal(verifier({ tool_name: 'Write', tool_input: { file_path: 'app/vues/moi.php' } }), null)
})

test('le hook répond par le code 2 et une raison', () => {
  const refus = spawnSync(process.execPath, [crochet], {
    input: JSON.stringify({ tool_name: 'Bash', tool_input: { command: 'git push origin main' } }),
    encoding: 'utf8',
  })
  assert.equal(refus.status, 2)
  assert.match(refus.stderr, /git push/)

  const permis = spawnSync(process.execPath, [crochet], {
    input: JSON.stringify({ tool_name: 'Bash', tool_input: { command: 'git status' } }),
    encoding: 'utf8',
  })
  assert.equal(permis.status, 0)
})
