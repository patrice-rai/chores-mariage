# Prompt — analyser l'export WhatsApp du groupe des cousins

Écrit le 12 octobre 2026. Comment obtenir l'export : dans WhatsApp, ouvrir le groupe → ⋮ (ou le nom du groupe) → Plus → Exporter la discussion → « Sans fichiers multimédias » (le fichier .txt suffit ; avec les médias, l'archive peut peser plusieurs centaines de Mo). Déposer le fichier dans `P:\PROJECT\chores-mariage\whatsapp\` puis coller le prompt ci-dessous dans Claude Code (ou dans Claude avec le fichier joint).

## Prompt

<mission>
Tu analyses l'export d'un groupe WhatsApp familial dans lequel neuf cousins organisent des chorégraphies pour le mariage de l'un d'eux. Le fichier est ici : `P:\PROJECT\chores-mariage\whatsapp\[NOM DU FICHIER].txt`. Ton travail : en tirer, sans rien inventer, tout ce qui sert à préparer les chorés et les répétitions, et produire un état des lieux structuré que je pourrai verser dans le site « Chorés du mariage » et renvoyer au groupe.
</mission>

<contexte>
- Les danseurs attendus : Alex, Catherine, Fabien, Flobert, Jessica, Josiane, Nadine, Patrice, Stéphane. D'autres personnes (les mariés, des parents, des amis) peuvent être dans le groupe : note-les à part.
- Distribution connue au 12 octobre : « Celebration of Varisu » (tous sauf Nadine), « Yaaro Yaarodi » (Josiane, Catherine, Jessica), « Thalapathy Kacheri » (Fabien, Flobert, Alex), « Inisai » (Nadine, solo), « Kanimaa » (tous sauf Nadine). Si la discussion montre autre chose, c'est la discussion qui a raison : signale chaque écart.
- Les messages mélangent français, tamoul en lettres latines, anglais, emojis, messages vocaux et médias omis (« <Médias omis> », « fichier joint », « message vocal »). Un emoji 👍 ou ❤️ sur une proposition vaut souvent accord : compte-le comme un « oui faible », en le disant.
- Format WhatsApp : chaque ligne commence par une date, une heure et un prénom ou un numéro ; un message peut tenir sur plusieurs lignes. Les numéros de téléphone sans nom sont des personnes dont le contact n'est pas enregistré : désigne-les par leur numéro tronqué (« +33 6 … 42 »), jamais en entier dans le rapport.
</contexte>

<a_extraire>
1. **Le groupe** : nom du groupe, période couverte (premier et dernier message), nombre de messages, qui écrit (nombre de messages par personne), qui n'a jamais écrit, qui a quitté ou rejoint.
2. **Les décisions**, dans l'ordre chronologique : chaque décision prise (chanson retenue ou écartée, distribution, durée coupée, ordre de passage, date, lieu, tenue, qui lance la musique, qui crée la choré), avec la date, qui l'a proposée, qui a dit oui, et la citation exacte du message (entre guillemets, avec l'heure). Distingue « décidé » (accord explicite de plusieurs personnes), « proposé » (une seule personne, pas de réponse) et « contesté » (désaccord).
3. **Les chansons** : toutes celles qui ont été évoquées, même écartées, avec les liens YouTube/Spotify/Instagram/TikTok partagés (URL complète), qui les a partagés, et ce qu'on en a dit.
4. **Les répétitions** : dates et lieux proposés, dates confirmées, qui a dit qu'il venait, qui ne pouvait pas, les contraintes récurrentes (travail, enfants, distance, horaires) ; les comptes rendus après une répétition s'il y en a.
5. **La répartition du travail** : qui a pris en charge quoi (créer une choré, couper la musique, trouver une salle, filmer, acheter la tenue), avec la citation.
6. **Les vidéos et fichiers** : messages vocaux et vidéos échangés (date, auteur, ce que le message autour en dit : « voilà le refrain au ralenti »), pour savoir lesquels demander ou retrouver.
7. **Les questions restées sans réponse** et les points de désaccord ouverts, chacun avec la citation et la date.
8. **Les contraintes et détails pratiques** : date du mariage, lieu, heure de la fête, durée disponible, taille de la scène, sono, tenue, budget, s'ils apparaissent.
</a_extraire>

<regles>
- N'invente rien. Chaque fait porte une date et une citation. Si une information manque, écris « non mentionné dans le groupe ».
- Ne juge personne : pas de commentaire sur le caractère, l'engagement ou la motivation des cousins. « N'a pas encore répondu à la proposition du [date] » est un fait ; « peu motivé » n'en est pas un.
- Ne cite les messages privés ou sans rapport avec les chorés (santé, argent, disputes, vie personnelle) que s'ils expliquent une contrainte de répétition, et alors en une ligne neutre, sans citation.
- Ne recopie aucun numéro de téléphone complet. Ne crée aucun fichier en dehors du rapport demandé.
- Si le fichier est long, lis-le en entier avant d'écrire : les décisions se défont souvent plus loin.
</regles>

<livrables>
1. **Rapport** `P:\PROJECT\chores-mariage\whatsapp\analyse-[AAAA-MM-JJ].md` avec les huit sections ci-dessus, chacune en tableau quand c'est possible.
2. **Mise à jour proposée de `distribution.md`** : la distribution telle que le groupe l'a décidée, avec les écarts par rapport à la version du 12 octobre, en « diff » lisible (ligne retirée / ligne ajoutée).
3. **Un message pour le groupe**, prêt à coller dans WhatsApp, huit lignes au plus, tutoiement, ton joyeux : ce qui est décidé, ce qui attend une réponse (avec les prénoms concernés), la prochaine date. Pas d'emoji sauf un seul à la fin si tu veux.
4. **Un fichier JSON** `P:\PROJECT\chores-mariage\whatsapp\etat.json` pour le site : `danseurs[]`, `chansons[] {titre, danseurs[], statut: "decidee" | "proposee" | "ecartee", liens[], choregraphe}`, `repetitions[] {date, lieu, statut, presents[], absents[]}`, `questions_ouvertes[]`, chaque entrée avec la date de son dernier message source.
5. Une note de cinq lignes : ce que tu n'as pas pu lire (vocaux, images) et ce qu'il faudrait demander directement aux cousins.
</livrables>
