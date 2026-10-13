<?php

declare(strict_types=1);

use function Chores\champ_csrf;
use function Chores\composant;
use function Chores\e;
use function Chores\format_comptes;
use function Chores\format_duree;
use function Chores\format_duree_section;
use function Chores\format_rapport;
use function Chores\url;

use const Chores\CHAMP_CSRF;

/**
 * Gabarits (phase 3) : tout le visuel du site, avec des données de démonstration (« Démo », « Section A ») ; rien
 * n'est un fait sauf les prénoms des cousins et les deux tutoriels de docs/recherches/.
 * @var Chores\Contexte $c
 * @var array<string, mixed> $config
 * @var array{clair: array<string, string>, sombre: array<string, string>} $jetons
 * @var list<array{theme: string, avant: string, arriere: string, seuil: float, usage: string, rapport: ?float, ok: bool}> $contrastes
 * @var array{cle: string, titre: string, film: string, duree_s: int, bpm: ?int} $chanson
 * @var list<array{nom: string, debut: float, fin: float, comptes: int}> $sections
 * @var int $courante
 * @var ?string $video
 * @var list<list<array{prenom: string, x: int, y: int}>> $placements
 * @var string $moi
 * @var bool $essai
 * @var bool $surligner
 */
$essaiAction = url($config, '/gabarits/essai');
$page = url($config, '/gabarits');
$hrefRepeter = url($config, '/gabarits/repeter?section=');
$nomsSections = array_column($sections, 'nom');
$duree = format_duree($chanson['duree_s']);
$prenomsScene = array_column($placements[0], 'prenom');
$section = $sections[$courante];
$couleurs = ['fond', 'surface', 'surface-2', 'texte', 'secondaire', 'grenat', 'sur-grenat', 'or', 'sur-or', 'filet', 'vert', 'orange', 'orange-texte', 'accent', 'lien', 'decompte'];
$tailles = [
    ['t-entree', 'titre', 'Yeseva One 42 — titre d’entrée'],
    ['t-salut', 'titre', 'Yeseva One 38 — « Salut »'],
    ['t-ecran', 'titre', 'Yeseva One 34 — titre d’écran'],
    ['t-chanson', 'titre', 'Yeseva One 32 — titre de chanson'],
    ['t-lecteur', 'titre', 'Yeseva One 28 — lecteur, section en surimpression'],
    ['t-h2', 'titre', 'Yeseva One 24 — titres de partie'],
    ['t-barre', 'titre', 'Yeseva One 20 — barre haute'],
    ['t-titre-carte', 'gras', 'Nunito Sans 800, 19 — titre de carte'],
    ['t-corps', 'texte', 'Nunito Sans 17 — corps, interligne 1,4'],
    ['t-petit', 'texte', 'Nunito Sans 15 — textes secondaires'],
    ['t-mini', 'texte', 'Nunito Sans 14 — le plus petit texte permis'],
];
$niveauxDemo = static fn (int $i): array => [($i + 2) % 3, ($i + 1) % 3, $i % 3];
?>
<div class="contenu contenu--large gabarits">
  <h1 class="titre-ecran">Gabarits</h1>
  <p>Tout le visuel du site au même endroit, pour Patrice. Les données sont des exemples (« Démo », « Section A ») : rien de tout cela n’est enregistré.</p>
<?php if ($essai): ?>
  <?= composant($c, 'confirmation', ['texte' => 'Démonstration : rien n’est enregistré, c’était un essai.', 'id' => 'essai']) ?>
<?php endif; ?>
  <nav class="gabarits__sommaire" aria-label="Sommaire des gabarits">
    <ul>
      <li><a href="#fondations">Fondations</a></li>
      <li><a href="#composants">Composants</a></li>
      <li><a href="#lecteur">Lecteur</a></li>
      <li><a href="#decompte">Décompte</a></li>
      <li><a href="#scene-titre-partie">Scène de placements</a></li>
    </ul>
  </nav>

  <section class="gabarits__partie" aria-labelledby="fondations">
    <h2 class="gabarits__titre" id="fondations">Fondations</h2>

    <h3 class="gabarits__sous-titre">Palette des deux thèmes</h3>
    <div class="nuanciers">
