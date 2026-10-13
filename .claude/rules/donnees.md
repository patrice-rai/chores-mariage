---
paths:
  - 'app/migrations/**'
  - 'scripts/**'
  - 'tests/**'
---

# Données (spec § 3 et 5)

- Sources des faits : `docs/whatsapp/etat.json` et `docs/distribution.md`, importés par `scripts/importer-etat.php` (idempotent). Rien d'autre.
- Jamais inventé : prénom, chanson, date, tempo, section, placement, ordre de passage, compte rendu, présence. Une valeur absente reste NULL ou entre crochets (« [date] », « [lieu] ») et va dans `docs/A-FAIRE-PAR-PATRICE.md` avec l'endroit où la saisir.
- Jamais importé : les valeurs de démonstration de la maquette (sections Intro / Couplet / Refrain, BPM 96/128/72/112, « on vise 2 min 30 », ordre 1-5, vidéos, commentaires, check-list de cinq lignes, présents de la répétition passée).
- Tempos de `docs/recherches/` : `bpm_verifie = 0`, affichés « tempo à confirmer » ; tempo inconnu : décompte à 120 avec « tempo à régler ».
- Migrations numérotées `app/migrations/NNN-nom.sql`, jamais modifiées une fois commitées, appliquées par `scripts/migrer.php` (idempotent).
- Dates ISO 8601 en UTC en base, affichées en Europe/Paris.
- Données de test : « Démo », « Section A », « Section B », vidéos synthétiques (canvas + MediaRecorder), jamais versionnées, jamais dans l'import de production. Les neuf prénoms réels peuvent servir dans les tests.
