<?php

declare(strict_types=1);

use function Chores\e;
use function Chores\pas_de_cinq;

/**
 * Carte de chanson (maquette 1b, planche 1v) : titre, durée, film, danseurs, état (à créer, décidée, en attente),
 * progression (barre or par classes de 5 %, aucun attribut style), « Répéter ».
 * @var string $titre
 * @var string $duree « 4 min 04 » ou « durée inconnue »
 * @var string $film
 * @var string $danseurs « tout le groupe (8) », « solo », prénoms…
 * @var ?string $createurs non null : « Choré à créer — {createurs} »
 * @var ?string $decidee non null : texte vert (« La seule choré décidée par le groupe »)
 * @var ?string $attente non null : encart « En attente — … » (pas de progression)
 * @var int $maitrisees
 * @var int $total nombre de sections (0 : sections pas encore posées)
 * @var string $href page de la chanson
 * @var int $niveau niveau du titre (3 par défaut, sous le h2 « Tes chorés »)
 */
$niveau ??= 3;
$createurs ??= null;
$decidee ??= null;
$attente ??= null;
$avecProgression = $attente === null && $total > 0;
$pourcentage = $total > 0 ? 100 * $maitrisees / $total : 0;
if ($maitrisees === 0) {
    $progression = 'rien de marqué encore';
} else {
    $progression = sprintf('%d section%s sur %d maîtrisée%s', $maitrisees, $maitrisees > 1 ? 's' : '', $total, $maitrisees > 1 ? 's' : '');
}
?>
<article class="carte-chanson">
  <div class="carte-chanson__tete">
    <h<?= (int) $niveau ?> class="carte-chanson__titre"><?= e($titre) ?></h<?= (int) $niveau ?>>
    <p class="carte-chanson__duree"><?= e($duree) ?></p>
  </div>
  <p class="carte-chanson__infos"><?= e($film) ?> · <?= e($danseurs) ?></p>
<?php if ($createurs !== null): ?>
  <p class="carte-chanson__a-creer">Choré à créer — <?= e($createurs) ?></p>
<?php endif; ?>
<?php if ($decidee !== null): ?>
  <p class="carte-chanson__decidee"><?= e($decidee) ?></p>
<?php endif; ?>
<?php if ($attente !== null): ?>
  <p class="carte-chanson__attente">En attente — <?= e($attente) ?></p>
<?php endif; ?>
<?php if ($avecProgression): ?>
  <div class="progression">
    <div class="progression__piste" aria-hidden="true"><div class="progression__barre p-<?= e(pas_de_cinq($pourcentage)) ?>"></div></div>
    <p class="progression__texte"><?= e($progression) ?></p>
  </div>
<?php elseif ($attente === null): ?>
  <p class="carte-chanson__infos">Les sections ne sont pas encore posées.</p>
<?php endif; ?>
  <a class="bouton bouton--plein carte-chanson__action" href="<?= e($href) ?>">Répéter <span class="visuellement-cache"><?= e($titre) ?></span></a>
</article>
