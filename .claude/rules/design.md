---
paths:
  - 'app/vues/**'
  - 'public/css/**'
  - 'public/js/**'
---

# Visuel (maquette `docs/maquette/`, lue dans `docs/spec/00-lecture-maquette.md`, corrections de la spec § 9)

- Couleurs : uniquement les jetons (variables CSS de `public/css/`) ; aucune valeur en dur hors de la déclaration des jetons.
- Clair : fond #fffaf3, surface #f6ead8, surface-2 #efe0c8, texte #2a1a12, secondaire #5c4a44, grenat #8a1538 (texte dessus #fffaf3), or #c9930a (texte dessus #2a1a12), filet #e3d3bb, vert #2e6b3a, orange #b4530a.
- Sombre : fond #17121a, surfaces #241c28 / #312736, texte #fff7ec, secondaire #cdbfb6, grenat #a4284c (surfaces seulement, jamais du texte), or #e0ab2a, filet #3d3242, vert #7fcf8c, orange #f0a060. Mode répétition toujours sombre.
- Polices : Yeseva One (titres, numéros, décompte) et Nunito Sans (texte), auto-hébergées (phase 3) ; en attendant, polices système. Aucune police externe.
- Corps 17 px, interligne 1,4 ; rien sous 14 px ; chiffres tabulaires.
- Rayons 6 · 8 · 10 · 12 · 14 · 16 · 24 ; espacements 4 · 8 · 12 · 16 · 24 · 32 · 40 ; marges d'écran 16 px.
- Interdits : ombre, dégradé, emoji, photo, image générée, texte anglais, animation autre que le décompte, attribut `style`, `<script>` inline, `on*=` (vérifié par `scripts/lint-inline.php`).
- Classes en BEM français (`.lecteur`, `.lecteur__commande`, `.lecteur--miroir`) ; un gabarit par composant dans `app/vues/composants/`.
