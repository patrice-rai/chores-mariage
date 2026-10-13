// Pilote automatique de « Chorés du mariage » : enchaîne les phases 2 à 6 avec Claude Code en mode non interactif.
// Lancement : node docs/kit/autopilot/autopilot.mjs   (idempotent : saute les phases déjà terminées)
// Arrêt : fermer la fenêtre, ou créer le fichier docs/autopilot/STOP
// Option : APRES=<chemin d'un STATUS.md d'un autre pilote> pour attendre qu'il ait fini avant de commencer.
// Adapté le 13 octobre 2026 du pilote de P:\PROJECT\hospitalite\docs\kit\autopilot\autopilot.mjs.
import { spawn, execSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..", "..", "..");
// Le chemin change à chaque mise à jour de l'extension VS Code : on prend la version installée la plus récente.
function trouverClaude() {
  if (process.env.CLAUDE_BIN) return process.env.CLAUDE_BIN;
  const ext = "C:/Users/prai/.vscode/extensions";
  const versions = fs.readdirSync(ext)
    .filter((d) => d.startsWith("anthropic.claude-code-") && fs.existsSync(path.join(ext, d, "resources", "native-binary", "claude.exe")))
    .sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));
  if (!versions.length) throw new Error("claude.exe introuvable dans " + ext);
  return path.join(ext, versions[versions.length - 1], "resources", "native-binary", "claude.exe");
}
const CLAUDE = trouverClaude();
const OUT = path.join(ROOT, "docs", "autopilot");
const LOGS = path.join(OUT, "logs");
const PROGRESS = path.join(ROOT, "docs", "PROGRESS.md");
const FIRST_PHASE = 2;
const LAST_PHASE = 6;
const MAX_ATTEMPTS = 4;
const PORT = 8095;
// Modèle imposé par l'orchestrateur : node autopilot.mjs --model claude-opus-5-5 (sinon celui configuré dans Claude Code)
const MODELE = (() => {
  const i = process.argv.indexOf("--model");
  return i > -1 ? process.argv[i + 1] : process.env.PILOTE_MODELE || "";
})();
fs.mkdirSync(LOGS, { recursive: true });

// Fichiers lus par l'orchestrateur (P:\PROJECT\orchestrateur) : PID du pilote et état courant.
const PIDFILE = path.join(OUT, "PID");
const ETATFILE = path.join(OUT, "ETAT.json");
fs.writeFileSync(PIDFILE, String(process.pid));
const etat = (obj) => fs.writeFileSync(ETATFILE, JSON.stringify({ ...obj, pid: process.pid, maj: new Date().toISOString() }, null, 2));
process.on("exit", () => {
  try {
    fs.unlinkSync(PIDFILE);
  } catch {}
});

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const now = () => new Date().toLocaleString("fr-FR", { timeZone: "Europe/Paris" });
const status = (line) => {
  const f = path.join(OUT, "STATUS.md");
  const head = "# Pilote automatique — journal\n\nUne ligne par événement, la plus récente en bas.\n\n";
  if (!fs.existsSync(f)) fs.writeFileSync(f, head);
  fs.appendFileSync(f, `- ${now()} — ${line}\n`);
  console.log(`[${now()}] ${line}`);
};
const stopRequested = () => fs.existsSync(path.join(OUT, "STOP"));
const progress = () => (fs.existsSync(PROGRESS) ? fs.readFileSync(PROGRESS, "utf8") : "");
// La ligne exacte, en début de ligne, avec une vraie date : une mention du gabarit « Phase N terminée le AAAA-MM-JJ » ne compte pas.
const isDone = (n) => new RegExp(`^Phase\\s*${n}\\s*termin[ée]e le \\d{4}-\\d{2}-\\d{2}`, "im").test(progress());

const CHARTER = fs.readFileSync(path.join(ROOT, "docs", "kit", "autopilot", "charte-autonomie.md"), "utf8");

