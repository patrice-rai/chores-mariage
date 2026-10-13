<?php

declare(strict_types=1);

use function Chores\e;

/**
 * Bouton prénom de l'entrée (72 px), à placer dans le formulaire POST /prenom.
 * @var int $id
 * @var string $prenom
 */
?>
<button class="bouton-prenom" type="submit" name="cousin" value="<?= e($id) ?>"><?= e($prenom) ?></button>
