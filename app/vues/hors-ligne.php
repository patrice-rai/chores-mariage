<?php

declare(strict_types=1);

use function Chores\e;
use function Chores\url;

/**
 * Hors ligne (spec § 2 et 8) : servie par le service worker quand la page demandée n'est pas gardée. Aucune donnée
 * du groupe : la liste des vidéos gardées sur ce téléphone est lue dans le cache par public/js/pwa.js.
 * @var array<string, mixed> $config
 */
?>
<div class="contenu hors-ligne">
  <h1 class="titre-ecran">Pas de réseau</h1>
  <p>Cette page n’est pas gardée sur ton téléphone. Les pages que tu as déjà ouvertes restent lisibles, et les vidéos gardées se lisent sans réseau.</p>
  <section class="hors-ligne__gardees" aria-labelledby="gardees-titre">
    <h2 class="ma-page__titre" id="gardees-titre">Gardées sur ton téléphone</h2>
    <p data-gardees-vide>Rien n’est gardé sur ce téléphone pour l’instant. Avec du réseau, touche « Garder sur mon téléphone » sous une vidéo.</p>
    <ul class="hors-ligne__liste" data-liste-gardees hidden></ul>
  </section>
  <p><a class="bouton bouton--plein" href="<?= e(url($config, '/moi')) ?>">Réessayer Ma page</a></p>
</div>
