<?php

declare(strict_types=1);

use function Chores\e;

/**
 * Résumé des erreurs en haut d'un formulaire (spec § 12) : chaque erreur en toutes lettres, avec un lien vers son
 * champ (id → message).
 * @var array<string, string> $erreurs
 * @var ?string $titre
 */
$titre ??= null;
$nombre = count($erreurs);
?>
<?php if ($nombre > 0): ?>
<div class="formulaire__resume" role="alert" tabindex="-1" data-resume-serveur>
  <p class="formulaire__resume-titre"><?= e($titre ?? ($nombre > 1 ? 'Il reste ' . $nombre . ' choses à corriger avant d’enregistrer :' : 'Il reste une chose à corriger avant d’enregistrer :')) ?></p>
  <ul>
<?php foreach ($erreurs as $id => $message): ?>
    <li><a href="#<?= e($id) ?>"><?= e($message) ?></a></li>
<?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>
