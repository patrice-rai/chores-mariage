<?php

declare(strict_types=1);

use function Chores\e;

/**
 * Décompte 5-6-7-8 autonome (planche 1v) : un chiffre par temps au tempo, texte « Décompte : n » toujours écrit,
 * annonce polie pour les lecteurs d'écran, bouton « Rejouer » (decompte.js, lancé par site.js). Sans JavaScript,
 * le chiffre 8 et son texte restent affichés.
 * @var string $id
 * @var ?int $bpm null : tempo inconnu, 120 par défaut et « tempo à régler »
 * @var bool $grand 260 px (mode répétition) au lieu de 120 px
 */
$bpm ??= null;
$grand ??= false;
?>
<div class="decompte<?= $grand ? ' decompte--grand' : '' ?>" data-theme="sombre" data-decompte data-bpm="<?= e($bpm ?? 120) ?>">
  <p class="decompte__chiffre" aria-hidden="true" data-decompte-chiffre>8</p>
  <p class="decompte__texte" data-decompte-texte>Décompte : 8</p>
  <p class="visuellement-cache" aria-live="polite" data-decompte-annonce></p>
  <p class="decompte__tempo"><?= $bpm === null ? 'Tempo à régler : on compte à 120 temps par minute.' : 'Tempo : ' . e($bpm) . ' temps par minute.' ?></p>
  <button class="bouton bouton--or decompte__rejouer" type="button" data-decompte-rejouer hidden>Rejouer</button>
</div>
