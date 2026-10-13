---
name: phase
description: Démarre ou reprend une phase du plan de construction du site « Chorés du mariage » à partir de docs/prompts/phase-NN.md et de docs/PROGRESS.md. Invocation manuelle seulement, par /phase ou /phase N.
argument-hint: "[numéro de phase, facultatif]"
disable-model-invocation: true
---

# /phase — lancer ou reprendre une phase

Tu exécutes une seule phase du plan, puis tu t'arrêtes. Tu n'enchaînes jamais deux phases.

## 1. Lire l'état

1. Lis `CLAUDE.md` (ou, s'il n'existe pas encore, `docs/prompts/phase-00.md`), puis `docs/PROGRESS.md` en entier, puis `docs/spec/01-spec.md`.
2. Détermine la phase visée :
   - Si l'argument `$ARGUMENTS` contient un numéro, c'est cette phase.
   - Sinon : si PROGRESS montre une phase commencée mais non déclarée terminée, c'est elle (reprise). Sinon, c'est la phase qui suit la dernière phase terminée ; la première phase exécutable est la 2 (la 1, la maquette, est faite ; la 0 est le contrat).
3. Une phase est « terminée » seulement si PROGRESS l'écrit explicitement, avec `composer check` et `pnpm test:e2e` verts et les critères d'acceptation cochés. En cas de doute, considère-la non terminée.

## 2. Garde-fous avant de commencer

- Si la phase précédente n'est pas terminée et que l'argument demande la suivante, refuse : dis ce qui manque dans PROGRESS et arrête-toi.
- Si le fichier `docs/prompts/phase-NN.md` (NN sur deux chiffres) n'existe pas, dis-le et arrête-toi.
- Vérifie que Docker répond (`docker info`) ; sinon dis-le et arrête-toi.
- Résume en cinq lignes au plus : phase visée, nouvelle ou reprise, ce que PROGRESS dit déjà fait, ce qui reste. Attends que l'utilisateur réponde « go ». Ne commence rien avant.

## 3. Exécuter

- Pose le tag `git tag -f avant-phase-N` (pas en reprise si le tag existe déjà).
- Lis `docs/prompts/phase-NN.md` et suis-le comme si l'utilisateur venait de le coller. Le contrat de mission prime sur toute improvisation ; `docs/spec/01-spec.md` pour les fonctions et les données ; `docs/maquette/` pour le visuel ; `docs/distribution.md` et `docs/whatsapp/etat.json` pour les faits.
- En reprise, ne refais pas ce que PROGRESS déclare fait et vérifié ; repars du premier point non fait ou non vérifié.
- Phases 5 et 6 : passe en mode plan avant tout fichier qui touche au téléversement, au service des médias, au service worker, aux en-têtes de sécurité ou au paquet de déploiement, et obtiens une validation explicite.
- Les règles de `CLAUDE.md` priment sur tout le reste (jamais de `config.php`, de push, de `docker prune`, d'image générée, de service tiers, de prénom, chanson, date, tempo, section ou placement inventés).

## 4. Terminer

- `composer check` et les tests de la phase (`node --test tests/js`, `pnpm test:e2e`) doivent être verts. Si quelque chose échoue, dis-le avec la sortie ; ne déclare pas la phase terminée.
- Captures des écrans modifiés en 390 et 1 280 px dans `docs/captures/phase-NN/`.
- Mets à jour `docs/PROGRESS.md` : phase, fait, non fait, non vérifié, décisions, questions ouvertes, et la ligne « Phase N terminée le AAAA-MM-JJ » seulement si tout est vert.
- Complète `docs/A-FAIRE-PAR-PATRICE.md`, propose le commit indiqué par le prompt, puis arrête-toi. Dernière ligne : « Pour la suite : nouvelle session, puis /phase. »