<?php foreach (['clair' => 'Mode clair', 'sombre' => 'Mode sombre (répéter le soir, mode répétition)'] as $theme => $nomTheme): ?>
      <div class="nuancier" data-theme="<?= e($theme) ?>">
        <p class="nuancier__titre"><?= e($nomTheme) ?></p>
        <ul class="nuancier__liste">
<?php foreach ($couleurs as $jeton): ?>
          <li class="nuancier__couleur"><span class="nuancier__pastille nuancier__pastille--<?= e($jeton) ?>" aria-hidden="true"></span><span><code>--<?= e($jeton) ?></code> <?= e($jetons[$theme][$jeton] ?? '[absent]') ?></span></li>
<?php endforeach; ?>
        </ul>
      </div>
<?php endforeach; ?>
    </div>

    <h3 class="gabarits__sous-titre">Contrastes mesurés</h3>
    <p class="gabarits__note">Lus dans <code>public/css/jetons.css</code> et mesurés par la formule WCAG, comme le fait <code>scripts/contraste.php</code> à chaque contrôle. Texte : 4,5:1 au moins ; contours et focus : 3:1.</p>
    <div class="tableau-defilant" role="region" aria-labelledby="contrastes-legende" tabindex="0">
      <table class="tableau">
        <caption id="contrastes-legende">Paires de couleurs autorisées</caption>
        <thead>
          <tr><th scope="col">Premier plan sur fond</th><th scope="col">Thème</th><th scope="col">Rapport</th><th scope="col">Seuil</th><th scope="col">Résultat</th><th scope="col">Usage</th></tr>
        </thead>
        <tbody>
<?php foreach ($contrastes as $mesure): ?>
          <tr>
            <th scope="row"><code>--<?= e($mesure['avant']) ?></code> sur <code>--<?= e($mesure['arriere']) ?></code></th>
            <td><?= e($mesure['theme']) ?></td>
            <td><?= $mesure['rapport'] !== null ? e(format_rapport($mesure['rapport'])) : 'absent' ?></td>
            <td><?= e(format_rapport($mesure['seuil'])) ?></td>
            <td><?= $mesure['ok'] ? 'conforme' : 'insuffisant' ?></td>
            <td><?= e($mesure['usage']) ?></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <h3 class="gabarits__sous-titre">Typographie</h3>
    <ul class="echelle">
<?php foreach ($tailles as [$jeton, $style, $libelle]): ?>
      <li class="echelle__ligne"><span class="echelle__exemple echelle__exemple--<?= e($style) ?> echelle__exemple--<?= e($jeton) ?>">Kanimaa 4 × 8</span><span class="echelle__nom"><?= e($libelle) ?></span></li>
<?php endforeach; ?>
    </ul>
    <p class="gabarits__note">Chiffres tabulaires partout : 0:17 · 4 × 8 · 0,75 ×. Décompte : Yeseva One 120 dans le lecteur, 260 en mode répétition.</p>

    <h3 class="gabarits__sous-titre">Espacements, rayons, cibles</h3>
    <ul class="echantillons" aria-label="Espacements">
<?php foreach ([4, 8, 12, 16, 24, 32, 40] as $e): ?>
      <li class="echantillon"><span class="echantillon__espace echantillon__espace--<?= $e ?>" aria-hidden="true"></span><?= $e ?></li>
<?php endforeach; ?>
    </ul>
    <ul class="echantillons" aria-label="Rayons">
<?php foreach (['6', '8', '10', '12', '14', '16', '24', 'rond'] as $r): ?>
      <li class="echantillon"><span class="echantillon__rayon echantillon__rayon--<?= e($r) ?>" aria-hidden="true"></span><?= e($r) ?></li>
<?php endforeach; ?>
    </ul>
    <ul class="echantillons echantillons--cibles" aria-label="Hauteurs des cibles">
<?php foreach (['44' => 'états, select', '48' => 'boutons courants', '56' => 'lecteur', '60' => 'répétition (secondaires)', '64' => 'Lecture', '72' => 'prénoms, répétition'] as $h => $usage): ?>
      <li class="echantillon__cible echantillon__cible--<?= e($h) ?>"><?= e($h) ?> · <?= e($usage) ?></li>
