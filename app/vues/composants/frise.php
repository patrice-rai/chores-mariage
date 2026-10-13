<?php

declare(strict_types=1);

use function Chores\e;

/**
 * Frise du Jour J (maquette 1q) : ordre de passage numéroté, « Qui entre / D'où / Musique » (« [à compléter] »
 * quand rien n'est fixé). Liste vide : « L'ordre de passage n'est pas encore fixé ».
 * @var list<array{titre: string, duree: string, qui: ?string, dou: ?string, musique: ?string}> $elements
 * @var int $niveau niveau des titres
 */
$niveau ??= 3;
$ou = static fn (?string $v): string => $v === null || $v === '' ? '[à compléter]' : $v;
?>
<?php if ($elements === []): ?>
<p class="frise__vide">L’ordre de passage n’est pas encore fixé.</p>
<?php else: ?>
<ol class="frise">
<?php foreach ($elements as $i => $element): ?>
  <li class="frise__element">
    <span class="frise__numero" aria-hidden="true"><?= e($i + 1) ?></span>
    <div class="frise__corps">
      <h<?= (int) $niveau ?> class="frise__titre"><span class="visuellement-cache">Numéro <?= e($i + 1) ?> : </span><?= e($element['titre']) ?> <span class="frise__duree"><?= e($element['duree']) ?></span></h<?= (int) $niveau ?>>
      <p class="frise__ligne"><strong>Qui entre :</strong> <?= e($ou($element['qui'])) ?></p>
      <p class="frise__ligne"><strong>D’où :</strong> <?= e($ou($element['dou'])) ?></p>
      <p class="frise__ligne"><strong>Musique :</strong> <?= e($ou($element['musique'])) ?></p>
    </div>
  </li>
<?php endforeach; ?>
</ol>
<?php endif; ?>
