<?php

declare(strict_types=1);

use function Chores\e;
use function Chores\url;

/**
 * Page d'erreur (404, 405, 400, 429, 500).
 * @var array<string, mixed> $config
 * @var string $titre
 * @var string $message
 */
?>
<div class="contenu">
  <h1 class="titre-ecran"><?= e($titre) ?></h1>
  <p><?= e($message) ?></p>
  <p><a class="bouton bouton--contour" href="<?= e(url($config, '/')) ?>">Revenir à l’accueil</a></p>
</div>
