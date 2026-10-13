<?php

declare(strict_types=1);

use function Chores\composant;
use function Chores\e;
use function Chores\url;

/**
 * Vidéos des cousins (maquette 1p) : « Ajouter ma vidéo », « Ces vidéos restent entre nous. », un album par chanson
 * (ordre de passage puis titre), chaque vidéo avec ses commentaires horodatés.
 * @var Chores\Contexte $c
 * @var array<string, mixed> $config
 * @var list<array{titre: string, videos: list<array<string, mixed>>}> $albums
 * @var ?string $confirmation
 */
?>
<div class="contenu videos">
  <h1 class="titre-ecran">Vidéos des cousins</h1>
<?php if ($confirmation !== null): ?>
  <?= composant($c, 'confirmation', ['texte' => $confirmation]) ?>
<?php endif; ?>
  <a class="bouton bouton--plein bouton--56 videos__ajouter" href="<?= e(url($config, '/videos/ajouter')) ?>">Ajouter ma vidéo</a>
  <p class="videos__aide">Ces vidéos restent entre nous : elles ne quittent jamais le site.</p>
<?php if ($albums === []): ?>
  <p class="encart encart--pointille">Personne n’a encore posté de vidéo. Filme une section ou deux et sois la première ou le premier.</p>
<?php endif; ?>
<?php foreach ($albums as $i => $album): ?>
  <section class="videos__album" aria-labelledby="album-<?= e($i) ?>">
    <h2 class="videos__titre-album" id="album-<?= e($i) ?>"><?= e($album['titre']) ?> <span class="videos__nombre"><?= e(count($album['videos'])) ?> vidéo<?= count($album['videos']) > 1 ? 's' : '' ?></span></h2>
    <div class="videos__liste">
<?php foreach ($album['videos'] as $carte): ?>
      <?= composant($c, 'carte-video', $carte) ?>
<?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
</div>