<?php endforeach; ?>
    </ul>
  </section>

  <section class="gabarits__partie" aria-labelledby="composants">
    <h2 class="gabarits__titre" id="composants">Composants</h2>

    <h3 class="gabarits__sous-titre">Barre haute</h3>
    <p class="gabarits__note">En haut de cette page : titre et bascule « Mode sombre ». Variante avec Retour :</p>
    <div class="gabarits__cadre">
      <?= composant($c, 'barre-haute', ['titre' => 'Démo', 'action' => 'retour', 'retour' => $page . '#composants', 'bascule' => false]) ?>
    </div>

    <h3 class="gabarits__sous-titre">Bouton prénom</h3>
    <form class="gabarits__prenoms" method="post" action="<?= e($essaiAction) ?>">
      <input type="hidden" name="<?= e(CHAMP_CSRF) ?>" value="<?= e(champ_csrf($c)) ?>">
      <fieldset class="entree__groupe">
        <legend class="visuellement-cache">Exemple de prénoms</legend>
        <div class="entree__prenoms">
<?php foreach (['Alex', 'Catherine', 'Stéphane'] as $i => $prenom): ?>
          <?= composant($c, 'bouton-prenom', ['id' => $i + 1, 'prenom' => $prenom]) ?>
<?php endforeach; ?>
        </div>
      </fieldset>
    </form>

    <h3 class="gabarits__sous-titre">Carte « Prochaine répétition »</h3>
    <div class="gabarits__grille">
      <?= composant($c, 'carte-repetition', ['id' => 'repetition-reponses', 'niveau' => 4, 'precision' => 'exemple avec réponses', 'quand' => '[date] · [heure]', 'lieu' => '[lieu]', 'travail' => 'Démo, Section A', 'viennent' => ['Alex', 'Josiane'], 'nePeuventPas' => ['Fabien'], 'maReponse' => 'oui', 'action' => $essaiAction]) ?>
      <?= composant($c, 'carte-repetition', ['id' => 'repetition-personne', 'niveau' => 4, 'precision' => 'exemple sans réponse', 'quand' => '[date] · [heure]', 'lieu' => '[lieu]', 'travail' => 'Démo, Section B', 'action' => $essaiAction]) ?>
      <?= composant($c, 'carte-repetition', ['id' => 'repetition-sans-date', 'niveau' => 4, 'precision' => 'exemple sans sondage', 'travail' => 'Démo', 'action' => $essaiAction]) ?>
      <?= composant($c, 'carte-repetition', ['id' => 'repetition-sondage', 'niveau' => 4, 'precision' => 'exemple avec sondage', 'lienSondage' => 'https://framadate.org/', 'action' => $essaiAction]) ?>
    </div>

    <h3 class="gabarits__sous-titre">Bouton de présence (sur une carte)</h3>
    <div class="gabarits__grille">
      <div class="gabarits__surface"><?= composant($c, 'presence', ['action' => $essaiAction, 'reponse' => 'non', 'sujet' => 'la répétition Démo']) ?></div>
      <div class="gabarits__surface"><?= composant($c, 'presence', ['action' => $essaiAction, 'reponse' => null, 'sujet' => 'la répétition Démo 2']) ?></div>
    </div>

    <h3 class="gabarits__sous-titre">Carte de chanson</h3>
    <div class="gabarits__grille">
      <?= composant($c, 'carte-chanson', ['niveau' => 4, 'titre' => 'Démo', 'duree' => $duree, 'film' => $chanson['film'], 'danseurs' => 'tout le groupe (8)', 'maitrisees' => 2, 'total' => 3, 'href' => $page . '#lecteur']) ?>
      <?= composant($c, 'carte-chanson', ['niveau' => 4, 'titre' => 'Démo 2', 'duree' => $duree, 'film' => $chanson['film'], 'danseurs' => 'Alex, Fabien, Flobert', 'maitrisees' => 0, 'total' => 3, 'href' => $page . '#lecteur']) ?>
      <?= composant($c, 'carte-chanson', ['niveau' => 4, 'titre' => 'Démo à créer', 'duree' => 'durée inconnue', 'film' => $chanson['film'], 'danseurs' => 'Catherine, Jessica, Josiane', 'createurs' => 'Catherine, Jessica, Josiane', 'maitrisees' => 0, 'total' => 0, 'href' => $page . '#lecteur']) ?>
      <?= composant($c, 'carte-chanson', ['niveau' => 4, 'titre' => 'Démo décidée', 'duree' => $duree, 'film' => $chanson['film'], 'danseurs' => 'solo', 'createurs' => 'Nadine', 'decidee' => 'Choré décidée par le groupe', 'maitrisees' => 0, 'total' => 0, 'href' => $page . '#lecteur']) ?>
      <?= composant($c, 'carte-chanson', ['niveau' => 4, 'titre' => 'Démo en attente', 'duree' => $duree, 'film' => $chanson['film'], 'danseurs' => 'Alex, Fabien, Flobert · Patrice en attente', 'attente' => 'tu as demandé à t’ajouter, pas encore de réponse. Tu peux déjà répéter.', 'maitrisees' => 0, 'total' => 3, 'href' => $page . '#lecteur']) ?>
      <?= composant($c, 'carte-chanson', ['niveau' => 4, 'titre' => 'Démo sans sections', 'duree' => $duree, 'film' => $chanson['film'], 'danseurs' => 'tout le groupe (8)', 'maitrisees' => 0, 'total' => 0, 'href' => $page . '#lecteur']) ?>
    </div>

    <h3 class="gabarits__sous-titre">Encarts</h3>
    <div class="gabarits__grille">
      <?= composant($c, 'encart', ['variante' => 'pointille', 'titre' => 'Proposées, pas tranchées', 'texte' => 'Démo 2, Démo 3', 'note' => 'Personne n’a décidé : on garde ou on laisse tomber ? À régler à la prochaine répétition.']) ?>
      <?= composant($c, 'encart', ['variante' => 'orange', 'titre' => 'La choré n’est pas encore posée. Qui s’en occupe ?', 'prenoms' => ['Catherine', 'Jessica', 'Josiane'], 'choisi' => 'Catherine', 'action' => $essaiAction]) ?>
      <?= composant($c, 'encart', ['variante' => 'orange', 'titre' => 'La choré n’est pas encore posée. Qui s’en occupe ?', 'prenoms' => ['Catherine', 'Jessica', 'Josiane'], 'action' => $essaiAction]) ?>
      <?= composant($c, 'encart', ['variante' => 'attente', 'texte' => 'En attente : tu as demandé à rejoindre cette choré, pas encore de réponse.']) ?>
    </div>

    <h3 class="gabarits__sous-titre">Ce qui bouge</h3>
    <?= composant($c, 'fil', ['elements' => [
        ['vignette' => 'Démo · 0:12', 'titre' => 'Alex a posté « Essai section A »', 'detail' => 'Démo · [date]', 'href' => $page . '#composants'],
        ['vignette' => 'Démo · 0:04', 'titre' => 'Josiane à 0:04 : « Démo : le bras part trop tôt »', 'detail' => 'sur la vidéo d’Alex · Démo', 'href' => $page . '#composants'],
        ['vignette' => 'Démo', 'titre' => 'Stéphane maîtrise Section A', 'detail' => 'Démo · [date]', 'href' => $page . '#lecteur'],
    ]]) ?>
    <?= composant($c, 'fil', ['elements' => [], 'vide' => 'Rien de neuf pour l’instant : les vidéos, commentaires et réponses apparaîtront ici.']) ?>

    <h3 class="gabarits__sous-titre">Liens vers des tutoriels</h3>
    <div class="gabarits__liste-liens">
      <?= composant($c, 'lien-tutoriel', ['url' => 'https://youtu.be/Sr5hYJ7vzlM', 'titre' => 'Mahir Shanz, vue miroir, pas à pas (malayalam)', 'duree' => '9:41', 'miroir' => true, 'type' => 'tutoriel']) ?>
      <?= composant($c, 'lien-tutoriel', ['url' => 'https://youtu.be/VJEqJWeo_p0', 'titre' => 'Sherif Moves (chorégraphe du film)', 'duree' => '5:49', 'type' => 'tutoriel débutants']) ?>
    </div>

    <h3 class="gabarits__sous-titre">Vidéo de cousin et commentaires horodatés</h3>
    <div class="gabarits__grille">
      <?= composant($c, 'carte-video', ['niveau' => 4, 'chanson' => 'Démo', 'duree' => '0:20', 'auteur' => 'Alex', 'titre' => 'Essai section A', 'date' => '[date]', 'note' => 'Vidéo de démonstration.', 'href' => $page . '#lecteur-demo', 'commentaires' => [
          ['temps' => 4.0, 'auteur' => 'Josiane', 'texte' => 'Démo : le bras part trop tôt'],
          ['temps' => 12.0, 'auteur' => 'Fabien', 'texte' => 'Démo : le tour est bon'],
          ['temps' => null, 'auteur' => 'Patrice', 'texte' => 'Démo : commentaire général, sans horodatage'],
      ]]) ?>
      <?= composant($c, 'carte-video', ['niveau' => 4, 'chanson' => 'Démo', 'duree' => null, 'auteur' => 'Jessica', 'titre' => 'Essai section B', 'date' => '[date]', 'format' => 'format iPhone : peut-être illisible ailleurs, Patrice la convertira', 'href' => $page . '#lecteur-demo']) ?>
    </div>

    <h3 class="gabarits__sous-titre">Bannière hors ligne</h3>
    <?= composant($c, 'banniere-hors-ligne') ?>

    <h3 class="gabarits__sous-titre">Frise du Jour J</h3>
    <?= composant($c, 'frise', ['niveau' => 4, 'elements' => [
        ['titre' => 'Démo', 'duree' => $duree, 'qui' => 'tout le groupe (8)', 'dou' => null, 'musique' => null],
        ['titre' => 'Démo 2', 'duree' => 'durée inconnue', 'qui' => 'Catherine, Jessica, Josiane', 'dou' => null, 'musique' => null],
    ]]) ?>
    <?= composant($c, 'frise', ['elements' => []]) ?>

    <h3 class="gabarits__sous-titre">Check-list</h3>
    <div class="checklist">
      <?= composant($c, 'ligne-checklist', ['libelle' => 'Tenue', 'fait' => false, 'action' => $essaiAction]) ?>
      <?= composant($c, 'ligne-checklist', ['libelle' => 'Enceinte', 'fait' => true, 'par' => 'Patrice', 'action' => $essaiAction]) ?>
      <?= composant($c, 'ligne-checklist', ['libelle' => 'Musiques coupées à la bonne durée', 'fait' => false, 'action' => $essaiAction]) ?>
    </div>

    <h3 class="gabarits__sous-titre">Champs de formulaire</h3>
    <form class="formulaire" method="post" action="<?= e($essaiAction) ?>" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="<?= e(CHAMP_CSRF) ?>" value="<?= e(champ_csrf($c)) ?>">
      <div class="formulaire__resume" role="alert">
        <p class="formulaire__resume-titre">Il manque une chose avant d’envoyer :</p>
        <ul><li><a href="#demo-titre-erreur">Le titre : écris quelques mots (80 caractères au plus).</a></li></ul>
      </div>
      <?= composant($c, 'champ', ['type' => 'select', 'nom' => 'chanson', 'id' => 'demo-chanson', 'libelle' => 'Chanson', 'requis' => true, 'options' => ['demo' => 'Démo', 'demo-2' => 'Démo 2'], 'valeur' => 'demo']) ?>
      <?= composant($c, 'champ', ['type' => 'texte', 'nom' => 'titre', 'id' => 'demo-titre', 'libelle' => 'Titre', 'requis' => true, 'aide' => '80 caractères au plus, par exemple « Section A chez moi ».', 'attributs' => ['maxlength' => '80', 'autocomplete' => 'off']]) ?>
      <?= composant($c, 'champ', ['type' => 'texte', 'nom' => 'titre-erreur', 'id' => 'demo-titre-erreur', 'libelle' => 'Titre (exemple en erreur)', 'requis' => true, 'erreur' => 'Le titre est vide : écris quelques mots (80 caractères au plus).', 'attributs' => ['maxlength' => '80', 'autocomplete' => 'off']]) ?>
      <?= composant($c, 'champ', ['type' => 'zone', 'nom' => 'note', 'id' => 'demo-note', 'libelle' => 'Note', 'aide' => '300 caractères au plus.', 'attributs' => ['maxlength' => '300']]) ?>
      <?= composant($c, 'champ', ['type' => 'case', 'nom' => 'miroir', 'id' => 'demo-miroir', 'libelle' => 'Filmée en miroir']) ?>
      <?= composant($c, 'champ', ['type' => 'fichier', 'nom' => 'fichier', 'id' => 'demo-fichier', 'libelle' => 'Ta vidéo', 'aide' => 'MP4, MOV ou WebM, 200 Mo au plus. Une section ou deux, pas la chanson entière.', 'attributs' => ['accept' => 'video/mp4,video/quicktime,video/webm']]) ?>
      <button class="bouton bouton--plein bouton--56" type="submit">Envoyer</button>
    </form>

    <h3 class="gabarits__sous-titre">Messages</h3>
    <?= composant($c, 'confirmation', ['texte' => 'C’est noté : tu viens. Merci !']) ?>
    <?= composant($c, 'nouvelle-version', ['href' => $page]) ?>
  </section>

  <section class="gabarits__partie" aria-labelledby="lecteur">
    <h2 class="gabarits__titre" id="lecteur">Lecteur</h2>
    <p class="gabarits__note">La chanson « Démo » : vidéo synthétique de 20 secondes (bandes de couleur et numéro de la seconde), trois sections, tempo inconnu.<?= $video === null ? ' La vidéo n’est pas encore fabriquée sur ce poste (node scripts/video-demo.mjs) : le lecteur montre l’état « Pas encore de vidéo de référence ».' : '' ?></p>
    <div class="page-chanson">
      <div class="page-chanson__principal">
        <div>
          <h3 class="titre-chanson"><?= e($chanson['titre']) ?></h3>
          <p class="page-chanson__infos"><?= e($chanson['film']) ?> · <?= e($duree) ?> · tempo à régler · tout le groupe (8)</p>
        </div>
        <?= composant($c, 'lecteur', ['id' => 'lecteur-demo', 'chanson' => $chanson, 'video' => $video, 'sections' => $sections, 'courante' => $courante, 'mode' => 'page', 'hrefSection' => $hrefRepeter]) ?>
        <section class="sections" aria-labelledby="sections-titre">
          <h4 class="sections__titre" id="sections-titre">Sections</h4>
          <p class="sections__aide">Touche une section pour la lancer. On compte 5-6-7-8 pour partir.</p>
          <ol class="sections__liste" data-sections-de="lecteur-demo">
