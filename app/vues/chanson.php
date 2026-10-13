<?php

declare(strict_types=1);

use function Chores\chanson_pour_lecteur;
use function Chores\composant;
use function Chores\e;
use function Chores\format_temps;
use function Chores\infos_chanson;
use function Chores\non_places;
use function Chores\texte_danseurs;
use function Chores\texte_progression;
use function Chores\url;

use const Chores\TYPES_LIENS;

/**
 * Page d'une chanson (maquette 1e à 1j, 1z, 1s ; bureau 1t) : en-tête, « qui s'en occupe », lecteur, sections et
 * niveaux, ma place, qui maîtrise quoi, tutoriels et références, vidéos des cousins (phase 5).
 * @var Chores\Contexte $c
 * @var array<string, mixed> $config
 * @var array<string, mixed> $chanson
 * @var list<array{cousin_id: int, prenom: string, statut: string}> $distribution
 * @var ?string $maPlace « danse », « en_attente » ou null
 * @var list<array{id: int, ordre: int, nom: string, debut: float, fin: float, comptes: int, note: ?string}> $sections
 * @var int $courante
 * @var array<int, int> $niveaux
 * @var list<array{prenom: string, attente: bool, niveaux: list<int>}> $grille
 * @var list<list<array{cousin_id: int, prenom: string, x: int, y: int}>> $placements
 * @var list<array<string, mixed>> $liens
 * @var ?string $video
 * @var ?string $createur
 * @var int $maitrisees
 * @var int $total
 * @var bool $aPlusieurs
 * @var ?string $confirmation
 * @var list<array<string, mixed>> $videos vidéos des cousins pour cette chanson
 * @var ?array{url: string, taille: int} $reference vidéo de référence à garder sur le téléphone
 */
$slug = (string) $chanson['slug'];
$page = url($config, '/chanson/' . $slug);
$infos = infos_chanson($chanson);
$moi = (string) $c->cousin['prenom'];
$danseurs = array_values(array_filter($distribution, static fn (array $d): bool => $d['statut'] === 'danse'));
$jePeuxNoter = $maPlace !== null;
$aCreer = (int) $chanson['a_creer'] === 1;
$tutoriels = array_values(array_filter($liens, static fn (array $l): bool => $l['type'] === 'tutoriel'));
$references = array_values(array_filter($liens, static fn (array $l): bool => $l['type'] !== 'tutoriel'));
$lien = static fn (array $l): array => [
    'url' => (string) $l['url'],
    'titre' => (string) $l['titre'],
    'duree' => $l['duree_s'] !== null ? format_temps((int) $l['duree_s']) : null,
    'miroir' => $l['en_miroir'] === null ? null : (int) $l['en_miroir'] === 1,
    'type' => $l['type'] === 'reference' && $l['partage_par'] !== null ? 'référence partagée par ' . $l['partage_par'] : TYPES_LIENS[$l['type']],
];
?>
<div class="contenu contenu--large">
  <div class="page-chanson">
    <div class="page-chanson__principal">
      <div>
        <h1 class="titre-chanson"><?= e($chanson['titre']) ?></h1>
        <p class="page-chanson__infos"><?= e($infos['film']) ?> · <?= e($infos['duree']) ?> · <?= e($infos['tempo']) ?> · <?= e(texte_danseurs($distribution)) ?></p>
<?php if ($chanson['note'] !== null): ?>
        <p class="page-chanson__infos"><?= e($chanson['note']) ?></p>
<?php endif; ?>
<?php if ($chanson['statut'] === 'decidee'): ?>
        <p class="page-chanson__decidee">Choré décidée par le groupe.</p>
<?php elseif ($chanson['statut'] === 'ecartee'): ?>
        <p class="page-chanson__infos">Chanson écartée par le groupe.</p>
<?php endif; ?>
      </div>
<?php if ($confirmation !== null): ?>
      <?= composant($c, 'confirmation', ['texte' => $confirmation]) ?>
<?php endif; ?>
<?php if ($aCreer): ?>
      <div id="qui-s-en-occupe">
        <?= composant($c, 'encart', [
            'variante' => 'orange',
            'titre' => 'La choré n’est pas encore posée. Qui s’en occupe ?',
            'prenoms' => $maPlace === 'danse' ? array_column($danseurs, 'prenom') : [],
            'choisi' => $createur,
            'action' => url($config, '/chanson/' . $slug . '/createur'),
            'texte' => $maPlace === 'danse' ? null : ($createur !== null ? $createur . ' s’en occupe.' : 'Personne ne s’en occupe encore.'),
            'note' => $maPlace === 'danse' ? 'Touche le prénom de qui s’en occupe : tout le monde le verra.' : null,
        ]) ?>
      </div>
<?php endif; ?>
<?php if ($maPlace === 'en_attente'): ?>
      <?= composant($c, 'encart', ['variante' => 'attente', 'texte' => 'En attente : tu as demandé à rejoindre cette choré, pas encore de réponse. Tu peux déjà répéter.']) ?>
<?php endif; ?>

      <?= composant($c, 'lecteur', [
          'id' => 'lecteur-chanson',
          'chanson' => chanson_pour_lecteur($chanson),
          'video' => $video,
          'sections' => $sections,
          'courante' => $courante,
          'mode' => 'page',
          'hrefSection' => url($config, '/chanson/' . $slug . '/repeter?section='),
      ]) ?>
