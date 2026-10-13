# Chorés du mariage — kit de démarrage

Préparé le 12 octobre 2026. Un site privé, sans compte, pour que neuf cousins répètent cinq chorégraphies de mariage : lecteur vidéo au ralenti et en miroir avec décompte, sections de 8 temps, placements sur scène, suivi « je maîtrise », répétitions avec présence, vidéos privées des cousins, page « Jour J ».

## Ce qu'il y a dans `docs/`

- `distribution.md` : qui danse quoi (distribution connue au 12 octobre 2026) et ce qui est en attente.
- `recherches/outils-organisation-2026-10-12.md` : les outils existants comparés, la combinaison gratuite, ce qu'un site sur mesure apporte, les pièges (HEVC, droits musicaux, vie privée).
- `recherches/chansons-et-tutoriels-2026-10-12.md` : fiche de chaque chanson, liens officiels, tutoriels existants.
- `whatsapp/etat.json` : la distribution (danseurs, chansons, liens publics, répétition décidée), importée par le site. Les échanges du groupe WhatsApp ne sont pas versionnés (dépôt public).
- `prompts/prompt-claude-design.md` : le prompt qui a servi à faire la maquette ; `prompts/prompt-analyse-whatsapp.md` : le prompt d'analyse du groupe.
- `prompts/00-demarrage.md` : le prompt à coller dans Claude Code, ouvert dans ce dossier, pour importer la maquette et lancer la construction.
- `maquette/` : la maquette Claude Design exportée en fichiers (`Planches.dc.html`, `support.js`).

## Comment démarrer

1. Ouvrir Claude Code dans `P:\PROJECT\chores-mariage`.
2. Taper `/design-login` et se connecter (une fois).
3. Coller `docs/prompts/00-demarrage.md`.
