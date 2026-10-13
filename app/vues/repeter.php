<?php

declare(strict_types=1);

use function Chores\composant;
use function Chores\e;

/**
 * Mode répétition (maquette 1k, 1l ; spec § 4) : plein écran, toujours sombre (thème forcé par le contrôleur),
 * vidéo en haut, section et comptes en surimpression, grandes commandes, Quitter vers la page de la chanson.
 * @var Chores\Contexte $c
 * @var array{cle: string, titre: string, duree_s: ?int, bpm: ?int} $chanson
 * @var list<array{nom: string, debut: float, fin: float, comptes: int}> $sections
 * @var int $courante
 * @var ?string $video
 * @var string $hrefQuitter
 * @var string $hrefSection
 */
?>
<div class="repetition">
  <h1 class="visuellement-cache">Mode répétition : <?= e($chanson['titre']) ?></h1>
  <?= composant($c, 'lecteur', ['id' => 'lecteur-repetition', 'chanson' => $chanson, 'video' => $video, 'sections' => $sections, 'courante' => $courante, 'mode' => 'repetition', 'hrefSection' => $hrefSection, 'hrefQuitter' => $hrefQuitter]) ?>
</div>