<?php if ($reference !== null): ?>
      <?= composant($c, 'garder', ['id' => 'garder-reference', 'url' => $reference['url'], 'taille' => $reference['taille'], 'titre' => (string) $chanson['titre'], 'page' => $page]) ?>
<?php endif; ?>

      <section class="sections" aria-labelledby="sections-titre">
        <h2 class="sections__titre" id="sections-titre">Sections</h2>
<?php if ($sections === []): ?>
        <p class="sections__aide">Les sections ne sont pas encore posées. Patrice peut les créer dans l’administration.</p>
<?php else: ?>
        <p class="sections__aide">Touche une section pour la lancer. On compte 5-6-7-8 pour partir.<?= $jePeuxNoter ? '' : ' Tu ne danses pas sur cette choré : les niveaux sont ceux des danseurs.' ?></p>
<?php if ($jePeuxNoter): ?>
        <p class="sections__progression" data-progression>Toi : <?= e(texte_progression($maitrisees, $total)) ?>.</p>
<?php endif; ?>
        <p class="sections__message" role="status" data-niveau-message></p>
        <ol class="sections__liste" data-sections-de="lecteur-chanson">
<?php foreach ($sections as $i => $s): ?>
          <?= composant($c, 'ligne-section', [
              'index' => $i,
              'section' => $s,
              'courante' => $i === $courante,
              'niveau' => $niveaux[$s['id']] ?? 0,
              'hrefChoisir' => $page . '?section=' . ($i + 1) . '#lecteur-chanson',
              'actionNiveau' => $jePeuxNoter ? url($config, '/chanson/' . $slug . '/section/' . $s['id'] . '/niveau') : null,
          ]) ?>
<?php endforeach; ?>
        </ol>
<?php endif; ?>
      </section>
    </div>

    <div class="page-chanson__cote">
<?php if ($aPlusieurs): ?>
      <section class="page-chanson__partie" aria-labelledby="ma-place-titre">
        <h2 class="sections__titre" id="ma-place-titre">Ma place</h2>
<?php if ($sections === []): ?>
        <p class="sections__aide">Les placements viendront avec les sections.</p>
<?php else: ?>
<?php foreach ($sections as $i => $s): ?>
        <div data-mini-scene="<?= e($i) ?>"<?= $i === $courante ? '' : ' hidden' ?>>
          <?= composant($c, 'mini-scene', [
              'section' => $s['nom'],
              'points' => $placements[$i],
              'moi' => $moi,
              'jeDanse' => $maPlace !== null,
              'nonPlaces' => non_places($distribution, $placements[$i]),
              'hrefPlacements' => url($config, '/placements/' . $slug . '?section=' . ($i + 1) . '#scene'),
          ]) ?>
        </div>
<?php endforeach; ?>
<?php endif; ?>
      </section>
<?php endif; ?>

      <section class="page-chanson__partie" aria-labelledby="grille-titre">
        <h2 class="sections__titre" id="grille-titre">Qui maîtrise quoi</h2>
<?php if ($sections === []): ?>
        <p class="sections__aide">La grille apparaîtra quand les sections seront posées.</p>
<?php else: ?>
        <?= composant($c, 'grille-maitrise', [
            'id' => 'grille-chanson',
            'legende' => 'Qui maîtrise quoi — ' . $chanson['titre'],
            'sections' => array_column($sections, 'nom'),
            'lignes' => $grille,
            'moi' => $moi,
        ]) ?>
<?php endif; ?>
      </section>

      <section class="page-chanson__partie" aria-labelledby="tutoriels-titre">
        <h2 class="sections__titre" id="tutoriels-titre">Tutoriels et références</h2>
<?php if ($liens === []): ?>
        <p class="sections__aide"><?= $aCreer ? 'Pas de tutoriel : c’est vous qui posez la choré. Filmez-la, elle apparaîtra ici.' : 'Pas encore de tutoriel ni de référence pour cette chanson.' ?></p>
<?php endif; ?>
<?php if ($references !== []): ?>
        <div class="page-chanson__liens">
<?php foreach ($references as $l): ?>
          <?= composant($c, 'lien-tutoriel', $lien($l)) ?>
<?php endforeach; ?>
        </div>
<?php endif; ?>
<?php if ($tutoriels !== []): ?>
        <div class="page-chanson__liens">
<?php foreach ($tutoriels as $l): ?>
          <?= composant($c, 'lien-tutoriel', $lien($l)) ?>
<?php endforeach; ?>
        </div>
<?php elseif ($liens !== [] && $aCreer): ?>
        <p class="sections__aide">Pas de tutoriel : c’est vous qui posez la choré. Filmez-la, elle apparaîtra ici.</p>
<?php endif; ?>
      </section>

      <section class="page-chanson__partie" aria-labelledby="videos-titre">
        <h2 class="sections__titre" id="videos-titre">Vidéos des cousins</h2>
<?php if ($videos === []): ?>
        <p class="sections__aide">Personne n’a encore posté pour cette chanson.</p>
<?php endif; ?>
<?php foreach ($videos as $v): ?>
        <?= composant($c, 'carte-video', \Chores\Controleurs\carte_de_video($c, $v)) ?>
<?php endforeach; ?>
        <div class="page-chanson__actions">
          <a class="bouton bouton--plein bouton--56" href="<?= e(url($config, '/videos/ajouter?chanson=' . $slug)) ?>">Ajouter ma vidéo</a>
          <a class="bouton bouton--secondaire" href="<?= e(url($config, '/videos')) ?>">Toutes les vidéos</a>
        </div>
      </section>
    </div>
  </div>
</div>