<?php foreach ($sections as $i => $s): ?>
            <?= composant($c, 'ligne-section', ['index' => $i, 'section' => $s, 'courante' => $i === $courante, 'niveau' => [2, 1, 0][$i] ?? 0, 'hrefChoisir' => $page . '?section=' . ($i + 1) . '#lecteur-demo', 'actionNiveau' => $essaiAction]) ?>
<?php endforeach; ?>
          </ol>
        </section>
      </div>
      <div class="page-chanson__cote">
        <section aria-labelledby="ma-place-titre">
          <h4 class="sections__titre" id="ma-place-titre">Ma place</h4>
          <?= composant($c, 'mini-scene', ['section' => $section['nom'], 'points' => $placements[$courante] ?? [], 'moi' => $moi, 'hrefPlacements' => $page . '?section=' . ($courante + 1) . '#scene']) ?>
        </section>
        <section aria-labelledby="grille-titre">
          <h4 class="sections__titre" id="grille-titre">Qui maîtrise quoi</h4>
          <?= composant($c, 'grille-maitrise', ['id' => 'grille-demo', 'legende' => 'Qui maîtrise quoi — Démo (niveaux d’exemple)', 'sections' => $nomsSections, 'moi' => $moi, 'lignes' => array_merge(
              array_map(static fn (string $p, int $i): array => ['prenom' => $p, 'attente' => false, 'niveaux' => $niveauxDemo($i)], $prenomsScene, array_keys($prenomsScene)),
              [['prenom' => 'Nadine', 'attente' => true, 'niveaux' => [0, 0, 0]]]
          )]) ?>
        </section>
      </div>
    </div>

    <h3 class="gabarits__sous-titre">Sans vidéo de référence ni section</h3>
    <?= composant($c, 'lecteur', ['id' => 'lecteur-vide', 'chanson' => ['cle' => 'demo-vide', 'titre' => 'Démo sans vidéo', 'duree_s' => null, 'bpm' => 140], 'video' => null, 'sections' => [], 'courante' => 0, 'mode' => 'page', 'hrefSection' => $hrefRepeter]) ?>

    <h3 class="gabarits__sous-titre">États du lecteur (illustrations figées)</h3>
    <div class="gabarits__grille gabarits__grille--lecteurs">
