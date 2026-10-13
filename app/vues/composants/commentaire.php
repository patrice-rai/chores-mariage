<?php

declare(strict_types=1);

use function Chores\de;
use function Chores\e;
use function Chores\format_temps;

/**
 * Commentaire horodaté (maquette 1p) : « à 0:42 » est un lien vers la vidéo à cet instant ; avec JavaScript, il
 * positionne directement le lecteur de la page (data-temps). Commentaire général : pas d'horodatage.
 * @var ?float $temps secondes, null : commentaire général
 * @var string $auteur
 * @var string $texte
 * @var string $href adresse de la vidéo à cet instant
 * @var ?string $id identifiant (ancre)
 * @var ?string $hrefSupprimer lien vers la confirmation de suppression (auteur du commentaire ou Patrice)
 */
$temps ??= null;
$id ??= null;
$hrefSupprimer ??= null;
?>
<p class="commentaire"<?= $id !== null ? ' id="' . e($id) . '"' : '' ?>>
<?php if ($temps !== null): ?>
  <a class="commentaire__temps" href="<?= e($href) ?>" data-temps="<?= e(round($temps, 1)) ?>">à <?= e(format_temps($temps)) ?><span class="visuellement-cache"> (aller à cet instant)</span></a>
<?php endif; ?>
  <span class="commentaire__texte"><strong><?= e($auteur) ?> :</strong> <?= e($texte) ?></span>
<?php if ($hrefSupprimer !== null): ?>
  <a class="commentaire__supprimer" href="<?= e($hrefSupprimer) ?>">Supprimer<span class="visuellement-cache"> le commentaire <?= e(de($auteur)) ?></span></a>
<?php endif; ?>
</p>
