<?php

declare(strict_types=1);

use function Chores\composant;
use function Chores\duree_jour_j;
use function Chores\e;
use function Chores\url;

/**
 * Jour J (maquette 1q) : date et lieu du mariage (réglages, crochets s'ils manquent), « Mode scène — placements en
 * grand », frise de l'ordre de passage (ou « pas encore fixé »), check-list partagée.
 * @var Chores\Contexte $c
 * @var array<string, mixed> $config
 * @var string $date
 * @var string $lieu
 * @var ?string $dureeDanses
 * @var list<array<string, mixed>> $ordre
 * @var ?string $scene adresse du mode scène
 * @var list<array{id: int, libelle: string, fait: bool, par: ?string}> $checklist
 * @var ?string $confirmation
 */
?>
<div class="contenu jour-j">
  <h1 class="titre-ecran">Jour J</h1>
  <p class="jour-j__date">Mariage le <?= e($date) ?> · <?= e($lieu) ?><?= $dureeDanses !== null ? ' · ' . e($dureeDanses) . ' minutes pour les danses' : '' ?>.</p>
<?php if ($confirmation !== null): ?>
  <?= composant($c, 'confirmation', ['texte' => $confirmation]) ?>
<?php endif; ?>
<?php if ($scene !== null): ?>
  <a class="bouton bouton--or bouton--56 jour-j__scene" href="<?= e($scene) ?>">Mode scène — placements en grand</a>
<?php else: ?>
  <p class="encart encart--pointille">Pas encore de choré à plusieurs : le mode scène viendra avec elles.</p>
<?php endif; ?>

  <section class="jour-j__partie" aria-labelledby="ordre-titre">
    <h2 class="ma-page__titre" id="ordre-titre">Ordre de passage</h2>
    <?= composant($c, 'frise', ['elements' => array_map(static fn (array $ch): array => [
        'titre' => (string) $ch['titre'],
        'duree' => duree_jour_j($ch),
        'qui' => $ch['entree_qui'],
        'dou' => $ch['entree_dou'],
        'musique' => $ch['musique_qui'],
    ], $ordre)]) ?>
<?php if ($ordre === []): ?>
    <p class="jour-j__aide">Patrice le fixera quand le groupe aura décidé.</p>
<?php endif; ?>
  </section>

  <section class="jour-j__partie" aria-labelledby="checklist-titre">
    <h2 class="ma-page__titre" id="checklist-titre">Check-list</h2>
<?php if ($checklist === []): ?>
    <p>La check-list est vide : Patrice peut y ajouter des lignes.</p>
<?php else: ?>
    <p class="jour-j__aide">Touche une ligne pour la marquer « fait » (ou de nouveau « à faire ») : tout le monde le voit.</p>
<?php endif; ?>
<?php foreach ($checklist as $ligne): ?>
    <?= composant($c, 'ligne-checklist', [
        'id' => 'check-' . $ligne['id'],
        'libelle' => $ligne['libelle'],
        'fait' => $ligne['fait'],
        'par' => $ligne['par'],
        'action' => url($config, '/jour-j/check/' . $ligne['id']),
    ]) ?>
<?php endforeach; ?>
  </section>
</div>
