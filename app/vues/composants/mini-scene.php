<?php

declare(strict_types=1);

use function Chores\e;
use function Chores\pas_de_cinq;
use function Chores\position_en_mots;
use function Chores\prenom_abrege;

/**
 * Mini-scène « Ma place » (maquette 1e) : la section en cours, mon point en or, les autres en grenat, et la phrase
 * qui dit ma place en mots (la seule information utile pour un lecteur d'écran).
 * @var string $section nom de la section en cours
 * @var list<array{prenom: string, x: int, y: int}> $points
 * @var string $moi
 * @var ?string $hrefPlacements null : pas de lien (page des placements elle-même)
 * @var bool $jeDanse le cousin fait partie de la choré (sinon : « Tu ne danses pas sur celle-ci. »)
 * @var list<string> $nonPlaces prénoms sans point sur cette section
 * @var ?string $titre titre de la mini-scène (vue d'ensemble des placements)
 * @var int $niveauTitre niveau du titre (h3 par défaut)
 */
$hrefPlacements ??= null;
$jeDanse ??= true;
$nonPlaces ??= [];
$titre ??= null;
$niveauTitre ??= 3;
$mien = null;
foreach ($points as $p) {
    if ($p['prenom'] === $moi) {
        $mien = $p;
    }
}
if ($points === []) {
    $phrase = 'Les placements de cette section ne sont pas encore posés.';
} elseif ($moi === '') {
    // Aperçu de l'administration : pas de « tu ».
    $phrase = '';
} elseif ($mien !== null) {
    $phrase = 'Tu es ' . position_en_mots($mien['x'], $mien['y']) . '.';
} elseif ($jeDanse) {
    $phrase = 'Ta place n’est pas encore posée sur celle-ci.';
} else {
    $phrase = 'Tu ne danses pas sur celle-ci.';
}
?>
<div class="mini-scene">
<?php if ($titre !== null): ?>
  <h<?= (int) $niveauTitre ?> class="mini-scene__titre"><?= e($titre) ?></h<?= (int) $niveauTitre ?>>
<?php endif; ?>
<?php $texte = trim(($titre === null ? 'Section en cours : ' . $section . '.' : '') . ' ' . $phrase); ?>
<?php if ($texte !== ''): ?>
  <p class="mini-scene__texte"><?= e($texte) ?></p>
<?php endif; ?>
<?php if ($points !== []): ?>
  <div class="mini-scene__plateau" aria-hidden="true">
<?php foreach ($points as $p): ?>
    <span class="scene__ancre col-<?= e(pas_de_cinq($p['x'])) ?> lig-<?= e(pas_de_cinq($p['y'])) ?>"><span class="mini-scene__point<?= $p['prenom'] === $moi ? ' mini-scene__point--moi' : '' ?>"><?= e(prenom_abrege($p['prenom'])) ?></span></span>
<?php endforeach; ?>
    <p class="scene__public">Public</p>
  </div>
<?php endif; ?>
<?php if ($points !== [] && $nonPlaces !== []): ?>
  <p class="mini-scene__texte">Non placé<?= count($nonPlaces) > 1 ? 's' : '' ?> : <?= e(implode(', ', $nonPlaces)) ?>.</p>
<?php endif; ?>
<?php if ($hrefPlacements !== null): ?>
  <a class="bouton bouton--contour mini-scene__lien" href="<?= e($hrefPlacements) ?>">Voir tous les placements</a>
<?php endif; ?>
</div>
