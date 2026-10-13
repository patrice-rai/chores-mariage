# Le visuel dans le code

Écrit en phase 3 (13 octobre 2026). La maquette (`docs/maquette/`, lue dans `docs/spec/00-lecture-maquette.md`) prime pour le visuel ; la spec § 9 et § 12 fixent les corrections. Tout est visible sur `/gabarits` (libre en développement, réservé au cookie d'administration de Patrice sinon).

## Où vivent les choses

| Quoi | Où |
| --- | --- |
| Couleurs des deux thèmes, polices, tailles, cibles, rayons, espacements | `public/css/jetons.css` (seule feuille autorisée à contenir une couleur) |
| Remise à zéro, page, typographie, boutons, tableaux, focus, mouvement réduit | `public/css/base.css` |
| Un bloc par composant (BEM en français : `.lecteur`, `.lecteur__commande`, `.lecteur--miroir`) | `public/css/composants.css` |
| Styles propres à `/gabarits` | `public/css/gabarits.css` |
| Gabarits PHP des composants | `app/vues/composants/*.php`, appelés par `Chores\composant($c, 'nom', [...])` |
| Lecteur, décompte, bascule de thème | `public/js/lecteur.js`, `lecteur-calc.js` (fonctions pures testées par Node), `decompte.js`, `site.js` |
| Paires de couleurs mesurées | `app/jetons.php` (`paires_de_couleurs()`), contrôlées par `scripts/contraste.php` |
| Polices | `public/polices/` (copiées par `node scripts/polices.mjs`, licences dans `LICENCES.md`) |

## Changer un jeton

1. Modifier la valeur dans `public/css/jetons.css`. Pour le sombre, la modifier **deux fois** : sous `[data-theme="sombre"]` et dans le bloc `@media (prefers-color-scheme: dark)` (copie exacte ; `scripts/contraste.php` échoue s'ils diffèrent).
2. Lancer `docker compose exec -T web composer check` : `contraste.php` mesure toutes les paires dans les deux thèmes (texte 4,5:1, contours et focus 3:1) et `lint-inline.php` refuse toute couleur écrite ailleurs.
3. Une nouvelle paire de couleurs (un texte sur un fond qui n'existait pas) s'ajoute à `paires_de_couleurs()` dans `app/jetons.php`.
4. `/gabarits` affiche les valeurs et les contrastes lus dans la feuille : vérifier visuellement les deux thèmes (bouton « Mode sombre »).

## Thèmes

- Clair par défaut ; sombre si la bascule l'a choisi (cookie `cm_theme`, rendu serveur par `data-theme` sur `<html>`, sans éclair) ou, sans choix, si le système est en sombre.
- Les jetons sont posés sur des sélecteurs d'attribut (`[data-theme="sombre"]`) et pas seulement sur `:root` : un bloc peut forcer un thème localement (nuanciers de `/gabarits`, décompte).
- Le mode répétition force `data-theme="sombre"` et n'a pas de bascule.

## Corrections apportées à la maquette (et pourquoi)

| Maquette | Code | Raison |
| --- | --- | --- |
| Orange #b4530a en texte « Choré à créer » (4,23:1 sur la surface) | `--orange-texte: #a04a08` (5,08:1 sur `--surface`, 4,65:1 sur `--surface-2`, 5,81:1 sur le fond) ; #b4530a reste le contour | WCAG 1.4.3 |
| Grenat éclairci #a4284c en texte et contour des boutons en sombre (2,6:1) | `--accent` = `--texte` en sombre (contours, vitesses, Miroir, Boucle, et plein des boutons choisis) ; `--lien` = or éclairci en sombre ; `--grenat-inverse` pour le texte posé sur la pastille claire de la carte grenat ; le grenat éclairci ne sert qu'aux surfaces | WCAG 1.4.3 et 1.4.11, spec § 9 |
| Vitesse choisie signalée par la couleur seule | plein accent et « ✓ » écrit | état jamais par la couleur seule |
| Focus or seul (2,6:1 sur le jasmin) | or doublé d'un filet sombre (boutons), fond or (liens), contour sombre (champs) | WCAG 1.4.11 |
| Textes de 11 à 13 px (points, « Public », état de la check-list, vignettes) | 14 px au moins ; points abrégés à trois lettres (prénom entier dans la liste textuelle) | règle « rien sous 14 px » |
| « BPM », « Stop » | « temps par minute », « Arrêter » | aucun mot anglais |
| Quadrillage de scène en `linear-gradient` | lignes SVG | aucun dégradé |
| `aria-label` sur des `div` (points, cellules), `aria-live="assertive"` sur le décompte | scène en `role="img"` + liste textuelle, vraie `<table>` pour la grille, `aria-live="polite"` | ARIA correct |
| Niveau choisi signalé par la couleur seule | `aria-pressed`, contour accent et « ✓ » visible | état jamais par la couleur seule |
| Bascule dont le libellé change (« Mode sombre » / « Mode clair ») | libellé stable « Mode sombre » + état écrit « activé / désactivé » + `aria-pressed` | un bouton bascule garde son nom |
| Styles et scripts inline, polices Google | feuilles et modules locaux, polices auto-hébergées | CSP sans `unsafe-inline`, rien ne sort |

## Positions sans attribut `style`

La CSP interdit les attributs `style`. Les barres de progression reçoivent une classe `p-N` (N × 5 %) ; les points de scène une ancre de taille nulle posée sur une grille de 20 × 20 par `col-N lig-N` (pas de 5 %). Avec JavaScript, `element.style` (permis par la CSP) sert aux positions fines (progression du lecteur, bornes de section).

## Interdits (vérifiés automatiquement quand c'est possible)

Ombre, dégradé, emoji, photo, image générée, texte anglais, animation autre que le décompte (`battement`, coupée en mouvement réduit), attribut `style`, `<script>` inline, `on*=`, couleur hors `jetons.css`, ressource externe (`scripts/lint-inline.php`) ; texte sous 14 px (test Playwright sur `/gabarits`) ; contrastes (`scripts/contraste.php`).

## Budgets

Polices 87 Ko (≤ 120) ; CSS des pages ≈ 11 Ko compressées (≤ 40 ; 54 Ko non compressées, garde-fou à 60) ; JS ≈ 30 Ko (≤ 40), `lecteur.js` ≈ 22 Ko (≤ 25). Contrôlés par `tests/phpunit/OutilsTest.php`.
