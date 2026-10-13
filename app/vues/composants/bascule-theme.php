<?php

declare(strict_types=1);

use function Chores\champ_csrf;
use function Chores\e;
use function Chores\url;

use const Chores\CHAMP_CSRF;

/**
 * Bascule « Mode sombre » (spec § 8) : fonctionne sans JavaScript (formulaire, cookie cm_theme, retour à la page).
 * Deux boutons sont rendus, un seul est visible selon le thème effectif (CSS : choix enregistré, sinon système) ;
 * le libellé reste « Mode sombre », l'état est écrit (« activé / désactivé ») et dit par aria-pressed.
 * site.js bascule sans rechargement et enregistre le choix par fetch.
 * @var Chores\Contexte $c
 * @var array<string, mixed> $config
 */
?>
<form class="bascule-theme" method="post" action="<?= e(url($config, '/theme')) ?>" data-bascule-theme>
  <input type="hidden" name="<?= e(CHAMP_CSRF) ?>" value="<?= e(champ_csrf($c)) ?>">
  <input type="hidden" name="retour" value="<?= e($c->ici()) ?>">
  <button class="bascule-theme__bouton bascule-theme__bouton--vers-sombre" type="submit" name="theme" value="sombre" aria-pressed="false">Mode sombre <span class="bascule-theme__etat">désactivé</span></button>
  <button class="bascule-theme__bouton bascule-theme__bouton--vers-clair" type="submit" name="theme" value="clair" aria-pressed="true">Mode sombre <span class="bascule-theme__etat">activé</span></button>
</form>
