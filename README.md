# Chorés du mariage

Version 1.0.0 (`app/version.php`).

Site où neuf cousins répètent cinq chorégraphies pour un mariage : on touche son prénom, et c'est parti. Deux modes dans `config.php` : accès libre (`ACCES_LIBRE => true`, le site s'ouvre à tous) ou un seul lien secret à partager. PHP 8.3 sans framework + SQLite, HTML/CSS/JS natifs, aucune ressource externe.

Dépôt public : le code, la distribution (prénoms des danseurs, chansons, liens publics) et les documents de travail y sont ; les échanges du groupe WhatsApp, les vidéos et la configuration réelle n'y sont pas.

## Mettre en ligne (Hostinger)

Tout est dans `docs/deploy/` :

- `README.md` : pas à pas hPanel (PHP 8.3, paquet, `chemin-app.php`, `config.php`, droits, installation en SSH ou par `/installer`) ;
- `verification.md` : la liste à cocher après la mise en ligne (en-têtes avec `curl.exe`, fichiers protégés, accès) ;
- `sauvegardes.md`, `mise-a-jour.md`, `lien.md`.

Le paquet se construit sur le PC : `docker compose exec -T web php scripts/paquet.php` → `dist/chores-mariage-v1.0.0.zip`.

## Démarrer (développement local)

```
docker compose up -d --build
docker compose exec -T web php scripts/nouveau-lien.php
```

Ouvre le lien affiché (http://127.0.0.1:8095/e/…), puis touche ton prénom.

## Tester

```
pnpm install
docker compose exec -T web composer check   # lint, styles et scripts inline, contrastes, PHPUnit
pnpm test:js                                 # fonctions du lecteur, garde-fous Claude Code
pnpm test:e2e                                # Playwright + axe, 390 et 1 280 px (dont la recette de la phase 6)
pnpm check                                   # les trois
pnpm captures phase-06                       # captures dans docs/captures/phase-06/
pnpm lighthouse                              # Lighthouse mobile, 3 passes, médiane → docs/recette/lighthouse.json
pnpm essai:paquet                            # le zip installé dans un conteneur qui imite Hostinger (port 8096)
```

## Où sont les docs

- `CLAUDE.md` : règles du projet.
- `docs/spec/01-spec.md` : spécification ; `docs/spec/00-lecture-maquette.md` : lecture de la maquette (`docs/maquette/`).
- `docs/ADMIN.md` : mode d'emploi de l'administration ; `docs/VIDEOS.md` : réglages, conversion et remplacement des vidéos.
- `docs/RECETTE.md` : résultats de la recette (accessibilité, performance, sécurité, contenu, paquet, sauvegardes).
- `docs/TOOLING.md` : outillage (Docker, base, lien, tests).
- `docs/PROGRESS.md` : avancement ; `docs/A-FAIRE-PAR-PATRICE.md` : ce qui attend Patrice, dans l'ordre.
