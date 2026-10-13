<?php

declare(strict_types=1);

use function Chores\e;

/**
 * Encart « Nouvelle version : recharge » (spec § 8), montré en bas de page par le service worker (phase 5) quand
 * une nouvelle version du site est prête. Le lien recharge la page, avec ou sans JavaScript.
 * @var string $href adresse de la page courante
 */
?>
<div class="nouvelle-version" role="status">
  <p class="nouvelle-version__texte">Nouvelle version du site : recharge la page pour l’avoir.</p>
  <a class="bouton bouton--or nouvelle-version__action" href="<?= e($href) ?>">Recharger</a>
</div>
