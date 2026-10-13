<?php

declare(strict_types=1);

use function Chores\composant;
use function Chores\e;
use function Chores\format_comptes;
use function Chores\format_duree_section;
use function Chores\non_places;
use function Chores\url;

/**
 * Placements (maquette 1m ; bureau 1u ; mode scène) : chanson choisie par chips, une section à la fois avec ses
 * flèches vers la suivante, « Me surligner », déplacement de mon point ; au bureau, toutes les sections côte à côte.
 * @var Chores\Contexte $c
 * @var array<string, mixed> $config
 * @var ?array<string, mixed> $chanson null : aucune choré à plusieurs danseurs
 * @var list<array<string, mixed>> $chips
 * @var list<array{cousin_id: int, prenom: string, statut: string}> $distribution
 * @var ?string $maPlace
 * @var list<array{id: int, nom: string, debut: float, fin: float, comptes: int}> $sections
 * @var int $courante
 * @var list<list<array{cousin_id: int, prenom: string, x: int, y: int}>> $placements
 * @var bool $verrouilles
 * @var bool $scene mode scène (points 68 px, Quitter)
 * @var bool $depuisJourJ mode scène ouvert depuis le Jour J : Quitter y revient
 * @var bool $surligner
 * @var ?string $confirmation
 */
$moi = (string) $c->cousin['prenom'];
$distribution ??= [];
$sections ??= [];
$placements ??= [];
$scene ??= false;
$depuisJourJ ??= false;
$confirmation ??= null;
?>
<div class="contenu contenu--large placements">
<?php if ($chanson === null): ?>
  <h1 class="titre-ecran">Placements</h1>
  <p>Aucune choré à plusieurs pour l’instant : les placements viendront avec elles.</p>
<?php else: ?>
<?php $page = url($config, '/placements/' . $chanson['slug']); ?>
<?php if ($scene): ?>
  <div class="placements__scene-tete">
    <p class="placements__mode">Mode scène</p>
    <a class="bouton bouton--contour" href="<?= e($depuisJourJ ? url($config, '/jour-j') : $page . '?section=' . ($courante + 1) . '#scene') ?>">Quitter <span class="visuellement-cache">le mode scène</span></a>
  </div>
<?php endif; ?>
  <div>
    <h1 class="titre-chanson"><?= e($chanson['titre']) ?><span class="visuellement-cache"> : placements</span></h1>
    <p class="page-chanson__infos">La scène vue de dessus, le public en bas.</p>
  </div>
<?php if ($confirmation !== null): ?>
  <?= composant($c, 'confirmation', ['texte' => $confirmation]) ?>
<?php endif; ?>
<?php if (!$scene): ?>
  <nav class="placements__chips" aria-label="Choisir la chanson">
<?php foreach ($chips as $chip): ?>
    <a class="placements__chip" href="<?= e(url($config, '/placements/' . $chip['slug'])) ?>"<?= $chip['slug'] === $chanson['slug'] ? ' aria-current="page"' : '' ?>><?= e($chip['titre']) ?><?= $chip['slug'] === $chanson['slug'] ? '<span class="visuellement-cache"> (affichée)</span>' : '' ?></a>
<?php endforeach; ?>
  </nav>
<?php endif; ?>
<?php if ($sections === []): ?>
  <p>Les sections ne sont pas encore posées : les placements viendront avec elles.</p>
<?php else: ?>
<?php
    $s = $sections[$courante];
    $suivante = $sections[$courante + 1] ?? null;
?>
  <?= composant($c, 'scene', [
      'id' => 'scene',
      'niveau' => 2,
      'sections' => array_column($sections, 'nom'),
      'courante' => $courante,
      'points' => $placements[$courante],
      'suivants' => $suivante !== null ? $placements[$courante + 1] : null,
      'comptes' => format_comptes($s['comptes']) . ' · ' . format_duree_section($s['debut'], $s['fin']),
      'moi' => $moi,
      'surligner' => $surligner,
      'grand' => $scene,
      'hrefPage' => $page,
      'suite' => $scene ? '&scene=1' . ($depuisJourJ ? '&depuis=jour-j' : '') : '',
      'actionDeplacer' => $verrouilles ? null : url($config, '/placements/' . $s['id'] . '/moi'),
      'jeDanse' => $maPlace !== null,
      'nonPlaces' => non_places($distribution, $placements[$courante]),
  ]) ?>
<?php if (!$scene): ?>
  <a class="bouton bouton--plein bouton--56 placements__mode-scene" href="<?= e($page . '?section=' . ($courante + 1) . '&scene=1#scene') ?>">Mode scène <span class="placements__mode-detail">placements en grand</span></a>
<?php endif; ?>
<?php if (count($sections) > 1): ?>
  <section class="placements__toutes" aria-labelledby="toutes-sections">
    <h2 class="sections__titre" id="toutes-sections">Toutes les sections</h2>
    <div class="placements__grille">
<?php foreach ($sections as $i => $section): ?>
      <?= composant($c, 'mini-scene', [
          'titre' => ($i + 1) . '. ' . $section['nom'],
          'section' => $section['nom'],
          'points' => $placements[$i],
          'moi' => $moi,
          'jeDanse' => $maPlace !== null,
          'nonPlaces' => non_places($distribution, $placements[$i]),
      ]) ?>
<?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
</div>
