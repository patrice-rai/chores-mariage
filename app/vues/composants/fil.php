<?php

declare(strict_types=1);

use function Chores\e;

/**
 * Fil « Ce qui bouge » (maquette 1b) : chaque élément est un lien, avec une vignette en aplat grenat (jamais de
 * photo) qui porte le titre de la chanson et un temps, en 14 px.
 * @var list<array{vignette: string, titre: string, detail: string, href: string}> $elements
 * @var string $vide texte quand il n'y a rien
 */
$vide ??= 'Rien de neuf pour l’instant.';
?>
<?php if ($elements === []): ?>
<p class="fil__vide"><?= e($vide) ?></p>
<?php else: ?>
<ul class="fil">
<?php foreach ($elements as $element): ?>
  <li class="fil__element">
    <a class="fil__lien" href="<?= e($element['href']) ?>">
      <span class="fil__vignette" aria-hidden="true"><?= e($element['vignette']) ?></span>
      <span class="fil__textes">
        <span class="fil__titre"><?= e($element['titre']) ?></span>
        <span class="fil__detail"><?= e($element['detail']) ?></span>
      </span>
    </a>
  </li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
