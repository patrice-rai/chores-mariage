# Prompt Claude Design — site interactif « Chorés du mariage »

Écrit le 12 octobre 2026. À coller tel quel dans Claude Design (type « Design »). Aucun design system à joindre : la direction visuelle est dans le prompt. Les faits (chansons, danseurs, tutoriels) viennent de `distribution.md` et de `recherches/`.

## Prompt

<mission>
Tu es le directeur artistique et designer produit d'un petit site privé, « Chorés du mariage », fait pour neuf cousins qui préparent cinq chorégraphies pour le mariage de l'un d'eux. Les cousins ne sont pas tous dans la même ville, ils ont des niveaux de danse très différents, et ils répètent surtout seuls, chez eux, le téléphone posé contre un mur, avant de se retrouver pour des répétitions en groupe. Le site doit leur donner un seul lien, sans compte, où chacun trouve ses chorés, répète avec la vidéo au ralenti et en miroir, voit sa place sur scène, dit ce qu'il maîtrise, et sait quand est la prochaine répétition. Tu dessines une application web mobile d'abord, utilisée à une main, avec de très grandes commandes, et un prototype cliquable.
</mission>

<faits>
- Les neuf danseurs : Alex, Catherine, Fabien, Flobert, Jessica, Josiane, Nadine, Patrice, Stéphane. N'invente aucun autre prénom.
- Les cinq chansons (musique de films tamouls) et leur distribution :
  1. « Celebration of Varisu » (film Varisu, 2023, 2 min 48, rapide, 140 BPM) : tout le groupe, 8 danseurs (tous sauf Nadine).
  2. « Yaaro Yaarodi » (film Alaipayuthey, A.R. Rahman, 5 min 47, à couper) : Josiane, Catherine, Jessica.
  3. « Thalapathy Kacheri » (film Jana Nayagan, 2026, 3 min 17) : Fabien, Flobert, Alex ; Patrice en attente (montre cet état « en attente »).
  4. « Innisai » (film Varalaru, A.R. Rahman, 2006, semi-classique, lent ; Nadine la coupe elle-même) : Nadine, en solo. C'est la seule chorégraphie formellement décidée par le groupe.
  5. « Kanimaa » (film Retro, 2025, 4 min 04, rythme de mariage) : tout le groupe, 8 danseurs.
- Pour les chansons 1, 3 et 5, un tutoriel YouTube existe ; pour les chansons 2 et 4, la chorégraphie est à créer par les cousins eux-mêmes. Les références : pour Kanimaa, la version dansée d'Ahinth Vijay ft. The Officials ; pour Yaaro Yaarodi, la danse de mariage de VrindhArjun.
- Trois chansons sont proposées, sans décision (Maamadura, The Wedding Song, Sangeeth Celebration) : prévois un état « proposée, pas tranchée » pour une chanson.
- Jessica et Stéphane ne sont pas dans le groupe WhatsApp : le site doit marcher pour eux aussi, sans WhatsApp.
- Une chorégraphie se découpe en sections de « 8 temps » (on compte 5-6-7-8 pour partir). Une section a un nom (« Intro », « Refrain 1 », « Rap », « Final »), un début et une fin dans la vidéo, des comptes, et un placement sur scène.
- Dates, lieux de répétition et date du mariage ne sont pas connus : utilise des libellés de démonstration évidents (« Samedi [date] », « Chez [cousin] ») plutôt que des dates inventées.
</faits>

<ecrans>
Dessine, en 390 × 844 (mobile), les écrans suivants, puis deux versions bureau 1 280 px (la page d'une chanson et les placements). Tous les textes en français, tutoiement, ton joyeux et direct, jamais mièvre.

1. **Entrée** : un seul écran. « Chorés du mariage », une phrase, et la liste des neuf prénoms en grands boutons : chacun touche le sien. Pas de mot de passe visible (le lien est secret) ; une ligne discrète « Tu n'es pas dans la liste ? Demande le lien à Patrice. »
2. **Ma page** (l'accueil de chaque cousin) : « Salut Jessica » ; la prochaine répétition en tête (jour, heure, lieu, deux boutons « Je viens » / « Je ne peux pas », et qui a déjà répondu) ; « Tes chorés » : une carte par chanson avec la progression (par exemple « 5 sections sur 8 maîtrisées ») et un bouton « Répéter » ; « Ce qui bouge » : les dernières vidéos postées par les cousins et les derniers commentaires. Dessine la version de Jessica (trois chorés) et celle de Nadine (un solo).
3. **Page d'une chanson** (par exemple Kanimaa) : en haut, le lecteur vidéo de référence avec des commandes énormes, utilisables du bout du pied ou à bout de bras : lecture, vitesse (0,5 ×, 0,75 ×, 1 ×), **miroir** (un bouton qui dit « Miroir » en toutes lettres et change d'état), **boucle sur la section**, et **décompte** « 5, 6, 7, 8 » affiché en très grand avant que la vidéo parte. Sous le lecteur, la liste des sections : nom, comptes (« 2 × 8 »), durée, et pour chacune un bouton d'état à trois positions « Pas encore / Presque / Je maîtrise ». Puis « Ma place » : un mini-schéma de la scène avec le point de Jessica en évidence pour la section en cours. Puis « Qui maîtrise quoi » : une grille cousins × sections, lisible d'un coup d'œil. Puis les tutoriels liés (liens sortants vers YouTube, présentés sobrement : titre, durée, « en miroir » si c'est le cas). Puis « Vidéos des cousins » pour cette chanson.
4. **Mode répétition** (plein écran, depuis le lecteur) : la vidéo occupe tout, les commandes restent géantes et contrastées, le nom de la section et ses comptes en surimpression, le décompte 5-6-7-8 en plein écran, un bouton « Section suivante ». L'écran doit rester lisible à deux mètres, le téléphone posé contre un mur.
5. **Placements** : la scène vue de dessus, une grille simple, huit points avec les prénoms, le public en bas ; une section à la fois, avec les flèches de déplacement vers la section suivante ; un sélecteur de section ; « Me surligner ». Version bureau : les huit sections côte à côte.
6. **Répétitions** : la liste des répétitions à venir et passées ; pour chacune : date, heure, lieu, ce qu'on travaille (quelles chansons, quelles sections), les présents et les absents, un compte-rendu de trois lignes après coup ; un bouton « Proposer des dates » qui renvoie vers un sondage externe.
7. **Vidéos des cousins** : un album par chanson ; chaque vidéo a un auteur, une date, un commentaire ; les commentaires sont horodatés dans la vidéo (« à 0:42 : le bras gauche part trop tôt ») ; un bouton « Ajouter ma vidéo » depuis le téléphone ; mention « Ces vidéos restent entre nous. »
8. **Jour J** : l'ordre de passage des cinq chorés, une frise, pour chaque choré « qui entre, d'où, qui lance la musique », une check-list (tenue, enceinte, musique coupée à la bonne durée), et un bouton « Mode scène » qui affiche les placements en grand sans autre chose.

