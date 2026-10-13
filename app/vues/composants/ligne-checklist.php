<?php

declare(strict_types=1);

use function Chores\champ_csrf;
use function Chores\e;

use const Chores\CHAMP_CSRF;

/**
 * Ligne de la check-list du Jour J (maquette 1q) : un bouton qui envoie l'état voulu (« fait » ou « à faire »,
 * jamais une bascule : deux cousins qui touchent en même temps ne s'annulent pas), état écrit en 14 px, et qui l'a
 * fait.
 * @var Chores\Contexte $c
 * @var string $libelle
 * @var bool $fait
 * @var ?string $par prénom de qui l'a coché
 * @var string $action
 * @var ?string $id identifiant de la ligne (ancre de retour)
 */
$par ??= null;
$id ??= null;
?>
<form class="ligne-checklist<?= $fait ? ' ligne-checklist--fait' : '' ?>" method="post" action="<?= e($action) ?>"<?= $id !== null ? ' id="' . e($id) . '"' : '' ?>>
  <input type="hidden" name="<?= e(CHAMP_CSRF) ?>" value="<?= e(champ_csrf($c)) ?>">
  <input type="hidden" name="fait" value="<?= $fait ? '0' : '1' ?>">
  <button class="ligne-checklist__bouton" type="submit" aria-pressed="<?= $fait ? 'true' : 'false' ?>">
    <span class="ligne-checklist__case" aria-hidden="true"><?= $fait ? '✓' : '' ?></span>
    <span class="ligne-checklist__libelle"><?= e($libelle) ?></span>
    <span class="ligne-checklist__etat"><?= $fait ? 'fait' . ($par !== null ? ' par ' . e($par) : '') : 'à faire' ?></span>
  </button>
</form>