function phasePrompt(n, attempt) {
  const nn = String(n).padStart(2, "0");
  const common = `Exécute la phase ${n} du plan du site « Chorés du mariage » en mode autonome (tentative ${attempt}/${MAX_ATTEMPTS}).
1. Lis docs/prompts/phase-00.md (contrat de mission) et CLAUDE.md, puis docs/PROGRESS.md, docs/prompts/phase-${nn}.md, docs/spec/01-spec.md (la spécification, qui prime pour les fonctions et les données) et docs/A-FAIRE-PAR-PATRICE.md s'il existe. La maquette est dans docs/maquette/ (lue dans docs/spec/00-lecture-maquette.md) ; les faits dans docs/distribution.md et docs/whatsapp/etat.json.
2. Si PROGRESS montre la phase ${n} déjà commencée, reprends au premier point non fait ou non vérifié ; ne refais pas le reste.
3. Avant de modifier quoi que ce soit : git tag -f avant-phase-${n} (pas en reprise si le tag existe déjà).
4. Exécute docs/prompts/phase-${nn}.md en entier, en appliquant la charte d'autonomie (prompt système), qui prime sur le prompt de phase.
5. Tu tournes en mode non interactif : personne ne te rappellera quand une commande en arrière-plan se termine. Ne termine jamais ton tour pour « reprendre à la fin » d'un build, d'un lint ou de tests : lance-les au premier plan avec un délai suffisant (jusqu'à 10 minutes), ou attends-les dans une boucle, puis continue dans le même tour. Un tour qui se termine sans la ligne « Phase ${n} terminée le AAAA-MM-JJ » compte comme une tentative perdue.
6. Le site tourne dans Docker (php:8.3-apache) sur http://127.0.0.1:${PORT} ; ports interdits : 3000, 3100, 3102, 5432, 5433, 7001, 8090, 3307. N'invente ni prénom, ni chanson, ni date, ni tempo, ni section, ni placement : crochets et docs/A-FAIRE-PAR-PATRICE.md.`;
  if (n === 2)
    return `${common}
Ce dossier ne contient que docs/, CLAUDE.md et .gitignore : tout le reste est à créer (PHP 8.3 sans framework + SQLite + HTML/CSS/JS statiques, voir docs/spec/01-spec.md § 1). Le dépôt git existe déjà sur la branche main : ne le réinitialise pas. Réécris CLAUDE.md depuis docs/prompts/phase-00.md (100 lignes au plus), puis le reste de la phase. Vérifie que Docker répond (docker info) avant de lancer le conteneur ; s'il ne répond pas, écris-le dans PROGRESS et arrête-toi sans déclarer la phase terminée.`;
  if (n === 6)
    return `${common}
Restriction propre à cette tentative : recette et paquet en local uniquement. Aucun accès à Hostinger, à un serveur, à un DNS ni à un compte ; aucun déploiement réel ; aucun téléphone réel (les tests iPhone et Android sont listés pour Patrice). Tout ce qui demande l'hébergeur ou un humain va dans docs/A-FAIRE-PAR-PATRICE.md, dans l'ordre d'exécution, avec les commandes exactes. Ligne de fin : « Phase 6 terminée le AAAA-MM-JJ (recette locale et paquet) ».`;
  return common;
}

function runClaude(n, attempt) {
  return new Promise((resolve) => {
    const log = path.join(LOGS, `phase-${String(n).padStart(2, "0")}-tentative-${attempt}.log`);
    const out = fs.createWriteStream(log);
    const args = ["-p", phasePrompt(n, attempt), "--dangerously-skip-permissions", "--append-system-prompt", CHARTER, "--output-format", "text", ...(MODELE ? ["--model", MODELE] : [])];
    const child = spawn(CLAUDE, args, { cwd: ROOT, windowsHide: true });
    etat({ etat: "phase", phase: n, tentative: attempt, depuis: new Date().toISOString(), claudePid: child.pid, journal: path.relative(ROOT, log) });
    let tail = "";
    const grab = (d) => {
      out.write(d);
      tail = (tail + d.toString()).slice(-4000);
    };
    child.stdout.on("data", grab);
    child.stderr.on("data", grab);
    child.on("close", (code) => {
      out.end();
      resolve({ code, tail, log });
    });
  });
}

