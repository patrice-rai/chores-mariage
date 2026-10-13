<?php

declare(strict_types=1);

use function Chores\e;
use function Chores\lien_sortant_valide;

/**
 * Lien vers un tutoriel ou une vidéo de référence sur YouTube : lien sortant seulement (jamais intégré),
 * rel="noopener noreferrer", titre réel (docs/recherches/), durée et « en miroir » quand on les connaît.
 * @var string $url
 * @var string $titre
 * @var ?string $duree « 9:41 »
 * @var ?bool $miroir null : inconnu
 * @var ?string $type « tutoriel », « référence partagée dans le groupe »…
 */
$duree ??= null;
$miroir ??= null;
$type ??= null;
$infos = array_values(array_filter([$type, $duree, $miroir === true ? 'en miroir' : null]));
// Une adresse qui n'est pas en https:// n'est jamais un lien (ni javascript:, ni data:).
if (!lien_sortant_valide($url)) {
    $url = null;
}
?>
<?php if ($url === null): ?>
<p class="lien-tutoriel lien-tutoriel--sans-lien"><span class="lien-tutoriel__textes"><span class="lien-tutoriel__titre"><?= e($titre) ?></span><span class="lien-tutoriel__infos">adresse à vérifier par Patrice</span></span></p>
<?php else: ?>
<a class="lien-tutoriel" href="<?= e($url) ?>" rel="noopener noreferrer">
  <span class="lien-tutoriel__textes">
    <span class="lien-tutoriel__titre"><?= e($titre) ?></span>
<?php if ($infos !== []): ?>
    <span class="lien-tutoriel__infos"><?= e(implode(' · ', $infos)) ?></span>
<?php endif; ?>
  </span>
  <span class="lien-tutoriel__cible">YouTube <span aria-hidden="true">↗</span><span class="visuellement-cache">(nouveau site)</span></span>
</a>
<?php endif; ?>
