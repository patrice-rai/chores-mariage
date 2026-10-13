# Prompt de démarrage — à coller dans Claude Code ouvert dans `P:\PROJECT\chores-mariage`

Prérequis : `/design-login` fait dans cette session (le MCP `claude_design` est déjà enregistré).

## Prompt

Use the claude_design MCP (https://api.anthropic.com/v1/design/mcp, auth via /design-login) to import this project:
[lien du projet Claude Design, privé]

Focus on these files (the whole project is readable):
- `Planches.dc.html`

Also read these files the selection imports:
- `support.js`

Contexte : tu es dans le projet « Chorés du mariage ». Lis d'abord `CLAUDE.md`, `docs/kit/00-LIS-MOI.md`, `docs/distribution.md`, `docs/whatsapp/etat.json` et `docs/prompts/prompt-claude-design.md` (le brief qui a produit la maquette). Je suis Patrice, le propriétaire ; je suis présent, pose-moi tes questions, mais une à la fois et seulement quand tu es bloqué.

Travail demandé, dans l'ordre, avec un arrêt pour validation entre chaque étape :

1. **Lire la maquette et me la raconter.** Importe le projet Claude Design, enregistre une copie de `Planches.dc.html` et `support.js` dans `docs/maquette/`, puis résume en dix lignes au plus : les écrans qu'elle contient, les jetons (couleurs, polices, tailles, rayons) tels qu'ils y sont écrits, les composants, les états dessinés, et la note de décisions. Signale tout écart entre la maquette et le brief, et tout ce qui manque pour coder (écran absent, état non dessiné, texte en anglais, valeur floue). N'invente rien à la place de la maquette.

2. **Écrire la spécification** `docs/spec/01-spec.md` à partir de la maquette et des faits (`distribution.md`, `etat.json`) : liste des écrans et de leurs routes ; modèle de données (cousins, chansons, sections avec début/fin/comptes, placements par section, états « pas encore / presque / je maîtrise » par cousin et section, répétitions avec présence, vidéos des cousins avec commentaires horodatés, réglages : lien secret, ordre de passage) ; le lecteur (vitesses 0,5/0,75/1, miroir, boucle A-B par section, décompte 5-6-7-8 au tempo) ; l'accès par lien secret + prénom (cookie), sans compte ; le téléversement des vidéos des cousins depuis le téléphone (taille max, formats, conversion côté PC de Patrice pour les vidéos de référence) ; la PWA (cache de l'interface et, sur demande, d'une vidéo) ; l'hébergement sur le Hostinger mutualisé de Patrice (PHP 8.3 + SQLite, sous-dossier ou sous-domaine, `.htaccess`, `noindex`) ; l'accessibilité (WCAG 2.2 AA) ; la vie privée (rien ne sort du serveur, vidéos ignorées par git). Propose la pile par défaut de `CLAUDE.md` et dis-moi si tu en préfères une autre, avec une raison.

3. **Proposer le plan en phases** dans `docs/prompts/` (comme pour mes autres projets : un prompt par phase, chaque phase se termine par quelque chose de visible, vérifié, commité en local) : 2 socle et accès ; 3 jetons, composants et lecteur ; 4 données, chansons, sections, placements et suivi ; 5 répétitions, vidéos des cousins, Jour J, PWA ; 6 recette (accessibilité, performance, test sur iPhone et Android), paquet et procédure de mise en ligne sur Hostinger. Ajoute `docs/kit/autopilot/` sur le modèle de `P:\PROJECT\hospitalite\docs\kit\autopilot\` (lecture seule), adapté à ce projet, et l'entrée pour `P:\PROJECT\orchestrateur\projets.json` (ne la pose pas toi-même : donne-la-moi).

4. **Attends mon accord avant de coder la phase 2.** Je décide aussi si le pilote automatique tourne (sur Opus) ou si on avance phase par phase ici.

Règles : jamais de `git push`, jamais de déploiement réel, jamais de compte créé, jamais de donnée ou de vidéo envoyée à un service tiers ; aucune image générée par IA ; aucun prénom, chanson ou date inventé ; français, tutoiement dans le site, vouvoiement avec moi si tu veux, peu importe. Fais `git init -b main` et un premier commit des docs si le dépôt n'existe pas.
