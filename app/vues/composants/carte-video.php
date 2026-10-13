<?php

declare(strict_types=1);

use function Chores\composant;
use function Chores\de;
use function Chores\e;

/**
 * Carte de vidéo d'un cousin (maquette 1p, planche 1v) : vignette en aplat grenat (titre de la chanson et durée,
 * jamais de photo), « auteur · titre », date, note, commentaires horodatés.
 * @var Chores\Contexte $c
 * @var string $chanson titre de la chanson
 * @var ?string $duree « 0:48 »
 * @var string $auteur
 * @var string $titre
 * @var string $date
 * @var ?string $note
 * @var ?string $format « format iPhone » si la vidéo est peut-être en HEVC
 * @var list<array{temps: ?float, auteur: string, texte: string}> $commentaires
 * @var string $href page de la vidéo
 * @var int $niveau niveau du titre
 */
$duree ??= null;
$note ??= null;
$format ??= null;
$commentaires ??= [];
$niveau ??= 3;
?>
<article class="carte-video">
  <a class="carte-video__vignette" href="<?= e($href) ?>">
    <span class="carte-video__chanson"><?= e($chanson) ?></span>
    <span class="carte-video__duree"><?= e($duree ?? 'durée inconnue') ?></span>
    <span class="visuellement-cache">: voir la vidéo <?= e(de($auteur)) ?>, « <?= e($titre) ?> »</span>
  </a>
  <div class="carte-video__corps">
    <div class="carte-video__tete">
      <h<?= (int) $niveau ?> class="carte-video__titre"><?= e($auteur) ?> · <?= e($titre) ?></h<?= (int) $niveau ?>>
      <p class="carte-video__date"><?= e($date) ?></p>
    </div>
<?php if ($format !== null): ?>
    <p class="carte-video__format"><?= e($format) ?></p>
<?php endif; ?>
<?php if ($note !== null): ?>
    <p class="carte-video__note"><?= e($note) ?></p>
<?php endif; ?>
<?php if ($commentaires === []): ?>
    <p class="carte-video__sans-commentaire">Pas encore de commentaire.</p>
<?php endif; ?>
<?php foreach ($commentaires as $commentaire): ?>
    <?= composant($c, 'commentaire', $commentaire + ['href' => $href]) ?>
<?php endforeach; ?>
  </div>
</article>
