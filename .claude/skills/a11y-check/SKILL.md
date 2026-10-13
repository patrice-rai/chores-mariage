---
name: a11y-check
description: Vérifie l'accessibilité (WCAG 2.2 AA) des pages touchées du site « Chorés du mariage » : axe par Playwright en 390 et 1 280 px, puis liste manuelle. Invocation par /a11y-check [routes].
argument-hint: "[routes, par exemple / /moi]"
---

# /a11y-check — accessibilité des pages touchées

1. Pages visées : les routes de `$ARGUMENTS`, sinon celles dont les vues ont changé depuis le dernier commit (`git diff --name-only HEAD -- app/vues public/css public/js`).
2. Vérifie que le site répond : `curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8095/lien` doit donner 200 ; sinon `docker compose up -d --build`.
3. Lance axe : `pnpm test:e2e --grep axe`. Toute violation est bloquante.
4. Contrastes : `docker compose exec -T web php scripts/contraste.php` ; toute paire nouvelle doit y figurer.
5. Inline : `docker compose exec -T web php scripts/lint-inline.php`.
6. Liste manuelle pour chaque page (à cocher dans la réponse, avec ce qui a été vu) :
   - un seul `h1`, titre « {écran} — Chorés du mariage », lien d'évitement qui mène au `<main>` ;
   - parcours complet au clavier (Tab, Maj+Tab, Entrée, Espace), focus toujours visible et non masqué ;
   - cibles ≥ 44 px (prénoms 72 px), 8 px entre cibles ;
   - états écrits par un mot ; aucun état par la couleur seule ;
   - largeur 320 px et zoom 200 % sans défilement horizontal ;
   - `prefers-reduced-motion` : rien ne bouge hors décompte ;
   - français accentué, tutoiement, aucun mot anglais ni emoji.
7. Pour une relecture indépendante, lance le sous-agent `a11y-reviewer` sur les mêmes pages.
8. Ce qui exige un humain (VoiceOver iPhone, TalkBack Android) va dans `docs/A-FAIRE-PAR-PATRICE.md`.