<?php foreach ([
    ['Miroir et boucle à 0,75 ×, en lecture', ['vitesse' => 0.75, 'miroir' => true, 'boucle' => true, 'lecture' => 'Pause', 'temps' => 5]],
    ['Décompte en cours', ['lecture' => 'Arrêter', 'voile' => 'decompte', 'chiffre' => 7]],
    ['Chargement', ['voile' => 'chargement', 'temps' => 9]],
    ['Vidéo gardée sur le téléphone', ['voile' => 'garde', 'temps' => 0]],
] as $i => [$legende, $etat]): ?>
      <figure class="gabarits__figure">
        <div inert><?= composant($c, 'lecteur', ['id' => 'lecteur-etat-' . $i, 'chanson' => $chanson, 'video' => $video ?? '', 'sections' => $sections, 'courante' => 0, 'mode' => 'page', 'hrefSection' => $hrefRepeter, 'illustration' => $etat]) ?></div>
        <figcaption class="gabarits__legende"><?= e($legende) ?></figcaption>
      </figure>
<?php endforeach; ?>
      <figure class="gabarits__figure">
        <div class="gabarits__panneau-sombre" data-theme="sombre" inert><?= composant($c, 'lecteur', ['id' => 'lecteur-etat-repetition', 'chanson' => ['cle' => 'demo-vide', 'titre' => 'Démo sans vidéo', 'duree_s' => null, 'bpm' => null], 'video' => null, 'sections' => $sections, 'courante' => 1, 'mode' => 'repetition', 'hrefSection' => $hrefRepeter, 'hrefQuitter' => $page, 'illustration' => []]) ?></div>
        <figcaption class="gabarits__legende">Mode répétition, chanson sans vidéo de référence</figcaption>
      </figure>
    </div>

    <h3 class="gabarits__sous-titre">Mode répétition</h3>
    <p class="gabarits__note">Plein écran, toujours sombre, commandes de 72 et 60 px, décompte en 260 px.</p>
    <a class="bouton bouton--plein" href="<?= e($hrefRepeter . ($courante + 1)) ?>">Ouvrir le mode répétition (Démo, <?= e($section['nom']) ?>)</a>
  </section>

  <section class="gabarits__partie" aria-labelledby="decompte">
    <h2 class="gabarits__titre" id="decompte">Décompte</h2>
    <p class="gabarits__note">Un chiffre par temps, au tempo de la chanson (140 temps par minute : 0,43 s par chiffre ; jamais sous 0,38 s). Ici, tempo inconnu : 120 temps par minute, 0,5 s par chiffre. En mouvement réduit, les chiffres apparaissent sans mise à l’échelle.</p>
    <?= composant($c, 'decompte', ['id' => 'decompte-demo', 'bpm' => null]) ?>
  </section>

  <section class="gabarits__partie" aria-labelledby="scene-titre-partie">
    <h2 class="gabarits__titre" id="scene-titre-partie">Scène de placements</h2>
    <p class="gabarits__note">Positions de démonstration (géométriques), huit points : les neuf cousins sauf Nadine.</p>
    <?= composant($c, 'scene', ['id' => 'scene', 'niveau' => 3, 'sections' => $nomsSections, 'courante' => $courante, 'points' => $placements[$courante], 'suivants' => $placements[$courante + 1] ?? null, 'comptes' => format_comptes($section['comptes']) . ' · ' . format_duree_section($section['debut'], $section['fin']), 'moi' => $moi, 'surligner' => $surligner, 'hrefPage' => $page, 'actionDeplacer' => $essaiAction]) ?>
  </section>
</div>
