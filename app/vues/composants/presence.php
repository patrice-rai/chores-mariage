<?php

declare(strict_types=1);

use function Chores\champ_csrf;
use function Chores\e;

use const Chores\CHAMP_CSRF;

/**
 * Bouton de présence « Je viens / Je ne peux pas » (POST, aria-pressed, état écrit : « ✓ » et « Ta réponse : … »).
 * Une réponse « peut-être » venue du sondage WhatsApp est dite en toutes lettres.
 * @var Chores\Contexte $c
 * @var string $action adresse du formulaire
 * @var ?string $reponse « oui », « non », « peut_etre » ou null
 * @var string $sujet ce dont on parle, pour les lecteurs d'écran (« la répétition du [date] »)
 * @var string $variante « grenat » (dans la carte répétition) ou « carte »
 * @var ?string $retour « moi » : revenir sur Ma page après la réponse
 */
$reponse ??= null;
$variante ??= 'carte';
$retour ??= null;
$choix = Chores\REPONSES_PRESENCE;
$etat = match ($reponse) {
    'oui' => 'Ta réponse : je viens.',
    'non' => 'Ta réponse : je ne peux pas.',
    'peut_etre' => 'Tu as voté « dispo » dans le sondage WhatsApp : confirme ici.',
    default => 'Tu n’as pas encore répondu.',
};
?>
<form class="presence presence--<?= e($variante) ?>" method="post" action="<?= e($action) ?>">
  <input type="hidden" name="<?= e(CHAMP_CSRF) ?>" value="<?= e(champ_csrf($c)) ?>">
<?php if ($retour !== null): ?>
  <input type="hidden" name="retour" value="<?= e($retour) ?>">
<?php endif; ?>
  <fieldset class="presence__groupe">
    <legend class="visuellement-cache">Ta réponse pour <?= e($sujet) ?></legend>
<?php foreach ($choix as $valeur => $libelle): ?>
    <button class="presence__bouton" type="submit" name="reponse" value="<?= e($valeur) ?>" aria-pressed="<?= $reponse === $valeur ? 'true' : 'false' ?>"><?= e($libelle) ?><?php if ($reponse === $valeur): ?> <span aria-hidden="true">✓</span><?php endif; ?></button>
<?php endforeach; ?>
  </fieldset>
  <p class="presence__etat"><?= e($etat) ?></p>
</form>
