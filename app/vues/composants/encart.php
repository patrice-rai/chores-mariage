<?php

declare(strict_types=1);

use function Chores\champ_csrf;
use function Chores\e;

use const Chores\CHAMP_CSRF;

/**
 * Encart (maquette 1b, 1g, 1i) en trois variantes :
 * - « pointille » : cadre pointillé (chansons proposées, pas tranchées) ;
 * - « orange » : « La choré n'est pas encore posée. Qui s'en occupe ? » avec un bouton par danseur (POST,
 *   aria-pressed, le choisi devient « {prénom} s'en occupe ») ;
 * - « attente » : « En attente : … ».
 * @var Chores\Contexte $c
 * @var string $variante
 * @var ?string $titre
 * @var ?string $texte
 * @var ?string $note texte secondaire
 * @var list<string> $prenoms (orange)
 * @var ?string $choisi (orange)
 * @var ?string $action adresse du formulaire (orange)
 */
$titre ??= null;
$texte ??= null;
$note ??= null;
$prenoms ??= [];
$choisi ??= null;
$action ??= null;
?>
<div class="encart encart--<?= e($variante) ?>"<?= $variante === 'orange' ? ' role="note"' : '' ?>>
<?php if ($titre !== null): ?>
  <p class="encart__titre"><?= e($titre) ?></p>
<?php endif; ?>
<?php if ($texte !== null): ?>
  <p class="encart__texte"><?= e($texte) ?></p>
<?php endif; ?>
<?php if ($variante === 'orange' && $action !== null && $prenoms !== []): ?>
  <form class="encart__formulaire" method="post" action="<?= e($action) ?>">
    <input type="hidden" name="<?= e(CHAMP_CSRF) ?>" value="<?= e(champ_csrf($c)) ?>">
    <fieldset class="encart__groupe">
      <legend class="visuellement-cache">Qui s’en occupe ?</legend>
<?php foreach ($prenoms as $prenom): ?>
      <button class="encart__prenom" type="submit" name="createur" value="<?= e($prenom) ?>" aria-pressed="<?= $prenom === $choisi ? 'true' : 'false' ?>"><?= e($prenom) ?><?= $prenom === $choisi ? ' s’en occupe' : '' ?></button>
<?php endforeach; ?>
    </fieldset>
  </form>
<?php if ($choisi === null): ?>
  <p class="encart__note">Personne ne s’en occupe encore.</p>
<?php endif; ?>
<?php endif; ?>
<?php if ($note !== null): ?>
  <p class="encart__note"><?= e($note) ?></p>
<?php endif; ?>
</div>
