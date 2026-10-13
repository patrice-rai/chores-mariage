<?php

declare(strict_types=1);

use function Chores\champ_csrf;
use function Chores\composant;
use function Chores\e;
use function Chores\url;

use const Chores\CHAMP_CSRF;

/**
 * Barre haute (maquette : Retour ou Changer, titre, bascule Mode sombre).
 * @var Chores\Contexte $c
 * @var array<string, mixed> $config
 * @var string $titre
 * @var ?string $action « changer » (formulaire Changer, si un prénom est choisi), « moi » (lien Ma page),
 *                      « retour » (lien Retour vers $retour) ou null
 * @var ?string $retour adresse complète du lien Retour
 * @var bool $bascule
 */
$action ??= null;
$retour ??= null;
$bascule ??= true;
?>
<header class="barre-haute">
<?php if ($action === 'moi'): ?>
  <a class="bouton bouton--contour barre-haute__action" href="<?= e(url($config, '/moi')) ?>">Ma page</a>
<?php elseif ($action === 'retour' && $retour !== null): ?>
  <a class="bouton bouton--contour barre-haute__action" href="<?= e($retour) ?>"><span aria-hidden="true">←</span> Retour</a>
<?php elseif ($action === 'changer' && $c->cousin !== null): ?>
  <form class="barre-haute__formulaire" method="post" action="<?= e(url($config, '/changer')) ?>">
    <input type="hidden" name="<?= e(CHAMP_CSRF) ?>" value="<?= e(champ_csrf($c)) ?>">
    <button class="bouton bouton--contour barre-haute__action" type="submit">Changer <span class="visuellement-cache">de prénom (tu es <?= e($c->cousin['prenom']) ?>)</span></button>
  </form>
<?php endif; ?>
  <p class="barre-haute__titre"><?= e($titre) ?></p>
<?php if ($bascule): ?>
  <?= composant($c, 'bascule-theme') ?>
<?php endif; ?>
</header>