États à dessiner : première visite (personne n'a encore rien marqué) ; vidéo en cours de chargement ; hors ligne (« Pas de réseau : tu peux répéter les sections déjà enregistrées sur ton téléphone ») ; répétition à laquelle personne n'a encore répondu ; chanson dont la chorégraphie est « à créer » (Yaaro Yaarodi et Inisai : un encart « La choré n'est pas encore posée. Qui s'en occupe ? » avec les prénoms concernés).
</ecrans>

<direction>
- Sujet : un mariage tamoul, la fête, les saris de soie. Palette : un grenat profond (environ #8a1538) pour l'identité et les grands aplats, un or (environ #c9930a) pour l'accent et les états « je maîtrise », un fond jasmin très clair (environ #fffaf3) ; texte presque noir chaud. Mode sombre pour répéter le soir : fond #17121a, mêmes grenat et or éclaircis. Pas d'autre couleur, sauf un vert sobre pour « présent » et un orange pour « à créer ».
- Typographie : une display expressive avec du caractère pour les titres et le décompte 5-6-7-8 (par exemple Yeseva One), une sans très lisible pour tout le reste (par exemple Nunito Sans), corps 17 à 18 px, chiffres tabulaires pour les comptes et les durées.
- Formes : coins arrondis de 12 à 16 px sur les cartes, boutons pleins et hauts (56 px pour les commandes du lecteur, 48 px ailleurs), aucune ombre portée lourde, aucun dégradé.
- Mouvement : un seul moment fort, le décompte « 5, 6, 7, 8 » qui s'affiche chiffre par chiffre au tempo de la chanson avant la lecture. Tout le reste est immobile. Prévois l'état « mouvement réduit ».
- Images : aucune photo de personne générée. Les vignettes de vidéos sont des aplats grenat avec le nom de la chanson et la durée. Aucun emoji dans l'interface ; les états se disent avec des mots.
- Évite le « look application générique » : pas de barre d'onglets à cinq icônes identiques, pas de cartes blanches toutes pareilles, pas de dégradé violet, pas de texte gris sur gris. La page d'une chanson doit se reconnaître entre mille : le lecteur et ses commandes énormes sont sa signature.
</direction>

<interactions>
Le prototype doit être cliquable : choisir son prénom → Ma page → « Répéter » → la page de la chanson → une section → le mode répétition avec le décompte → retour ; changer l'état « Pas encore / Presque / Je maîtrise » et voir la grille « Qui maîtrise quoi » se mettre à jour ; répondre « Je viens » à la répétition ; passer d'une section à l'autre dans les placements. Montre les états des boutons du lecteur (miroir activé, boucle activée, vitesse choisie).
</interactions>

<accessibilite>
WCAG 2.2 AA : cibles de 44 px au moins, 56 px pour les commandes du lecteur ; contrastes 4,5:1 sur tout le texte, y compris en mode sombre et dans le mode répétition ; tout état dit par un mot, jamais par la couleur seule ; libellés visibles ; le décompte est aussi affiché en texte ; aucun glisser-déposer obligatoire (les placements se modifient aussi par des boutons « ← → ↑ ↓ ») ; tout fonctionne au clavier sur la version bureau ; zoom 200 % sans défilement horizontal.
</accessibilite>

<livrables>
1. Les huit écrans mobiles et les deux écrans bureau, avec leurs états, nommés clairement.
2. Une planche « composants » : lecteur et ses commandes, carte de chanson, ligne de section avec ses trois états, point de placement, bouton de présence, vidéo de cousin avec commentaire horodaté, décompte.
3. Une planche « fondations » : palette avec les contrastes mesurés, échelle typographique, espacements, rayons, les deux modes clair et sombre.
4. Un prototype cliquable qui enchaîne le parcours décrit dans <interactions>.
5. Une note de dix lignes au plus : les décisions prises, et ce qu'il faudrait demander aux cousins avant de coder.
Ne produis rien qui ressemble à un site vitrine : c'est un outil qu'on ouvre entre deux passages, le souffle court.
</livrables>