async function waitForDocker() {
  try {
    execSync("docker info", { stdio: "ignore" });
    return;
  } catch {}
  status(`Docker Desktop ne répond pas : le conteneur php:8.3-apache (port ${PORT}) en dépend. J'attends qu'il soit lancé.`);
  while (!stopRequested()) {
    await sleep(5 * 60000);
    try {
      execSync("docker info", { stdio: "ignore" });
      return status("Docker répond, je continue");
    } catch {}
  }
}

// Si APRES pointe vers le STATUS.md d'un autre pilote, on attend qu'il ait écrit TERMINÉ ou ARRÊT, pour ne pas
// faire tourner deux agents en même temps sur le même abonnement.
async function waitForOtherPilot() {
  const other = process.env.APRES;
  if (!other || !fs.existsSync(other)) return;
  status(`attente de la fin de l'autre pilote (${other})`);
  while (!stopRequested()) {
    const txt = fs.readFileSync(other, "utf8");
    if (/TERMINÉ|ARRÊT/.test(txt)) return status("l'autre pilote a fini, je commence");
    await sleep(5 * 60000);
  }
}

async function main() {
  status(`démarrage (projet : ${ROOT}, phases ${FIRST_PHASE} à ${LAST_PHASE})`);
  etat({ etat: "demarrage" });
  await waitForOtherPilot();
  await waitForDocker();
  for (let n = FIRST_PHASE; n <= LAST_PHASE; n++) {
    if (stopRequested()) return status("arrêt demandé (fichier STOP)");
    if (isDone(n)) {
      status(`phase ${n} déjà terminée, je passe`);
      continue;
    }
    let done = false;
    let attempt = 1;
    while (attempt <= MAX_ATTEMPTS && !done) {
      if (stopRequested()) return status("arrêt demandé (fichier STOP)");
      status(`phase ${n} — tentative ${attempt} lancée${MODELE ? ` (modèle ${MODELE})` : ""}`);
      const { code, tail, log } = await runClaude(n, attempt);
      done = isDone(n);
      if (done) {
        status(`phase ${n} terminée (code ${code}, journal ${path.relative(ROOT, log)})`);
        break;
      }
      // Une limite d'usage n'est pas un échec : on attend que le quota revienne sans consommer de tentative.
      const limited = /usage limit|rate limit|429|limit reached|quota|hit your [\w .-]*limit|session limit|weekly limit|limit will reset|limit · resets|resets \d|out of extra usage|reached your [\w .-]*limit|switch to another model|usage credits/i.test(tail);
      if (limited) {
        status(`phase ${n} : limite d'usage atteinte pendant la tentative ${attempt} ; j'attends 60 min sans compter de tentative`);
        etat({ etat: "attente", phase: n, tentative: attempt, limite: true, reprise: new Date(Date.now() + 60 * 60000).toISOString() });
        await sleep(60 * 60000);
        continue;
      }
      status(`phase ${n} non terminée après la tentative ${attempt} (code ${code}) ; reprise dans 15 min`);
      etat({ etat: "attente", phase: n, tentative: attempt, limite: false, reprise: new Date(Date.now() + 15 * 60000).toISOString() });
      await sleep(15 * 60000);
      attempt++;
    }
    if (!done) {
      status(`ARRÊT : phase ${n} non terminée après ${MAX_ATTEMPTS} tentatives. Voir docs/PROGRESS.md et les journaux de docs/autopilot/logs.`);
      etat({ etat: "arret", phase: n });
      return;
    }
  }
  status(`TERMINÉ : phases ${FIRST_PHASE} à ${LAST_PHASE} faites en local. Ce qui reste à faire par un humain : docs/A-FAIRE-PAR-PATRICE.md`);
  etat({ etat: "termine" });
}

main().catch((e) => status(`erreur du pilote : ${e.stack || e}`));
